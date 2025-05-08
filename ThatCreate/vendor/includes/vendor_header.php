<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/database.php';

// Start session
start_session_if_not_started();

// Get current user if logged in
$current_user = get_logged_in_user();

// Connect to database
$database = new Database();
$conn = $database->connect();

// Check if user is a vendor, otherwise redirect to login
if (!is_vendor() && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'register.php') {
    $_SESSION['error_message'] = "Please login as a vendor to access this page.";
    header("Location: login.php");
    exit;
}

// Get vendor data if logged in
$vendor_data = [];
if (is_vendor()) {
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
    $stmt->execute([$current_user['id']]);
    $vendor_data = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?> Vendor Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
</head>
<body class="vendor-portal">
    <!-- Header -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="fas fa-gem me-2"></i><?php echo SITE_NAME; ?> Vendor Portal
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <?php if (is_vendor()): ?>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">Products</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">Sales Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profile</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user"></i> <?php echo $current_user['name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../pages/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="container py-4">
        <?php
        // Display flash messages
        if (isset($_SESSION['success_message'])) {
            echo display_success($_SESSION['success_message']);
            unset($_SESSION['success_message']);
        }
        
        if (isset($_SESSION['error_message'])) {
            echo display_error($_SESSION['error_message']);
            unset($_SESSION['error_message']);
        }
        ?>