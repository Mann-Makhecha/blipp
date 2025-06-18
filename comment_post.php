<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to comment']);
    exit;
}

// Check if required fields are provided
if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Post ID and content are required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'];
$content = trim($_POST['content']);
$parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : null;

// Validate content
if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit;
}

if (strlen($content) > 280) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot exceed 280 characters']);
    exit;
}

// Insert comment
$insert_stmt = $mysqli->prepare("
    INSERT INTO comments (post_id, user_id, parent_id, content, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
");
$insert_stmt->bind_param("iiis", $post_id, $user_id, $parent_id, $content);

if ($insert_stmt->execute()) {
    $comment_id = $mysqli->insert_id;
    
    // Get user info for the response
    $user_stmt = $mysqli->prepare("
        SELECT username 
        FROM users 
        WHERE user_id = ?
    ");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $comment_id,
            'content' => $content,
            'username' => $user_data['username'],
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $user_id
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to post comment']);
}

$insert_stmt->close();
?> 