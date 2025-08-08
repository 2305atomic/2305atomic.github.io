<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Order Details';

// Get order ID
$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    $_SESSION['admin_error'] = 'Order not found.';
    redirect(ADMIN_URL . '/orders.php');
}

// Get order data
try {
    $pdo = getDBConnection();
    
    // Get order with customer info
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['admin_error'] = 'Order not found.';
        redirect(ADMIN_URL . '/orders.php');
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image, p.slug, p.stock_quantity
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

    // Get order status history
    $stmt = $pdo->prepare("
        SELECT osh.*, au.first_name, au.last_name
        FROM order_status_history osh
        LEFT JOIN admin_users au ON osh.changed_by = au.id
        WHERE osh.order_id = ?
        ORDER BY osh.created_at DESC
    ");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll();

    // Get shipping address
    $shipping_address = null;
    if ($order['shipping_address']) {
        $shipping_address = json_decode($order['shipping_address'], true);
    }

    // Get customer info for guest orders
    $customer_info = null;
    if ($order['customer_info']) {
        $customer_info = json_decode($order['customer_info'], true);
    }
    
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Error loading order: ' . $e->getMessage();
    redirect(ADMIN_URL . '/orders.php');
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    $notes = sanitize($_POST['notes']);
    
    try {
        $pdo->beginTransaction();
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
        $stmt->execute([$new_status, $payment_status, $order_id]);
        
        // Add to status history if notes provided
        if (!empty($notes)) {
            $stmt = $pdo->prepare("
                INSERT INTO order_status_history (order_id, status, notes, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $new_status, $notes, $_SESSION['admin_id']]);
        }
        
        $pdo->commit();
        $_SESSION['admin_success'] = 'Order status updated successfully!';
        redirect(ADMIN_URL . '/order-detail.php?id=' . $order_id);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = 'Error updating order: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Order Details: <?php echo htmlspecialchars($order['order_number']); ?></h2>
        <div class="d-flex gap-3 mt-2">
            <?php
            $status_colors = [
                'pending' => 'warning',
                'processing' => 'info',
                'shipped' => 'primary',
                'delivered' => 'success',
                'cancelled' => 'danger'
            ];
            $payment_colors = [
                'pending' => 'warning',
                'paid' => 'success',
                'failed' => 'danger',
                'refunded' => 'info'
            ];
            $status_color = $status_colors[$order['status']] ?? 'secondary';
            $payment_color = $payment_colors[$order['payment_status']] ?? 'secondary';
            ?>
            <span class="badge bg-<?php echo $status_color; ?> fs-6">
                <i class="fas fa-circle me-1"></i><?php echo ucfirst($order['status']); ?>
            </span>
            <span class="badge bg-<?php echo $payment_color; ?> fs-6">
                <i class="fas fa-credit-card me-1"></i><?php echo ucfirst($order['payment_status']); ?>
            </span>
            <small class="text-muted">
                <i class="fas fa-clock me-1"></i>
                Created <?php echo formatDate($order['created_at']); ?>
            </small>
        </div>
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-2"></i>Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Order
                </a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#emailCustomerModal">
                    <i class="fas fa-envelope me-2"></i>Email Customer
                </a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="fas fa-sticky-note me-2"></i>Add Note
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDeleteOrder()">
                    <i class="fas fa-trash me-2"></i>Delete Order
                </a></li>
            </ul>
        </div>
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print
        </button>
        <a href="<?php echo ADMIN_URL; ?>/orders.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<!-- Order Status Timeline -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>Order Timeline
        </h5>
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php if (!empty($status_history)): ?>
                <?php foreach ($status_history as $index => $history): ?>
                    <div class="timeline-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <div class="timeline-marker">
                            <i class="fas fa-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <h6 class="mb-1">
                                Status changed to: <span class="badge bg-<?php echo $status_colors[$history['new_status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($history['new_status']); ?>
                                </span>
                            </h6>
                            <p class="text-muted mb-1"><?php echo htmlspecialchars($history['notes'] ?? ''); ?></p>
                            <small class="text-muted">
                                <?php echo formatDate($history['created_at']); ?>
                                <?php if ($history['first_name']): ?>
                                    by <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-history fa-2x mb-2"></i>
                    <p>No status history available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Order Information -->
    <div class="col-lg-8">
        <!-- Order Status -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Order Status</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                    <i class="fas fa-edit me-1"></i>Update Status
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Order Status</label>
                            <div>
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
                                <span class="badge bg-<?php echo $color; ?> fs-6">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Payment Status</label>
                            <div>
                                <?php
                                $payment_colors = [
                                    'pending' => 'warning',
                                    'paid' => 'success',
                                    'failed' => 'danger',
                                    'refunded' => 'secondary'
                                ];
                                $payment_color = $payment_colors[$order['payment_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $payment_color; ?> fs-6">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Order Date</label>
                            <div><?php echo formatDate($order['created_at']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <div><?php echo htmlspecialchars($order['payment_method'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-product.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                 class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <?php if ($item['slug']): ?>
                                                    <small>
                                                        <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $item['slug']; ?>" 
                                                           target="_blank" class="text-decoration-none">
                                                            View Product <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($item['price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><strong><?php echo formatPrice($item['total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Subtotal</th>
                                <th><?php echo formatPrice($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount']); ?></th>
                            </tr>
                            <?php if ($order['shipping_amount'] > 0): ?>
                                <tr>
                                    <th colspan="3">Shipping</th>
                                    <th><?php echo formatPrice($order['shipping_amount']); ?></th>
                                </tr>
                            <?php endif; ?>
                            <?php if ($order['tax_amount'] > 0): ?>
                                <tr>
                                    <th colspan="3">Tax</th>
                                    <th><?php echo formatPrice($order['tax_amount']); ?></th>
                                </tr>
                            <?php endif; ?>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <th colspan="3">Discount</th>
                                    <th class="text-success">-<?php echo formatPrice($order['discount_amount']); ?></th>
                                </tr>
                            <?php endif; ?>
                            <tr class="table-primary">
                                <th colspan="3">Total</th>
                                <th><?php echo formatPrice($order['total_amount']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Addresses -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Shipping & Billing Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Shipping Address</h6>
                        <div class="border p-3 rounded">
                            <?php if ($order['shipping_address']): ?>
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            <?php else: ?>
                                <em class="text-muted">No shipping address provided</em>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Billing Address</h6>
                        <div class="border p-3 rounded">
                            <?php if ($order['billing_address']): ?>
                                <?php echo nl2br(htmlspecialchars($order['billing_address'])); ?>
                            <?php else: ?>
                                <em class="text-muted">Same as shipping address</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Notes -->
        <?php if ($order['notes']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Notes</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <?php if ($order['first_name']): ?>
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <div><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div>
                            <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>">
                                <?php echo htmlspecialchars($order['email']); ?>
                            </a>
                        </div>
                    </div>
                    <?php if ($order['phone']): ?>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <div>
                                <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>">
                                    <?php echo htmlspecialchars($order['phone']); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="d-grid">
                        <a href="<?php echo ADMIN_URL; ?>/customer-detail.php?id=<?php echo $order['user_id']; ?>" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-user me-2"></i>View Customer Profile
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-user-slash fa-2x mb-2"></i>
                        <p>Guest Customer</p>
                        <small>No customer account associated with this order</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Order Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Order Number:</span>
                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Items:</span>
                    <span><?php echo count($order_items); ?> items</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Amount:</span>
                    <strong class="text-primary"><?php echo formatPrice($order['total_amount']); ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Created:</span>
                    <span><?php echo formatDate($order['created_at']); ?></span>
                </div>
                <?php if ($order['updated_at'] !== $order['created_at']): ?>
                    <div class="d-flex justify-content-between">
                        <span>Last Updated:</span>
                        <span><?php echo formatDate($order['updated_at']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        <i class="fas fa-edit me-2"></i>Update Status
                    </button>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Order
                    </button>
                    <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>?subject=Order <?php echo htmlspecialchars($order['order_number']); ?>" 
                       class="btn btn-outline-info">
                        <i class="fas fa-envelope me-2"></i>Email Customer
                    </a>
                </div>
            </div>
        </div>
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
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($order['order_number']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Order Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="failed" <?php echo $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Add notes about this status update..."></textarea>
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

<!-- Email Customer Modal -->
<div class="modal fade" id="emailCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo ADMIN_URL; ?>/send-order-email.php">
                <div class="modal-body">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <input type="email" class="form-control" name="email"
                               value="<?php echo htmlspecialchars($order['email'] ?: ($customer_info['email'] ?? '')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject"
                               value="Update on your order <?php echo htmlspecialchars($order['order_number']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="6" required>Dear Customer,

We wanted to update you on your order <?php echo htmlspecialchars($order['order_number']); ?>.

Current Status: <?php echo ucfirst($order['status']); ?>
Payment Status: <?php echo ucfirst($order['payment_status']); ?>

Thank you for your business!

Best regards,
TeWuNeed Team</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Order Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_note" value="1">
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="4"
                                  placeholder="Add a note about this order..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Timeline Styles */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-bottom: 20px;
}

.timeline-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 14px;
    height: 14px;
    background: #6c757d;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-item.active .timeline-marker {
    background: #0d6efd;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
}

.timeline-marker i {
    font-size: 6px;
    color: white;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}

.timeline-item.active .timeline-content {
    border-left-color: #0d6efd;
    background: #e3f2fd;
}

/* Order Items Enhancement */
.product-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.order-item-row:hover {
    background-color: #f8f9fa;
}

/* Status Badges */
.status-badge {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
}

/* Print Styles */
@media print {
    .btn, .modal, .dropdown {
        display: none !important;
    }

    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }

    .timeline::before {
        background: #000 !important;
    }

    .timeline-marker {
        background: #000 !important;
    }
}
</style>

<script>
function confirmDeleteOrder() {
    if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo ADMIN_URL; ?>/orders.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_order';
        input.value = '1';

        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = '<?php echo $order['id']; ?>';

        form.appendChild(input);
        form.appendChild(orderIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-refresh order status every 30 seconds
setInterval(function() {
    if (document.visibilityState === 'visible') {
        fetch('<?php echo ADMIN_URL; ?>/api/get-order-status.php?id=<?php echo $order['id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.order) {
                    // Update status badges if changed
                    const currentStatus = '<?php echo $order['status']; ?>';
                    const currentPayment = '<?php echo $order['payment_status']; ?>';

                    if (data.order.status !== currentStatus || data.order.payment_status !== currentPayment) {
                        // Reload page to show updated status
                        location.reload();
                    }
                }
            })
            .catch(error => console.log('Status check failed:', error));
    }
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>
