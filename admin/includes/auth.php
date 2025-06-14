<?php
session_start();

// Initialize $admin as null by default
$admin = null;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once __DIR__ . '/../../includes/db.php';

// Only proceed with database operations if we have a valid connection
if (isset($mysqli) && !$mysqli->connect_error) {
    // Get admin info
    $admin_id = $_SESSION['user_id'];
    $admin_query = $mysqli->prepare("SELECT username, email FROM users WHERE user_id = ?");
    
    if ($admin_query) {
        $admin_query->bind_param("i", $admin_id);
        if ($admin_query->execute()) {
            $result = $admin_query->get_result();
            $admin = $result->fetch_assoc();
        }
        $admin_query->close();
    }
}

// If we couldn't get admin info, set a default
if (!is_array($admin)) {
    $admin = [
        'username' => 'Admin',
        'email' => 'admin@blipp.com'
    ];
}

?> 