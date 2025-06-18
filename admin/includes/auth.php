<?php
session_start();

// Include required files
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Initialize $admin as null by default
$admin = null;

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Only proceed with database operations if we have a valid connection
if (isset($conn) && !$conn->connect_error) {
    // Get admin info
    $admin_id = $_SESSION['user_id'];
    $admin_query = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    
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