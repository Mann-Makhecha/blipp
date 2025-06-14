<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set to IST

// Initialize $mysqli as null and handle database connection errors
$mysqli = null;
$errors = [];
try {
    require_once 'includes/db.php';
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
include 'includes/checklogin.php';
// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Get community_id from URL
$community_id = isset($_GET['community_id']) ? (int)$_GET['community_id'] : 0;

// Fetch community details
$community = null;
$is_member = false;
if ($mysqli && $community_id > 0) {
    try {
        $query = "SELECT c.*, \n                   COUNT(DISTINCT cm.user_id) as member_count,\n                   COUNT(DISTINCT p.post_id) as post_count,\n                   u.username as creator_username\n            FROM communities c\n            LEFT JOIN community_members cm ON c.community_id = cm.community_id\n            LEFT JOIN posts p ON c.community_id = p.community_id\n            LEFT JOIN users u ON c.creator_id = u.user_id\n            WHERE c.community_id = ?\n            GROUP BY c.community_id";
        
        // Debugging: Output the query
        // echo "<!-- SQL Query: " . htmlspecialchars($query) . " -->";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $community_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $community = $result->fetch_assoc();
        $stmt->close();

        // Debugging: Output the fetched community array
        // echo "<!-- Community Data: "; var_dump($community); echo " -->";

        // Check if user is a member
        if ($user_id) {
            $stmt = $mysqli->prepare("SELECT * FROM community_members WHERE community_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $community_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $is_member = $result->num_rows > 0;
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error fetching community: " . $e->getMessage();
    }
}

// Handle joining the community
if (isset($_GET['join']) && $user_id && $mysqli && $community_id > 0) {
    try {
        if ($community && !$community['is_private']) {
            $stmt = $mysqli->prepare("INSERT INTO community_members (community_id, user_id, joined_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $community_id, $user_id);
            $stmt->execute();
            $stmt->close();

            // Update member_count
            $stmt = $mysqli->prepare("UPDATE communities SET member_count = (SELECT COUNT(*) FROM community_members WHERE community_id = ?) WHERE community_id = ?");
            $stmt->bind_param("ii", $community_id, $community_id);
            $stmt->execute();
            $stmt->close();

            header("Location: community.php?community_id=$community_id");
            exit();
        } else {
            $errors[] = "This community is private. Please request to join.";
        }
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error joining community: " . $e->getMessage();
    }
}

// Handle leaving the community
if (isset($_GET['leave']) && $user_id && $mysqli && $community_id > 0) {
    try {
        // Check if user is the creator
        if ($community['creator_id'] == $user_id) {
            $_SESSION['error_message'] = "You cannot leave a community you created. Please delete it instead.";
        } else {
            // Start transaction
            $mysqli->begin_transaction();
            
            // Remove user from community members
            $stmt = $mysqli->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $community_id, $user_id);
            $stmt->execute();

            // Update member count
            $stmt = $mysqli->prepare("UPDATE communities SET member_count = (SELECT COUNT(*) FROM community_members WHERE community_id = ?) WHERE community_id = ?");
            $stmt->bind_param("ii", $community_id, $community_id);
            $stmt->execute();

            $mysqli->commit();
            $_SESSION['success_message'] = "You have successfully left the community.";
        }
        
        header("Location: community.php?community_id=" . $community_id);
        exit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error_message'] = "Error leaving community: " . $e->getMessage();
        header("Location: community.php?community_id=" . $community_id);
        exit();
    }
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post']) && $user_id && $is_member && $mysqli) {
    $content = trim($_POST['content'] ?? '');
    if (empty($content)) {
        $errors[] = "Post content is required.";
    }

    if (empty($errors)) {
        try {
            // Insert the post
            $stmt = $mysqli->prepare("INSERT INTO posts (community_id, user_id, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("iis", $community_id, $user_id, $content);
            $stmt->execute();
            $post_id = $mysqli->insert_id;
            $stmt->close();

            // Handle file upload
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['file']['tmp_name'];
                $file_name = $_FILES['file']['name'];
                $file_size = $_FILES['file']['size'];
                $file_type = $_FILES['file']['type'];

                // Generate a unique file name
                $unique_file_name = uniqid() . '_' . $file_name;
                $file_path = 'uploads/' . $unique_file_name;

                if (move_uploaded_file($file_tmp, $file_path)) {
                    $stmt = $mysqli->prepare("INSERT INTO files (post_id, file_name, file_path, file_type, file_size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isssi", $post_id, $file_name, $file_path, $file_type, $file_size);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $errors[] = "Error uploading file.";
                }
            }

            header("Location: community.php?community_id=$community_id");
            exit();
        } catch (mysqli_sql_exception $e) {
            $errors[] = "Error creating post: " . $e->getMessage();
        }
    }
}

// Fetch posts for the community
$posts = [];
if ($mysqli && $community_id > 0) {
    try {
        $stmt = $mysqli->prepare("
            SELECT p.post_id, p.content, p.upvotes, p.downvotes, p.views, p.created_at, 
                   u.username, u.user_id
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.community_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param("i", $community_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($post = $result->fetch_assoc()) {
            // Fetch files for each post
            $stmt2 = $mysqli->prepare("SELECT file_path, file_type FROM files WHERE post_id = ?");
            $stmt2->bind_param("i", $post['post_id']);
            $stmt2->execute();
            $files_result = $stmt2->get_result();
            $post['files'] = [];
            while ($file = $files_result->fetch_assoc()) {
                $post['files'][] = $file;
            }
            $stmt2->close();
            $posts[] = $post;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error fetching posts: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        body {
            background-color: #000;
            color: #fff;
        }

        .community-header {
            border-bottom: 1px solid #333;
            padding-bottom: 1rem;
        }

        .post-card {
            border-bottom: 1px solid #333;
            transition: background-color 0.2s;
        }

        .post-card:hover {
            background-color: #111;
        }

        .form-control,
        .form-control-file {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
        }

        .form-control:focus {
            background-color: #1a1a1a;
            border-color: #1d9bf0;
            box-shadow: 0 0 0 0.25rem rgba(29, 155, 240, 0.25);
        }

        .btn-primary {
            background-color: #1d9bf0;
            border-color: #1d9bf0;
        }

        .btn-primary:hover {
            background-color: #1a8cd8;
            border-color: #1a8cd8;
        }

        .post-image {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
        }

        @media (max-width: 767px) {
            body {
                padding-bottom: 60px;
                /* Match sidebar.php's padding for bottom nav */
            }
        }
    </style>

</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-md-3 d-none d-md-block bg-dark text-white">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            <?php include 'includes/mobilemenu.php'; ?>
            <!-- Main Content -->
            <div class="col-md-6 py-4 px-3 position-relative vh-100">
                <!-- Database Connection Error -->
                <?php if (!$mysqli): ?>
                    <div class="alert alert-danger text-center" role="alert">
                        Unable to connect to the database. Please try again later.
                    </div>
                <?php endif; ?>

                <!-- Other Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger text-center" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Community Details -->
                <?php if ($community): ?>
                    <div class="community-header mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="fw-bold"><?= htmlspecialchars($community['name']) ?></h3>
                            <?php if ($is_member && $community['creator_id'] != $_SESSION['user_id']): ?>
                                <a href="community.php?community_id=<?= $community_id ?>&leave=1" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to leave this community?')">
                                    <i class="fas fa-sign-out-alt"></i> Leave Community
                                </a>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted"><?= htmlspecialchars($community['description']) ?></p>
                        <div class="d-flex gap-3">
                            <span><i class="fas fa-users"></i> <?= number_format($community['member_count']) ?> members</span>
                            <span><i class="fas fa-file-alt"></i> <?= number_format($community['post_count']) ?> posts</span>
                            <?php if ($community['is_private']): ?>
                                <span class="badge bg-warning"><i class="fas fa-lock"></i> Private</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Create Post Form (for members only) -->
                    <?php if ($user_id && $is_member): ?>
                        <div class="mb-4">
                            <h5 class="fw-bold mb-3">Create a Post</h5>
                            <form method="POST" action="community.php?community_id=<?= $community_id ?>" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <textarea class="form-control" name="content" rows="3" placeholder="What's on your mind?" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <input type="file" class="form-control-file" name="file" accept="image/*,video/*">
                                </div>
                                <button type="submit" name="create_post" class="btn btn-primary">Post</button>
                            </form>
                        </div>
                    <?php elseif ($user_id): ?>
                        <div class="alert alert-info text-center mb-4" role="alert">
                            Join the community to create a post!
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center mb-4" role="alert">
                            Log in and join the community to create a post!
                        </div>
                    <?php endif; ?>

                    <!-- Posts Listing -->
                    <h5 class="fw-bold mb-3">Posts</h5>
                    <?php if (!empty($posts)): ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="post-card p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user-circle fa-2x me-2" style="color: #666;"></i>
                                    <div>
                                        <a href="profile.php?user_id=<?= $post['user_id'] ?>" class="text-white text-decoration-none fw-bold">
                                            <?= htmlspecialchars($post['username']) ?>
                                        </a>
                                        <div class="text-white small">
                                            Posted on <?= date('F j, Y, g:i A', strtotime($post['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <p><?= htmlspecialchars($post['content']) ?></p>
                                <?php if (!empty($post['files'])): ?>
                                    <?php foreach ($post['files'] as $file): ?>
                                        <?php if (strpos($file['file_type'], 'image') !== false): ?>
                                            <img src="<?= htmlspecialchars($file['file_path']) ?>" alt="Post Image" class="post-image">
                                        <?php elseif (strpos($file['file_type'], 'video') !== false): ?>
                                            <video controls class="post-image">
                                                <source src="<?= htmlspecialchars($file['file_path']) ?>" type="<?= htmlspecialchars($file['file_type']) ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="text-white small mt-2">
                                    <i class="fas fa-thumbs-up me-1"></i> <?= $post['upvotes'] ?>
                                    <i class="fas fa-thumbs-down me-1 ms-3"></i> <?= $post['downvotes'] ?>
                                    <i class="fas fa-eye me-1 ms-3"></i> <?= $post['views'] ?>
                                    <a href="post.php?post_id=<?= $post['post_id'] ?>" class="text-white ms-3">
                                        <i class="fas fa-comment me-1"></i> Comments
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-white p-3">No posts found. Be the first to post!</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        Community not found.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="col-md-3 bg-dark text-white">
                <?php require_once 'includes/rightsidebar.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>