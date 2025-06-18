<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/db.php';

function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function send_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $msg = "You must be logged in to follow users.";
    if (is_ajax()) send_json(['success' => false, 'message' => $msg]);
    $_SESSION['error_message'] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

if (!isset($_POST['followed_id']) || !is_numeric($_POST['followed_id'])) {
    $msg = "Invalid user ID.";
    if (is_ajax()) send_json(['success' => false, 'message' => $msg]);
    $_SESSION['error_message'] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

$followed_id = (int)$_POST['followed_id'];
if ($user_id == $followed_id) {
    $msg = "You cannot follow yourself.";
    if (is_ajax()) send_json(['success' => false, 'message' => $msg]);
    $_SESSION['error_message'] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

$stmt = $conn->prepare("SELECT user_id, username FROM users WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $followed_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $msg = "User not found.";
    if (is_ajax()) send_json(['success' => false, 'message' => $msg]);
    $_SESSION['error_message'] = $msg;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}
$followed_user = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
$stmt->bind_param("ii", $user_id, $followed_id);
$stmt->execute();
$is_following = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($is_following) {
    $stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->bind_param("ii", $user_id, $followed_id);
    $success = $stmt->execute();
    $stmt->close();
    if ($success) {
        $msg = "You have unfollowed @" . htmlspecialchars($followed_user['username']) . ".";
        if (is_ajax()) send_json(['success' => true, 'message' => $msg, 'action' => 'unfollow']);
        $_SESSION['success_message'] = $msg;
    } else {
        $msg = "Failed to unfollow user.";
        if (is_ajax()) send_json(['success' => false, 'message' => $msg]);
        $_SESSION['error_message'] = $msg;
    }
} else {
    $stmt = $conn->prepare("INSERT INTO follows (follower_id, followed_id, followed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $followed_id);
    $success = $stmt->execute();
    $stmt->close();
    if ($success) {
        $msg = "You are now following @" . htmlspecialchars($followed_user['username']) . ".";
        $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, content, reference_id, created_at) VALUES (?, 'follow', ?, ?, NOW())");
        $notification_content = "User @" . ($_SESSION['username'] ?? 'Someone') . " started following you.";
        $notification_stmt->bind_param("isi", $followed_id, $notification_content, $user_id);
        $notification_stmt->execute();
        $notification_stmt->close();
        if (is_ajax()) send_json(['success' => true, 'message' => $msg, 'action' => 'follow']);
        $_SESSION['success_message'] = $msg;
    } else {
        $msg = "Failed to follow user.";
        if (is_ajax()) send_json(['success' => false, 'message' => $msg]);
        $_SESSION['error_message'] = $msg;
    }
}
$conn->close();
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit(); 