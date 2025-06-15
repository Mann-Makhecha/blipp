<?php
require_once 'includes/db.php';
require_once 'includes/settings.php';
require_once 'includes/upload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content']);
    $community_id = isset($_POST['community_id']) ? (int)$_POST['community_id'] : null;
    
    // Validate content
    if (empty($content)) {
        $error = "Post content cannot be empty.";
    } else {
        // Handle file upload if present
        $filename = null;
        if (isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = handle_file_upload($_FILES['media'], 'uploads/posts');
            
            if (!$upload_result['success']) {
                $error = $upload_result['message'];
            } else {
                $filename = $upload_result['filename'];
            }
        }
        
        if (empty($error)) {
            // Insert post
            $stmt = $mysqli->prepare("
                INSERT INTO posts (user_id, community_id, content, media_file) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiss", $_SESSION['user_id'], $community_id, $content, $filename);
            
            if ($stmt->execute()) {
                $success = "Post created successfully!";
                // Clear form
                $content = '';
                $community_id = null;
            } else {
                $error = "Failed to create post. Please try again.";
                // Delete uploaded file if post creation failed
                if ($filename) {
                    delete_file('uploads/posts/' . $filename);
                }
            }
        }
    }
}

// Get user's communities for the dropdown
$communities_query = $mysqli->prepare("
    SELECT c.id, c.name 
    FROM communities c 
    JOIN community_members cm ON c.id = cm.community_id 
    WHERE cm.user_id = ? AND cm.status = 'active'
    ORDER BY c.name
");
$communities_query->bind_param("i", $_SESSION['user_id']);
$communities_query->execute();
$communities = $communities_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - <?= htmlspecialchars(get_setting('site_name', 'Blipp')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h1 class="h4 mb-0">Create New Post</h1>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="content" class="form-label">What's on your mind?</label>
                                <textarea class="form-control" id="content" name="content" rows="4" 
                                          required><?= htmlspecialchars($content ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="community_id" class="form-label">Post to Community (Optional)</label>
                                <select class="form-select" id="community_id" name="community_id">
                                    <option value="">Select a community</option>
                                    <?php while ($community = $communities->fetch_assoc()): ?>
                                        <option value="<?= $community['id'] ?>" 
                                                <?= ($community_id ?? '') == $community['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($community['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="media" class="form-label">Add Media (Optional)</label>
                                <input type="file" class="form-control" id="media" name="media" 
                                       accept="<?= '.' . implode(',.', get_allowed_file_types()) ?>">
                                <div class="form-text">
                                    Maximum file size: <?= get_max_file_size() ?>MB. 
                                    Allowed types: <?= implode(', ', get_allowed_file_types()) ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Post
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 