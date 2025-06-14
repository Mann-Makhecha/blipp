<?php
function is_admin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not admin
if (!is_admin()) {
    header("Location: ../login.php");
    exit();
} 