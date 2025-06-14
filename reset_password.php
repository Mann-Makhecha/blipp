<?php
session_start();
require_once 'includes/db.php';

$error = '';
$success = '';

if (!isset($_SESSION['reset_user_id'])) {
    $error = "Unauthorized access. Please start the password reset process again.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $user_id = $_SESSION['reset_user_id'];
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        if ($stmt->execute()) {
            $success = "Your password has been successfully reset. You can now log in.";
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['security_question']);
        } else {
            $error = "Failed to reset password. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Blipp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        :root {
            --background-primary: #000;
            --background-secondary: #1a1a1a;
            --background-sidebar: #212529;
            --text-primary: #fff;
            --text-secondary: #666;
            --border-primary: #333;
            --accent-primary: #1d9bf0;
            --accent-primary-hover: #1a8cd8;
            --error-primary: #ff3333;
            --error-primary-hover: #cc0000;
            --success-primary: #28a745;
            --warning-primary: #ffc107;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--background-primary) 0%, #111 100%);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(29, 155, 240, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(29, 155, 240, 0.05) 0%, transparent 50%);
            z-index: -1;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .reset-card {
            background: rgba(33, 37, 41, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-primary);
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-header {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-primary-hover));
            color: white;
            padding: 2rem;
            border-radius: 1.5rem 1.5rem 0 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .reset-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(45deg,
                    transparent,
                    transparent 10px,
                    rgba(255, 255, 255, 0.05) 10px,
                    rgba(255, 255, 255, 0.05) 20px);
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .reset-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .reset-body {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            background-color: var(--background-secondary);
            border: 1px solid var(--border-primary);
            color: var(--text-primary);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            background-color: var(--background-secondary);
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(29, 155, 240, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: var(--text-primary);
        }

        .btn {
            border-radius: 0.75rem;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-primary-hover));
            color: white;
            width: 100%;
            margin-bottom: 1rem;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-primary-hover), #1578b8);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(29, 155, 240, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-danger {
            background: rgba(255, 51, 51, 0.1);
            color: var(--error-primary);
            border-left: 4px solid var(--error-primary);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-primary);
            border-left: 4px solid var(--success-primary);
        }

        .btn-link {
            background: none;
            border: none;
            color: var(--accent-primary);
            text-decoration: none;
            padding: 0;
            font-size: 0.9rem;
        }

        .btn-link:hover {
            color: var(--accent-primary-hover);
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h1><i class="fas fa-key me-2"></i>Reset Password</h1>
            </div>

            <div class="reset-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <p><a href="login.php" class="btn-link">Return to Login</a></p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div style="position: relative;">
                                <input type="password" name="password" class="form-control"
                                    placeholder="Enter your new password" required id="password">
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <div style="position: relative;">
                                <input type="password" name="confirm_password" class="form-control"
                                    placeholder="Confirm your new password" required id="confirm-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm-password')">
                                    <i class="fas fa-eye" id="confirm-password-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-eye');

            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>

</html>