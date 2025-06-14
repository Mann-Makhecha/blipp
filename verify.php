<?php
require_once 'includes/db.php';
require_once 'includes/settings.php';

$error = '';
$success = '';

// Check if token is provided
if (!isset($_GET['token'])) {
    $error = "Invalid verification link.";
} else {
    $token = $_GET['token'];
    
    // Check if token exists and is not expired
    $stmt = $mysqli->prepare("
        SELECT v.*, u.email 
        FROM email_verifications v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.token = ? AND v.expires_at > NOW() AND v.verified = 0
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = "Invalid or expired verification link.";
    } else {
        $verification = $result->fetch_assoc();
        
        // Mark verification as complete
        $stmt = $mysqli->prepare("
            UPDATE email_verifications 
            SET verified = 1, verified_at = NOW() 
            WHERE token = ?
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        
        // Update user's email_verified status
        $stmt = $mysqli->prepare("
            UPDATE users 
            SET email_verified = 1 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $verification['user_id']);
        $stmt->execute();
        
        $success = "Your email has been verified successfully. You can now log in.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?= htmlspecialchars(get_setting('site_name', 'Blipp')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
        }
        .verification-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .verification-icon.success {
            color: #28a745;
        }
        .verification-icon.error {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($error): ?>
            <i class="fas fa-times-circle verification-icon error"></i>
            <h1 class="h3 mb-3">Verification Failed</h1>
            <p class="text-danger"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <i class="fas fa-check-circle verification-icon success"></i>
            <h1 class="h3 mb-3">Verification Successful</h1>
            <p class="text-success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        </div>
    </div>
</body>
</html> 