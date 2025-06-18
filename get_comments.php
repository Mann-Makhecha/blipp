<?php
// Prevent any output before headers
ob_start();

session_start();
require_once 'includes/db.php';

// Ensure we're sending JSON response
header('Content-Type: application/json');

// Disable error display (we'll handle errors ourselves)
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendJsonResponse($success, $data = null, $message = '') {
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check session
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, null, 'Not logged in');
}

// Check post_id
if (!isset($_GET['post_id'])) {
    sendJsonResponse(false, null, 'Post ID not provided');
}

$post_id = (int)$_GET['post_id'];

// Check database connection
if (!isset($conn)) {
    sendJsonResponse(false, null, 'Database connection not initialized');
}

if ($conn->connect_error) {
    error_log("Database connection error: " . $conn->connect_error);
    sendJsonResponse(false, null, 'Unable to connect to the database. Please try again later.');
}

try {
    // Get comments for the post
    $query = "SELECT c.*, u.username 
              FROM comments c 
              JOIN users u ON c.user_id = u.user_id 
              WHERE c.post_id = ? 
              ORDER BY c.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!$stmt->bind_param('i', $post_id)) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }

    $comments = [];
    while ($comment = $result->fetch_assoc()) {
        $comments[] = [
            'comment_id' => $comment['comment_id'],
            'content' => htmlspecialchars($comment['content']),
            'username' => htmlspecialchars($comment['username']),
            'created_at' => $comment['created_at']
        ];
    }

    sendJsonResponse(true, $comments);

} catch (Exception $e) {
    error_log("Error in get_comments.php: " . $e->getMessage());
    sendJsonResponse(false, null, 'Error loading comments: ' . $e->getMessage());
} 