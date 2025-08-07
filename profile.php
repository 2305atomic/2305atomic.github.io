<?php
require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    redirect(SITE_URL . '/login.php');
}

$page_title = 'My Profile';
$page_description = 'Manage your account information and preferences.';

$success_message = '';
$error_message = '';

// Get user data
try {
    $stmt = getDBConnection()->prepare("
        SELECT * FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect(SITE_URL . '/logout.php');
    }

    // Ensure all required fields exist with default values
    $user = array_merge([
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'date_of_birth' => '',
        'gender' => ''
    ], $user);

} catch (Exception $e) {
    $error_message = 'Failed to load user data: ' . $e->getMessage();
    error_log("Profile Error: " . $e->getMessage());
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    
    if (empty($first_name) || empty($last_name)) {
        $error_message = 'First name and last name are required.';
    } else {
        try {
            $stmt = getDBConnection()->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $dob = $date_of_birth ? $date_of_birth : null;
            $gender_val = $gender ?: null;
            
            if ($stmt->execute([$first_name, $last_name, $phone, $dob, $gender_val, $_SESSION['user_id']])) {
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $success_message = 'Profile updated successfully!';
                
                // Refresh user data
                $user['first_name'] = $first_name;
                $user['last_name'] = $last_name;
                $user['phone'] = $phone;
                $user['date_of_birth'] = $dob;
                $user['gender'] = $gender_val;
            } else {
                $error_message = 'Failed to update profile.';
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred while updating profile: ' . $e->getMessage();
            error_log("Profile Update Error: " . $e->getMessage());
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All password fields are required.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match.';
    } else {
        try {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = getDBConnection()->prepare("
                    UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
                ");
                
                if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $success_message = 'Password changed successfully!';
                } else {
                    $error_message = 'Failed to change password.';
                }
            } else {
                $error_message = 'Current password is incorrect.';
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred while changing password.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
            <li class="breadcrumb-item active">My Profile</li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mb-3">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                    </div>
                    <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <div class="list-group mt-3">
                <a href="#profile-info" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                    <i class="fas fa-user me-2"></i>Profile Information
                </a>
                <a href="#change-password" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                    <i class="fas fa-lock me-2"></i>Change Password
                </a>
                <a href="<?php echo SITE_URL; ?>/orders.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-box me-2"></i>My Orders
                </a>
                <a href="<?php echo SITE_URL; ?>/wishlist.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-heart me-2"></i>Wishlist
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="tab-content">
                <!-- Profile Information -->
                <div class="tab-pane fade show active" id="profile-info">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    <small class="form-text text-muted">Email cannot be changed</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                               value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="tab-pane fade" id="change-password">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="6" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
