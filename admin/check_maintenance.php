<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';

// Check if maintenance settings exist
$result = $mysqli->query("SELECT * FROM admin_settings WHERE setting_key IN ('maintenance_mode', 'maintenance_message')");
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

echo "Current Maintenance Settings:\n";
echo "Maintenance Mode: " . (isset($settings['maintenance_mode']) ? $settings['maintenance_mode'] : 'Not Set') . "\n";
echo "Maintenance Message: " . (isset($settings['maintenance_message']) ? $settings['maintenance_message'] : 'Not Set') . "\n";

// If settings don't exist, create them
if (!isset($settings['maintenance_mode'])) {
    $stmt = $mysqli->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
    $stmt->execute();
    echo "\nCreated maintenance_mode setting\n";
}

if (!isset($settings['maintenance_message'])) {
    $stmt = $mysqli->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('maintenance_message', 'We are currently performing scheduled maintenance. We will be back shortly!')");
    $stmt->execute();
    echo "Created maintenance_message setting\n";
}

// Test maintenance mode function
echo "\nTesting is_maintenance_mode() function:\n";
echo "Result: " . (is_maintenance_mode() ? 'Enabled' : 'Disabled') . "\n"; 