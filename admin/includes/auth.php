<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_query = $mysqli->prepare("SELECT username, email FROM users WHERE user_id = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin = $admin_query->get_result()->fetch_assoc();
?> 