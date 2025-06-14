<?php
/**
 * Get a site setting value
 * 
 * @param string $key The setting key
 * @param mixed $default Default value if setting is not found
 * @return mixed The setting value or default value
 */
function get_setting($key, $default = null) {
    global $mysqli;
    
    static $settings = null;
    
    // Load all settings if not already loaded
    if ($settings === null) {
        $settings = [];
        $result = $mysqli->query("SELECT setting_key, setting_value FROM admin_settings");
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Check if registration is allowed
 * 
 * @return bool True if registration is allowed
 */
function is_registration_allowed() {
    return (bool)get_setting('allow_registration', 1);
}

/**
 * Check if email verification is required
 * 
 * @return bool True if email verification is required
 */
function is_email_verification_required() {
    global $mysqli;
    $result = $mysqli->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'require_email_verification'");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'] === '1';
    }
    return false; // Default to false if setting doesn't exist
}

/**
 * Get maximum allowed file size in MB
 * 
 * @return int Maximum file size in MB
 */
function get_max_file_size() {
    return (int)get_setting('max_file_size', 5);
}

/**
 * Get allowed file types as array
 * 
 * @return array Array of allowed file extensions
 */
function get_allowed_file_types() {
    $types = get_setting('allowed_file_types', 'jpg,jpeg,png,gif');
    return array_map('trim', explode(',', $types));
}

/**
 * Check if a file type is allowed
 * 
 * @param string $extension File extension to check
 * @return bool True if file type is allowed
 */
function is_file_type_allowed($extension) {
    $allowed_types = get_allowed_file_types();
    return in_array(strtolower($extension), $allowed_types);
}

function get_community_creation_points() {
    global $mysqli;
    $result = $mysqli->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'community_creation_points'");
    if ($row = $result->fetch_assoc()) {
        return (int)$row['setting_value'];
    }
    return 1000; // Default value if setting not found
}

function get_user_points($user_id) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT points FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (int)$row['points'];
    }
    return 0; // Default to 0 if user not found
}

function can_create_community($user_id) {
    global $mysqli;
    
    // Get required points from settings
    $required_points = get_community_creation_points();
    
    // Get user's current points
    $stmt = $mysqli->prepare("SELECT points FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (int)$row['points'] >= $required_points;
    }
    
    return false;
} 