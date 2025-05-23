<?php
/**
 * Login Page
 * File: auth/login.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: ../dashboard/');
    exit();
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (!checkLoginAttempts($username)) {
        $remainingTime = getRemainingLockoutTime($username);
        $minutes = ceil($remainingTime / 60);
        $error = "Too many failed attempts. Account locked for {$minutes} minutes.";
    } else {
        $user = new User();
        
        if ($user->authenticate($username, $password)) {
            // Clear failed login attempts
            clearLoginAttempts($username);
            
            // Set session fingerprint for security
            setSessionFingerprint();
            
            // Handle remember me
            if ($rememberMe) {
                $token = generateRandomString(32);
                // Store remember token in database (implement if needed)
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', SECURE_COOKIES, true);
            }
            
            // Log successful login
            logActivity("User {$username} logged in successfully");
            
            // Redirect to intended page or dashboard
            $redirectUrl = $_SESSION['intended_url'] ?? '../dashboard/';
            unset($_SESSION['intended_url']);
            
            header("Location: {$redirectUrl}");
            exit();
        } else {
            recordFailedLogin($username);
            $error = 'Invalid username or password.';
            logActivity("Failed login attempt for user {$username}", 'WARNING');
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 300;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
        }
        
        .form-floating input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-check {
            margin: 1rem 0;
        }
        
        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            color: #5a6fd8;
            text-decoration: underline;
        }
        
        .login-footer {
            background: #f8f9fa;
            padding: 1rem 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <i class="fas fa-shield-alt fa-2x mb-2"></i>
                        <h2>Welcome Back</h2>
                        <p>Sign in to your account</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo e($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Username"
                                       value="<?php echo e($username); ?>"
                                       required
                                       autocomplete="username">
                                <label for="username">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Password"
                                       required
                                       autocomplete="current-password">
                                <label for="password">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="remember_me" 
                                       name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Remember me
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="forgot-password.php" class="forgot-password">
                                    <i class="fas fa-key me-1"></i>Forgot your password?
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="login-footer">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Need help? Contact your system administrator
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Demo Credentials Modal -->
    <div class="modal fade" id="demoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Demo Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Available Test Accounts:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Admin:</strong><br>
                            Username: admin<br>
                            Password: password123
                        </div>
                        <div class="col-md-6">
                            <strong>Director:</strong><br>
                            Username: director1<br>
                            Password: password123
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Accountant:</strong><br>
                            Username: accountant1<br>
                            Password: password123
                        </div>
                        <div class="col-md-6">
                            <strong>Employee:</strong><br>
                            Username: employee1<br>
                            Password: password123
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Show demo modal on page load in development -->
    <?php if (DEBUG_MODE): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show demo credentials after 2 seconds
            setTimeout(function() {
                var modal = new bootstrap.Modal(document.getElementById('demoModal'));
                modal.show();
            }, 2000);
        });
    </script>
    <?php endif; ?>
</body>
</html>