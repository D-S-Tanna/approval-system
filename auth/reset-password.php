<?php
/**
 * Reset Password Page
 * File: auth/reset-password.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: ../dashboard/');
    exit();
}

$token = sanitize($_GET['token'] ?? '');
$error = '';
$success = '';
$validToken = false;
$userData = null;

// Validate token
if (!empty($token)) {
    $db = new Database();
    $user = $db->select('users', 
        'password_reset_token = :token AND password_reset_expires > NOW() AND is_active = 1', 
        [':token' => $token]
    );
    
    if ($user && count($user) > 0) {
        $validToken = true;
        $userData = $user[0];
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
    }
} else {
    $error = 'No reset token provided.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Validate password strength
        $passwordValidation = validatePasswordStrength($newPassword);
        
        if (!$passwordValidation['valid']) {
            $error = implode('<br>', $passwordValidation['errors']);
        } else {
            // Update password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $updateResult = $db->update('users', [
                'password_hash' => $passwordHash,
                'password_reset_token' => null,
                'password_reset_expires' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', [':id' => $userData['id']]);
            
            if ($updateResult) {
                $success = 'Password has been reset successfully. You can now log in with your new password.';
                $validToken = false; // Prevent further submissions
                
                logActivity("Password reset completed for user: {$userData['email']}");
                
                // Send confirmation email
                $subject = APP_NAME . " - Password Reset Confirmation";
                $emailBody = "
                <html>
                <head>
                    <style>
                        .container { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f8f9fa; }
                        .footer { padding: 20px; text-align: center; color: #666; font-size: 0.9em; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>" . APP_NAME . "</h2>
                            <p>Password Reset Confirmation</p>
                        </div>
                        <div class='content'>
                            <h3>Hello " . e($userData['first_name']) . ",</h3>
                            <p>Your password has been successfully reset for your " . APP_NAME . " account.</p>
                            <p>If you did not make this change, please contact your system administrator immediately.</p>
                            <p>For security reasons, we recommend:</p>
                            <ul>
                                <li>Use a strong, unique password</li>
                                <li>Enable two-factor authentication if available</li>
                                <li>Keep your login credentials confidential</li>
                            </ul>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from " . APP_NAME . "</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                sendEmail($userData['email'], $subject, $emailBody);
            } else {
                $error = 'Failed to reset password. Please try again.';
                error_log("Failed to reset password for user ID: " . $userData['id']);
            }
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
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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
        
        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .reset-header h2 {
            margin: 0;
            font-weight: 300;
        }
        
        .reset-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .reset-body {
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
        
        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .btn-back {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-back:hover {
            color: #5a6fd8;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            background: #e9ecef;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            width: 0%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="reset-container">
                    <div class="reset-header">
                        <i class="fas fa-lock fa-2x mb-2"></i>
                        <h2>Reset Password</h2>
                        <p>Create a new secure password</p>
                    </div>
                    
                    <div class="reset-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo e($success); ?>
                            </div>
                            
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary btn-reset">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            </div>
                        <?php elseif ($validToken): ?>
                        <form method="POST" action="" id="resetForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="New Password"
                                       required
                                       autocomplete="new-password">
                                <label for="password">
                                    <i class="fas fa-lock me-2"></i>New Password
                                </label>
                                <div class="password-strength">
                                    <div class="password-strength-bar" id="strengthBar"></div>
                                </div>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Confirm Password"
                                       required
                                       autocomplete="new-password">
                                <label for="confirm_password">
                                    <i class="fas fa-lock me-2"></i>Confirm Password
                                </label>
                            </div>
                            
                            <div class="password-requirements">
                                <strong>Password Requirements:</strong>
                                <ul>
                                    <li>At least <?php echo PASSWORD_MIN_LENGTH; ?> characters long</li>
                                    <li>At least one lowercase letter</li>
                                    <li>At least one uppercase letter</li>
                                    <li>At least one number</li>
                                    <li>At least one special character</li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-reset mt-3">
                                <i class="fas fa-save me-2"></i>Reset Password
                            </button>
                        </form>
                        <?endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn-back">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password strength checker
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            let color = '#dc3545';
            
            // Check length
            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) strength += 20;
            
            // Check lowercase
            if (/[a-z]/.test(password)) strength += 20;
            
            // Check uppercase
            if (/[A-Z]/.test(password)) strength += 20;
            
            // Check numbers
            if (/[0-9]/.test(password)) strength += 20;
            
            // Check special characters
            if (/[^a-zA-Z0-9]/.test(password)) strength += 20;
            
            // Set color based on strength
            if (strength >= 80) color = '#28a745';
            else if (strength >= 60) color = '#ffc107';
            else if (strength >= 40) color = '#fd7e14';
            
            strengthBar.style.width = strength + '%';
            strengthBar.style.backgroundColor = color;
        });
        
        // Confirm password validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>