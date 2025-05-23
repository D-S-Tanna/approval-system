<?php
/**
 * Forgot Password Page
 * File: auth/forgot-password.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Redirect if already logged in
if (isAuthenticated()) {
    header('Location: ../dashboard/');
    exit();
}

$message = '';
$messageType = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'danger';
    } elseif (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'danger';
    } elseif (!validateEmail($email)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $db = new Database();
        
        // Check if email exists
        $user = $db->select('users', 'email = :email AND is_active = 1', [':email' => $email]);
        
        if ($user && count($user) > 0) {
            $userData = $user[0];
            
            // Generate reset token
            $resetToken = generateRandomString(64);
            $resetExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Update user with reset token
            $db->update('users', [
                'password_reset_token' => $resetToken,
                'password_reset_expires' => $resetExpires,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', [':id' => $userData['id']]);
            
            // Send reset email
            $resetUrl = APP_URL . "/auth/reset-password.php?token=" . $resetToken;
            $subject = APP_NAME . " - Password Reset Request";
            
            $emailBody = "
            <html>
            <head>
                <style>
                    .container { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8f9fa; }
                    .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { padding: 20px; text-align: center; color: #666; font-size: 0.9em; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>" . APP_NAME . "</h2>
                        <p>Password Reset Request</p>
                    </div>
                    <div class='content'>
                        <h3>Hello " . e($userData['first_name']) . ",</h3>
                        <p>We received a request to reset your password for your " . APP_NAME . " account.</p>
                        <p>Click the button below to reset your password:</p>
                        <p><a href='{$resetUrl}' class='button'>Reset Password</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                        <p><strong>This link will expire in 1 hour.</strong></p>
                        <p>If you didn't request this password reset, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from " . APP_NAME . "</p>
                    </div>
                </div>
            </body>
            </html>";
            
            // Send email (in production, use proper email service)
            if (sendEmail($email, $subject, $emailBody)) {
                logActivity("Password reset requested for user: {$email}");
                $message = 'Password reset instructions have been sent to your email address.';
                $messageType = 'success';
                $email = ''; // Clear email field
            } else {
                $message = 'Failed to send reset email. Please try again later.';
                $messageType = 'danger';
                error_log("Failed to send password reset email to: {$email}");
            }
        } else {
            // Don't reveal if email exists or not for security
            $message = 'If that email address is in our system, you will receive password reset instructions.';
            $messageType = 'info';
            logActivity("Password reset attempted for non-existent email: {$email}", 'WARNING');
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
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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
        
        .forgot-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .forgot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .forgot-header h2 {
            margin: 0;
            font-weight: 300;
        }
        
        .forgot-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .forgot-body {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="forgot-container">
                    <div class="forgot-header">
                        <i class="fas fa-key fa-2x mb-2"></i>
                        <h2>Forgot Password</h2>
                        <p>Enter your email to reset your password</p>
                    </div>
                    
                    <div class="forgot-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                                <?php echo e($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($messageType !== 'success'): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="form-floating">
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Email address"
                                       value="<?php echo e($email); ?>"
                                       required
                                       autocomplete="email">
                                <label for="email">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-reset">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                            </button>
                        </form>
                        <?php endif; ?>
                        
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
</body>
</html>
