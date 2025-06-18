<?php
session_start();

// Initialize $conn as null and handle database connection errors
$conn = null;
$errors = [];
try {
    require_once 'includes/db.php';
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
include 'includes/checklogin.php';

// Get user points and check if they can create a community
$user_points = 0;
$can_create_community = false;
if ($user_id && $conn) {
    try {
        $stmt = $conn->prepare("SELECT points FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_points = $row['points'];
            $can_create_community = $user_points >= 1000;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error checking points: " . $e->getMessage();
    }
}

// Handle community creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_community']) && $user_id && $can_create_community && $conn) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    if (empty($name)) {
        $errors[] = "Community name is required.";
    } elseif (strlen($name) > 50) {
        $errors[] = "Community name must be less than 50 characters.";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO communities (name, description, is_private, created_at, creator_id) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("ssii", $name, $description, $is_private, $user_id);
            $stmt->execute();
            $community_id = $conn->insert_id;
            $stmt->close();

            if ($community_id > 0) {
                // Add creator as a member
                $stmt = $conn->prepare("INSERT INTO community_members (community_id, user_id, joined_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $community_id, $user_id);
                $stmt->execute();
                $stmt->close();

                // Update member_count in communities table
                $stmt = $conn->prepare("UPDATE communities SET member_count = (SELECT COUNT(*) FROM community_members WHERE community_id = ?) WHERE community_id = ?");
                $stmt->bind_param("ii", $community_id, $community_id);
                $stmt->execute();
                $stmt->close();

                header("Location: communities.php");
                exit();
            } else {
                $errors[] = "Failed to retrieve the new community ID.";
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = "Error creating community: " . $e->getMessage();
        }
    }
}

// Handle joining a community
if (isset($_GET['join']) && $user_id && $conn) {
    $community_id = (int)$_GET['join'];
    try {
        // Check if community is private
        $stmt = $conn->prepare("SELECT is_private FROM communities WHERE community_id = ?");
        $stmt->bind_param("i", $community_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $community = $result->fetch_assoc();
        $stmt->close();

        if ($community) {
            if ($community['is_private']) {
                $errors[] = "This community is private. Please request to join.";
            } else {
                // Check if user is already a member
                $stmt = $conn->prepare("SELECT * FROM community_members WHERE community_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $community_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO community_members (community_id, user_id, joined_at) VALUES (?, ?, NOW())");
                    $stmt->bind_param("ii", $community_id, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    // Update member_count in communities table
                    $stmt = $conn->prepare("UPDATE communities SET member_count = (SELECT COUNT(*) FROM community_members WHERE community_id = ?) WHERE community_id = ?");
                    $stmt->bind_param("ii", $community_id, $community_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt->close();
                }
                header("Location: communities.php");
                exit();
            }
        }
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error joining community: " . $e->getMessage();
    }
}

// Fetch all communities
$communities = [];
if ($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT c.community_id, c.name, c.description, c.is_private, c.created_at, c.member_count, c.creator_id
            FROM communities c
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $communities[] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error fetching communities: " . $e->getMessage();
    }
}

// Reward points to community owners
if ($user_id && $conn) {
    try {
        $stmt = $conn->prepare("
            SELECT community_id, member_count
            FROM communities c
            WHERE c.creator_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['member_count'] >= 10000) {
                // Award 100 points (only once per community)
                $stmt2 = $conn->prepare("UPDATE users SET points = points + 100 WHERE user_id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $errors[] = "Error processing rewards: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communities - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">
    <link rel="stylesheet" href="communities.css">
    
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

                <!-- Points Info and Create Community Button -->
                <?php if ($user_id): ?>
                    <div class="points-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Your Points: <?= number_format($user_points) ?></h5>
                                <p class="mb-0 text-muted">Need 1,000 points to create a community</p>
                            </div>
                            <?php if ($can_create_community): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCommunityModal">
                                    <i class="fas fa-plus"></i> Create Community
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- List of Communities -->
                <h5 class="fw-bold mb-3">Communities</h5>
                <?php if (!empty($communities)): ?>
                    <?php foreach ($communities as $community): ?>
                        <div class="community-card p-3 d-flex align-items-center">
                            <i class="fas fa-users fa-2x me-3" style="color: #666;"></i>
                            <div class="flex-grow-1">
                                <a href="community.php?community_id=<?= $community['community_id'] ?>" class="text-white text-decoration-none fw-bold">
                                    <?= htmlspecialchars($community['name']) ?>
                                </a>
                                <div class="text-white small"><?= htmlspecialchars($community['description'] ?? 'No description') ?></div>
                                <div class="text-white small">Members: <?= $community['member_count'] ?></div>
                            </div>
                            <div class="d-flex align-items-center">
                                <?php if ($user_id): ?>
                                    <?php
                                    $is_member = false;
                                    $is_creator = $community['creator_id'] === $user_id;
                                    $stmt = $conn->prepare("SELECT * FROM community_members WHERE community_id = ? AND user_id = ?");
                                    $stmt->bind_param("ii", $community['community_id'], $user_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $is_member = true;
                                    }
                                    $stmt->close();
                                    ?>
                                    <?php if ($is_creator): ?>
                                        <a href="manage_community.php?id=<?= $community['community_id'] ?>" class="btn btn-outline-info btn-sm me-2">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$is_member): ?>
                                        <a href="communities.php?join=<?= $community['community_id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                                            Join
                                        </a>
                                    <?php else: ?>
                                        <span class="text-white small">Member</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white p-3">No communities found.</p>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="col-md-3 bg-dark text-white">
                <?php require_once 'includes/rightsidebar.php'; ?>
            </div>
        </div>
    </div>

    <!-- Create Community Modal -->
    <div class="modal fade" id="createCommunityModal" tabindex="-1" aria-labelledby="createCommunityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="createCommunityModalLabel">Create New Community</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="communities.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Community Name</label>
                            <input type="text" class="form-control bg-dark text-white" id="name" name="name" required maxlength="50" 
                                   placeholder="Enter community name">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control bg-dark text-white" id="description" name="description" rows="3" 
                                      placeholder="Describe your community"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="is_private" name="is_private">
                            <label class="form-check-label" for="is_private">Make this community private</label>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_community" class="btn btn-primary">Create Community</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#createCommunityModal form');
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                if (name.length < 3) {
                    e.preventDefault();
                    alert('Community name must be at least 3 characters long.');
                }
            });
        });
    </script>
</body>

</html>