<?php
/**
 * Main Index Page
 * File: index.php
 */

require_once 'config/config.php';
require_once 'includes/session.php';

// Check if user is already logged in
if (isAuthenticated()) {
    // Redirect to appropriate dashboard based on role
    $role = $_SESSION['role'];
    
    switch ($role) {
        case ROLE_ADMIN:
            header('Location: admin/');
            break;
        case ROLE_DIRECTOR:
            header('Location: dashboard/director-dashboard.php');
            break;
        case ROLE_ACCOUNTANT:
            header('Location: dashboard/accountant-dashboard.php');
            break;
        case ROLE_EMPLOYEE:
        default:
            header('Location: dashboard/employee-dashboard.php');
            break;
    }
    exit();
}

// Handle logout message
$message = '';
$messageType = '';

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $message = 'You have been logged out successfully.';
    $messageType = 'success';
} elseif (isset($_GET['error']) && $_GET['error'] == 'logout_failed') {
    $message = 'An error occurred during logout.';
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Streamline Your Financial Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 300;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
            border: none;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .btn-hero {
            background: white;
            color: #667eea;
            border: none;
            border-radius: 50px;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 10px 10px 0;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-hero:hover {
            background: #f8f9fa;
            color: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-hero-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-hero-outline:hover {
            background: white;
            color: #667eea;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 5rem 0;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 1.1rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .features-section {
            padding: 5rem 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 300;
            color: #333;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .footer {
            background: #333;
            color: white;
            padding: 2rem 0;
            text-align: center;
        }
        
        .demo-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 300px;
        }
    </style>
</head>
<body>
    <!-- Demo Alert -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show demo-alert" role="alert">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo e($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">
                    <i class="fas fa-shield-alt me-3"></i>
                    Financial Approval System
                </h1>
                <p class="hero-subtitle">
                    Streamline your business financial approvals with our comprehensive multi-entity management system. 
                    From request submission to director approval, manage it all in one secure platform.
                </p>
                <div class="mt-4">
                    <a href="auth/login.php" class="btn-hero">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </a>
                    <a href="#features" class="btn-hero btn-hero-outline">
                        <i class="fas fa-info-circle me-2"></i>Learn More
                    </a>
                </div>
                
                <?php if (DEBUG_MODE): ?>
                <div class="mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.2);">
                    <small style="opacity: 0.8;">
                        <i class="fas fa-code me-1"></i>
                        Demo Mode - Use credentials: admin/password123, director1/password123, employee1/password123
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-number">5</div>
                    <div class="stat-label">Director Approvers</div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-number">8</div>
                    <div class="stat-label">Accounting Teams</div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-number">∞</div>
                    <div class="stat-label">Employees Supported</div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">System Availability</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Powerful Features for Modern Businesses</h2>
                <p>Everything you need to manage financial approvals across multiple business entities efficiently and securely.</p>
            </div>
            
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-users feature-icon"></i>
                        <h4>Multi-Entity Support</h4>
                        <p>Manage approvals across multiple businesses with separate workflows and approval hierarchies for each entity.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-route feature-icon"></i>
                        <h4>Flexible Workflows</h4>
                        <p>Configure parallel or sequential approval processes with amount-based rules and automatic approvals for small requests.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-shield-check feature-icon"></i>
                        <h4>Role-Based Security</h4>
                        <p>Comprehensive user roles with granular permissions ensuring proper access control and data security.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-history feature-icon"></i>
                        <h4>Complete Audit Trail</h4>
                        <p>Track every action with detailed logs for compliance and accountability. Never lose track of who approved what.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-bell feature-icon"></i>
                        <h4>Smart Notifications</h4>
                        <p>Real-time email and system notifications keep everyone informed about pending approvals and status changes.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-chart-bar feature-icon"></i>
                        <h4>Detailed Reporting</h4>
                        <p>Generate comprehensive reports on approvals, spending patterns, and team performance with export capabilities.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-file-upload feature-icon"></i>
                        <h4>Document Management</h4>
                        <p>Secure file uploads for receipts, invoices, and supporting documents with automatic organization.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-mobile-alt feature-icon"></i>
                        <h4>Mobile Responsive</h4>
                        <p>Access the system from any device with a fully responsive design optimized for mobile and tablet use.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card text-center">
                        <i class="fas fa-cogs feature-icon"></i>
                        <h4>Easy Integration</h4>
                        <p>Built on standard PHP/MySQL stack for easy deployment and integration with existing business systems.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="hero-section" style="min-height: 50vh;">
        <div class="container">
            <div class="hero-content">
                <h2 style="font-size: 2.5rem;">Ready to Streamline Your Approvals?</h2>
                <p class="hero-subtitle">Join businesses already using our system to manage their financial approval workflows efficiently.</p>
                <div class="mt-4">
                    <a href="auth/login.php" class="btn-hero">
                        <i class="fas fa-rocket me-2"></i>Get Started Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-md-start text-center">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end text-center">
                    <p>Version <?php echo APP_VERSION; ?> | Built with PHP & MySQL</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>