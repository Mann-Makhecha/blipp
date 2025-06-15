<?php
require_once '../includes/db.php';

// Default settings
$default_settings = [
    'site_name' => 'Blipp',
    'site_description' => 'A social platform for sharing and connecting',
    'require_email_verification' => '0',
    'community_creation_points' => '1000' // Points required to create a community
];

// Insert or update settings
foreach ($default_settings as $key => $value) {
    $stmt = $mysqli->prepare("
        INSERT INTO admin_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
}

echo "Settings have been set up successfully!";
?> 