<?php
session_start();
require_once 'includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to follow users.']);
    exit();
}

// Check if followed_id is provided
if (!isset($_POST['followed_id']) || !is_numeric($_POST['followed_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit();
}

$followed_id = (int)$_POST['followed_id'];

// Prevent users from following themselves
if ($user_id == $followed_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot follow yourself.']);
    exit();
}

// Check if the user to follow exists
$stmt = $mysqli->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $followed_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}

$followed_user = $result->fetch_assoc();
$stmt->close();

// Check if already following
$stmt = $mysqli->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
$stmt->bind_param("ii", $user_id, $followed_id);
$stmt->execute();
$is_following = $stmt->get_result()->num_rows > 0;
$stmt->close();

// Handle follow/unfollow action
if ($is_following) {
    // Unfollow
    $stmt = $mysqli->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->bind_param("ii", $user_id, $followed_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'action' => 'unfollowed',
            'message' => 'You have unfollowed @' . htmlspecialchars($followed_user['username']) . '.',
            'button_text' => 'Follow',
            'button_class' => 'btn-primary',
            'icon_class' => 'fa-user-plus'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unfollow user.']);
    }
    $stmt->close();
} else {
    // Follow
    $stmt = $mysqli->prepare("INSERT INTO follows (follower_id, followed_id, followed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $followed_id);
    
    if ($stmt->execute()) {
        // Create notification for the followed user
        $notification_stmt = $mysqli->prepare("
            INSERT INTO notifications (user_id, type, content, reference_id, created_at) 
            VALUES (?, 'follow', ?, ?, NOW())
        ");
        $notification_content = "User @" . $_SESSION['username'] . " started following you.";
        $notification_stmt->bind_param("isi", $followed_id, $notification_content, $user_id);
        $notification_stmt->execute();
        $notification_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'action' => 'followed',
            'message' => 'You are now following @' . htmlspecialchars($followed_user['username']) . '.',
            'button_text' => 'Unfollow',
            'button_class' => 'btn-secondary',
            'icon_class' => 'fa-user-minus'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to follow user.']);
    }
    $stmt->close();
}

$mysqli->close();
?> 