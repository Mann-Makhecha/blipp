<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Get the user ID to view (default to current user)
$profile_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;

// Get the type (followers or following)
$type = $_GET['type'] ?? 'followers';

// Fetch user information
$user_stmt = $conn->prepare("SELECT username, profile_image FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $profile_user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    header("Location: index.php");
    exit();
}

// Fetch followers or following based on type
$users = [];
if ($type === 'followers') {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.profile_image, u.created_at,
               " . ($user_id ? "(SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = u.user_id) as is_following" : "0 as is_following") . "
        FROM follows f
        JOIN users u ON f.follower_id = u.user_id
        WHERE f.followed_id = ?
        ORDER BY f.followed_at DESC
    ");
    if ($user_id) {
        $stmt->bind_param("ii", $user_id, $profile_user_id);
    } else {
        $stmt->bind_param("i", $profile_user_id);
    }
} else {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.profile_image, u.created_at,
               " . ($user_id ? "(SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = u.user_id) as is_following" : "0 as is_following") . "
        FROM follows f
        JOIN users u ON f.followed_id = u.user_id
        WHERE f.follower_id = ?
        ORDER BY f.followed_at DESC
    ");
    if ($user_id) {
        $stmt->bind_param("ii", $user_id, $profile_user_id);
    } else {
        $stmt->bind_param("i", $profile_user_id);
    }
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Function to format time ago
function timeAgo($datetime) {
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $time = new DateTime($datetime, new DateTimeZone('Asia/Kolkata'));
    $interval = $now->diff($time);

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
    <title><?= ucfirst($type) ?> - @<?= htmlspecialchars($user['username']) ?> - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        body {
            background-color: #000;
            color: #fff;
        }

        .user-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: background-color 0.2s;
        }

        .user-card:hover {
            background-color: #222;
        }

        .profile-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.5rem;
        }

        .btn-primary {
            background-color: #1d9bf0;
            border-color: #1d9bf0;
        }

        .btn-primary:hover {
            background-color: #1a8cd8;
            border-color: #1a8cd8;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        .nav-tabs .nav-link {
            color: #999;
            border: none;
            border-bottom: 2px solid transparent;
        }

        .nav-tabs .nav-link.active {
            color: #1d9bf0;
            border-bottom-color: #1d9bf0;
        }

        .nav-tabs .nav-link:hover {
            color: #1d9bf0;
            border-bottom-color: #1d9bf0;
        }

        @media (max-width: 767px) {
            body {
                padding-bottom: 60px;
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
            <div class="col-md-6 py-4 px-3">
                <!-- Header -->
                <div class="d-flex align-items-center mb-4">
                    <a href="profile.php?user_id=<?= $profile_user_id ?>" class="btn btn-link text-white me-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h4 class="mb-0">@<?= htmlspecialchars($user['username']) ?></h4>
                        <small class="text-muted"><?= ucfirst($type) ?></small>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="followTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $type === 'followers' ? 'active' : '' ?>" 
                           href="followers.php?user_id=<?= $profile_user_id ?>&type=followers">
                            Followers
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $type === 'following' ? 'active' : '' ?>" 
                           href="followers.php?user_id=<?= $profile_user_id ?>&type=following">
                            Following
                        </a>
                    </li>
                </ul>

                <!-- Users List -->
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user_item): ?>
                        <div class="user-card">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <?php if ($user_item['profile_image']): ?>
                                        <img src="<?= htmlspecialchars($user_item['profile_image']) ?>" alt="Profile" class="profile-image me-3">
                                    <?php else: ?>
                                        <div class="profile-icon me-3">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="profile.php?user_id=<?= $user_item['user_id'] ?>" class="text-white text-decoration-none fw-bold">
                                            @<?= htmlspecialchars($user_item['username']) ?>
                                        </a>
                                        <div class="text-muted small">
                                            Joined <?= timeAgo($user_item['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($user_id && $user_item['user_id'] != $user_id): ?>
                                    <div>
                                        <form method="POST" action="follow_user.php" style="display: inline;">
                                            <input type="hidden" name="followed_id" value="<?= $user_item['user_id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $user_item['is_following'] ? 'btn-secondary' : 'btn-primary' ?>">
                                                <i class="fas <?= $user_item['is_following'] ? 'fa-user-minus' : 'fa-user-plus' ?>"></i>
                                                <?= $user_item['is_following'] ? 'Unfollow' : 'Follow' ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No <?= $type ?> yet.</p>
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