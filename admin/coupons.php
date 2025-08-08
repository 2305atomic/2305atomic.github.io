<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Coupons Management';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_coupon'])) {
        $code = strtoupper(sanitize($_POST['code']));
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $min_amount = (float)$_POST['min_amount'];
        $max_uses = (int)$_POST['max_uses'];
        $expires_at = $_POST['expires_at'];
        $description = sanitize($_POST['description']);
        
        if (empty($code) || $value <= 0) {
            $_SESSION['admin_error'] = 'Code and value are required.';
        } else {
            try {
                $stmt = getDBConnection()->prepare("
                    INSERT INTO coupons (code, type, value, min_amount, max_uses, expires_at, description, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                if ($stmt->execute([$code, $type, $value, $min_amount, $max_uses, $expires_at, $description])) {
                    $_SESSION['admin_success'] = 'Coupon created successfully!';
                } else {
                    $_SESSION['admin_error'] = 'Failed to create coupon.';
                }
            } catch (Exception $e) {
                $_SESSION['admin_error'] = 'Error creating coupon: ' . $e->getMessage();
            }
        }
        redirect(ADMIN_URL . '/coupons.php');
    }
    
    if (isset($_POST['toggle_status'])) {
        $coupon_id = (int)$_POST['coupon_id'];
        $new_status = $_POST['new_status'];
        
        try {
            $stmt = getDBConnection()->prepare("UPDATE coupons SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $coupon_id])) {
                $_SESSION['admin_success'] = 'Coupon status updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update coupon status.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating coupon: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/coupons.php');
    }
    
    if (isset($_POST['delete_coupon'])) {
        $coupon_id = (int)$_POST['coupon_id'];
        try {
            $stmt = getDBConnection()->prepare("DELETE FROM coupons WHERE id = ?");
            if ($stmt->execute([$coupon_id])) {
                $_SESSION['admin_success'] = 'Coupon deleted successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to delete coupon.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error deleting coupon: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/coupons.php');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(code LIKE ? OR description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get coupons
    $sql = "SELECT * FROM coupons {$where_clause} ORDER BY created_at DESC";
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    $coupons = $stmt->fetchAll();
    
} catch (Exception $e) {
    $coupons = [];
    $_SESSION['admin_error'] = 'Error loading coupons: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Coupons Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCouponModal">
        <i class="fas fa-plus me-2"></i>Create New Coupon
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Coupon code or description">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" name="type">
                    <option value="">All Types</option>
                    <option value="percentage" <?php echo $type_filter === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                    <option value="fixed" <?php echo $type_filter === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="<?php echo ADMIN_URL; ?>/coupons.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Coupons Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Coupons (<?php echo count($coupons); ?> items)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($coupons)): ?>
            <div class="text-center py-4">
                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                <h5>No coupons found</h5>
                <p class="text-muted">Create your first discount coupon.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                    <i class="fas fa-plus me-2"></i>Create First Coupon
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Amount</th>
                            <th>Usage</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $coupon): ?>
                            <?php
                            $expires_at = $coupon['expires_at'] ?? $coupon['valid_until'] ?? null;
                            $max_uses = $coupon['max_uses'] ?? $coupon['usage_limit'] ?? 0;
                            $used_count = $coupon['used_count'] ?? 0;

                            $is_expired = $expires_at && strtotime($expires_at) < time();
                            $is_used_up = $max_uses > 0 && $used_count >= $max_uses;
                            ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo htmlspecialchars($coupon['code'] ?? $coupon['CODE'] ?? 'N/A'); ?></strong>
                                    <?php if (!empty($coupon['description'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($coupon['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $type = $coupon['type'] ?? $coupon['TYPE'] ?? 'fixed';
                                    ?>
                                    <span class="badge bg-<?php echo $type === 'percentage' ? 'info' : 'warning'; ?>">
                                        <?php echo ucfirst($type); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>
                                        <?php
                                        $value = $coupon['value'] ?? $coupon['VALUE'] ?? 0;
                                        if ($type === 'percentage'): ?>
                                            <?php echo $value; ?>%
                                        <?php else: ?>
                                            <?php echo formatPrice($value); ?>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $min_amount = $coupon['min_amount'] ?? $coupon['minimum_amount'] ?? 0;
                                    echo ($min_amount > 0) ? formatPrice($min_amount) : 'No minimum';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($max_uses > 0): ?>
                                        <span class="badge bg-<?php echo $is_used_up ? 'danger' : 'secondary'; ?>">
                                            <?php echo $used_count; ?> / <?php echo $max_uses; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Unlimited</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($expires_at): ?>
                                        <span class="<?php echo $is_expired ? 'text-danger' : ''; ?>">
                                            <?php echo formatDate($expires_at); ?>
                                        </span>
                                        <?php if ($is_expired): ?>
                                            <br><small class="text-danger">Expired</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $coupon['status'] ?? $coupon['STATUS'] ?? 'active';
                                    $status_color = 'secondary';
                                    $status_text = ucfirst($status);

                                    if ($is_expired || $is_used_up) {
                                        $status_color = 'danger';
                                        $status_text = $is_expired ? 'Expired' : 'Used Up';
                                    } elseif ($status === 'active') {
                                        $status_color = 'success';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php $coupon_code = $coupon['code'] ?? $coupon['CODE'] ?? 'N/A'; ?>
                                        <button class="btn btn-outline-primary"
                                                onclick="copyCouponCode('<?php echo $coupon_code; ?>')"
                                                title="Copy Code">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <?php if (!$is_expired && !$is_used_up): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id'] ?? 0; ?>">
                                                <input type="hidden" name="new_status"
                                                       value="<?php echo $status === 'active' ? 'inactive' : 'active'; ?>">
                                                <button type="submit" name="toggle_status"
                                                        class="btn btn-outline-<?php echo $status === 'active' ? 'warning' : 'success'; ?>"
                                                        title="<?php echo $status === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-toggle-<?php echo $status === 'active' ? 'off' : 'on'; ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Are you sure you want to delete this coupon?')">
                                            <input type="hidden" name="coupon_id" value="<?php echo $coupon['id'] ?? 0; ?>">
                                            <button type="submit" name="delete_coupon" class="btn btn-outline-danger" title="Delete">
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

<!-- Add Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_code" class="form-label">Coupon Code *</label>
                                <input type="text" class="form-control" id="add_code" name="code" required 
                                       style="text-transform: uppercase;">
                                <small class="text-muted">Will be converted to uppercase</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_type" class="form-label">Discount Type *</label>
                                <select class="form-select" id="add_type" name="type" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (Rp)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_value" class="form-label">Discount Value *</label>
                                <input type="number" class="form-control" id="add_value" name="value" 
                                       step="0.01" min="0" required>
                                <small class="text-muted" id="value_hint">Enter percentage (1-100) or amount</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_min_amount" class="form-label">Minimum Order Amount</label>
                                <input type="number" class="form-control" id="add_min_amount" name="min_amount" 
                                       step="0.01" min="0" value="0">
                                <small class="text-muted">0 = No minimum</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_max_uses" class="form-label">Maximum Uses</label>
                                <input type="number" class="form-control" id="add_max_uses" name="max_uses" 
                                       min="0" value="0">
                                <small class="text-muted">0 = Unlimited uses</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_expires_at" class="form-label">Expiry Date</label>
                                <input type="datetime-local" class="form-control" id="add_expires_at" name="expires_at">
                                <small class="text-muted">Leave empty for no expiry</small>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_coupon" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update value hint based on type
document.getElementById('add_type').addEventListener('change', function() {
    const hint = document.getElementById('value_hint');
    if (this.value === 'percentage') {
        hint.textContent = 'Enter percentage (1-100)';
        document.getElementById('add_value').max = 100;
    } else {
        hint.textContent = 'Enter fixed amount in Rupiah';
        document.getElementById('add_value').removeAttribute('max');
    }
});

// Copy coupon code
function copyCouponCode(code) {
    navigator.clipboard.writeText(code).then(function() {
        showNotification('Coupon code copied to clipboard!', 'success');
    });
}

// Generate random coupon code
function generateCouponCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('add_code').value = result;
}

// Add generate button
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('add_code');
    const generateBtn = document.createElement('button');
    generateBtn.type = 'button';
    generateBtn.className = 'btn btn-outline-secondary btn-sm mt-1';
    generateBtn.innerHTML = '<i class="fas fa-random me-1"></i>Generate';
    generateBtn.onclick = generateCouponCode;
    codeInput.parentNode.appendChild(generateBtn);
});
</script>

<?php include 'includes/footer.php'; ?>
