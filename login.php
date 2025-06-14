<?php
// Start the session
session_start();
require_once 'includes/db.php';
// include 'logout.php'; // Include logout functionality if needed
$error = '';
$success = '';

// Handle different actions
$action = $_GET['action'] ?? '';

// Logout functionality
if ($action === 'logout') {
    session_destroy();
    $success = "You have been successfully logged out.";
}

// Password reset request using security question
if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['reset_email']) && isset($_POST['security_answer'])) {
        $reset_email = trim($_POST['reset_email']);
        $security_answer = trim($_POST['security_answer']);
        
        if (!filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            // Check if email exists and get security question/answer
            $stmt = $mysqli->prepare("SELECT user_id, security_question, security_answer FROM users WHERE email = ?");
            $stmt->bind_param("s", $reset_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($security_answer, $user['security_answer'])) {
                    // Answer is correct, allow password reset
                    $_SESSION['reset_user_id'] = $user['user_id'];
                    header("Location: reset_password.php");
                    exit();
                } else {
                    $error = "Incorrect answer to the security question.";
                }
            } else {
                $error = "No account found with that email.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['reset_email'])) {
        // Show security question form
        $reset_email = trim($_POST['reset_email']);
        if (!filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            $stmt = $mysqli->prepare("SELECT user_id, security_question FROM users WHERE email = ?");
            $stmt->bind_param("s", $reset_email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $_SESSION['reset_email'] = $reset_email;
                $_SESSION['security_question'] = $user['security_question'];
                $success = "Please answer the security question to proceed.";
            } else {
                $error = "No account found with that email.";
            }
            $stmt->close();
        }
    }
}

