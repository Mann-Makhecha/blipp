<?php
/**
 * Common utility functions for Blipp
 */

/**
 * Format time ago (e.g., "5m ago")
 * 
 * @param string $datetime The datetime string
 * @return string Formatted time ago string
 */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $post_time = new DateTime($datetime, new DateTimeZone('Asia/Kolkata'));
        $interval = $now->diff($post_time);

        if ($interval->y > 0) return $interval->y . 'y ago';
        if ($interval->m > 0) return $interval->m . 'mo ago';
        if ($interval->d > 0) return $interval->d . 'd ago';
        if ($interval->h > 0) return $interval->h . 'h ago';
        if ($interval->i > 0) return $interval->i . 'm ago';
        return $interval->s . 's ago';
    }
}

/**
 * Format timestamp for display
 * 
 * @param string $timestamp The timestamp string
 * @return string Formatted timestamp
 */
if (!function_exists('formatTimeAgo')) {
    function formatTimeAgo($timestamp) {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return $diff . 's';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . 'm';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . 'h';
        } else {
            return floor($diff / 86400) . 'd';
        }
    }
}

/**
 * Sanitize user input
 * 
 * @param string $input The input to sanitize
 * @return string Sanitized input
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate email format
 * 
 * @param string $email The email to validate
 * @return bool True if valid email
 */
if (!function_exists('isValidEmail')) {
    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Generate random token
 * 
 * @param int $length The length of the token
 * @return string Random token
 */
if (!function_exists('generateToken')) {
    function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

/**
 * Redirect to a URL
 * 
 * @param string $url The URL to redirect to
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

/**
 * Display success message
 * 
 * @param string $message The message to display
 */
if (!function_exists('showSuccess')) {
    function showSuccess($message) {
        $_SESSION['success_message'] = $message;
    }
}

/**
 * Display error message
 * 
 * @param string $message The message to display
 */
if (!function_exists('showError')) {
    function showError($message) {
        $_SESSION['error_message'] = $message;
    }
}
?> 