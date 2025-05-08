<?php
// Admin home page - redirects to dashboard
require_once '../config/constants.php';
require_once '../includes/auth.php';

// Require staff access
require_staff('../index.php');

// Redirect to dashboard
redirect('dashboard.php');
?>
