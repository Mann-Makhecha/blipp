<?php
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once 'includes/auth.php';

// Check if database connection is valid and $mysqli is an object
if (!$mysqli || $mysqli->connect_error) {
    error_log("admin/get_post.php: Database connection failed. Error: " . ($mysqli->connect_error ?? "Unknown error"));
    echo json_encode(['success' => false, 'message' => 'Database connection error. Please try again later.']);
    exit();
}

// Get post ID
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

// Fetch post data
$stmt = $mysqli->prepare("
    SELECT 
        p.*,
        u.username,
        c.name as community_name,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
        (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as report_count
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN communities c ON p.community_id = c.community_id
    WHERE p.post_id = ?
");

// Check if statement preparation failed
if (!$stmt) {
    error_log("admin/get_post.php: Failed to prepare post query: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve post data.']);
    exit();
}

$stmt->bind_param("i", $post_id);

// Check if execution failed
if (!$stmt->execute()) {
    error_log("admin/get_post.php: Failed to execute post query: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve post data.']);
    exit();
}

$result = $stmt->get_result();

if ($post = $result->fetch_assoc()) {
    // Fetch associated files
    $files_stmt = $mysqli->prepare("
        SELECT file_name, file_path, file_type
        FROM files
        WHERE post_id = ?
    ");

    // Check if files statement preparation failed
    if (!$files_stmt) {
        error_log("admin/get_post.php: Failed to prepare files query: " . $mysqli->error);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve file data.']);
        exit();
    }

    $files_stmt->bind_param("i", $post_id);
    
    // Check if files execution failed
    if (!$files_stmt->execute()) {
        error_log("admin/get_post.php: Failed to execute files query: " . $files_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve file data.']);
        exit();
    }

    $files_result = $files_stmt->get_result();
    
    $files = [];
    while ($file = $files_result->fetch_assoc()) {
        $files[] = $file;
    }
    
    $post['files'] = $files;
    $post['created_at'] = date('F j, Y g:i A', strtotime($post['created_at']));
    
    echo json_encode(['success' => true, 'post' => $post]);
} else {
    echo json_encode(['success' => false, 'message' => 'Post not found']);
}