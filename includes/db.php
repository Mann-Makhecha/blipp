<?php

$mysqli = mysqli_connect("127.0.0.1", "root", "", "blipp", 3307);

// Check connection
if ($mysqli === false) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    // Do not proceed with any database operations if connection failed
} else {
    // Create post_reports table if it doesn't exist
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS post_reports (
            report_id INT PRIMARY KEY AUTO_INCREMENT,
            post_id INT NOT NULL,
            reporter_id INT NOT NULL,
            reason VARCHAR(50) NOT NULL,
            details TEXT,
            created_at DATETIME NOT NULL,
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE
        )
    ");

    // Create users table if it doesn't exist
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            pass VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create admin_settings table if it doesn't exist
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS admin_settings (
            setting_id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Get the column name for password field
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE '%pass%'");
    $password_column = $result->fetch_assoc();
    $password_field = $password_column ? $password_column['Field'] : 'password';

    // Insert default admin user if not exists
    $admin_email = 'admin@blipp.com';
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_username = 'admin';

    $stmt = $mysqli->prepare("
        INSERT IGNORE INTO users (username, email, password_hash, role, created_at)
        VALUES (?, ?, ?, 'admin', NOW())
    ");
    $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);
    $stmt->execute();
    $stmt->close();
}
?>