<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is admin
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'user';

if (!$user_id || $user_role !== 'admin') {
    die("Access denied. Admin privileges required.");
}

// Target user email
$target_email = 'mann@mail.com';

// Start transaction
$mysqli->begin_transaction();

try {
    // First, check if the user exists
    $user_check_stmt = $mysqli->prepare("SELECT user_id, username, points FROM users WHERE email = ?");
    $user_check_stmt->bind_param("s", $target_email);
    $user_check_stmt->execute();
    $user_result = $user_check_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        throw new Exception("User with email '$target_email' not found.");
    }
    
    $user = $user_result->fetch_assoc();
    $user_check_stmt->close();
    
    echo "<h2>Verifying User: @{$user['username']}</h2>";
    echo "<p>Current Points: {$user['points']}</p>";
    
    // Add 10,000 points to the user
    $points_update_stmt = $mysqli->prepare("UPDATE users SET points = points + 10000 WHERE user_id = ?");
    $points_update_stmt->bind_param("i", $user['user_id']);
    
    if (!$points_update_stmt->execute()) {
        throw new Exception("Failed to update points: " . $points_update_stmt->error);
    }
    
    $points_update_stmt->close();
    echo "<p>✅ Added 10,000 points successfully!</p>";
    
    // Check if verification badge exists, if not create it
    $badge_check_stmt = $mysqli->prepare("SELECT badge_id FROM badges WHERE name = 'Verified'");
    $badge_check_stmt->execute();
    $badge_result = $badge_check_stmt->get_result();
    
    $verification_badge_id = null;
    if ($badge_result->num_rows === 0) {
        // Create verification badge
        $badge_insert_stmt = $mysqli->prepare("
            INSERT INTO badges (name, description, image_path) 
            VALUES ('Verified', 'Verified account with blue tick', '/assets/badges/verified.png')
        ");
        
        if (!$badge_insert_stmt->execute()) {
            throw new Exception("Failed to create verification badge: " . $badge_insert_stmt->error);
        }
        
        $verification_badge_id = $mysqli->insert_id;
        $badge_insert_stmt->close();
        echo "<p>✅ Created verification badge!</p>";
    } else {
        $verification_badge_id = $badge_result->fetch_assoc()['badge_id'];
        echo "<p>✅ Verification badge already exists!</p>";
    }
    $badge_check_stmt->close();
    
    // Check if user already has the verification badge
    $user_badge_check_stmt = $mysqli->prepare("
        SELECT user_badge_id FROM user_badges 
        WHERE user_id = ? AND badge_id = ?
    ");
    $user_badge_check_stmt->bind_param("ii", $user['user_id'], $verification_badge_id);
    $user_badge_check_stmt->execute();
    
    if ($user_badge_check_stmt->get_result()->num_rows === 0) {
        // Award verification badge to user
        $user_badge_insert_stmt = $mysqli->prepare("
            INSERT INTO user_badges (user_id, badge_id, awarded_at) 
            VALUES (?, ?, NOW())
        ");
        $user_badge_insert_stmt->bind_param("ii", $user['user_id'], $verification_badge_id);
        
        if (!$user_badge_insert_stmt->execute()) {
            throw new Exception("Failed to award verification badge: " . $user_badge_insert_stmt->error);
        }
        
        $user_badge_insert_stmt->close();
        echo "<p>✅ Awarded verification badge to user!</p>";
    } else {
        echo "<p>✅ User already has verification badge!</p>";
    }
    $user_badge_check_stmt->close();
    
    // Add point transaction record
    $transaction_stmt = $mysqli->prepare("
        INSERT INTO point_transactions (user_id, points, description, transaction_date) 
        VALUES (?, 10000, 'Verification bonus - Blue tick awarded', NOW())
    ");
    $transaction_stmt->bind_param("i", $user['user_id']);
    
    if (!$transaction_stmt->execute()) {
        throw new Exception("Failed to record point transaction: " . $transaction_stmt->error);
    }
    
    $transaction_stmt->close();
    echo "<p>✅ Recorded point transaction!</p>";
    
    // Commit transaction
    $mysqli->commit();
    
    // Get updated user info
    $updated_user_stmt = $mysqli->prepare("SELECT points FROM users WHERE user_id = ?");
    $updated_user_stmt->bind_param("i", $user['user_id']);
    $updated_user_stmt->execute();
    $updated_user = $updated_user_stmt->get_result()->fetch_assoc();
    $updated_user_stmt->close();
    
    echo "<h3>🎉 Verification Complete!</h3>";
    echo "<p><strong>User:</strong> @{$user['username']}</p>";
    echo "<p><strong>Email:</strong> {$target_email}</p>";
    echo "<p><strong>New Points Total:</strong> {$updated_user['points']}</p>";
    echo "<p><strong>Status:</strong> ✅ Verified with Blue Tick</p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Verification - Blipp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #000;
            color: #fff;
            padding: 2rem;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .info {
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-check-circle text-primary"></i> User Verification Tool</h1>
        <p class="text-muted">Admin tool to verify users and award blue tick badges</p>
        
        <div class="mt-4">
            <a href="admin/dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
            </a>
        </div>
    </div>
</body>
</html> 