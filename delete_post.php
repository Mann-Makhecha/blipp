<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $_SESSION['error_message'] = "You must be logged in to delete posts.";
    header("Location: index.php");
    exit();
}

// Check if post_id is provided
if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
    $_SESSION['error_message'] = "Invalid post ID.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

$post_id = (int)$_POST['post_id'];

// Verify that the user owns this post
$stmt = $mysqli->prepare("
    SELECT p.user_id, p.community_id, f.file_path 
    FROM posts p 
    LEFT JOIN files f ON p.post_id = f.post_id 
    WHERE p.post_id = ?
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Post not found.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

$post = $result->fetch_assoc();

// Check if user owns the post or is an admin
if ($post['user_id'] != $user_id && $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "You can only delete your own posts.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Delete associated files first
    if ($post['file_path'] && file_exists($post['file_path'])) {
        unlink($post['file_path']);
    }
    
    // Delete file records from database
    $file_stmt = $mysqli->prepare("DELETE FROM files WHERE post_id = ?");
    $file_stmt->bind_param("i", $post_id);
    $file_stmt->execute();
    $file_stmt->close();
    
    // Delete comments
    $comment_stmt = $mysqli->prepare("DELETE FROM comments WHERE post_id = ?");
    $comment_stmt->bind_param("i", $post_id);
    $comment_stmt->execute();
    $comment_stmt->close();
    
    // Delete post votes
    $vote_stmt = $mysqli->prepare("DELETE FROM post_votes WHERE post_id = ?");
    $vote_stmt->bind_param("i", $post_id);
    $vote_stmt->execute();
    $vote_stmt->close();
    
    // Delete post reports
    $report_stmt = $mysqli->prepare("DELETE FROM post_reports WHERE post_id = ?");
    $report_stmt->bind_param("i", $post_id);
    $report_stmt->execute();
    $report_stmt->close();
    
    // Delete post hashtags
    $hashtag_stmt = $mysqli->prepare("DELETE FROM posts_hashtags WHERE post_id = ?");
    $hashtag_stmt->bind_param("i", $post_id);
    $hashtag_stmt->execute();
    $hashtag_stmt->close();
    
    // Finally, delete the post
    $post_stmt = $mysqli->prepare("DELETE FROM posts WHERE post_id = ? AND user_id = ?");
    $post_stmt->bind_param("ii", $post_id, $user_id);
    
    if ($post_stmt->execute() && $post_stmt->affected_rows > 0) {
        $mysqli->commit();
        $_SESSION['success_message'] = "Post deleted successfully.";
    } else {
        throw new Exception("Failed to delete post.");
    }
    
    $post_stmt->close();
    
} catch (Exception $e) {
    $mysqli->rollback();
    $_SESSION['error_message'] = "Failed to delete post: " . $e->getMessage();
}

$mysqli->close();

// Redirect back to the previous page
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit();
?> 