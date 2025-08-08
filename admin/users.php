<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Customers Management';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['status'];
        
        try {
            $stmt = getDBConnection()->prepare("UPDATE users SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $user_id])) {
                $_SESSION['admin_success'] = 'Customer status updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update customer status.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating customer: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/users.php');
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        try {
            $pdo = getDBConnection();
            
            // Check if user has orders
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $order_count = $stmt->fetch()['count'];
            
            if ($order_count > 0) {
                $_SESSION['admin_error'] = "Cannot delete customer. They have {$order_count} orders.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $_SESSION['admin_success'] = 'Customer deleted successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to delete customer.';
                }
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error deleting customer: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/users.php');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status_filter) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get users with order count
    $sql = "
        SELECT u.*, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        {$where_clause}
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $users = [];
    $_SESSION['admin_error'] = 'Error loading customers: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Customers Management</h2>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Report
        </button>
        <a href="<?php echo ADMIN_URL; ?>/export.php?type=customers&format=csv" class="btn btn-outline-success">
            <i class="fas fa-download me-2"></i>Export CSV
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Name, email or phone">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="<?php echo ADMIN_URL; ?>/users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Customers (<?php echo count($users); ?> users)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-4">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5>No customers found</h5>
                <p class="text-muted">Customer accounts will appear here when users register.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if ($user['phone']): ?>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $user['order_count']; ?> orders</span>
                                </td>
                                <td>
                                    <strong><?php echo formatPrice($user['total_spent'] ?: 0); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'active' => 'success',
                                        'inactive' => 'secondary',
                                        'suspended' => 'danger'
                                    ];
                                    $color = $status_colors[$user['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_URL; ?>/customer-detail.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-success update-status-btn" 
                                                data-id="<?php echo $user['id']; ?>"
                                                data-status="<?php echo $user['status']; ?>"
                                                data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['order_count'] == 0): ?>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this customer?')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled title="Cannot delete - has orders">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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
                <h5 class="modal-title">Update Customer Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="update_user_id" name="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="update_customer_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Status</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
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
// Handle update status modal
document.addEventListener('DOMContentLoaded', function() {
    const updateButtons = document.querySelectorAll('.update-status-btn');
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('update_user_id').value = this.dataset.id;
            document.getElementById('update_customer_name').value = this.dataset.name;
            document.getElementById('update_status').value = this.dataset.status;
        });
    });
});

// Export customers function
function exportCustomers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '<?php echo ADMIN_URL; ?>/users.php?' + params.toString();
}
</script>

<?php include 'includes/footer.php'; ?>
