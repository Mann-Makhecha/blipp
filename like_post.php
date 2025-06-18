<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to like posts']);
    exit;
}

// Check if post_id is provided
if (!isset($_POST['post_id'])) {
    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'];

// Check if user has already liked the post
$check_stmt = $mysqli->prepare("
    SELECT vote_type 
    FROM post_votes 
    WHERE post_id = ? AND user_id = ?
");
$check_stmt->bind_param("ii", $post_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$existing_vote = $result->fetch_assoc();
$check_stmt->close();

if ($existing_vote) {
    // If already liked, remove the like
    $delete_stmt = $mysqli->prepare("
        DELETE FROM post_votes 
        WHERE post_id = ? AND user_id = ?
    ");
    $delete_stmt->bind_param("ii", $post_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Update post upvotes count
    $update_stmt = $mysqli->prepare("
        UPDATE posts 
        SET upvotes = upvotes - 1 
        WHERE post_id = ?
    ");
    $update_stmt->bind_param("i", $post_id);
    $update_stmt->execute();
    $update_stmt->close();

    echo json_encode(['success' => true, 'action' => 'unliked']);
} else {
    // Add new like
    $insert_stmt = $mysqli->prepare("
        INSERT INTO post_votes (post_id, user_id, vote_type, voted_at)
        VALUES (?, ?, 1, NOW())
    ");
    $insert_stmt->bind_param("ii", $post_id, $user_id);
    $insert_stmt->execute();
    $insert_stmt->close();

    // Update post upvotes count
    $update_stmt = $mysqli->prepare("
        UPDATE posts 
        SET upvotes = upvotes + 1 
        WHERE post_id = ?
    ");
    $update_stmt->bind_param("i", $post_id);
    $update_stmt->execute();
    $update_stmt->close();

    echo json_encode(['success' => true, 'action' => 'liked']);
}
?> 