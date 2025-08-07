<?php
require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    redirect(SITE_URL . '/login.php');
}

$page_title = 'My Addresses';
$page_description = 'Manage your saved addresses for faster checkout.';

$success_message = '';
$error_message = '';
$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_address') {
        $type = $_POST['type'] ?? 'home';
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $address_line_1 = trim($_POST['address_line_1'] ?? '');
        $address_line_2 = trim($_POST['address_line_2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'Indonesia');
        $phone = trim($_POST['phone'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($address_line_1) || empty($city) || empty($state) || empty($postal_code)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            try {
                $pdo = getDBConnection();
                
                // If this is set as default, remove default from other addresses
                if ($is_default) {
                    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_addresses (user_id, type, first_name, last_name, company, address_line_1, address_line_2, city, state, postal_code, country, phone, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$user_id, $type, $first_name, $last_name, $company, $address_line_1, $address_line_2, $city, $state, $postal_code, $country, $phone, $is_default])) {
                    $success_message = 'Address added successfully!';
                } else {
                    $error_message = 'Failed to add address.';
                }
            } catch (Exception $e) {
                $error_message = 'An error occurred while adding the address.';
                error_log("Add Address Error: " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'delete_address') {
        $address_id = $_POST['address_id'] ?? 0;
        
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$address_id, $user_id])) {
                $success_message = 'Address deleted successfully!';
            } else {
                $error_message = 'Failed to delete address.';
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred while deleting the address.';
            error_log("Delete Address Error: " . $e->getMessage());
        }
    }
    
    elseif ($action === 'set_default') {
        $address_id = $_POST['address_id'] ?? 0;
        
        try {
            $pdo = getDBConnection();
            
            // Remove default from all addresses
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Set new default
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$address_id, $user_id])) {
                $success_message = 'Default address updated successfully!';
            } else {
                $error_message = 'Failed to update default address.';
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred while updating the default address.';
            error_log("Set Default Address Error: " . $e->getMessage());
        }
    }
}

// Get user addresses
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$user_id]);
    $addresses = $stmt->fetchAll();
} catch (Exception $e) {
    $addresses = [];
    $error_message = 'Error loading addresses: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="container-lg my-5">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1 text-primary">
                <i class="fas fa-map-marker-alt me-2"></i>My Addresses
            </h1>
            <p class="text-muted">Manage your saved addresses for faster checkout</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
            <i class="fas fa-plus me-2"></i>Add New Address
        </button>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Addresses Grid -->
    <?php if (empty($addresses)): ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-map-marker-alt fa-4x text-muted"></i>
            </div>
            <h4 class="text-muted">No Addresses Found</h4>
            <p class="text-muted mb-4">You haven't added any addresses yet. Add your first address to get started.</p>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                <i class="fas fa-plus me-2"></i>Add Your First Address
            </button>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($addresses as $address): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card h-100 shadow-sm border-0 <?php echo $address['is_default'] ? 'border-primary' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <i class="fas fa-<?php echo $address['type'] === 'home' ? 'home' : ($address['type'] === 'work' ? 'building' : 'map-marker-alt'); ?> me-2 text-primary"></i>
                                        <?php echo ucfirst($address['type']); ?> Address
                                    </h5>
                                    <?php if ($address['is_default']): ?>
                                        <span class="badge bg-primary">Default</span>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if (!$address['is_default']): ?>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="set_default">
                                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="fas fa-star me-2"></i>Set as Default
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this address?')">
                                                <input type="hidden" name="action" value="delete_address">
                                                <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="address-details">
                                <p class="mb-2">
                                    <strong><?php echo htmlspecialchars($address['first_name'] . ' ' . $address['last_name']); ?></strong>
                                </p>
                                <?php if ($address['company']): ?>
                                    <p class="mb-2 text-muted"><?php echo htmlspecialchars($address['company']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><?php echo htmlspecialchars($address['address_line_1']); ?></p>
                                <?php if ($address['address_line_2']): ?>
                                    <p class="mb-1"><?php echo htmlspecialchars($address['address_line_2']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1">
                                    <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']); ?>
                                </p>
                                <p class="mb-2"><?php echo htmlspecialchars($address['country']); ?></p>
                                <?php if ($address['phone']): ?>
                                    <p class="mb-0 text-muted">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($address['phone']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Address
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_address">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Address Type *</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="home">Home</option>
                                <option value="work">Work</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                                <label class="form-check-label" for="is_default">
                                    Set as default address
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company" class="form-label">Company (Optional)</label>
                        <input type="text" class="form-control" id="company" name="company">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address_line_1" class="form-label">Address Line 1 *</label>
                        <input type="text" class="form-control" id="address_line_1" name="address_line_1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address_line_2" class="form-label">Address Line 2 (Optional)</label>
                        <input type="text" class="form-control" id="address_line_2" name="address_line_2">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City *</label>
                            <input type="text" class="form-control" id="city" name="city" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label">State/Province *</label>
                            <input type="text" class="form-control" id="state" name="state" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="postal_code" class="form-label">Postal Code *</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="country" class="form-label">Country *</label>
                            <input type="text" class="form-control" id="country" name="country" value="Indonesia" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Address
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.border-primary {
    border: 2px solid var(--bs-primary) !important;
}

.address-details {
    font-size: 0.9rem;
    line-height: 1.4;
}
</style>

<?php include 'includes/footer.php'; ?>
