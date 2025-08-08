<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $settings = [
            'site_name' => sanitize($_POST['site_name']),
            'site_description' => sanitize($_POST['site_description']),
            'site_email' => sanitize($_POST['site_email']),
            'site_phone' => sanitize($_POST['site_phone']),
            'site_address' => sanitize($_POST['site_address']),
            'currency' => $_POST['currency'],
            'tax_rate' => (float)$_POST['tax_rate'],
            'shipping_fee' => (float)$_POST['shipping_fee'],
            'free_shipping_min' => (float)$_POST['free_shipping_min'],
            'order_prefix' => sanitize($_POST['order_prefix']),
            'items_per_page' => (int)$_POST['items_per_page'],
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'allow_guest_checkout' => isset($_POST['allow_guest_checkout']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'auto_approve_reviews' => isset($_POST['auto_approve_reviews']) ? 1 : 0
        ];
        
        try {
            $pdo = getDBConnection();
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$key, $value]);
            }
            
            $_SESSION['admin_success'] = 'Settings updated successfully!';
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating settings: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/settings.php');
    }
}

// Get current settings
try {
    $stmt = getDBConnection()->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings_data = [];
}

// Default values
$defaults = [
    'site_name' => 'TeWuNeed',
    'site_description' => 'Your trusted online shopping destination',
    'site_email' => 'admin@tewuneed.com',
    'site_phone' => '+62 123 456 7890',
    'site_address' => 'Jakarta, Indonesia',
    'currency' => 'IDR',
    'tax_rate' => 10,
    'shipping_fee' => 15000,
    'free_shipping_min' => 100000,
    'order_prefix' => 'TWN',
    'items_per_page' => 12,
    'maintenance_mode' => 0,
    'allow_guest_checkout' => 1,
    'email_notifications' => 1,
    'auto_approve_reviews' => 0
];

// Merge with defaults
$settings = array_merge($defaults, $settings_data);

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Website Settings</h2>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Settings
        </button>
        <button class="btn btn-outline-warning" onclick="resetToDefaults()">
            <i class="fas fa-undo me-2"></i>Reset to Defaults
        </button>
    </div>
</div>

<form method="POST">
    <div class="row">
        <!-- General Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>General Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="site_name" class="form-label">Site Name</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" 
                               value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="site_email" name="site_email" 
                               value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_phone" class="form-label">Contact Phone</label>
                        <input type="text" class="form-control" id="site_phone" name="site_phone" 
                               value="<?php echo htmlspecialchars($settings['site_phone']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_address" class="form-label">Address</label>
                        <textarea class="form-control" id="site_address" name="site_address" rows="2"><?php echo htmlspecialchars($settings['site_address']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- E-commerce Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>E-commerce Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="currency" class="form-label">Currency</label>
                        <select class="form-select" id="currency" name="currency">
                            <option value="IDR" <?php echo $settings['currency'] === 'IDR' ? 'selected' : ''; ?>>Indonesian Rupiah (IDR)</option>
                            <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                            <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                        <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                               value="<?php echo $settings['tax_rate']; ?>" step="0.01" min="0" max="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="shipping_fee" class="form-label">Default Shipping Fee</label>
                        <input type="number" class="form-control" id="shipping_fee" name="shipping_fee" 
                               value="<?php echo $settings['shipping_fee']; ?>" step="0.01" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="free_shipping_min" class="form-label">Free Shipping Minimum</label>
                        <input type="number" class="form-control" id="free_shipping_min" name="free_shipping_min" 
                               value="<?php echo $settings['free_shipping_min']; ?>" step="0.01" min="0">
                        <small class="text-muted">Orders above this amount get free shipping</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="order_prefix" class="form-label">Order Number Prefix</label>
                        <input type="text" class="form-control" id="order_prefix" name="order_prefix" 
                               value="<?php echo htmlspecialchars($settings['order_prefix']); ?>" maxlength="5">
                        <small class="text-muted">e.g., TWN-2024-001</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Display Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-desktop me-2"></i>Display Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="items_per_page" class="form-label">Products Per Page</label>
                        <select class="form-select" id="items_per_page" name="items_per_page">
                            <option value="8" <?php echo $settings['items_per_page'] == 8 ? 'selected' : ''; ?>>8 products</option>
                            <option value="12" <?php echo $settings['items_per_page'] == 12 ? 'selected' : ''; ?>>12 products</option>
                            <option value="16" <?php echo $settings['items_per_page'] == 16 ? 'selected' : ''; ?>>16 products</option>
                            <option value="20" <?php echo $settings['items_per_page'] == 20 ? 'selected' : ''; ?>>20 products</option>
                            <option value="24" <?php echo $settings['items_per_page'] == 24 ? 'selected' : ''; ?>>24 products</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                               <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="maintenance_mode">
                            <strong>Maintenance Mode</strong>
                        </label>
                        <div class="text-muted small">Website will show maintenance page to visitors</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="allow_guest_checkout" name="allow_guest_checkout" 
                               <?php echo $settings['allow_guest_checkout'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_guest_checkout">
                            <strong>Allow Guest Checkout</strong>
                        </label>
                        <div class="text-muted small">Customers can checkout without creating an account</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notification Settings -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Settings</h5>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                               <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="email_notifications">
                            <strong>Email Notifications</strong>
                        </label>
                        <div class="text-muted small">Send email notifications for orders and updates</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="auto_approve_reviews" name="auto_approve_reviews" 
                               <?php echo $settings['auto_approve_reviews'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="auto_approve_reviews">
                            <strong>Auto-approve Reviews</strong>
                        </label>
                        <div class="text-muted small">Automatically approve customer reviews without moderation</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Email Configuration</strong><br>
                        Configure SMTP settings in your hosting control panel or contact your hosting provider.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Save Button -->
    <div class="card">
        <div class="card-body text-center">
            <button type="submit" name="update_settings" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Save All Settings
            </button>
            <div class="mt-2">
                <small class="text-muted">Changes will take effect immediately</small>
            </div>
        </div>
    </div>
</form>

<!-- System Information -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-server me-2"></i>System Information</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td><strong>PHP Version:</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Software:</strong></td>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database:</strong></td>
                        <td>
                            <?php
                            try {
                                $pdo = getDBConnection();
                                $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                                echo "MySQL " . $version;
                            } catch (Exception $e) {
                                echo "Connection Error";
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Upload Max Size:</strong></td>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Post Max Size:</strong></td>
                        <td><?php echo ini_get('post_max_size'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Memory Limit:</strong></td>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function resetToDefaults() {
    if (confirm('Are you sure you want to reset all settings to default values? This action cannot be undone.')) {
        // Reset form to default values
        document.getElementById('site_name').value = 'TeWuNeed';
        document.getElementById('site_description').value = 'Your trusted online shopping destination';
        document.getElementById('site_email').value = 'admin@tewuneed.com';
        document.getElementById('site_phone').value = '+62 123 456 7890';
        document.getElementById('site_address').value = 'Jakarta, Indonesia';
        document.getElementById('currency').value = 'IDR';
        document.getElementById('tax_rate').value = '10';
        document.getElementById('shipping_fee').value = '15000';
        document.getElementById('free_shipping_min').value = '100000';
        document.getElementById('order_prefix').value = 'TWN';
        document.getElementById('items_per_page').value = '12';
        document.getElementById('maintenance_mode').checked = false;
        document.getElementById('allow_guest_checkout').checked = true;
        document.getElementById('email_notifications').checked = true;
        document.getElementById('auto_approve_reviews').checked = false;
        
        showNotification('Settings reset to defaults. Click Save to apply changes.', 'warning');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
