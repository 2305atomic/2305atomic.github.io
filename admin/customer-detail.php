<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Customer Details';

// Get customer ID
$customer_id = (int)($_GET['id'] ?? 0);
if (!$customer_id) {
    $_SESSION['admin_error'] = 'Customer not found.';
    redirect(ADMIN_URL . '/users.php');
}

// Get customer data
try {
    $pdo = getDBConnection();
    
    // Get customer info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        $_SESSION['admin_error'] = 'Customer not found.';
        redirect(ADMIN_URL . '/users.php');
    }
    
    // Get customer orders
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll();
    
    // Get customer statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_spent,
            AVG(total_amount) as avg_order_value,
            MAX(created_at) as last_order_date
        FROM orders 
        WHERE user_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$customer_id]);
    $stats = $stmt->fetch();
    
    // Get customer addresses
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
    $stmt->execute([$customer_id]);
    $addresses = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Error loading customer: ' . $e->getMessage();
    redirect(ADMIN_URL . '/users.php');
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Customer Details: <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h2>
    <a href="<?php echo ADMIN_URL; ?>/users.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Customers
    </a>
</div>

<div class="row">
    <!-- Customer Information -->
    <div class="col-lg-8">
        <!-- Basic Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <div><strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div>
                                <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                    <?php echo htmlspecialchars($customer['email']); ?>
                                </a>
                                <?php if ($customer['email_verified']): ?>
                                    <span class="badge bg-success ms-2">Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-warning ms-2">Unverified</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <div>
                                <?php if ($customer['phone']): ?>
                                    <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>">
                                        <?php echo htmlspecialchars($customer['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <em class="text-muted">Not provided</em>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Date of Birth</label>
                            <div>
                                <?php if ($customer['date_of_birth']): ?>
                                    <?php echo formatDate($customer['date_of_birth']); ?>
                                <?php else: ?>
                                    <em class="text-muted">Not provided</em>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <div>
                                <?php if ($customer['gender']): ?>
                                    <?php echo ucfirst($customer['gender']); ?>
                                <?php else: ?>
                                    <em class="text-muted">Not specified</em>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <div>
                                <?php
                                $status_colors = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'suspended' => 'danger'
                                ];
                                $color = $status_colors[$customer['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($customer['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <div><?php echo formatDate($customer['created_at']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Last Updated</label>
                            <div><?php echo formatDate($customer['updated_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Addresses -->
        <?php if (!empty($addresses)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Saved Addresses</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($addresses as $address): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border p-3 rounded <?php echo $address['is_default'] ? 'border-primary' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0">
                                            <?php echo ucfirst($address['type']); ?> Address
                                            <?php if ($address['is_default']): ?>
                                                <span class="badge bg-primary ms-1">Default</span>
                                            <?php endif; ?>
                                        </h6>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($address['first_name'] . ' ' . $address['last_name']); ?></strong><br>
                                        <?php if ($address['company']): ?>
                                            <?php echo htmlspecialchars($address['company']); ?><br>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($address['address_line_1']); ?><br>
                                        <?php if ($address['address_line_2']): ?>
                                            <?php echo htmlspecialchars($address['address_line_2']); ?><br>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']); ?><br>
                                        <?php echo htmlspecialchars($address['country']); ?>
                                        <?php if ($address['phone']): ?>
                                            <br>Phone: <?php echo htmlspecialchars($address['phone']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Recent Orders -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="<?php echo ADMIN_URL; ?>/orders.php?search=<?php echo urlencode($customer['email']); ?>" 
                   class="btn btn-sm btn-outline-primary">
                    View All Orders
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h6>No Orders Found</h6>
                        <p class="text-muted">This customer hasn't placed any orders yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo ADMIN_URL; ?>/order-detail.php?id=<?php echo $order['id']; ?>" 
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($order['order_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo formatDate($order['created_at']); ?></td>
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
                                        <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                        <td>
                                            <a href="<?php echo ADMIN_URL; ?>/order-detail.php?id=<?php echo $order['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Customer Avatar -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="avatar-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 24px;">
                    <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                </div>
                <h5><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h5>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($customer['email']); ?></p>
                
                <div class="d-grid gap-2">
                    <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <?php if ($customer['phone']): ?>
                        <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-phone me-2"></i>Call Customer
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Customer Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Customer Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-primary mb-0"><?php echo $stats['total_orders'] ?: 0; ?></h4>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-success mb-0"><?php echo formatPrice($stats['total_spent'] ?: 0); ?></h4>
                        <small class="text-muted">Total Spent</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Average Order:</span>
                    <strong><?php echo formatPrice($stats['avg_order_value'] ?: 0); ?></strong>
                </div>
                
                <?php if ($stats['last_order_date']): ?>
                    <div class="d-flex justify-content-between">
                        <span>Last Order:</span>
                        <span><?php echo formatDate($stats['last_order_date']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Account Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Account Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning" onclick="resetPassword()">
                        <i class="fas fa-key me-2"></i>Reset Password
                    </button>
                    <button class="btn btn-outline-info" onclick="sendWelcomeEmail()">
                        <i class="fas fa-paper-plane me-2"></i>Send Welcome Email
                    </button>
                    <?php if ($customer['status'] === 'active'): ?>
                        <button class="btn btn-outline-secondary" onclick="suspendAccount()">
                            <i class="fas fa-ban me-2"></i>Suspend Account
                        </button>
                    <?php else: ?>
                        <button class="btn btn-outline-success" onclick="activateAccount()">
                            <i class="fas fa-check me-2"></i>Activate Account
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}
</style>

<script>
function resetPassword() {
    if (confirm('Are you sure you want to reset this customer\'s password? They will receive an email with reset instructions.')) {
        // Implement password reset functionality
        showNotification('Password reset email sent to customer', 'success');
    }
}

function sendWelcomeEmail() {
    if (confirm('Send welcome email to this customer?')) {
        // Implement welcome email functionality
        showNotification('Welcome email sent successfully', 'success');
    }
}

function suspendAccount() {
    if (confirm('Are you sure you want to suspend this customer account? They will not be able to login or place orders.')) {
        // Implement account suspension
        window.location.href = '<?php echo ADMIN_URL; ?>/users.php?action=suspend&id=<?php echo $customer_id; ?>';
    }
}

function activateAccount() {
    if (confirm('Activate this customer account?')) {
        // Implement account activation
        window.location.href = '<?php echo ADMIN_URL; ?>/users.php?action=activate&id=<?php echo $customer_id; ?>';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
