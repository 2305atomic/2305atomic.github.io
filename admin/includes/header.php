<?php
$admin = getAdminUser();
if (!$admin) {
    redirect(ADMIN_URL . '/login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . ADMIN_TITLE : ADMIN_TITLE; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Admin CSS -->
    <style>
        :root {
            --admin-primary: #0d6efd;
            --admin-secondary: #6c757d;
            --admin-success: #198754;
            --admin-danger: #dc3545;
            --admin-warning: #ffc107;
            --admin-info: #0dcaf0;
            --admin-dark: #212529;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--admin-primary) 0%, #0b5ed7 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .logo {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }
        
        .content-wrapper {
            padding: 0 2rem 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--admin-primary);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--admin-dark);
        }
        
        .btn {
            border-radius: 6px;
            font-weight: 500;
        }
        
        .badge {
            font-weight: 500;
        }

        /* Real-time Notifications */
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .notification-bell:hover {
            background: rgba(0,0,0,0.1);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1050;
            display: none;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.new {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .live-stats {
            animation: fadeIn 0.5s ease-in;
        }

        .connection-status {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            z-index: 1060;
            transition: all 0.3s;
        }

        .connection-status.connected {
            background: #d4edda;
            color: #155724;
        }

        .connection-status.disconnected {
            background: #f8d7da;
            color: #721c24;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="logo">
            <h4 class="mb-0">
                <i class="fas fa-shield-alt me-2"></i>
                TeWuNeed
            </h4>
            <small class="opacity-75">Admin Panel</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </li>
            
            <?php if (hasPermission('manage_products')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/products.php">
                    <i class="fas fa-box"></i>Products
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/categories.php">
                    <i class="fas fa-tags"></i>Categories
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('manage_orders')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/orders.php">
                    <i class="fas fa-shopping-cart"></i>Orders
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('view_users')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/users.php">
                    <i class="fas fa-users"></i>Customers
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reviews') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/reviews.php">
                    <i class="fas fa-star"></i>Reviews
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'coupons') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/coupons.php">
                    <i class="fas fa-ticket-alt"></i>Coupons
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>" 
                   href="<?php echo ADMIN_URL; ?>/settings.php">
                    <i class="fas fa-cog"></i>Settings
                </a>
            </li>
            
            <li class="nav-item mt-3">
                <a class="nav-link" href="<?php echo SITE_URL; ?>" target="_blank">
                    <i class="fas fa-external-link-alt"></i>View Website
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?php echo ADMIN_URL; ?>/logout.php">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-primary d-md-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0 d-none d-md-inline">
                        <?php echo isset($page_title) ? $page_title : 'Dashboard'; ?>
                    </h5>
                </div>
                
                <div class="d-flex align-items-center">
                    <!-- Real-time Notifications -->
                    <div class="position-relative me-3">
                        <div class="notification-bell" id="notificationBell">
                            <i class="fas fa-bell fa-lg"></i>
                            <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
                        </div>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0">Notifications</h6>
                                <small class="text-muted">Real-time updates</small>
                            </div>
                            <div id="notificationList">
                                <div class="notification-item text-center text-muted">
                                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                    <p class="mb-0">No new notifications</p>
                                </div>
                            </div>
                            <div class="p-2 border-top text-center">
                                <button class="btn btn-sm btn-outline-primary" onclick="clearAllNotifications()">
                                    Clear All
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Connection Status -->
                    <div class="connection-status connected" id="connectionStatus">
                        <i class="fas fa-circle me-1"></i>Connected
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo htmlspecialchars($admin['first_name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text">
                                    <strong><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo ucfirst($admin['role']); ?></small>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo ADMIN_URL; ?>/profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo ADMIN_URL; ?>/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['admin_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['admin_success']; unset($_SESSION['admin_success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['admin_error']; unset($_SESSION['admin_error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

<!-- Real-time Notifications JavaScript -->
<script>
class AdminNotifications {
    constructor() {
        this.eventSource = null;
        this.notifications = [];
        this.maxNotifications = 50;
        this.notificationSound = null;
        this.init();
    }

    init() {
        this.setupEventSource();
        this.setupUI();
        this.createNotificationSound();
    }

    setupEventSource() {
        if (typeof(EventSource) !== "undefined") {
            this.eventSource = new EventSource('<?php echo ADMIN_URL; ?>/api/realtime-notifications.php');

            this.eventSource.addEventListener('new_order', (event) => {
                const data = JSON.parse(event.data);
                this.handleNewOrder(data);
            });

            this.eventSource.addEventListener('order_updated', (event) => {
                const data = JSON.parse(event.data);
                this.handleOrderUpdate(data);
            });

            this.eventSource.addEventListener('stats_update', (event) => {
                const data = JSON.parse(event.data);
                this.updateStats(data);
            });

            this.eventSource.addEventListener('heartbeat', (event) => {
                this.updateConnectionStatus(true);
            });

            this.eventSource.onerror = () => {
                this.updateConnectionStatus(false);
                // Reconnect after 5 seconds
                setTimeout(() => {
                    if (this.eventSource.readyState === EventSource.CLOSED) {
                        this.setupEventSource();
                    }
                }, 5000);
            };
        }
    }

    setupUI() {
        // Notification bell click handler
        document.getElementById('notificationBell').addEventListener('click', () => {
            this.toggleNotificationDropdown();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');

            if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    createNotificationSound() {
        // Create notification sound using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.notificationSound = audioContext;
        } catch (e) {
            console.log('Web Audio API not supported');
        }
    }

    playNotificationSound() {
        if (this.notificationSound) {
            try {
                const oscillator = this.notificationSound.createOscillator();
                const gainNode = this.notificationSound.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(this.notificationSound.destination);

                oscillator.frequency.setValueAtTime(800, this.notificationSound.currentTime);
                oscillator.frequency.setValueAtTime(600, this.notificationSound.currentTime + 0.1);

                gainNode.gain.setValueAtTime(0.3, this.notificationSound.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, this.notificationSound.currentTime + 0.3);

                oscillator.start(this.notificationSound.currentTime);
                oscillator.stop(this.notificationSound.currentTime + 0.3);
            } catch (e) {
                console.log('Could not play notification sound');
            }
        }
    }

    handleNewOrder(data) {
        this.playNotificationSound();

        const notification = {
            id: 'order_' + data.id,
            type: 'new_order',
            title: 'New Order Received!',
            message: `Order ${data.order_number} from ${data.customer_name}`,
            amount: data.formatted_amount,
            time: new Date().toLocaleTimeString(),
            data: data,
            isNew: true
        };

        this.addNotification(notification);
        this.showToast(notification);

        // Update page if on orders page
        if (window.location.pathname.includes('orders.php')) {
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    }

    handleOrderUpdate(data) {
        const notification = {
            id: 'update_' + data.id,
            type: 'order_update',
            title: 'Order Updated',
            message: `Order ${data.order_number} status changed`,
            time: new Date().toLocaleTimeString(),
            data: data,
            isNew: true
        };

        this.addNotification(notification);
    }

    updateStats(data) {
        // Update live statistics on dashboard
        const elements = {
            'today_orders': data.today_orders,
            'today_revenue': data.formatted_revenue,
            'pending_orders': data.pending_orders
        };

        Object.keys(elements).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = elements[id];
                element.classList.add('live-stats');
                setTimeout(() => {
                    element.classList.remove('live-stats');
                }, 500);
            }
        });
    }

    addNotification(notification) {
        this.notifications.unshift(notification);

        // Keep only max notifications
        if (this.notifications.length > this.maxNotifications) {
            this.notifications = this.notifications.slice(0, this.maxNotifications);
        }

        this.updateNotificationUI();
    }

    updateNotificationUI() {
        const count = this.notifications.filter(n => n.isNew).length;
        const badge = document.getElementById('notificationCount');
        const list = document.getElementById('notificationList');

        // Update badge
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }

        // Update notification list
        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-item text-center text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p class="mb-0">No new notifications</p>
                </div>
            `;
        } else {
            list.innerHTML = this.notifications.map(notification => `
                <div class="notification-item ${notification.isNew ? 'new' : ''}"
                     onclick="adminNotifications.handleNotificationClick('${notification.id}')">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${notification.title}</h6>
                            <p class="mb-1 text-muted small">${notification.message}</p>
                            ${notification.amount ? `<span class="badge bg-success">${notification.amount}</span>` : ''}
                        </div>
                        <small class="text-muted">${notification.time}</small>
                    </div>
                </div>
            `).join('');
        }
    }

    handleNotificationClick(notificationId) {
        const notification = this.notifications.find(n => n.id === notificationId);
        if (notification) {
            notification.isNew = false;

            if (notification.type === 'new_order' && notification.data) {
                window.open(`<?php echo ADMIN_URL; ?>/order-detail.php?id=${notification.data.id}`, '_blank');
            }

            this.updateNotificationUI();
        }
    }

    toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';

        // Mark all as read when opened
        this.notifications.forEach(n => n.isNew = false);
        this.updateNotificationUI();
    }

    updateConnectionStatus(connected) {
        const status = document.getElementById('connectionStatus');
        if (connected) {
            status.className = 'connection-status connected';
            status.innerHTML = '<i class="fas fa-circle me-1"></i>Connected';
        } else {
            status.className = 'connection-status disconnected';
            status.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Disconnected';
        }
    }

    showToast(notification) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'toast position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '1060';
        toast.innerHTML = `
            <div class="toast-header">
                <i class="fas fa-shopping-cart text-primary me-2"></i>
                <strong class="me-auto">${notification.title}</strong>
                <small class="text-muted">${notification.time}</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${notification.message}
                ${notification.amount ? `<br><strong>Amount: ${notification.amount}</strong>` : ''}
            </div>
        `;

        document.body.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Remove toast element after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            document.body.removeChild(toast);
        });
    }
}

// Global functions
function clearAllNotifications() {
    adminNotifications.notifications = [];
    adminNotifications.updateNotificationUI();
}

// Initialize notifications when page loads
let adminNotifications;
document.addEventListener('DOMContentLoaded', function() {
    adminNotifications = new AdminNotifications();
});
</script>
