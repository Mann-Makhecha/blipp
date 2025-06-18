<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!isset($user_id)) {
    redirect('login.php');
}

include 'includes/checklogin.php';

// Determine whose profile to display (self or another user)
$profile_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;
$is_own_profile = $profile_user_id === $user_id;

// Database migrations
// Add bio column
$check_bio = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
if ($check_bio->num_rows === 0) {
    if (!$conn->query("ALTER TABLE users ADD bio VARCHAR(160) DEFAULT NULL")) {
        die("Failed to add bio column: " . $conn->error);
    }
}

// Add profile_views column
$check_views = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_views'");
if ($check_views->num_rows === 0) {
    if (!$conn->query("ALTER TABLE users ADD profile_views INT DEFAULT 0")) {
        die("Failed to add profile_views column: " . $conn->error);
    }
}

// Increment profile views if not own profile (using prepared statement)
if (!$is_own_profile) {
    $update_views = $conn->prepare("UPDATE users SET profile_views = profile_views + 1 WHERE user_id = ?");
    $update_views->bind_param("i", $profile_user_id);
    $update_views->execute();
    $update_views->close();
}

// Fetch user details
$user_stmt = $conn->prepare("
    SELECT username, name, bio, profile_image, points, is_premium, premium_until, created_at, profile_views 
    FROM users 
    WHERE user_id = ?
");
$user_stmt->bind_param("i", $profile_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user) {
    die("User not found.");
}

// Handle profile update (only for own profile)
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $is_own_profile) {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Validate inputs
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username cannot exceed 50 characters.";
    }
    if (strlen($name) > 255) {
        $errors[] = "Name cannot exceed 255 characters.";
    }
    if (strlen($bio) > 160) {
        $errors[] = "Bio cannot exceed 160 characters.";
    }

    // Check username uniqueness
    $username_check_stmt = $conn->prepare("
        SELECT user_id 
        FROM users 
        WHERE username = ? AND user_id != ?
    ");
    $username_check_stmt->bind_param("si", $username, $profile_user_id);
    $username_check_stmt->execute();
    if ($username_check_stmt->get_result()->num_rows > 0) {
        $errors[] = "Username is already taken.";
    }
    $username_check_stmt->close();

    // Handle profile image upload
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file = $_FILES['profile_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Profile image upload failed with error code: " . $file['error'];
        } elseif (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPEG, PNG, and GIF files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Profile image size must not exceed 2MB.";
        } else {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('profile_') . '.' . $file_extension;
            $profile_image = $upload_dir . $file_name;

            // Delete old profile image if exists
            if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                unlink($user['profile_image']);
            }

            if (!move_uploaded_file($file['tmp_name'], $profile_image)) {
                $errors[] = "Failed to save the profile image.";
            }
        }
    }

    // Update profile if no errors
    if (empty($errors)) {
        $update_stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, name = ?, bio = ?, profile_image = ? 
            WHERE user_id = ?
        ");
        $update_stmt->bind_param("ssssi", $username, $name, $bio, $profile_image, $profile_user_id);
        if ($update_stmt->execute()) {
            $success = "Profile updated successfully.";
            $user['username'] = $username;
            $user['name'] = $name;
            $user['bio'] = $bio;
            $user['profile_image'] = $profile_image;
        } else {
            $errors[] = "Failed to update profile: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Handle follow/unfollow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_action']) && !$is_own_profile) {
    $action = $_POST['follow_action'];
    if ($action === 'follow') {
        $follow_stmt = $conn->prepare("
            INSERT INTO follows (follower_id, followed_id, followed_at) 
            VALUES (?, ?, NOW())
        ");
        $follow_stmt->bind_param("ii", $user_id, $profile_user_id);
        $follow_stmt->execute();
        $follow_stmt->close();
    } elseif ($action === 'unfollow') {
        $unfollow_stmt = $conn->prepare("
            DELETE FROM follows 
            WHERE follower_id = ? AND followed_id = ?
        ");
        $unfollow_stmt->bind_param("ii", $user_id, $profile_user_id);
        $unfollow_stmt->execute();
        $unfollow_stmt->close();
    }
}

// Check if current user is following profile user
$is_following = false;
if (!$is_own_profile) {
    $follow_check_stmt = $conn->prepare("
        SELECT 1 
        FROM follows 
        WHERE follower_id = ? AND followed_id = ?
    ");
    $follow_check_stmt->bind_param("ii", $user_id, $profile_user_id);
    $follow_check_stmt->execute();
    $is_following = $follow_check_stmt->get_result()->num_rows > 0;
    $follow_check_stmt->close();
}

// Get followers and following counts
$followers_stmt = $conn->prepare("SELECT COUNT(*) as count FROM follows WHERE followed_id = ?");
$followers_stmt->bind_param("i", $profile_user_id);
$followers_stmt->execute();
$followers_count = $followers_stmt->get_result()->fetch_assoc()['count'];
$followers_stmt->close();

$following_stmt = $conn->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ?");
$following_stmt->bind_param("i", $profile_user_id);
$following_stmt->execute();
$following_count = $following_stmt->get_result()->fetch_assoc()['count'];
$following_stmt->close();

// Fetch user badges
$badges_stmt = $conn->prepare("
    SELECT b.badge_id, b.name, b.description, b.image_path 
    FROM user_badges ub 
    JOIN badges b ON ub.badge_id = b.badge_id 
    WHERE ub.user_id = ?
");
$badges_stmt->bind_param("i", $profile_user_id);
$badges_stmt->execute();
$badges_result = $badges_stmt->get_result();
$badges = [];
while ($row = $badges_result->fetch_assoc()) {
    $badges[] = $row;
}
$badges_stmt->close();

// Fetch recent point transactions
$points_stmt = $conn->prepare("
    SELECT description, points, transaction_date 
    FROM point_transactions 
    WHERE user_id = ? 
    ORDER BY transaction_date DESC 
    LIMIT 5
");
$points_stmt->bind_param("i", $profile_user_id);
$points_stmt->execute();
$points_result = $points_stmt->get_result();
$point_transactions = [];
while ($row = $points_result->fetch_assoc()) {
    $point_transactions[] = $row;
}
$points_stmt->close();

// Fetch user posts with pagination
$posts_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $posts_per_page;

$posts_stmt = $conn->prepare("
    SELECT 
        p.post_id, p.title, p.content, p.created_at, p.upvotes, p.downvotes, p.views, p.user_id,
        u.username,
        f.file_path,
        c.community_id, c.name as community_name,
        GROUP_CONCAT(h.hashtag) as hashtags,
        (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.post_id) as comment_count
    FROM posts p 
    JOIN users u ON p.user_id = u.user_id 
    LEFT JOIN files f ON p.post_id = f.post_id 
    LEFT JOIN communities c ON p.community_id = c.community_id
    LEFT JOIN posts_hashtags ph ON p.post_id = ph.post_id 
    LEFT JOIN hashtags h ON ph.hashtag_id = h.hashtag_id
    WHERE p.user_id = ?
    GROUP BY p.post_id
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$posts_stmt->bind_param("iii", $profile_user_id, $posts_per_page, $offset);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();
$posts = [];
while ($row = $posts_result->fetch_assoc()) {
    $row['hashtags'] = $row['hashtags'] ? explode(',', $row['hashtags']) : [];
    
    // Check if current user is following this post's author
    if (isset($user_id) && $user_id && $user_id != $row['user_id']) {
        $follow_check_stmt = $conn->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
        $follow_check_stmt->bind_param("ii", $user_id, $row['user_id']);
        $follow_check_stmt->execute();
        $row['is_following'] = $follow_check_stmt->get_result()->num_rows > 0;
        $follow_check_stmt->close();
    } else {
        $row['is_following'] = false;
    }
    
    $posts[] = $row;
}
$posts_stmt->close();

// Get total posts for pagination
$total_posts_stmt = $conn->prepare("SELECT COUNT(*) as total FROM posts WHERE user_id = ?");
$total_posts_stmt->bind_param("i", $profile_user_id);
$total_posts_stmt->execute();
$total_posts = $total_posts_stmt->get_result()->fetch_assoc()['total'];
$total_posts_stmt->close();
$total_pages = ceil($total_posts / $posts_per_page);

// Fetch communities
$communities_stmt = $conn->prepare("
    SELECT c.community_id, c.name, cm.is_admin 
    FROM communities c 
    JOIN community_members cm ON c.community_id = cm.community_id 
    WHERE cm.user_id = ?
");
$communities_stmt->bind_param("i", $profile_user_id);
$communities_stmt->execute();
$communities_result = $communities_stmt->get_result();
$communities = [];
while ($row = $communities_result->fetch_assoc()) {
    $communities[] = $row;
}
$communities_stmt->close();

// Check if current user is following the profile user
$is_following_profile = false;
if ($user_id && $user_id != $profile_user_id) {
    $follow_check_stmt = $conn->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
    $follow_check_stmt->bind_param("ii", $user_id, $profile_user_id);
    $follow_check_stmt->execute();
    $is_following_profile = $follow_check_stmt->get_result()->num_rows > 0;
    $follow_check_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css">
    <script src="https://kit.fontawesome.com/c508d42d1a.js" crossorigin="anonymous"></script>
    <link rel="icon" href="favicon (2).png" type="image/x-icon">
    <link rel="stylesheet" href="profile.css">
    <style>
        :root {
            --background-primary: #000;
            --background-secondary: #1a1a1a;
            --background-sidebar: #212529;
            --text-primary: #fff;
            --text-secondary: #999;
            --border-primary: #333;
            --accent-primary: #1d9bf0;
            --accent-primary-hover: #1a8cd8;
            --error-primary: #ff3333;
            --error-primary-hover: #cc0000;
            --success-primary: #28a745;
            --warning-primary: #ffc107;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--background-primary) 0%, #111 100%);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(29, 155, 240, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(29, 155, 240, 0.05) 0%, transparent 50%);
            z-index: -1;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

               /* Main content adjustments */
        .main-content {
            flex-grow: 1; /* Allows main content to take all available space within its wrapper */
            padding: 1rem;
        }

        .main-content-inner {
            max-width: 700px; /* Keep a max-width for readability */
            width: 100%; /* Ensure it takes full width up to max-width */
            margin: 0 auto; /* Center content within the flex-grown main-content area */
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 2rem;
            }
        }

        @media (max-width: 767px) {
            .main-content {
                padding-bottom: 70px; /* Adjust for mobile nav */
                width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
 .right-sidebar-container {
            width: 300px;
            flex-shrink: 0; /* Prevents sidebar from shrinking */
            position: sticky; /* Make it sticky */
            top: 20px; /* Stick to the top of its scrolling parent */
            /* Removed height/max-height, let content define height */
            /* Removed overflow-y: auto, let body handle primary scroll */
            position: fixed; /* Fixes the sidebar in place */
            right: 0;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        @media (max-width: 991px) {
            .right-sidebar-container {
                display: none; /* Hide right sidebar on smaller screens */
            }
        }

        .profile-card, .post-card, .communities-card, .badges-card, .points-card {
            background: rgba(33, 37, 41, 0.95);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-card:hover, .post-card:hover, .communities-card:hover, .badges-card:hover, .points-card:hover {
            background: rgba(40, 44, 48, 0.95);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-primary);
        }

        .profile-icon {
            font-size: 100px;
            color: var(--text-secondary);
        }

        .form-control {
            background: var(--background-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-primary);
            border-radius: 0.5rem;
        }

        .form-control:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(29, 155, 240, 0.1);
            background: var(--background-secondary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-primary-hover));
            border: none;
            border-radius: 0.75rem;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-primary-hover), #1578b8);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(29, 155, 240, 0.3);
        }

        .btn-follow {
            background: var(--background-secondary);
            color: var(--accent-primary);
            border: 1px solid var(--accent-primary);
        }

        .btn-follow:hover {
            background: var(--accent-primary);
            color: var(--text-primary);
        }

        .btn-icon {
            color: var(--accent-primary);
            font-size: 1.2rem;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background: var(--background-secondary);
            border-radius: 50%;
        }

        .file-preview {
            max-width: 100px;
            border-radius: 0.5rem;
            border: 1px solid var(--border-primary);
        }

        .remove-file-btn {
            color: var(--error-primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-file-btn:hover {
            color: var(--error-primary-hover);
        }

        .file-name {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .error-message {
            background: rgba(255, 51, 51, 0.1);
            color: var(--error-primary);
            border-left: 4px solid var(--error-primary);
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .success-message {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-primary);
            border-left: 4px solid var(--success-primary);
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .post-card .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .post-card .username {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .post-card .timestamp {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .post-card .post-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .post-card .post-content {
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .post-card .post-image {
            max-width: 100%;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            object-fit: cover;
        }

        .post-card .engagement-bar {
            display: flex;
            gap: 2rem;
        }

        .post-card .engagement-btn {
            color: var(--text-secondary);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .post-card .engagement-btn:hover {
            color: var(--accent-primary);
        }

        .badges-card img {
            width: 40px;
            height: 40px;
            margin-right: 0.75rem;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--border-primary);
        }

        .badge-item {
            display: flex;
            align-items: center;
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .badge-item:hover {
            background: rgba(29, 155, 240, 0.1);
            border-color: var(--accent-primary);
            transform: translateY(-2px);
        }

        .badge-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--accent-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            color: white;
            font-size: 1.2rem;
        }

        .badge-info {
            flex: 1;
        }

        .badge-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .badge-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .badge-icon-small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent-primary);
            color: white;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }

        .badge-icon-small:hover {
            transform: scale(1.1);
            background: var(--accent-primary-hover);
        }

        .communities-card ul, .badges-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .communities-card li, .badges-card li {
            padding: 0;
        }

        .communities-card a {
            color: var(--accent-primary);
            text-decoration: none;
        }

        .communities-card a:hover {
            color: var(--accent-primary-hover);
            text-decoration: underline;
        }

        .points-card table {
            color: var(--text-primary);
        }

        .points-card th, .points-card td {
            border-color: var(--border-primary);
        }

        .right-sidebar {
            background: var(--background-sidebar);
            height: 100vh;
           
            position: sticky;
            top: 0;
            padding: 1.5rem;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-primary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        .post-card .follow-btn.btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        .profile-card a {
            color: var(--text-primary);
            transition: color 0.3s ease;
        }

        .profile-card a:hover {
            color: var(--accent-primary);
        }

        .profile-card .btn {
            transition: all 0.3s ease;
        }

        .profile-card .btn:hover {
            transform: scale(1.05);
        }

        .community-link {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .community-link:hover {
            color: var(--accent-primary-hover);
            text-decoration: underline;
        }

        .community-link i {
            margin-right: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

   <div class="main-content-area">
        <!-- Main Content -->
        <div class="main-content">
            <div class="main-content-inner">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="d-flex align-items-center mb-3">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Picture" class="profile-image me-3">
                        <?php else: ?>
                            <i class="fas fa-user-circle profile-icon me-3" aria-hidden="true"></i>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h2 class="mb-0">
                                @<?= htmlspecialchars($user['username']) ?>
                                <?php if (!empty($badges)): ?>
                                    <div class="d-inline-flex align-items-center ms-2">
                                        <?php foreach ($badges as $badge): ?>
                                            <?php if (in_array(strtolower($badge['name']), ['verified', 'premium', 'moderator'])): ?>
                                                <span class="badge-icon-small me-1" title="<?= htmlspecialchars($badge['name']) ?>">
                                                    <?php
                                                    $icon = 'fa-medal';
                                                    switch (strtolower($badge['name'])) {
                                                        case 'verified':
                                                            $icon = 'fa-check-circle';
                                                            break;
                                                        case 'premium':
                                                            $icon = 'fa-crown';
                                                            break;
                                                        case 'moderator':
                                                            $icon = 'fa-shield-alt';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="fas <?= $icon ?>"></i>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </h2>
                            <?php if ($user['name']): ?>
                                <p class="text-secondary mb-0"><?= htmlspecialchars($user['name']) ?></p>
                            <?php endif; ?>
                            <p class="text-secondary mb-0">Joined <?= (new DateTime($user['created_at']))->format('F Y') ?></p>
                        </div>
                        <?php if (!$is_own_profile && $user_id): ?>
                            <div class="ms-3">
                                <form method="POST" action="follow_user.php" style="display: inline;">
                                    <input type="hidden" name="followed_id" value="<?= $profile_user_id ?>">
                                    <button type="submit" class="btn <?= $is_following_profile ? 'btn-secondary' : 'btn-primary' ?>">
                                        <i class="fas <?= $is_following_profile ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                                        <?= $is_following_profile ? 'Unfollow' : 'Follow' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="mb-3"><?= htmlspecialchars($user['bio'] ?? 'No bio available') ?></p>
                    <div class="d-flex gap-3 mb-3">
                        <a href="followers.php?user_id=<?= $profile_user_id ?>&type=followers" class="text-decoration-none">
                            <span><strong><?= $followers_count ?></strong> Followers</span>
                        </a>
                        <a href="followers.php?user_id=<?= $profile_user_id ?>&type=following" class="text-decoration-none">
                            <span><strong><?= $following_count ?></strong> Following</span>
                        </a>
                        <span><strong><?= $user['points'] ?></strong> Points</span>
                        <span><strong><?= $user['profile_views'] ?></strong> Profile Views</span>
                    </div>
                    <?php if ($user['is_premium'] && $user['premium_until'] > date('Y-m-d H:i:s')): ?>
                        <span class="badge bg-warning text-dark mb-3">Premium Member</span>
                    <?php endif; ?>
                    <?php if ($is_own_profile): ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">Edit Profile</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Edit Profile Modal -->
                <?php if ($is_own_profile): ?>
                    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content bg-dark text-white">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="modal-body">
                                        <?php if (!empty($errors)): ?>
                                            <div class="error-message">
                                                <?php foreach ($errors as $error): ?>
                                                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($success): ?>
                                            <div class="success-message">
                                                <p class="mb-0"><?= htmlspecialchars($success) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required maxlength="50">
                                        </div>
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" maxlength="255">
                                        </div>
                                        <div class="mb-3">
                                            <label for="bio" class="form-label">Bio</label>
                                            <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="160"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                            <small class="text-white">160 characters remaining</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="profile-image" class="form-label">Profile Image</label>
                                            <input type="file" class="form-control" id="profile-image" name="profile_image" accept="image/jpeg,image/png,image/gif">
                                            <div id="file-preview-container" class="file-preview-container d-flex align-items-center gap-3 mt-2"></div>
                                            <span id="file-name" class="file-name"></span>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Badges Card -->
                <div class="badges-card">
                    <h3>Badges</h3>
                    <?php if (!empty($badges)): ?>
                        <ul>
                            <?php foreach ($badges as $badge): ?>
                                <li class="badge-item">
                                    <?php if ($badge['image_path'] && file_exists($badge['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($badge['image_path']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <?php endif; ?>
                                    <div class="badge-icon" style="<?= ($badge['image_path'] && file_exists($badge['image_path'])) ? 'display: none;' : 'display: flex;' ?>">
                                        <?php
                                        // Default icon based on badge name
                                        $icon = 'fa-medal';
                                        switch (strtolower($badge['name'])) {
                                            case 'verified':
                                                $icon = 'fa-check-circle';
                                                break;
                                            case 'premium':
                                                $icon = 'fa-crown';
                                                break;
                                            case 'moderator':
                                                $icon = 'fa-shield-alt';
                                                break;
                                            case 'contributor':
                                                $icon = 'fa-star';
                                                break;
                                            case 'early adopter':
                                                $icon = 'fa-rocket';
                                                break;
                                            default:
                                                $icon = 'fa-medal';
                                        }
                                        ?>
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                    <div class="badge-info">
                                        <div class="badge-name"><?= htmlspecialchars($badge['name']) ?></div>
                                        <p class="badge-description"><?= htmlspecialchars($badge['description']) ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-medal text-secondary mb-3" style="font-size: 3rem;"></i>
                            <p class="text-secondary mb-0">No badges earned yet.</p>
                            <small class="text-muted">Complete achievements to earn badges!</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Points History Card -->
                <div class="points-card">
                    <h3>Points History</h3>
                    <?php if (!empty($point_transactions)): ?>
                        <table class="table table-dark table-bordered">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Points</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($point_transactions as $transaction): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transaction['description']) ?></td>
                                        <td><?= htmlspecialchars($transaction['points']) ?></td>
                                        <td><?= (new DateTime($transaction['transaction_date']))->format('M d, Y') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-white">No points transactions yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Communities Card -->
                <div class="communities-card">
                    <h3>Communities</h3>
                    <?php if (!empty($communities)): ?>
                        <ul>
                            <?php foreach ($communities as $community): ?>
                                <li>
                                    <a href="community.php?id=<?= $community['community_id'] ?>">
                                        <?= htmlspecialchars($community['name']) ?>
                                    </a>
                                    <?php if ($community['is_admin']): ?>
                                        <span class="badge bg-primary ms-2">Admin</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-white">Not a member of any communities.</p>
                    <?php endif; ?>
                </div>

                <!-- User Posts -->
                <h3>Posts</h3>
                <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-circle profile-icon me-3" aria-hidden="true"></i>
                                    <div class="user-info d-flex align-items-center gap-3">
                                        <span class="username">@<?= htmlspecialchars($post['username']) ?></span>
                                        <span class="timestamp">
                                            <?= timeAgo($post['created_at']) ?>
                                            <?php if ($post['community_id'] && $post['community_name']): ?>
                                                · <a href="community.php?community_id=<?= $post['community_id'] ?>" class="community-link">
                                                    <i class="fas fa-users"></i> <?= htmlspecialchars($post['community_name']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($user_id && $post['user_id'] == $user_id): ?>
                                    <div class="post-actions">
                                        <form method="POST" action="delete_post.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                                            <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Post">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($user_id && $post['user_id'] != $user_id): ?>
                                    <div class="post-actions">
                                        <form method="POST" action="follow_user.php" style="display: inline;">
                                            <input type="hidden" name="followed_id" value="<?= $post['user_id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $post['is_following'] ? 'btn-secondary' : 'btn-primary' ?>" title="<?= $post['is_following'] ? 'Unfollow' : 'Follow' ?> User">
                                                <i class="fas <?= $post['is_following'] ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                                                <?= $post['is_following'] ? 'Unfollow' : 'Follow' ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
                            <?php if ($post['content']): ?>
                                <div class="post-content"><?= htmlspecialchars($post['content']) ?></div>
                            <?php endif; ?>
                            <?php if ($post['file_path']): ?>
                                <img src="<?= htmlspecialchars($post['file_path']) ?>" alt="Post Image" class="post-image img-fluid">
                            <?php endif; ?>
                            <?php if (!empty($post['hashtags'])): ?>
                                <div class="mb-2">
                                    <?php foreach ($post['hashtags'] as $hashtag): ?>
                                        <a href="search.php?tag=<?= urlencode($hashtag) ?>" class="text-decoration-none text-primary me-2">#<?= htmlspecialchars($hashtag) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex gap-3 mb-2">
                                <span><strong><?= $post['views'] ?></strong> Views</span>
                            </div>
                            <div class="engagement-bar d-flex gap-4">
                                <span class="engagement-btn">
                                    <i class="fas fa-comment" aria-hidden="true"></i> <?= $post['comment_count'] ?>
                                </span>
                                <span class="engagement-btn">
                                    <i class="fas fa-heart" aria-hidden="true"></i> <?= $post['upvotes'] ?>
                                </span>
                                <span class="engagement-btn">
                                    <i class="fas fa-thumbs-down" aria-hidden="true"></i> <?= $post['downvotes'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <!-- Pagination -->
                    <nav aria-label="Posts pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&user_id=<?= $profile_user_id ?>" aria-label="Previous">
                                    <span aria-hidden="true">«</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&user_id=<?= $profile_user_id ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&user_id=<?= $profile_user_id ?>" aria-label="Next">
                                    <span aria-hidden="true">»</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php else: ?>
                    <p class="pw-light p-3 text-center">No posts yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>  
    <div class="right-sidebar-container d-none d-lg-block ">
            <?php include 'includes/rightsidebar.php'; ?>
        </div>
 <?php include 'includes/mobilemenu.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>
    <script>
        // File upload preview
        const fileInput = document.getElementById('profile-image');
        const fileNameSpan = document.getElementById('file-name');
        const filePreviewContainer = document.getElementById('file-preview-container');

        fileInput?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            fileNameSpan.textContent = '';
            filePreviewContainer.innerHTML = '';

            if (file) {
                const maxSize = 2 * 1024 * 1024;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and GIF files are allowed.');
                    e.target.value = '';
                    return;
                }
                if (file.size > maxSize) {
                    alert('File size must not exceed 2MB.');
                    e.target.value = '';
                    return;
                }

                fileNameSpan.textContent = file.name;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'file-preview';
                    img.alt = 'Image Preview';

                    const removeBtn = document.createElement('span');
                    removeBtn.textContent = 'Remove';
                    removeBtn.className = 'remove-file-btn';
                    removeBtn.onclick = function() {
                        fileInput.value = '';
                        fileNameSpan.textContent = '';
                        filePreviewContainer.innerHTML = '';
                    };

                    filePreviewContainer.appendChild(img);
                    filePreviewContainer.appendChild(removeBtn);
                };
                reader.readAsDataURL(file);
            }
        });

        // Bio character counter
        const bioTextarea = document.querySelector('textarea[name="bio"]');
        if (bioTextarea) {
            const charCounter = document.createElement('small');
            charCounter.className = 'text-white';
            bioTextarea.parentNode.appendChild(charCounter);

            function updateCharCount() {
                const remaining = 160 - this.value.length;
                charCounter.textContent = `${remaining} characters remaining`;
            }

            bioTextarea.addEventListener('input', updateCharCount);
            updateCharCount.call(bioTextarea);
        }

        // Badge image fallback handling
        document.addEventListener('DOMContentLoaded', function() {
            const badgeImages = document.querySelectorAll('.badge-item img');
            badgeImages.forEach(img => {
                img.addEventListener('error', function() {
                    // Hide the broken image
                    this.style.display = 'none';
                    
                    // Show the fallback icon
                    const fallbackIcon = this.nextElementSibling;
                    if (fallbackIcon && fallbackIcon.classList.contains('badge-icon')) {
                        fallbackIcon.style.display = 'flex';
                    }
                });

                // Check if image loads successfully
                img.addEventListener('load', function() {
                    // Hide the fallback icon if image loads successfully
                    const fallbackIcon = this.nextElementSibling;
                    if (fallbackIcon && fallbackIcon.classList.contains('badge-icon')) {
                        fallbackIcon.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>