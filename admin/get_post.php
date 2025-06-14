<?php
require_once '../includes/db.php';
require_once 'includes/auth.php';

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
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($post = $result->fetch_assoc()) {
    // Fetch associated files
    $files_stmt = $mysqli->prepare("
        SELECT file_name, file_path, file_type
        FROM files
        WHERE post_id = ?
    ");
    $files_stmt->bind_param("i", $post_id);
    $files_stmt->execute();
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