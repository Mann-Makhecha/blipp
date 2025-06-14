<?php
require_once __DIR__ . '/../includes/db.php';

// Check if communities table exists
$result = $mysqli->query("SHOW TABLES LIKE 'communities'");
if ($result->num_rows === 0) {
    die("Communities table does not exist!");
}

// Get table structure
$result = $mysqli->query("DESCRIBE communities");
echo "Communities table structure:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

// Get sample data
$result = $mysqli->query("SELECT * FROM communities LIMIT 1");
if ($row = $result->fetch_assoc()) {
    echo "\nSample data:\n";
    print_r($row);
} else {
    echo "\nNo communities found in the table.";
} 