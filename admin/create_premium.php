<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Premium user details
$username = 'premium';
$email = 'premium@blipp.com';
$password = generateToken(12); // Generate a random password
$role = 'admin';

// Check if user already exists
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
$check_stmt->bind_param("ss", $email, $username);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "Premium user already exists!";
} else {
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the premium user
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password_hash, role, email_verified, is_active) 
        VALUES (?, ?, ?, ?, TRUE, TRUE)
    ");
    $stmt->bind_param("ssss", $username, $email, $password_hash, $role);
    
    if ($stmt->execute()) {
        echo "Premium user created successfully!<br>";
        echo "Username: " . htmlspecialchars($username) . "<br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "Password: " . htmlspecialchars($password) . "<br>";
        echo "<strong>Please save this password securely and change it after first login!</strong><br>";
    } else {
        echo "Error creating premium user: " . $stmt->error;
    }
    $stmt->close();
}
$check_stmt->close();
?> 