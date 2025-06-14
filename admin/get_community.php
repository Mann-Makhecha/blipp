<?php
require_once '../includes/db.php';
require_once 'includes/auth.php';

// Get community ID
$community_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$community_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid community ID']));
}

// Get community details
$stmt = $mysqli->prepare("
    SELECT 
        c.*,
        u.username as creator_username,
        (SELECT COUNT(*) FROM community_members WHERE community_id = c.community_id) as member_count,
        (SELECT COUNT(*) FROM posts WHERE community_id = c.community_id) as post_count
    FROM communities c
    LEFT JOIN users u ON c.creator_id = u.user_id
    WHERE c.community_id = ?
");
$stmt->bind_param("i", $community_id);
$stmt->execute();
$result = $stmt->get_result();

if ($community = $result->fetch_assoc()) {
    echo json_encode($community);
} else {
    echo json_encode(['error' => 'Community not found']);
} 