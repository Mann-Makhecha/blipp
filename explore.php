<?php
session_start();

// Initialize $conn as null and handle database connection errors
$conn = null;
$errors = [];
try {
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
include 'includes/checklogin.php';
// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Handle search query and tab selection
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users'; // Default to 'users' tab
$search_results = [];

// Search logic based on the active tab
if ($search_query && $conn) {
    $search_term = '%' . $conn->real_escape_string($search_query) . '%';
    try {
        if ($active_tab === 'users') {
            // Search users by username
            $stmt = $conn->prepare("
                SELECT user_id, username
                FROM users
                WHERE username LIKE ?
                LIMIT 10
            ");
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $search_results[] = $row;
            }
            $stmt->close();
        } elseif ($active_tab === 'posts') {
            // Search posts by content
            $stmt = $conn->prepare("
                SELECT p.post_id, p.content, p.created_at, u.username
                FROM posts p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.content LIKE ?
                ORDER BY p.created_at DESC
                LIMIT 10
            ");
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $search_results[] = $row;
            }
            $stmt->close();
        } elseif ($active_tab === 'communities') {
            // Search communities by name
            $stmt = $conn->prepare("
                SELECT community_id, name
                FROM communities
                WHERE name LIKE ? AND is_private = 0
                LIMIT 10
            ");
            $stmt->bind_param("s", $search_term);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $search_results[] = $row;
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error executing search query: " . $e->getMessage();
    }
}

// Fetch suggested users (users the current user isn't following)
$suggested_users = [];
if ($user_id && $conn && empty($search_query)) {
    try {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username,
                   CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END as is_following
            FROM users u
            LEFT JOIN follows f ON u.user_id = f.followed_id AND f.follower_id = ?
            WHERE u.user_id != ?
            ORDER BY RAND()
            LIMIT 5
        ");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $suggested_users[] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error fetching suggested users: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">
    <link rel="stylesheet" href="css/explore.css">
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
                <?php if (!$conn): ?>
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

                <!-- Search Bar -->
                <div class="search-bar mb-4">
                    <form method="GET" action="explore.php">
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-dark text-white">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" name="q" placeholder="Search Blipp..." value="<?= htmlspecialchars($search_query) ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>
                </div>

                <!-- Tabs -->
                <?php if ($search_query): ?>
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link <?= $active_tab === 'users' ? 'active' : '' ?>" href="explore.php?q=<?= urlencode($search_query) ?>&tab=users">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $active_tab === 'posts' ? 'active' : '' ?>" href="explore.php?q=<?= urlencode($search_query) ?>&tab=posts">Posts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $active_tab === 'communities' ? 'active' : '' ?>" href="explore.php?q=<?= urlencode($search_query) ?>&tab=communities">Communities</a>
                        </li>
                    </ul>
                <?php endif; ?>

                <!-- Search Results or Suggested Users -->
                <?php if ($search_query && !empty($search_results)): ?>
                    <h5 class="fw-bold mb-3">Search Results for "<?= htmlspecialchars($search_query) ?>"</h5>
                    <?php if ($active_tab === 'users'): ?>
                        <?php foreach ($search_results as $user): ?>
                            <div class="user-card p-3 d-flex align-items-center">
                                <i class="fas fa-user-circle fa-2x me-3" style="color: #666;"></i>
                                <div class="flex-grow-1">
                                    <a href="profile.php?user_id=<?= $user['user_id'] ?>" class="text-white text-decoration-none fw-bold">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </a>
                                    <div class="text-white small">@<?= htmlspecialchars($user['username']) ?></div>
                                </div>
                                <?php if ($user_id && $user['user_id'] != $user_id): ?>
                                    <?php
                                    // Check if current user is following this user
                                    $follow_check = $conn->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
                                    $follow_check->bind_param("ii", $user_id, $user['user_id']);
                                    $follow_check->execute();
                                    $is_following = $follow_check->get_result()->num_rows > 0;
                                    $follow_check->close();
                                    ?>
                                    <button class="btn btn-sm rounded-pill follow-btn" 
                                            data-user-id="<?= $user['user_id'] ?>"
                                            data-following="<?= $is_following ? 1 : 0 ?>"
                                            onclick="toggleFollow(<?= $user['user_id'] ?>, this)">
                                        <?php if ($is_following): ?>
                                            <span class="btn btn-secondary btn-sm rounded-pill">Following</span>
                                        <?php else: ?>
                                            <span class="btn btn-outline-primary btn-sm rounded-pill">Follow</span>
                                        <?php endif; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($active_tab === 'posts'): ?>
                        <?php foreach ($search_results as $post): ?>
                            <div class="post-card p-3">
                                <div class="d-flex">
                                    <i class="fas fa-user-circle fa-2x me-3" style="color: #666;"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="fw-bold me-2">@<?= htmlspecialchars($post['username']) ?></span>
                                            <span class="text-white" style="font-size: 0.9rem;"><?= timeAgo($post['created_at']) ?></span>
                                        </div>
                                        <div class="post-content"><?= htmlspecialchars($post['content'] ?? 'No content available') ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($active_tab === 'communities'): ?>
                        <?php foreach ($search_results as $community): ?>
                            <div class="community-card p-3 d-flex align-items-center">
                                <i class="fas fa-users fa-2x me-3" style="color: #666;"></i>
                                <div class="flex-grow-1">
                                    <a href="community.php?community_id=<?= $community['community_id'] ?>" class="text-white text-decoration-none fw-bold">
                                        <?= htmlspecialchars($community['name']) ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php elseif ($search_query): ?>
                    <p class="text-white p-3">No results found for "<?= htmlspecialchars($search_query) ?>".</p>
                <?php else: ?>
                    <!-- Suggested Users -->
                    <?php if (!empty($suggested_users)): ?>
                        <h5 class="fw-bold mb-3">Suggested Users</h5>
                        <?php foreach ($suggested_users as $user): ?>
                            <div class="user-card p-3 d-flex align-items-center">
                                <i class="fas fa-user-circle fa-2x me-3" style="color: #666;"></i>
                                <div class="flex-grow-1">
                                    <a href="profile.php?user_id=<?= $user['user_id'] ?>" class="text-white text-decoration-none fw-bold">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </a>
                                    <div class="text-white small">@<?= htmlspecialchars($user['username']) ?></div>
                                </div>
                                <?php if ($user_id && $user['user_id'] != $user_id): ?>
                                    <button class="btn btn-sm rounded-pill follow-btn" 
                                            data-user-id="<?= $user['user_id'] ?>"
                                            data-following="<?= $user['is_following'] ?>"
                                            onclick="toggleFollow(<?= $user['user_id'] ?>, this)">
                                        <?php if ($user['is_following']): ?>
                                            <span class="btn btn-secondary btn-sm rounded-pill">Following</span>
                                        <?php else: ?>
                                            <span class="btn btn-outline-primary btn-sm rounded-pill">Follow</span>
                                        <?php endif; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-white p-3">No suggestions available.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="col-md-3 bg-dark text-white">
                <?php require_once 'includes/rightsidebar.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Follow/Unfollow functionality
        function toggleFollow(userId, button) {
            const isFollowing = button.getAttribute('data-following') === '1';
            const action = isFollowing ? 'unfollow' : 'follow';
            
            // Show loading state
            const originalContent = button.innerHTML;
            button.innerHTML = '<span class="btn btn-secondary btn-sm rounded-pill"><i class="fas fa-spinner fa-spin"></i></span>';
            button.disabled = true;
            
            // Make AJAX request
            fetch('follow_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `followed_id=${userId}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button state
                    if (action === 'follow') {
                        button.setAttribute('data-following', '1');
                        button.innerHTML = '<span class="btn btn-secondary btn-sm rounded-pill">Following</span>';
                    } else {
                        button.setAttribute('data-following', '0');
                        button.innerHTML = '<span class="btn btn-outline-primary btn-sm rounded-pill">Follow</span>';
                    }
                    
                    // Show success message
                    showToast(data.message, 'success');
                } else {
                    // Restore original state on error
                    button.innerHTML = originalContent;
                    showToast(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = originalContent;
                showToast('An error occurred while processing your request', 'error');
            })
            .finally(() => {
                button.disabled = false;
            });
        }
        
        // Toast notification function
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    max-width: 300px;
                `;
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
            toast.style.cssText = `
                margin-bottom: 10px;
                padding: 10px 15px;
                border-radius: 5px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                animation: slideIn 0.3s ease-out;
            `;
            
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Add toast to container
            toastContainer.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>