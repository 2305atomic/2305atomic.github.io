<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Orders Management';

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'];
        $payment_status = $_POST['payment_status'] ?? null;
        
        try {
            $pdo = getDBConnection();
            
            // Update order status
            $sql = "UPDATE orders SET status = ?";
            $params = [$new_status];
            
            if ($payment_status) {
                $sql .= ", payment_status = ?";
                $params[] = $payment_status;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $order_id;
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['admin_success'] = 'Order status updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update order status.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating order: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/orders.php');
    }
    
    if (isset($_POST['delete_order'])) {
        $order_id = (int)$_POST['order_id'];
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Delete order items first
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            // Delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
            $_SESSION['admin_success'] = 'Order deleted successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_error'] = 'Error deleting order: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/orders.php');
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($payment_filter) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_filter;
}

if ($search) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get orders
    $sql = "
        SELECT o.*, u.first_name, u.last_name, u.email,
               COUNT(oi.id) as item_count
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        {$where_clause}
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $orders = [];
    $_SESSION['admin_error'] = 'Error loading orders: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Orders Management</h2>
        <p class="text-muted mb-0">
            <span id="total_orders_count"><?php echo count($orders); ?></span> orders found
            <span class="ms-3">
                <i class="fas fa-circle text-warning me-1"></i>
                <span id="pending_count"><?php
                    echo count(array_filter($orders, function($o) { return $o['status'] === 'pending'; }));
                ?></span> pending
            </span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-2"></i>Bulk Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="bulkUpdateStatus('processing')">
                    <i class="fas fa-play me-2"></i>Mark as Processing
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="bulkUpdateStatus('shipped')">
                    <i class="fas fa-shipping-fast me-2"></i>Mark as Shipped
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="bulkUpdateStatus('delivered')">
                    <i class="fas fa-check me-2"></i>Mark as Delivered
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="bulkExport()">
                    <i class="fas fa-download me-2"></i>Export Selected
                </a></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="bulkDelete()">
                    <i class="fas fa-trash me-2"></i>Delete Selected
                </a></li>
            </ul>
        </div>
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Report
        </button>
        <a href="<?php echo ADMIN_URL; ?>/export.php?type=orders&format=csv" class="btn btn-outline-success">
            <i class="fas fa-download me-2"></i>Export CSV
        </a>
        <button class="btn btn-primary" onclick="refreshOrders()">
            <i class="fas fa-sync-alt me-2"></i>Refresh
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Order number, customer name or email">
            </div>
            <div class="col-md-3">
                <label class="form-label">Order Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Payment Status</label>
                <select class="form-select" name="payment">
                    <option value="">All Payments</option>
                    <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="<?php echo ADMIN_URL; ?>/orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Orders (<?php echo count($orders); ?> items)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="text-center py-4">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h5>No orders found</h5>
                <p class="text-muted">Orders will appear here when customers make purchases.</p>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    <label class="form-check-label" for="selectAll">
                        Select All (<span id="selected_count">0</span> selected)
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshOrdersTable()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sort me-1"></i>Sort
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?sort=newest">Newest First</a></li>
                            <li><a class="dropdown-item" href="?sort=oldest">Oldest First</a></li>
                            <li><a class="dropdown-item" href="?sort=amount_high">Highest Amount</a></li>
                            <li><a class="dropdown-item" href="?sort=amount_low">Lowest Amount</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="ordersTable">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" class="form-check-input" id="selectAllTable" onchange="toggleSelectAll()">
                            </th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Order Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr data-order-id="<?php echo $order['id']; ?>" class="order-row">
                                <td>
                                    <input type="checkbox" class="form-check-input order-checkbox"
                                           value="<?php echo $order['id']; ?>" onchange="updateSelectedCount()">
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo timeAgo($order['created_at']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($order['first_name']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Guest Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $order['item_count']; ?> items</span>
                                </td>
                                <td>
                                    <strong><?php echo formatPrice($order['total_amount']); ?></strong>
                                </td>
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
                                <td>
                                    <?php
                                    $payment_colors = [
                                        'pending' => 'warning',
                                        'paid' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'secondary'
                                    ];
                                    $payment_color = $payment_colors[$order['payment_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $payment_color; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($order['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_URL; ?>/order-detail.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-success update-status-btn" 
                                                data-id="<?php echo $order['id']; ?>"
                                                data-status="<?php echo $order['status']; ?>"
                                                data-payment="<?php echo $order['payment_status']; ?>"
                                                data-order="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this order?')">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" name="delete_order" class="btn btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="update_order_id" name="order_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Number</label>
                        <input type="text" class="form-control" id="update_order_number" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Order Status</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="update_payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="update_payment_status" name="payment_status">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle update status modal
document.addEventListener('DOMContentLoaded', function() {
    const updateButtons = document.querySelectorAll('.update-status-btn');
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('update_order_id').value = this.dataset.id;
            document.getElementById('update_order_number').value = this.dataset.order;
            document.getElementById('update_status').value = this.dataset.status;
            document.getElementById('update_payment_status').value = this.dataset.payment;
        });
    });
});