// First, let's add the email_verified column if it doesn't exist
$mysqli->query("
    ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE
");

// Regular login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['reset_email'])) {
    // Rate limiting check (basic implementation)
    $ip = $_SERVER['REMOTE_ADDR'];
    $current_time = time();
    
    // Check for too many failed attempts (you might want to store this in database)
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Clean old attempts (older than 15 minutes)
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 900; // 15 minutes
    });
    
    if (count($_SESSION['login_attempts']) >= 5) {
        $error = "Too many failed login attempts. Please try again in 15 minutes.";
    } else {
        // Validate inputs
        if (!isset($_POST['email']) || !isset($_POST['password'])) {
            $error = "Email and password are required!";
        } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $remember_me = isset($_POST['remember_me']);

            // Use prepared statement to fetch user
            $stmt = $mysqli->prepare("SELECT user_id, username, password_hash, role, email_verified, is_active FROM users WHERE email = ?");
            if ($stmt === false) {
                $error = "Database error: Unable to prepare statement.";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($user = $result->fetch_assoc()) {
                    // Check if account is active
                    if (isset($user['is_active']) && !$user['is_active']) {
                        $error = "Your account is deactivated. Please contact support.";
                    } elseif (password_verify($password, $user['password_hash'])) {
                        // Clear failed attempts on successful login
                        $_SESSION['login_attempts'] = [];
                        
                        // Update the user's last active timestamp
                        $updateStmt = $mysqli->prepare("UPDATE users SET updated_at = current_timestamp() WHERE user_id = ?");
                        $updateStmt->bind_param("i", $user['user_id']);
                        $updateStmt->execute();
                        $updateStmt->close();

                        // Set session
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Redirect admin to admin panel
                        if ($user['role'] === 'admin') {
                            header("Location: admin/index.php");
                        } else {
                            header("Location: index.php");
                        }
                        exit();
                    } else {
                        $_SESSION['login_attempts'][] = $current_time;
                        $error = "Invalid email or password";
                    }
                } else {
                    $_SESSION['login_attempts'][] = $current_time;
                    $error = "Invalid email or password";
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Blipp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon (2).png" type="image/x-icon">

    <style>
        :root {
            --background-primary: #000;         /* Main background color */
            --background-secondary: #1a1a1a;   /* Hover states, form selectors */
            --background-sidebar: #212529;      /* Sidebar background */
            --text-primary: #fff;               /* Main text color */
            --text-secondary: #666;             /* Muted text, icons */
            --border-primary: #333;             /* Dividers, borders */
            --accent-primary: #1d9bf0;          /* Buttons, links, active states */
            --accent-primary-hover: #1a8cd8;    /* Hover state for primary accent */
            --error-primary: #ff3333;           /* Error messages, warnings */
            --error-primary-hover: #cc0000;     /* Hover state for error color */
            --success-primary: #28a745;         /* Success messages */
            --warning-primary: #ffc107;         /* Warning messages */
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

        /* Animated background */
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

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-card {
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

        .login-header {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-primary-hover));
            color: white;
            padding: 2rem;
            border-radius: 1.5rem 1.5rem 0 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
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

        .login-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .login-body {
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

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .form-check-input {
            margin-right: 0.5rem;
            accent-color: var(--accent-primary);
        }

        .form-check-label {
            color: var(--text-primary);
            font-size: 0.9rem;
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

        .btn-outline {
            background: transparent;
            border: 2px solid var(--accent-primary);
            color: var(--accent-primary);
            width: 100%;
        }

        .btn-outline:hover {
            background: var(--accent-primary);
            color: white;
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

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-primary);
            z-index: 1;
        }

        .divider span {
            background: var(--background-sidebar);
            padding: 0 1rem;
            position: relative;
            z-index: 2;
        }

        .forgot-password-form {
            display: none;
        }

        .forgot-password-form.show {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }

        .login-form.hide {
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .back-to-login {
            text-align: center;
            margin-top: 1rem;
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

        .footer-links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-primary);
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 1rem;
        }

        .footer-links a:hover {
            color: var(--accent-primary);
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 1rem 0.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
        }

        /* Custom scrollbar */
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
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-lock me-2"></i>Welcome Back</h1>
            </div>
            
            <div class="login-body">
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

                <!-- Main Login Form -->
                <form method="POST" action="" class="login-form" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter your email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required autocomplete="email">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div style="position: relative;">
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required autocomplete="current-password" id="password">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" name="remember_me" class="form-check-input" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">
                            Keep me signed in
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                        <div class="loading" id="loginLoading">
                            <div class="spinner"></div>
                        </div>
                    </button>

                    <div class="text-center">
                        <button type="button" class="btn-link" onclick="showForgotPassword()">
                            Forgot your password?
                        </button>
                    </div>
                </form>

                <!-- Forgot Password Form -->
                <form method="POST" action="?action=reset" class="forgot-password-form" id="forgotForm">
                    <?php if (!isset($_SESSION['reset_email'])): ?>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="reset_email" class="form-control" 
                                   placeholder="Enter your email to reset password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-question-circle me-2"></i>
                            Next
                        </button>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label">Security Question</label>
                            <p><?php echo htmlspecialchars($_SESSION['security_question']); ?></p>
                            <input type="text" name="security_answer" class="form-control" 
                                   placeholder="Enter your answer" required>
                            <input type="hidden" name="reset_email" value="<?php echo htmlspecialchars($_SESSION['reset_email']); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-2"></i>
                            Verify Answer
                        </button>
                    <?php endif; ?>
                    <div class="back-to-login">
                        <button type="button" class="btn-link" onclick="showLogin()">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Login
                        </button>
                    </div>
                </form>

                <div class="divider">
                    <span>New to our platform?</span>
                </div>

                <a href="register.php" class="btn btn-outline">
                    <i class="fas fa-user-plus me-2"></i>
                    Create Account
                </a>

                <div class="footer-links">
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="terms.php">Terms of Service</a>
                    <a href="support.php">Support</a>
                </div>
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

        // Show forgot password form
        function showForgotPassword() {
            document.getElementById('loginForm').classList.add('hide');
            document.getElementById('forgotForm').classList.add('show');
        }

        // Show login form
        function showLogin() {
            document.getElementById('forgotForm').classList.remove('show');
            document.getElementById('loginForm').classList.remove('hide');
            <?php unset($_SESSION['reset_email']); unset($_SESSION['security_question']); ?>
        }

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const loading = document.getElementById('loginLoading');
            
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
            // Alt + L to focus email field
            if (e.altKey && e.key === 'l') {
                e.preventDefault();
                document.querySelector('input[name="email"]').focus();
            }
            
            // Escape to show login form if forgot password is shown
            if (e.key === 'Escape') {
                showLogin();
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