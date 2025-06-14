<?php
require_once '../includes/db.php';

// Premium user details
$username = 'premium';
$email = 'premium@blipp.com';
$password = 'premium123'; // This will be hashed
$role = 'admin';

// Check if user already exists
$check_stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
$check_stmt->bind_param("ss", $email, $username);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "Premium user already exists!";
} else {
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the premium user
    $stmt = $mysqli->prepare("
        INSERT INTO users (username, email, password_hash, role, email_verified, is_active) 
        VALUES (?, ?, ?, ?, TRUE, TRUE)
    ");
    $stmt->bind_param("ssss", $username, $email, $password_hash, $role);
    
    if ($stmt->execute()) {
        echo "Premium user created successfully!<br>";
        echo "Username: " . $username . "<br>";
        echo "Email: " . $email . "<br>";
        echo "Password: " . $password . "<br>";
    } else {
        echo "Error creating premium user: " . $stmt->error;
    }
    $stmt->close();
}
$check_stmt->close();
?> 