// Bulk Actions Functions
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll') || document.getElementById('selectAllTable');
    const checkboxes = document.querySelectorAll('.order-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });

    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const count = checkboxes.length;

    const countElement = document.getElementById('selected_count');
    if (countElement) {
        countElement.textContent = count;
    }

    // Update select all checkbox state
    const selectAll = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    const totalCheckboxes = document.querySelectorAll('.order-checkbox').length;

    if (selectAll) {
        selectAll.checked = count === totalCheckboxes;
        selectAll.indeterminate = count > 0 && count < totalCheckboxes;
    }
    if (selectAllTable) {
        selectAllTable.checked = count === totalCheckboxes;
        selectAllTable.indeterminate = count > 0 && count < totalCheckboxes;
    }
}

function getSelectedOrderIds() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function bulkUpdateStatus(newStatus) {
    const selectedIds = getSelectedOrderIds();

    if (selectedIds.length === 0) {
        alert('Please select at least one order.');
        return;
    }

    if (!confirm(`Are you sure you want to update ${selectedIds.length} orders to "${newStatus}" status?`)) {
        return;
    }

    // Show loading and update orders
    showToast('Processing', `Updating ${selectedIds.length} orders...`, 'info');

    fetch('<?php echo ADMIN_URL; ?>/api/bulk-update-orders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_ids: selectedIds,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Success', `${data.updated_count} orders updated successfully`, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message || 'Failed to update orders', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Failed to update orders', 'error');
    });
}

function bulkDelete() {
    const selectedIds = getSelectedOrderIds();

    if (selectedIds.length === 0) {
        alert('Please select at least one order.');
        return;
    }

    if (!confirm(`Are you sure you want to delete ${selectedIds.length} orders? This action cannot be undone.`)) {
        return;
    }

    showToast('Processing', `Deleting ${selectedIds.length} orders...`, 'warning');

    // For now, use form submission since we don't have the API endpoint yet
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo ADMIN_URL; ?>/orders.php';

    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_delete[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

function bulkExport() {
    const selectedIds = getSelectedOrderIds();

    if (selectedIds.length === 0) {
        alert('Please select at least one order.');
        return;
    }

    // Create form and submit for download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo ADMIN_URL; ?>/export.php';

    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'type';
    typeInput.value = 'orders';

    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = 'csv';

    const idsInput = document.createElement('input');
    idsInput.type = 'hidden';
    idsInput.name = 'order_ids';
    idsInput.value = JSON.stringify(selectedIds);

    form.appendChild(typeInput);
    form.appendChild(formatInput);
    form.appendChild(idsInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function refreshOrdersTable() {
    location.reload();
}

function refreshOrders() {
    location.reload();
}

function showToast(title, message, type = 'info') {
    // Simple alert for now, can be enhanced with Bootstrap toasts
    console.log(`${title}: ${message}`);

    // Create a simple notification
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
    notification.innerHTML = `
        <strong>${title}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Export orders function
function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '<?php echo ADMIN_URL; ?>/orders.php?' + params.toString();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>

<?php include 'includes/footer.php'; ?>
