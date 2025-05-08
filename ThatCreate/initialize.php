<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

// Connect to the database
$database = new Database();
$conn = $database->connect();

// Ensure the admin user exists
ensure_admin_user($conn);

echo "Admin user ensured.";
?>
