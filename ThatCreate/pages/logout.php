<?php
// Logout page
session_start(); // Start the session

require_once '../config/constants.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Log the user out
$result = logout_user();

// Ensure the result contains a message
if (isset($result['message'])) {
    $_SESSION['success_message'] = $result['message'];
} else {
    $_SESSION['success_message'] = "You have been logged out successfully.";
}

// Redirect to the homepage
redirect('../index.php');
?>
