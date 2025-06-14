<?php
require_once '../includes/db.php';

// Default settings
$settings = [
    'maintenance_mode' => '0',
    'maintenance_message' => 'The site is currently under maintenance. Please check back later.',
    'require_email_verification' => '0',
    'community_creation_points' => '1000' // Points required to create a community
];

// Insert or update settings
foreach ($settings as $key => $value) {
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