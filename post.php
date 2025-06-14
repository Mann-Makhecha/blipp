<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
include 'includes/checklogin.php';

// Fetch communities the user is a member of
$communities_stmt = $mysqli->prepare("
    SELECT c.community_id, c.name 
    FROM communities c 
    JOIN community_members cm ON c.community_id = cm.community_id 
    WHERE cm.user_id = ?
");
$communities_stmt->bind_param("i", $user_id);
$communities_stmt->execute();
$communities_result = $communities_stmt->get_result();
$communities = [];
while ($row = $communities_result->fetch_assoc()) {
    $communities[] = $row;
}
$communities_stmt->close();

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            INSERT INTO posts (community_id, user_id, content, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
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

            // Redirect to index.php after successful post
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Failed to create post: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        body {
            background-color: #000;
            color: #fff;
        }

        .form-control,
        .form-select {
            background-color: #1a1a1a;
            color: #fff;
            border-color: #333;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: #1a1a1a;
            color: #fff;
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

        .container {
            max-width: 600px;
        }

        textarea {
            resize: none;
        }

        .back-button {
            color: var(--accent-primary);
            text-decoration: none;
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .back-button i {
            margin-right: 0.5rem;
        }

        .back-button:hover {
            color: #1a8cd8;
        }
    </style>
</head>

<body>
    <div class="container mt-4">
        <a href="index.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <h2>Create a Post</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mt-3" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="mt-4">
            <div class="mb-3">
                <label for="community_id" class="form-label">Post to Community (Optional)</label>
                <select class="form-select" id="community_id" name="community_id">
                    <option value="" <?= !isset($_POST['community_id']) || empty($_POST['community_id']) ? 'selected' : '' ?>>Public Post</option>
                    <?php foreach ($communities as $community): ?>
                        <option value="<?= $community['community_id'] ?>" <?= isset($_POST['community_id']) && $_POST['community_id'] == $community['community_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($community['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="content" class="form-label text-white">What's happening?</label>
                <textarea class="form-control fw-light" id="content" name="content" rows="3" maxlength="280" required><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?></textarea>
                <small class="text-white fw-light">280 characters max</small>
            </div>
            <div class="mb-3">
                <label for="file" class="form-label">Add an Image (Optional)</label>
                <input type="file" class="form-control" id="file" name="file" accept="image/jpeg,image/png,image/gif">
                <small class="text-white">JPEG, PNG, GIF only, max 5MB</small>
            </div>
            <button type="submit" class="btn btn-primary">Post</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and GIF files are allowed.');
                    e.target.value = '';
                } else if (file.size > maxSize) {
                    alert('File size must not exceed 5MB.');
                    e.target.value = '';
                }
            }
        });

        document.getElementById('content').addEventListener('input', function() {
            if (this.value.length > 280) {
                this.value = this.value.substring(0, 280);
            }
        });
    </script>
</body>

</html>