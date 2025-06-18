<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/settings.php';
require_once 'includes/functions.php';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Redirect to login if not logged in
include 'includes/checklogin.php';

// Fetch communities the user is a member of (for the compose box dropdown)
$communities = [];
if ($user_id) {
    $communities_stmt = $conn->prepare("
        SELECT c.community_id, c.name 
        FROM communities c 
        JOIN community_members cm ON c.community_id = cm.community_id 
        WHERE cm.user_id = ?
    ");
    $param_user_id = $user_id;
    $communities_stmt->bind_param("i", $param_user_id);
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
        $stmt = $conn->prepare("
            SELECT 1 
            FROM community_members 
            WHERE community_id = ? AND user_id = ?
        ");
        $param_community_id = $community_id;
        $param_user_id = $user_id;
        $stmt->bind_param("ii", $param_community_id, $param_user_id);
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
        $stmt = $conn->prepare("
            INSERT INTO posts (community_id, user_id, content, created_at, updated_at, upvotes, downvotes)
            VALUES (?, ?, ?, NOW(), NOW(), 0, 0)
        ");
        
        // Create variables for bind_param
        $param_community_id = $community_id;
        $param_user_id = $user_id;
        $param_content = $content;
        
        $stmt->bind_param("iis", $param_community_id, $param_user_id, $param_content);
        
        if ($stmt->execute()) {
            $post_id = $conn->insert_id;

            // Insert file if uploaded
            if ($file_path) {
                $file_name = $file['name'];
                $file_type = $file['type'];
                $file_size = $file['size'];
                $file_stmt = $conn->prepare("
                    INSERT INTO files (post_id, file_name, file_path, file_type, file_size, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $file_stmt->bind_param("isssi", $post_id, $file_name, $file_path, $file_type, $file_size);
                if (!$file_stmt->execute()) {
                    $errors[] = "Failed to save file metadata: " . $file_stmt->error;
                }
                $file_stmt->close();
            }

            // Redirect to prevent form resubmission
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            $errors[] = "Failed to create post: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get posts from communities the user follows
$following_query = $conn->prepare("
    SELECT p.*, u.username, 
           (SELECT COUNT(*) FROM post_votes WHERE post_id = p.post_id AND vote_type = 1) as upvotes,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
           (SELECT vote_type FROM post_votes WHERE post_id = p.post_id AND user_id = ?) as user_vote
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    JOIN community_members cm ON p.community_id = cm.community_id
    WHERE cm.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");

if ($user_id) {
    $param_user_id = $user_id;
    $following_query->bind_param("ii", $param_user_id, $param_user_id);
    $following_query->execute();
    $following_result = $following_query->get_result();
}

// Get random posts for "For You" section
$for_you_query = $conn->prepare("
    SELECT p.*, u.username, 
           (SELECT COUNT(*) FROM post_votes WHERE post_id = p.post_id AND vote_type = 1) as upvotes,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
           (SELECT vote_type FROM post_votes WHERE post_id = p.post_id AND user_id = ?) as user_vote
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.community_id IS NULL
    ORDER BY RAND()
    LIMIT 10
");

$param_user_id = $user_id ?? 0;
$for_you_query->bind_param("i", $param_user_id);
$for_you_query->execute();
$for_you_result = $for_you_query->get_result();

// Handle post reporting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_post'])) {
    $post_id = $_POST['post_id'] ?? null;
    $reason = $_POST['reason'] ?? '';
    
    if ($post_id && $user_id) {
        $stmt = $conn->prepare("
            INSERT INTO post_reports (post_id, reporter_id, reason, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $param_post_id = $post_id;
        $param_user_id = $user_id;
        $param_reason = $reason;
        $stmt->bind_param("iis", $param_post_id, $param_user_id, $param_reason);
        
        if ($stmt->execute()) {
            showSuccess("Post has been reported successfully.");
        } else {
            showError("Failed to report the post.");
        }
        $stmt->close();
        
        // Redirect to prevent form resubmission
        redirect($_SERVER['HTTP_REFERER']);
    }
}

// Include header and sidebar
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
    <link rel="stylesheet" href="css/index_style.css">
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
                        <?php if ($for_you_result->num_rows > 0): ?>
                            <?php while ($post = $for_you_result->fetch_assoc()): ?>
                                <div class="post-card" data-post-id="<?= $post['post_id'] ?>">
                                    <div class="user-info">
                                        <div class="profile-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="user-details">
                                            <div class="username">
                                                @<?= htmlspecialchars($post['username']) ?>
                                                <?php if ($post['user_id']): ?>
                                                    <?php
                                                    // Check if user has verification badge
                                                    $verify_check = $conn->prepare("
                                                        SELECT 1 FROM user_badges ub 
                                                        JOIN badges b ON ub.badge_id = b.badge_id 
                                                        WHERE ub.user_id = ? AND b.name = 'Verified'
                                                    ");
                                                    $verify_check->bind_param("i", $post['user_id']);
                                                    $verify_check->execute();
                                                    if ($verify_check->get_result()->num_rows > 0): ?>
                                                        <i class="fas fa-check-circle text-primary ms-1" title="Verified Account" style="font-size: 0.9rem;"></i>
                                                    <?php endif; ?>
                                                    <?php $verify_check->close(); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="timestamp">
                                                <?= timeAgo($post['created_at']) ?>
                                                <?php if ($post['community_id'] && $post['community_name']): ?>
                                                    · <a href="community.php?community_id=<?= $post['community_id'] ?>" class="community-link">
                                                        <i class="fas fa-users"></i> <?= htmlspecialchars($post['community_name']) ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($user_id && $post['user_id'] == $user_id): ?>
                                            <div class="post-actions">
                                                <form method="POST" action="delete_post.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                                                    <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger delete-btn" title="Delete Post">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($user_id && $post['user_id'] != $user_id): ?>
                                            <div class="post-actions">
                                                <form method="POST" action="follow_user.php" style="display: inline;">
                                                    <input type="hidden" name="followed_id" value="<?= $post['user_id'] ?>">
                                                    <button type="submit" class="btn btn-sm <?= $post['user_vote'] == 1 ? 'btn-secondary' : 'btn-primary' ?> follow-btn" title="<?= $post['user_vote'] == 1 ? 'Unfollow' : 'Follow' ?> User">
                                                        <i class="fas <?= $post['user_vote'] == 1 ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                                                        <?= $post['user_vote'] == 1 ? 'Unfollow' : 'Follow' ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-content"><?= htmlspecialchars($post['content'] ?? 'No content available') ?></div>
                                    <?php if (isset($post['file_path']) && $post['file_path']): ?>
                                        <img src="<?= htmlspecialchars($post['file_path']) ?>" alt="Post Image" class="post-image">
                                    <?php endif; ?>
                                    <div class="engagement-bar">
                                        <button class="engagement-btn like-btn <?= $post['user_vote'] == 1 ? 'liked' : '' ?>" onclick="likePost(<?= $post['post_id'] ?>)">
                                            <i class="fas fa-heart"></i>
                                            <span class="like-count"><?= $post['upvotes'] ?></span>
                                        </button>
                                        <button class="engagement-btn comment-btn" onclick="toggleComments(<?= $post['post_id'] ?>)">
                                            <i class="fas fa-comment"></i>
                                            <span class="comment-count"><?= $post['comment_count'] ?></span>
                                        </button>
                                    </div>
                                    
                                    <div class="comments-section" style="display: none;">
                                        <div class="comment-form">
                                            <textarea placeholder="Write a comment..." maxlength="280"></textarea>
                                            <button onclick="submitComment(<?= $post['post_id'] ?>)">Reply</button>
                                        </div>
                                        <div class="comment-list"></div>
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
                            <?php if ($following_result->num_rows > 0): ?>
                                <?php while ($post = $following_result->fetch_assoc()): ?>
                                    <div class="post-card" data-post-id="<?= $post['post_id'] ?>">
                                        <div class="user-info">
                                            <div class="profile-icon">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="user-details">
                                                <div class="username">
                                                    @<?= htmlspecialchars($post['username']) ?>
                                                    <?php if ($post['user_id']): ?>
                                                        <?php
                                                        // Check if user has verification badge
                                                        $verify_check = $conn->prepare("
                                                            SELECT 1 FROM user_badges ub 
                                                            JOIN badges b ON ub.badge_id = b.badge_id 
                                                            WHERE ub.user_id = ? AND b.name = 'Verified'
                                                        ");
                                                        $verify_check->bind_param("i", $post['user_id']);
                                                        $verify_check->execute();
                                                        if ($verify_check->get_result()->num_rows > 0): ?>
                                                            <i class="fas fa-check-circle text-primary ms-1" title="Verified Account" style="font-size: 0.9rem;"></i>
                                                        <?php endif; ?>
                                                        <?php $verify_check->close(); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="timestamp">
                                                    <?= timeAgo($post['created_at']) ?>
                                                    <?php if ($post['community_id'] && $post['community_name']): ?>
                                                        · <a href="community.php?community_id=<?= $post['community_id'] ?>" class="community-link">
                                                            <i class="fas fa-users"></i> <?= htmlspecialchars($post['community_name']) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($post['user_id'] == $user_id): ?>
                                                <div class="post-actions">
                                                    <form method="POST" action="delete_post.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                                                        <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger delete-btn" title="Delete Post">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($post['user_id'] != $user_id): ?>
                                                <div class="post-actions">
                                                    <form method="POST" action="follow_user.php" style="display: inline;">
                                                        <input type="hidden" name="followed_id" value="<?= $post['user_id'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= $post['is_following'] ? 'btn-secondary' : 'btn-primary' ?> follow-btn" title="<?= $post['is_following'] ? 'Unfollow' : 'Follow' ?> User">
                                                            <i class="fas <?= $post['is_following'] ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                                                            <?= $post['is_following'] ? 'Unfollow' : 'Follow' ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="post-content"><?= htmlspecialchars($post['content'] ?? 'No content available') ?></div>
                                        <?php if (isset($post['file_path']) && $post['file_path']): ?>
                                            <img src="<?= htmlspecialchars($post['file_path']) ?>" alt="Post Image" class="post-image">
                                        <?php endif; ?>
                                        <div class="engagement-bar">
                                            <button class="engagement-btn like-btn <?= isset($post['user_vote']) && $post['user_vote'] == 1 ? 'liked' : '' ?>" onclick="likePost(<?= $post['post_id'] ?>)">
                                                <i class="fas fa-heart"></i>
                                                <span class="like-count"><?= $post['upvotes'] ?></span>
                                            </button>
                                            <button class="engagement-btn comment-btn" onclick="toggleComments(<?= $post['post_id'] ?>)">
                                                <i class="fas fa-comment"></i>
                                                <span class="comment-count"><?= $post['comment_count'] ?></span>
                                            </button>
                                        </div>
                                        
                                        <div class="comments-section" style="display: none;">
                                            <div class="comment-form">
                                                <textarea placeholder="Write a comment..." maxlength="280"></textarea>
                                                <button onclick="submitComment(<?= $post['post_id'] ?>)">Reply</button>
                                            </div>
                                            <div class="comment-list"></div>
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

        // AJAX Follow functionality
        document.addEventListener('submit', function(e) {
            if (e.target.action && e.target.action.includes('follow_user.php')) {
                e.preventDefault();
                
                const form = e.target;
                const button = form.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;
                
                // Disable button and show loading state
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                
                const formData = new FormData(form);
                
                fetch('follow_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update button appearance
                        button.className = `btn btn-sm ${data.button_class} follow-btn`;
                        button.innerHTML = `<i class="fas ${data.icon_class}"></i> ${data.button_text}`;
                        
                        // Show success message
                        showToast(data.message, 'success');
                    } else {
                        // Show error message
                        showToast(data.message, 'error');
                        // Re-enable button
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred. Please try again.', 'error');
                    // Re-enable button
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            }
        });

        // Toast notification function
        function showToast(message, type) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            
            const toast = document.createElement('div');
            toast.className = `toast show`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
            
            toast.innerHTML = `
                <div class="toast-header ${bgClass} text-white">
                    <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                    <button type="button" class="btn-close btn-close-white" onclick="this.closest('.position-fixed').remove()"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            
            toastContainer.appendChild(toast);
            document.body.appendChild(toastContainer);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toastContainer.parentNode) {
                    toastContainer.remove();
                }
            }, 3000);
        }

        function likePost(postId) {
            fetch('like_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'post_id=' + postId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const likeBtn = document.querySelector(`[data-post-id="${postId}"] .like-btn`);
                    const likeCount = document.querySelector(`[data-post-id="${postId}"] .like-count`);
                    
                    if (data.action === 'liked') {
                        likeBtn.classList.add('liked');
                        likeCount.textContent = parseInt(likeCount.textContent) + 1;
                    } else {
                        likeBtn.classList.remove('liked');
                        likeCount.textContent = parseInt(likeCount.textContent) - 1;
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function toggleComments(postId) {
            const commentsSection = document.querySelector(`[data-post-id="${postId}"] .comments-section`);
            const commentBtn = document.querySelector(`[data-post-id="${postId}"] .comment-btn`);
            
            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';
                commentBtn.classList.add('commented');
                loadComments(postId);
            } else {
                commentsSection.style.display = 'none';
                commentBtn.classList.remove('commented');
            }
        }

        function loadComments(postId) {
            const commentList = document.querySelector(`[data-post-id="${postId}"] .comment-list`);
            commentList.innerHTML = '<div class="text-center text-secondary">Loading comments...</div>';

            fetch(`get_comments.php?post_id=${postId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if (data.data && data.data.length > 0) {
                            commentList.innerHTML = data.data.map(comment => `
                                <div class="comment">
                                    <div class="comment-header">
                                        <span class="comment-username">${comment.username}</span>
                                        <span class="comment-time">${formatTimeAgo(comment.created_at)}</span>
                                    </div>
                                    <div class="comment-content">${comment.content}</div>
                                </div>
                            `).join('');
                        } else {
                            commentList.innerHTML = '<div class="text-center text-secondary">No comments yet</div>';
                        }
                    } else {
                        throw new Error(data.message || 'Failed to load comments');
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentList.innerHTML = `<div class="text-center text-danger">
                        Error loading comments: ${error.message}
                        <br>
                        <small>Please try refreshing the page</small>
                    </div>`;
                });
        }

        function formatTimeAgo(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000); // difference in seconds
            
            if (diff < 60) {
                return `${diff}s`;
            } else if (diff < 3600) {
                return `${Math.floor(diff / 60)}m`;
            } else if (diff < 86400) {
                return `${Math.floor(diff / 3600)}h`;
            } else {
                return `${Math.floor(diff / 86400)}d`;
            }
        }

        function submitComment(postId) {
            const form = document.querySelector(`[data-post-id="${postId}"] .comment-form`);
            const textarea = form.querySelector('textarea');
            const content = textarea.value.trim();

            if (!content) return;

            fetch('comment_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textarea.value = '';
                    loadComments(postId);
                }
            })
            .catch(error => console.error('Error:', error));
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