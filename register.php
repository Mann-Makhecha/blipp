<?php
require_once 'includes/db.php';
require_once 'includes/settings.php';

$error = '';
$success = '';

// Check if registration is allowed
if (!is_registration_allowed()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $cnfpassword = $_POST['cnfpassword'] ?? '';
    $security_question = trim($_POST['security_question'] ?? '');
    $security_answer = trim($_POST['security_answer'] ?? '');

    // Validate inputs
    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($cnfpassword) || empty($security_question) || empty($security_answer)) {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif ($password !== $cnfpassword) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    } else {
        // Check for existing username or email
        $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $check_email = $mysqli->query("SELECT user_id FROM users WHERE email = '$email'")->num_rows;
            $check_username = $mysqli->query("SELECT user_id FROM users WHERE username = '$username'")->num_rows;
            if ($check_username > 0) {
                $error = "Username already exists!";
            } elseif ($check_email > 0) {
                $error = "Email already exists!";
            }
        } else {
            // Hash password and security answer
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $security_answer_hash = password_hash(strtolower($security_answer), PASSWORD_DEFAULT); // Case-insensitive

            // Insert user using prepared statement
            $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password_hash, security_question, security_answer, points, is_premium) VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
            if ($stmt === false) {
                $error = "Failed to prepare statement: " . $mysqli->error;
            } else {
                $stmt->bind_param("ssssss", $name, $username, $email, $password_hash, $security_question, $security_answer_hash);

                if ($stmt->execute()) {
                    $success = "Registration successful! Please login.";
                    header("Location: login.php");
                    exit();
                } else {
                    $error = "Registration failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// After successful registration, check if email verification is required
if (is_email_verification_required()) {
    // Generate verification token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $mysqli->prepare("
        INSERT INTO email_verifications (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $user_id, $token, $expires);
    $stmt->execute();
    
    // Send verification email
    $verification_link = "https://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $token;
    $to = $email;
    $subject = "Verify your email address";
    $message = "Please click the following link to verify your email address:\n\n" . $verification_link;
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
    
    mail($to, $subject, $message, $headers);
    
    $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account.";
} else {
    $_SESSION['success_message'] = "Registration successful! You can now log in.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Blipp</title>
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
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .register-card {
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
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .register-header {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-primary-hover));
            color: white;
            padding: 2rem;
            border-radius: 1.5rem 1.5rem 0 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.05) 10px,
                rgba(255, 255, 255, 0.05) 20px
            );
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .register-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .register-body {
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
            position: relative;
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

        .back-to-login {
            text-align: center;
            margin-top: 1rem;
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

        .loading {
            display: none;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-primary);
            border-top: 2px solid var(--accent-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 1rem 0.5rem;
            }
            
            .register-body {
                padding: 2rem 1.5rem;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-primary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1><i class="fas fa-user-plus me-2"></i>Create Account</h1>
            </div>
            
            <div class="register-body">
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
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Enter your username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" 
                               placeholder="Enter your name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter your email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div style="position: relative;">
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required id="password">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div style="position: relative;">
                            <input type="password" name="cnfpassword" class="form-control" 
                                   placeholder="Confirm your password" required id="confirm-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm-password')">
                                <i class="fas fa-eye" id="confirm-password-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Security Question</label>
                        <select name="security_question" class="form-control" required>
                            <option value="">Select a question</option>
                            <option value="What is your favorite book?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === "What is your favorite book?") ? 'selected' : ''; ?>>What is your favorite book?</option>
                            <option value="What was your first pet's name?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === "What was your first pet's name?") ? 'selected' : ''; ?>>What was your first pet's name?</option>
                            <option value="What is your mother's maiden name?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === "What is your mother's maiden name?") ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                            <option value="What was the name of your first school?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === "What was the name of your first school?") ? 'selected' : ''; ?>>What was the name of your first school?</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Security Answer</label>
                        <input type="text" name="security_answer" class="form-control" 
                               placeholder="Enter your answer" 
                               value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>" 
                               required>
                    </div>

                    <button type="submit" class="btn btn-primary" id="registerBtn">
                        <i class="fas fa-user-plus me-2"></i>
                        Register
                        <div class="loading" id="registerLoading">
                            <div class="spinner"></div>
                        </div>
                    </button>

                    <div class="back-to-login">
                        <a href="login.php" class="btn-link">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>
    <script>
        // Toggle password visibility
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

        // Form submission with loading state
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            const loading = document.getElementById('registerLoading');
            
            btn.disabled = true;
            loading.style.display = 'block';
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                btn.disabled = false;
                loading.style.display = 'none';
            }, 5000);
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + R to focus username field
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                document.querySelector('input[name="username"]').focus();
            }
        });

        // Form validation feedback
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.required && !this.value.trim()) {
                    this.style.borderColor = 'var(--error-primary)';
                } else {
                    this.style.borderColor = 'var(--border-primary)';
                }
            });

            input.addEventListener('input', function() {
                this.style.borderColor = 'var(--border-primary)';
            });
        });
    </script>
</body>
</html>