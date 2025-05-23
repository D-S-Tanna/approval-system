</div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">Are you sure you want to perform this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmButton">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    
    <style>
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .flash-messages {
            position: relative;
            z-index: 1000;
        }
        
        .sidebar-toggle {
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
                position: fixed;
                top: 85px;
                left: 10px;
                z-index: 1001;
                background: var(--primary-color);
                border: none;
                color: white;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
        }
    </style>

    <!-- Mobile Sidebar Toggle -->
    <button class="btn sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadSidebarNavigation();
            loadNotifications();
            initializeTooltips();
            
            // Auto-hide flash messages
            setTimeout(function() {
                const alerts = document.querySelectorAll('.flash-messages .alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Load role-specific sidebar navigation
        function loadSidebarNavigation() {
            const userRole = '<?php echo $currentUser['role']; ?>';
            const currentPage = '<?php echo $currentPage; ?>';
            const sidebarNav = document.getElementById('sidebar-nav');
            
            let navItems = [];
            
            // Common navigation items
            navItems.push({
                href: '../dashboard/',
                icon: 'fas fa-home',
                text: 'Dashboard',
                active: '<?php echo $currentDir; ?>' === 'dashboard' && currentPage === 'index'
            });

            // Role-specific navigation
            switch(userRole) {
                case '<?php echo ROLE_ADMIN; ?>':
                    navItems.push(
                        { href: '../admin/manage-users.php', icon: 'fas fa-users', text: 'Manage Users', active: currentPage === 'manage-users' },
                        { href: '../admin/manage-businesses.php', icon: 'fas fa-building', text: 'Businesses', active: currentPage === 'manage-businesses' },
                        { href: '../admin/system-settings.php', icon: 'fas fa-cogs', text: 'System Settings', active: currentPage === 'system-settings' },
                        { href: '../admin/audit-logs.php', icon: 'fas fa-history', text: 'Audit Logs', active: currentPage === 'audit-logs' }
                    );
                    break;
                    
                case '<?php echo ROLE_DIRECTOR; ?>':
                    navItems.push(
                        { href: '../approvals/pending-approvals.php', icon: 'fas fa-clock', text: 'Pending Approvals', active: currentPage === 'pending-approvals' },
                        { href: '../approvals/approval-history.php', icon: 'fas fa-history', text: 'Approval History', active: currentPage === 'approval-history' },
                        { href: '../requests/my-requests.php', icon: 'fas fa-file-alt', text: 'My Requests', active: currentPage === 'my-requests' },
                        { href: '../requests/create-request.php', icon: 'fas fa-plus', text: 'New Request', active: currentPage === 'create-request' },
                        { href: '../reports/', icon: 'fas fa-chart-line', text: 'Reports', active: '<?php echo $currentDir; ?>' === 'reports' }
                    );
                    break;
                    
                case '<?php echo ROLE_ACCOUNTANT; ?>':
                    navItems.push(
                        { href: '../requests/all-requests.php', icon: 'fas fa-list', text: 'All Requests', active: currentPage === 'all-requests' },
                        { href: '../requests/my-requests.php', icon: 'fas fa-file-alt', text: 'My Requests', active: currentPage === 'my-requests' },
                        { href: '../requests/create-request.php', icon: 'fas fa-plus', text: 'New Request', active: currentPage === 'create-request' },
                        { href: '../reports/business-report.php', icon: 'fas fa-chart-bar', text: 'Business Reports', active: currentPage === 'business-report' }
                    );
                    break;
                    
                case '<?php echo ROLE_EMPLOYEE; ?>':
                default:
                    navItems.push(
                        { href: '../requests/my-requests.php', icon: 'fas fa-file-alt', text: 'My Requests', active: currentPage === 'my-requests' },
                        { href: '../requests/create-request.php', icon: 'fas fa-plus', text: 'New Request', active: currentPage === 'create-request' }
                    );
                    break;
            }
            
            // Profile and settings (common to all)
            navItems.push(
                { href: '../profile/view-profile.php', icon: 'fas fa-user', text: 'My Profile', active: currentPage === 'view-profile' },
                { href: '../profile/notification-settings.php', icon: 'fas fa-bell', text: 'Notifications', active: currentPage === 'notification-settings' }
            );
            
            // Build navigation HTML
            let navHTML = '';
            navItems.forEach(item => {
                navHTML += `
                    <a class="nav-link ${item.active ? 'active' : ''}" href="${item.href}">
                        <i class="${item.icon}"></i>
                        ${item.text}
                    </a>
                `;
            });
            
            sidebarNav.innerHTML = navHTML;
        }

        // Load notifications
        function loadNotifications() {
            fetch('../api/notifications.php?action=recent')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayNotifications(data.notifications);
                    }
                })
                .catch(error => {
                    document.getElementById('notification-list').innerHTML = 
                        '<div class="text-center p-3 text-muted">Failed to load notifications</div>';
                });
        }

        // Display notifications in dropdown
        function displayNotifications(notifications) {
            const notificationList = document.getElementById('notification-list');
            
            if (notifications.length === 0) {
                notificationList.innerHTML = 
                    '<div class="text-center p-3 text-muted">No new notifications</div>';
                return;
            }
            
            let html = '';
            notifications.slice(0, 5).forEach(notification => {
                html += `
                    <li>
                        <a class="dropdown-item ${notification.is_read ? '' : 'fw-bold'}" href="#" 
                           onclick="markAsRead(${notification.id})">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-${getNotificationIcon(notification.type)} me-2 mt-1 text-primary"></i>
                                <div class="flex-grow-1">
                                    <div class="small">${notification.title}</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        ${timeAgo(notification.created_at)}
                                    </div>
                                </div>
                            </div>
                        </a>
                    </li>
                `;
            });
            
            notificationList.innerHTML = html;
        }

        // Get notification icon based on type
        function getNotificationIcon(type) {
            const icons = {
                'request_submitted': 'file-plus',
                'approval_needed': 'clock',
                'request_approved': 'check-circle',
                'request_rejected': 'times-circle',
                'system_alert': 'exclamation-triangle'
            };
            return icons[type] || 'bell';
        }

        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });
        }

        // Initialize tooltips
        function initializeTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Time ago function (simplified)
        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
            return Math.floor(diffInSeconds / 86400) + 'd ago';
        }

        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('d-none');
        }

        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('d-none');
        }

        // Show confirmation modal
        function showConfirmation(message, callback) {
            document.getElementById('confirmMessage').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            
            document.getElementById('confirmButton').onclick = function() {
                modal.hide();
                if (callback) callback();
            };
            
            modal.show();
        }
    </script>
</body>
</html>