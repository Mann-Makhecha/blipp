<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/settings.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Redirect to login if not logged in
include 'includes/checklogin.php';

// Fetch communities the user is a member of (for the compose box dropdown)
$communities = [];
if ($user_id) {
    $communities_stmt = $mysqli->prepare("
        SELECT c.community_id, c.name 
        FROM communities c 
        JOIN community_members cm ON c.community_id = cm.community_id 
        WHERE cm.user_id = ?
    ");
    $communities_stmt->bind_param("i", $user_id);
    $communities_stmt->execute();
    $communities_result = $communities_stmt->get_result();
    while ($row = $communities_result->fetch_assoc()) {
        $communities[] = $row;
    }
    $communities_stmt->close();
}

// Handle form submission for creating a post
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $community_id = !empty($_POST['community_id']) ? $_POST['community_id'] : null;
    $content = trim($_POST['content'] ?? '');

    // Validate inputs
    if (empty($content)) {
        $errors[] = "Post content is required.";
    }
    if (strlen($content) > 280) {
        $errors[] = "Post cannot exceed 280 characters.";
    }
    if ($community_id) {
        $stmt = $mysqli->prepare("
            SELECT 1 
            FROM community_members 
            WHERE community_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $community_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $errors[] = "Invalid community or you are not a member.";
        }
        $stmt->close();
    }

    // Validate file upload
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed with error code: " . $file['error'];
        } elseif (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPEG, PNG, and GIF files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File size must not exceed 5MB.";
        } else {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($file['name']);
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                $errors[] = "Failed to save the file.";
            }
        }
    }

    // Insert post into database
    if (empty($errors)) {
        $stmt = $mysqli->prepare("
            INSERT INTO posts (community_id, user_id, content, created_at, updated_at, upvotes, downvotes)
            VALUES (?, ?, ?, NOW(), NOW(), 0, 0)
        ");
        if ($community_id === null) {
            $stmt->bind_param("iis", $community_id, $user_id, $content);
        } else {
            $stmt->bind_param("iis", $community_id, $user_id, $content);
        }
        if ($stmt->execute()) {
            $post_id = $mysqli->insert_id;

            // Insert file if uploaded
            if ($file_path) {
                $file_name = $file['name'];
                $file_type = $file['type'];
                $file_size = $file['size'];
                $file_stmt = $mysqli->prepare("
                    INSERT INTO files (post_id, file_name, file_path, file_type, file_size, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $file_stmt->bind_param("isssi", $post_id, $file_name, $file_path, $file_type, $file_size);
                if (!$file_stmt->execute()) {
                    $errors[] = "Failed to save file metadata: " . $file_stmt->error;
                }
                $file_stmt->close();
            }

            // Redirect to refresh the page and show the new post
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Failed to create post: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Random posts for "For You" (from all public communities and individual posts), latest first
$for_you_query = $mysqli->query("
    SELECT 
        p.post_id, p.content, p.created_at, p.upvotes, p.downvotes,
        u.username,
        f.file_path,
        (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.post_id) as comment_count
    FROM posts p 
    JOIN users u ON p.user_id = u.user_id 
    LEFT JOIN communities c ON p.community_id = c.community_id 
    LEFT JOIN files f ON p.post_id = f.post_id
    WHERE c.is_private = 0 OR p.community_id IS NULL
    ORDER BY p.created_at DESC 
    LIMIT 10
");

// Posts for "Following" (from communities the user is a member of), latest first
$following_query = $mysqli->query("
    SELECT 
        p.post_id, p.content, p.created_at, p.upvotes, p.downvotes,
        u.username,
        f.file_path,
        (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.post_id) as comment_count
    FROM posts p 
    JOIN users u ON p.user_id = u.user_id 
    JOIN community_members cm ON p.community_id = cm.community_id 
    LEFT JOIN files f ON p.post_id = f.post_id
    WHERE cm.user_id = " . ($user_id ? $mysqli->real_escape_string($user_id) : 0) . " 
    ORDER BY p.created_at DESC 
    LIMIT 10
");

// Add this after the session_start() at the top of the file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_post'])) {
    $post_id = $_POST['post_id'] ?? null;
    $reason = $_POST['reason'] ?? '';
    
    if ($post_id && $user_id) {
        $stmt = $mysqli->prepare("
            INSERT INTO post_reports (post_id, reporter_id, reason, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $post_id, $user_id, $reason);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Post has been reported successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to report the post.";
        }
        $stmt->close();
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Function to format time ago (e.g., "5m ago")
function timeAgo($datetime) {
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $post_time = new DateTime($datetime, new DateTimeZone('Asia/Kolkata'));
    $interval = $now->diff($post_time);

    if ($interval->y > 0) return $interval->y . 'y ago';
    if ($interval->m > 0) return $interval->m . 'mo ago';
    if ($interval->d > 0) return $interval->d . 'd ago';
    if ($interval->h > 0) return $interval->h . 'h ago';
    if ($interval->i > 0) return $interval->i . 'm ago';
    return $interval->s . 's ago';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css">
    <link rel="stylesheet" href="css/mobile-create-post.css">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --background-primary: #000;
            --background-secondary: #1a1a1a;
            --text-primary: #fff;
            --text-secondary: #999;
            --border-primary: #333;
            --accent-primary: #1d9bf0;
        }

        html,
        body {
            height: 100%;
            background: var(--background-primary);
            color: var(--text-primary)  !important;
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Prevent horizontal scroll */
            overflow-y: auto; /* Ensure body handles scrolling */
        }

        body {
            /* Removed display: flex and flex-direction: row */
            /* Removed padding-left: 300px as offset is now on layout-container */
        }

        /* Left Sidebar - should be fixed and take full height */
        .sidebar {
            width: 300px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000; /* Ensure it stays on top */
            background-color: var(--background-primary);
            /* Other sidebar styles */
        }

        /* New wrapper for all scrollable content (main content + right sidebar) */
        .main-content-area {
            display: flex; /* Arranges main content and right sidebar horizontally */
            margin-left: 300px; /* Offset for the fixed left sidebar */
            padding-top: 20px; /* Top padding for the content below header */
            align-items: flex-start; /* Align items to the top */
            /* Removed min-height: 100vh, let content define height */
        }

        @media (max-width: 767px) {
            .main-content-area {
                margin-left: 0; /* No offset on mobile */
                flex-direction: column; /* Stack main content and right sidebar on mobile */
                padding-top: 0; /* No top padding on mobile */
            }
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

        /* Right Sidebar Container */
        .right-sidebar-container {
            width: 300px;
            flex-shrink: 0; /* Prevents sidebar from shrinking */
            position: sticky; /* Make it sticky */
            top: 20px; /* Stick to the top of its scrolling parent */
            /* Removed height/max-height, let content define height */
            /* Removed overflow-y: auto, let body handle primary scroll */
            padding-left: 1rem;
            padding-right: 1rem;
        }

        @media (max-width: 991px) {
            .right-sidebar-container {
                display: none; /* Hide right sidebar on smaller screens */
            }
        }

        /* Compose box */
        .compose-box {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary) !important;
        }

        .compose-box textarea {
            background: transparent;
               color: var(--text-primary) !important; 
            border: 1px solid var(--border-primary);
            border-radius: 0.5rem;
            resize: none;
        }

        .compose-box textarea:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(29, 155, 240, 0.1);
            outline: none;
            background: transparent;
            color: var(--text-primary)  !important;
        }

        /* Post card */
        .post-card {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary) !important;
        }

        .post-card:hover {
            background: rgba(40, 44, 48, 0.95);
        }

        .post-image {
            max-width: 100%;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        /* Tabs */
        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
            position: relative;
            background: var(--background-secondary);
            padding: 0.5rem;
            border-radius: 0.75rem;
            gap: 0.5rem;
        }

        .nav-tabs::after {
            display: none;
        }

        .nav-tabs .nav-item {
            margin: 0;
            flex: 1;
            max-width: 200px;
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
            text-align: center;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .nav-tabs .nav-link::after {
            display: none;
        }

        .nav-tabs .nav-link:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-tabs .nav-link.active {
            color: var(--text-primary);
            background: var(--accent-primary);
        }

        .nav-tabs .nav-link i {
            font-size: 1.1rem;
        }

        .tab-content {
            position: relative;
        }

        .tab-pane {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Post card improvements */
        .post-card {
            background: var(--background-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            color: var(--text-primary) !important;
        }

        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .post-card .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .post-card .user-info .profile-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--background-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--text-secondary);
            font-size: 1.5rem;
        }

        .post-card .user-info .user-details {
            flex: 1;
        }

        .post-card .user-info .username {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .post-card .user-info .timestamp {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .post-card .post-content {
            font-size: 1.1rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            color: var(--text-primary)  !important;
        }

        .post-card .post-image {
            border-radius: 1rem;
            margin-bottom: 1rem;
            max-height: 400px;
            object-fit: cover;
            width: 100%;
        }

        .post-card .engagement-bar {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 3rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-primary);
            margin-top: 1rem;
            height: 40px;
        }

        .post-card .engagement-btn {
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            background: transparent;
            border: none;
            cursor: pointer;
            min-width: 80px;
            height: 32px;
            line-height: 1;
        }

        .post-card .engagement-btn:hover {
            color: var(--accent-primary);
            background: rgba(29, 155, 240, 0.1);
            transform: translateY(-1px);
        }

        .post-card .engagement-btn i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
            line-height: 1;
        }

        .post-card .engagement-btn:hover i {
            transform: scale(1.1);
        }

        .post-card .engagement-btn.liked {
            color: #e0245e;
        }

        .post-card .engagement-btn.liked:hover {
            background: rgba(224, 36, 94, 0.1);
        }

        .post-card .engagement-btn.commented {
            color: #17bf63;
        }

        .post-card .engagement-btn.commented:hover {
            background: rgba(23, 191, 99, 0.1);
        }

        .post-card .engagement-btn span {
            font-weight: 500;
            min-width: 1.5rem;
            text-align: center;
            line-height: 1;
        }

        .post-card .engagement-btn.report {
            color: var(--text-secondary);
            margin-left: auto;
        }

        .post-card .engagement-btn.report:hover {
            color: #ff4444;
            background: rgba(255, 68, 68, 0.1);
        }

        .report-modal .modal-content {
            background: var(--background-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-primary);
        }

        .report-modal .modal-header {
            border-bottom: 1px solid var(--border-primary);
        }

        .report-modal .modal-footer {
            border-top: 1px solid var(--border-primary);
        }

        .report-modal .form-control {
            background: var(--background-primary);
            border: 1px solid var(--border-primary);
            color: var(--text-primary);
        }

        .report-modal .form-control:focus {
            background: var(--background-primary);
            border-color: var(--accent-primary);
            color: var(--text-primary);
        }

        .content{
            color: white  !important;
        }

       *::placeholder {
  color: gray !important;
  opacity: 1; /* Firefox */
}
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-area">
        <!-- Main Content -->
        <div class="main-content">
            <div class="main-content-inner">
                <!-- Compose Post Container -->
                <?php if ($user_id): ?>
                    <div class="compose-box">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-user-circle fa-2x me-3" style="color: var(--text-secondary);"></i>
                                <select class="form-select bg-dark text-white border-dark" name="community_id">
                                    <option value="">Everyone</option>
                                    <?php foreach ($communities as $community): ?>
                                        <option value="<?= $community['community_id'] ?>" <?= isset($_POST['community_id']) && $_POST['community_id'] == $community['community_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($community['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <textarea name="content" class="form-control mb-3 text-white " placeholder="What's happening?" rows="3" maxlength="280" required><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?></textarea>
                                <div class="d-flex align-items-center mb-3">
                                    <label for="file-upload" class="btn btn-link text-primary p-0 me-3">
                                        <i class="far fa-image"></i>
                                    </label>
                                    <input type="file" id="file-upload" name="file" accept="image/jpeg,image/png,image/gif" class="d-none">
                                    <span id="file-name" class="text-white "></span>
                                </div>
                                <div id="file-preview-container" class="mb-3"></div>
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <?php foreach ($errors as $error): ?>
                                            <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary rounded-pill px-4">Post</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="compose-box text-white p-3 text-center">
                        Log in to create a post.
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="postTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="foryou-tab" data-bs-toggle="tab" href="#foryou" role="tab">
                            <i class="fas fa-fire me-2"></i>For You
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="following-tab" data-bs-toggle="tab" href="#following" role="tab">
                            <i class="fas fa-user-friends me-2"></i>Following
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- For You -->
                    <div class="tab-pane fade show active" id="foryou" role="tabpanel">
                        <?php if ($for_you_query->num_rows > 0): ?>
                            <?php while ($post = $for_you_query->fetch_assoc()): ?>
                                <div class="post-card">
                                    <div class="user-info">
                                        <div class="profile-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="user-details">
                                            <div class="username">@<?= htmlspecialchars($post['username']) ?></div>
                                            <div class="timestamp"><?= timeAgo($post['created_at']) ?></div>
                                        </div>
                                    </div>
                                    <div class="post-content"><?= htmlspecialchars($post['content'] ?? 'No content available') ?></div>
                                    <?php if ($post['file_path']): ?>
                                        <img src="<?= htmlspecialchars($post['file_path']) ?>" alt="Post Image" class="post-image">
                                    <?php endif; ?>
                                    <div class="engagement-bar">
                                        <button class="engagement-btn commented">
                                            <i class="fas fa-comment"></i>
                                            <span><?= $post['comment_count'] ?></span>
                                        </button>
                                        <button class="engagement-btn liked">
                                            <i class="fas fa-heart"></i>
                                            <span><?= $post['upvotes'] ?></span>
                                        </button>
                                        <button class="engagement-btn report" onclick="openReportModal(<?= $post['post_id'] ?>)">
                                            <i class="fas fa-flag"></i>
                                            <span>Report</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-white mb-3"></i>
                                <p class="text-white">No posts available.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Following -->
                    <div class="tab-pane fade" id="following" role="tabpanel">
                        <?php if ($user_id): ?>
                            <?php if ($following_query->num_rows > 0): ?>
                                <?php while ($post = $following_query->fetch_assoc()): ?>
                                    <div class="post-card">
                                        <div class="user-info">
                                            <div class="profile-icon">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="user-details">
                                                <div class="username">@<?= htmlspecialchars($post['username']) ?></div>
                                                <div class="timestamp"><?= timeAgo($post['created_at']) ?></div>
                                            </div>
                                        </div>
                                        <div class="post-content"><?= htmlspecialchars($post['content'] ?? 'No content available') ?></div>
                                        <?php if ($post['file_path']): ?>
                                            <img src="<?= htmlspecialchars($post['file_path']) ?>" alt="Post Image" class="post-image">
                                        <?php endif; ?>
                                        <div class="engagement-bar">
                                            <button class="engagement-btn commented">
                                                <i class="fas fa-comment"></i>
                                                <span><?= $post['comment_count'] ?></span>
                                            </button>
                                            <button class="engagement-btn liked">
                                                <i class="fas fa-heart"></i>
                                                <span><?= $post['upvotes'] ?></span>
                                            </button>
                                            <button class="engagement-btn report" onclick="openReportModal(<?= $post['post_id'] ?>)">
                                                <i class="fas fa-flag"></i>
                                                <span>Report</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-white mb-3"></i>
                                    <p class="text-white">No posts from followed communities.</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-lock fa-3x text-white mb-3"></i>
                                <p class="text-white">Please log in to see posts from communities you follow.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="right-sidebar-container d-none d-lg-block">
            <?php include 'includes/rightsidebar.php'; ?>
        </div>
    </div>

    <?php include 'includes/mobilemenu.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>
    <script>
        // File upload preview
        const fileInput = document.getElementById('file-upload');
        const fileNameSpan = document.getElementById('file-name');
        const filePreviewContainer = document.getElementById('file-preview-container');

        fileInput?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            fileNameSpan.textContent = '';
            filePreviewContainer.innerHTML = '';

            if (file) {
                const maxSize = 5 * 1024 * 1024;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and GIF files are allowed.');
                    e.target.value = '';
                    return;
                }
                if (file.size > maxSize) {
                    alert('File size must not exceed 5MB.');
                    e.target.value = '';
                    return;
                }

                fileNameSpan.textContent = file.name;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-fluid rounded';
                    img.alt = 'Image Preview';

                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'btn btn-link text-danger p-0 ms-2';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
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

        // Character limit for post content
        const textarea = document.querySelector('textarea[name="content"]');
        if (textarea) {
            textarea.addEventListener('input', function() {
                if (this.value.length > 280) {
                    this.value = this.value.substring(0, 280);
                }
            });
        }
    </script>

    <!-- Mobile Create Post Button -->
    <a href="post.php" class="mobile-create-post">
        <i class="fas fa-plus"></i>
    </a>

    <!-- Add Bootstrap JS and Popper.js before closing body tag -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-success text-white">
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= $_SESSION['success_message'] ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-danger text-white">
                    <strong class="me-auto">Error</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= $_SESSION['error_message'] ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Add this before the closing </body> tag -->
    <!-- Report Modal Template -->
    <div class="modal fade report-modal" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Post</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="post_id" id="reportPostId">
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for reporting</label>
                            <select class="form-select" name="reason" required>
                                <option value="">Select a reason</option>
                                <option value="spam">Spam</option>
                                <option value="inappropriate">Inappropriate Content</option>
                                <option value="harassment">Harassment</option>
                                <option value="hate_speech">Hate Speech</option>
                                <option value="violence">Violence</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="details" class="form-label">Additional Details (Optional)</label>
                            <textarea class="form-control" name="details" rows="3" placeholder="Please provide any additional details about your report"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="report_post" class="btn btn-danger">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize all modals
        document.addEventListener('DOMContentLoaded', function() {
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                new bootstrap.Modal(modal);
            });
        });

        // Function to open report modal
        function openReportModal(postId) {
            document.getElementById('reportPostId').value = postId;
            var reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
            reportModal.show();
        }

        // Reset form when modal is closed
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });
    </script>
</body>
</html>