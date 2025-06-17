<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $_SESSION['error_message'] = "You must be logged in to follow users.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

// Check if followed_id is provided
if (!isset($_POST['followed_id']) || !is_numeric($_POST['followed_id'])) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

$followed_id = (int)$_POST['followed_id'];

// Prevent users from following themselves
if ($user_id == $followed_id) {
    $_SESSION['error_message'] = "You cannot follow yourself.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

// Check if the user to follow exists
$stmt = $mysqli->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $followed_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
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
        $_SESSION['success_message'] = "You have unfollowed @" . htmlspecialchars($followed_user['username']) . ".";
    } else {
        $_SESSION['error_message'] = "Failed to unfollow user.";
    }
    $stmt->close();
} else {
    // Follow
    $stmt = $mysqli->prepare("INSERT INTO follows (follower_id, followed_id, followed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $followed_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "You are now following @" . htmlspecialchars($followed_user['username']) . ".";
        
        // Create notification for the followed user
        $notification_stmt = $mysqli->prepare("
            INSERT INTO notifications (user_id, type, content, reference_id, created_at) 
            VALUES (?, 'follow', ?, ?, NOW())
        ");
        $notification_content = "User @" . $_SESSION['username'] . " started following you.";
        $notification_stmt->bind_param("isi", $followed_id, $notification_content, $user_id);
        $notification_stmt->execute();
        $notification_stmt->close();
    } else {
        $_SESSION['error_message'] = "Failed to follow user.";
    }
    $stmt->close();
}

$mysqli->close();

// Redirect back to the previous page
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit();
?> 