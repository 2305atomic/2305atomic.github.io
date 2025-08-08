<?php
require_once 'config.php';

// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

// Redirect to login page
redirect(ADMIN_URL . '/login.php');
?>
