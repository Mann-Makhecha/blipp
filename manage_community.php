<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/checklogin.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = [];

// Get community details
$stmt = $mysqli->prepare("
    SELECT c.*, u.username as creator_username,
           (SELECT COUNT(*) FROM community_members WHERE community_id = c.community_id) as member_count,
           (SELECT COUNT(*) FROM posts WHERE community_id = c.community_id) as post_count
    FROM communities c
    LEFT JOIN users u ON c.creator_id = u.user_id
    WHERE c.community_id = ? AND c.creator_id = ?
");
$stmt->bind_param("ii", $community_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$community = $result->fetch_assoc();
$stmt->close();

if (!$community) {
    header('Location: communities.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_community'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $is_private = isset($_POST['is_private']) ? 1 : 0;

        if (empty($name)) {
            $errors[] = "Community name is required.";
        } elseif (strlen($name) > 50) {
            $errors[] = "Community name must be less than 50 characters.";
        }

        if (empty($errors)) {
            $stmt = $mysqli->prepare("UPDATE communities SET name = ?, description = ?, is_private = ? WHERE community_id = ? AND creator_id = ?");
            $stmt->bind_param("ssiii", $name, $description, $is_private, $community_id, $user_id);
            if ($stmt->execute()) {
                $success[] = "Community updated successfully.";
                $community['name'] = $name;
                $community['description'] = $description;
                $community['is_private'] = $is_private;
            } else {
                $errors[] = "Failed to update community.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_community'])) {
        // Start transaction
        $mysqli->begin_transaction();
        try {
            // Delete community members
            $stmt = $mysqli->prepare("DELETE FROM community_members WHERE community_id = ?");
            $stmt->bind_param("i", $community_id);
            $stmt->execute();
            $stmt->close();

            // Delete community posts
            $stmt = $mysqli->prepare("DELETE FROM posts WHERE community_id = ?");
            $stmt->bind_param("i", $community_id);
            $stmt->execute();
            $stmt->close();

            // Delete community
            $stmt = $mysqli->prepare("DELETE FROM communities WHERE community_id = ? AND creator_id = ?");
            $stmt->bind_param("ii", $community_id, $user_id);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
            header('Location: communities.php');
            exit();
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = "Failed to delete community: " . $e->getMessage();
        }
    }
}

// Get community members
$stmt = $mysqli->prepare("
    SELECT u.user_id, u.username, u.role, cm.joined_at
    FROM community_members cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.community_id = ?
    ORDER BY cm.joined_at DESC
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$members = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Community - <?= htmlspecialchars($community['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        body {
            background-color: #000;
            color: #fff;
        }
        .form-control, .form-check-input {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
        }
        .form-control:focus {
            background-color: #1a1a1a;
            border-color: #1d9bf0;
            box-shadow: 0 0 0 0.25rem rgba(29, 155, 240, 0.25);
        }
        .card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: white;
        }
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <!-- Back Button -->
                <div class="mb-4">
                    <a href="communities.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Back to Communities
                    </a>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Success Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php foreach ($success as $message): ?>
                            <p class="mb-0"><?= htmlspecialchars($message) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Community Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Manage Community</h2>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Community Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($community['name']) ?>" required maxlength="50">
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($community['description']) ?></textarea>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_private" name="is_private" 
                                       <?= $community['is_private'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_private">Private Community</label>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="update_community" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash"></i> Delete Community
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Community Stats -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title mb-4">Community Stats</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h4><?= number_format($community['member_count']) ?></h4>
                                    <p class="text-white">Members</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h4><?= number_format($community['post_count']) ?></h4>
                                    <p class="text-white">Posts</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h4><?= date('M d, Y', strtotime($community['created_at'])) ?></h4>
                                    <p class="text-white">Created</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Members List -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title mb-4">Members</h3>
                        <div class="table-responsive">
                            <table class="table table-dark">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($member = $members->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($member['username']) ?></td>
                                            <td>
                                                <?php if ($member['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Member</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($member['joined_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Delete Community</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this community? This action cannot be undone.</p>
                    <p class="text-danger">All posts and member data will be permanently deleted.</p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" class="d-inline">
                        <button type="submit" name="delete_community" class="btn btn-danger">Delete Community</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 