<?php
require_once 'config/config.php';

// Clear all session data
session_unset();
session_destroy();

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Start a new session for the logout message
session_start();
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to home page
redirect(SITE_URL);
?>
