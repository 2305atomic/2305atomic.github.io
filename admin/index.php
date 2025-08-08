<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Dashboard';

// Get dashboard statistics
try {
    $pdo = getDBConnection();
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
    $total_products = $stmt->fetch()['total'];
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $total_customers = $stmt->fetch()['total'];
    
    // Total revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'");
    $total_revenue = $stmt->fetch()['total'] ?: 0;
    
    // Recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll();
    
    // Low stock products
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE status = 'active' AND stock_quantity <= 5 
        ORDER BY stock_quantity ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $low_stock_products = $stmt->fetchAll();
    
    // Monthly sales data for chart
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_data = $stmt->fetchAll();
    
    // Order status distribution
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $order_status_data = $stmt->fetchAll();
    
} catch (Exception $e) {
    $total_products = $total_orders = $total_customers = $total_revenue = 0;
    $recent_orders = $low_stock_products = $monthly_data = $order_status_data = [];
}

include 'includes/header.php';
?>

<!-- Live Dashboard Stats -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-primary me-3">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h3 class="mb-0" id="total_products"><?php echo number_format($total_products); ?></h3>
                    <p class="text-muted mb-0">Total Products</p>
                    <small class="text-success">
                        <i class="fas fa-arrow-up"></i>
                        <span id="products_change">+0</span> this week
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-success me-3">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h3 class="mb-0" id="today_orders"><?php
                        try {
                            $stmt = getDBConnection()->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()");
                            echo number_format($stmt->fetch()['count']);
                        } catch (Exception $e) {
                            echo '0';
                        }
                    ?></h3>
                    <p class="text-muted mb-0">Today's Orders</p>
                    <small class="text-info">
                        <i class="fas fa-clock"></i>
                        Live updates
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-info me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 class="mb-0" id="total_customers"><?php echo number_format($total_customers); ?></h3>
                    <p class="text-muted mb-0">Total Customers</p>
                    <small class="text-primary">
                        <i class="fas fa-user-plus"></i>
                        <span id="customers_change">+0</span> this month
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="icon bg-warning me-3">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div>
                    <h3 class="mb-0" id="today_revenue"><?php
                        try {
                            $stmt = getDBConnection()->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
                            $today_revenue = $stmt->fetch()['total'] ?: 0;
                            echo formatPrice($today_revenue);
                        } catch (Exception $e) {
                            echo formatPrice(0);
                        }
                    ?></h3>
                    <p class="text-muted mb-0">Today's Revenue</p>
                    <small class="text-success">
                        <i class="fas fa-chart-line"></i>
                        Live tracking
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Alerts -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-tachometer-alt me-2"></i>Quick Actions
                </h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-warning" id="pending_orders"><?php
                        try {
                            $stmt = getDBConnection()->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
                            echo $stmt->fetch()['count'];
                        } catch (Exception $e) {
                            echo '0';
                        }
                    ?></span>
                    <small class="text-muted">Pending Orders</small>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="<?php echo ADMIN_URL; ?>/product-add.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo ADMIN_URL; ?>/orders.php?status=pending" class="btn btn-warning w-100">
                            <i class="fas fa-clock me-2"></i>Pending Orders
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo ADMIN_URL; ?>/users.php" class="btn btn-info w-100">
                            <i class="fas fa-users me-2"></i>View Customers
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo ADMIN_URL; ?>/settings.php" class="btn btn-secondary w-100">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>System Alerts
                </h5>
            </div>
            <div class="card-body">
                <div id="systemAlerts">
                    <?php if (count($low_stock_products) > 0): ?>
                        <div class="alert alert-warning alert-sm mb-2">
                            <i class="fas fa-box me-2"></i>
                            <strong><?php echo count($low_stock_products); ?></strong> products low in stock
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info alert-sm mb-2">
                        <i class="fas fa-chart-line me-2"></i>
                        Real-time monitoring active
                    </div>

                    <div class="alert alert-success alert-sm mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        System running smoothly
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Monthly Sales Overview</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Order Status Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders and Low Stock -->
<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="<?php echo ADMIN_URL; ?>/orders.php" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_orders)): ?>
                    <p class="text-muted text-center py-3">No orders found</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo ADMIN_URL; ?>/orders.php?view=<?php echo $order['id']; ?>" 
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($order['order_number']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($order['first_name']): ?>
                                                <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Guest</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'shipped' => 'primary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $color = $status_colors[$order['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($order['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Low Stock Alert</h5>
                <a href="<?php echo ADMIN_URL; ?>/products.php?filter=low_stock" class="btn btn-sm btn-outline-danger">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_products)): ?>
                    <p class="text-muted text-center py-3">All products are well stocked</p>
                <?php else: ?>
                    <?php foreach ($low_stock_products as $product): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                            </div>
                            <span class="badge bg-danger"><?php echo $product['stock_quantity']; ?> left</span>
                        </div>
                        <hr class="my-2">
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Orders',
            data: <?php echo json_encode(array_column($monthly_data, 'orders')); ?>,
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($order_status_data, 'status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($order_status_data, 'count')); ?>,
            backgroundColor: [
                '#ffc107',
                '#0dcaf0', 
                '#0d6efd',
                '#198754',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Real-time Dashboard Functions
function refreshOrders() {
    const button = event.target.closest('button');
    const icon = button.querySelector('i');

    // Show loading state
    icon.classList.add('fa-spin');

    fetch('<?php echo ADMIN_URL; ?>/api/get-recent-orders.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRecentOrdersTable(data.orders);
                document.getElementById('live_orders_count').textContent = data.orders.length;
            }
        })
        .catch(error => {
            console.error('Error refreshing orders:', error);
        })
        .finally(() => {
            icon.classList.remove('fa-spin');
        });
}

function updateRecentOrdersTable(orders) {
    const container = document.getElementById('recentOrdersContainer');

    if (orders.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4" id="noOrdersMessage">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h6>No orders found</h6>
                <p class="text-muted">Orders will appear here when customers make purchases</p>
            </div>
        `;
        return;
    }

    const tableHTML = `
        <div class="table-responsive">
            <table class="table table-hover" id="recentOrdersTable">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="recentOrdersBody">
                    ${orders.map(order => `
                        <tr data-order-id="${order.id}" class="table-row-new">
                            <td>
                                <a href="<?php echo ADMIN_URL; ?>/order-detail.php?id=${order.id}"
                                   class="text-decoration-none fw-bold">
                                    ${order.order_number}
                                </a>
                            </td>
                            <td>${order.customer_name || '<span class="text-muted">Guest Customer</span>'}</td>
                            <td><strong class="text-success">${order.formatted_amount}</strong></td>
                            <td>
                                <span class="badge bg-${getStatusColor(order.status)}">
                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-${getPaymentColor(order.payment_status)}">
                                    ${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}
                                </span>
                            </td>
                            <td><small class="text-muted">${order.formatted_date}</small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo ADMIN_URL; ?>/order-detail.php?id=${order.id}"
                                       class="btn btn-outline-primary btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    ${order.status === 'pending' ? `
                                        <button class="btn btn-outline-success btn-sm"
                                                onclick="quickUpdateStatus(${order.id}, 'processing')"
                                                title="Process Order">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = tableHTML;

    // Add animation to new rows
    setTimeout(() => {
        document.querySelectorAll('.table-row-new').forEach(row => {
            row.classList.remove('table-row-new');
        });
    }, 1000);
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'processing': 'info',
        'shipped': 'primary',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function getPaymentColor(status) {
    const colors = {
        'pending': 'warning',
        'paid': 'success',
        'failed': 'danger',
        'refunded': 'info'
    };
    return colors[status] || 'secondary';
}

function quickUpdateStatus(orderId, newStatus) {
    if (!confirm(`Are you sure you want to change this order status to ${newStatus}?`)) {
        return;
    }

    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;

    fetch('<?php echo ADMIN_URL; ?>/api/update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the orders table
            refreshOrders();

            // Show success message
            showToast('Success', `Order status updated to ${newStatus}`, 'success');
        } else {
            showToast('Error', data.message || 'Failed to update order status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating order status:', error);
        showToast('Error', 'Failed to update order status', 'error');
    })
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function showToast(title, message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    toastContainer.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', () => {
        toastContainer.removeChild(toast);
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Auto-refresh dashboard every 30 seconds
setInterval(() => {
    if (document.visibilityState === 'visible') {
        refreshOrders();
    }
}, 30000);

// Add CSS for new row animation
const style = document.createElement('style');
style.textContent = `
    .table-row-new {
        animation: highlightNew 2s ease-in-out;
    }

    @keyframes highlightNew {
        0% { background-color: #e3f2fd; }
        100% { background-color: transparent; }
    }

    .alert-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>
