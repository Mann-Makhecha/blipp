<?php
// Database configuration
$db_host = "127.0.0.1";
$db_user = "root";
$db_pass = "";
$db_name = "blipp";
$db_port = 3307;

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// Set charset to ensure proper encoding
$conn->set_charset("utf8mb4");

// Create alias for backward compatibility
$mysqli = $conn;
?>