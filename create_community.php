<?php
session_start(); // Must be the very first line
require_once 'includes/db.php';
require_once 'includes/settings.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please log in to create a community.";
    header("Location: login.php");
    exit();
}

// Check if user has enough points (skip for admins)
if ($_SESSION['role'] !== 'admin' && !can_create_community($_SESSION['user_id'])) {
    $required_points = get_community_creation_points();
    $user_points = get_user_points($_SESSION['user_id']);
    $_SESSION['error_message'] = "You need at least " . number_format($required_points) . " points to create a community. You currently have " . number_format($user_points) . " points.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    if (empty($name) || empty($description)) {
        $_SESSION['error_message'] = "Please fill in all required fields.";
        header("Location: create_community.php");
        exit();
    }
    
    // Check if community name already exists
    $stmt = $mysqli->prepare("SELECT community_id FROM communities WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error_message'] = "A community with this name already exists.";
        header("Location: create_community.php");
        exit();
    }
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Create community
        $stmt = $mysqli->prepare("INSERT INTO communities (name, description, creator_id, is_private) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $name, $description, $user_id, $is_private);
        
        if ($stmt->execute()) {
            $community_id = $mysqli->insert_id;
            
            // Add creator as first member
            $stmt = $mysqli->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'admin')");
            $stmt->bind_param("ii", $community_id, $user_id);
            $stmt->execute();
            
            $mysqli->commit();
            $_SESSION['success_message'] = "Community created successfully!";
            // Redirect to admin/communities.php
            header("Location: admin/communities.php");
            exit();
        } else {
            throw new Exception("Error creating community");
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error_message'] = "Error creating community: " . $e->getMessage();
        header("Location: create_community.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Community - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Create a New Community</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($_SESSION['error_message']) ?>
                                <?php unset($_SESSION['error_message']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?= htmlspecialchars($_SESSION['success_message']) ?>
                                <?php unset($_SESSION['success_message']); ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Community Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_private" name="is_private">
                                <label class="form-check-label" for="is_private">Make this community private</label>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Create Community</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 