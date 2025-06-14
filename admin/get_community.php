<?php
require_once '../includes/db.php';
require_once '../includes/settings.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Community ID is required']);
    exit;
}

$community_id = (int)$_GET['id'];

// Get community details with member and post counts
$query = "SELECT c.*, 
          COUNT(DISTINCT cm.user_id) as member_count,
          COUNT(DISTINCT p.post_id) as post_count,
          u.username as creator_username
          FROM communities c
          LEFT JOIN community_members cm ON c.community_id = cm.community_id
          LEFT JOIN posts p ON c.community_id = p.community_id
          LEFT JOIN users u ON c.creator_id = u.user_id
          WHERE c.community_id = ?
          GROUP BY c.community_id";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $community_id);
$stmt->execute();
$result = $stmt->get_result();

if ($community = $result->fetch_assoc()) {
    // Format the response
    $response = [
        'community_id' => $community['community_id'],
        'name' => $community['name'],
        'description' => $community['description'],
        'is_private' => (bool)$community['is_private'],
        'created_at' => $community['created_at'],
        'creator_username' => $community['creator_username'],
        'member_count' => (int)$community['member_count'],
        'post_count' => (int)$community['post_count']
    ];
    
    echo json_encode($response);
} else {
    echo json_encode(['error' => 'Community not found']);
} 