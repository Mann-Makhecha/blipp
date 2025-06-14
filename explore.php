<?php
session_start();

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

// Handle search query and tab selection
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users'; // Default to 'users' tab
$search_results = [];

// Search logic based on the active tab
if ($search_query && $mysqli) {
    $search_term = '%' . $mysqli->real_escape_string($search_query) . '%';
    try {
        if ($active_tab === 'users') {
            // Search users by username
            $stmt = $mysqli->prepare("
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
            $stmt = $mysqli->prepare("
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
            $stmt = $mysqli->prepare("
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
if ($user_id && $mysqli && empty($search_query)) {
    try {
        $stmt = $mysqli->prepare("
            SELECT u.user_id, u.username
            FROM users u
            WHERE u.user_id != ?
            AND u.user_id NOT IN (
                SELECT followed_id FROM follows WHERE follower_id = ?
            )
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
    <title>Explore - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        body {
            background-color: #000;
            color: #fff;
        }
        .search-bar .form-control {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
        }
        .search-bar .form-control::placeholder {
            color: #666;
        }
        .search-bar .form-control:focus {
            background-color: #1a1a1a;
            border-color: #1d9bf0;
            box-shadow: 0 0 0 0.25rem rgba(29, 155, 240, 0.25);
            color: #fff;
        }
        .search-bar .btn {
            background-color: #1d9bf0;
            border-color: #1d9bf0;
        }
        .search-bar .btn:hover {
            background-color: #1a8cd8;
            border-color: #1a8cd8;
        }
        .nav-tabs .nav-link {
            color: #fff;
            border: none;
        }
        .nav-tabs .nav-link.active {
            color: #1d9bf0;
            border-bottom: 2px solid #1d9bf0;
        }
        .user-card, .post-card, .community-card {
            border-bottom: 1px solid #333;
            transition: background-color 0.2s;
        }
        .user-card:hover, .post-card:hover, .community-card:hover {
            background-color: #111;
        }
        .user-card .btn-outline-primary {
            border-color: #1d9bf0;
            color: #1d9bf0;
            font-size: 0.8rem;
            padding: 2px 10px;
        }
        .user-card .btn-outline-primary:hover {
            background-color: #1d9bf0;
            color: #fff;
        }
        @media (max-width: 767px) {
            body {
                padding-bottom: 60px; /* Match sidebar.php's padding for bottom nav */
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
                                <button class="btn btn-outline-primary btn-sm rounded-pill">Follow</button>
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
                                <button class="btn btn-outline-primary btn-sm rounded-pill">Follow</button>
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
</body>
</html>