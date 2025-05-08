<?php
// Use dynamic file path resolution based on the current file location
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

// Start session
start_session_if_not_started();

// Get current user if logged in
$current_user = get_logged_in_user();

// Connect to database
try {
    $database = new Database();
    $conn = $database->connect();
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    die("Connection Error: " . $e->getMessage());
}

// Get cart count if user is logged in
$cart_count = 0;
if ($current_user) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$current_user['id']]);
    $result = $stmt->fetch();
    $cart_count = $result['total'] ? $result['total'] : 0;
}

// Get categories for navigation
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo '/assets/css/style.css'; ?>">
</head>
<body>
    <!-- Header -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container">
                <a class="navbar-brand" href="<?php echo '/index.php'; ?>">
                    <i class="fas fa-gem me-2"></i><?php echo SITE_NAME; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/index.php">Home</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                Categories
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <?php foreach ($categories as $category): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo '/pages/products.php?category=' . $category['id']; ?>">
                                        <?php echo $category['name']; ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo '/pages/products.php'; ?>">All Products</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo '/pages/products.php'; ?>">Shop</a>
                        </li>
                        <li class="nav-item">
                            <form class="d-flex" action="/pages/products.php" method="GET">
                                <input class="form-control me-2" type="search" name="search" placeholder="Search products..." aria-label="Search">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?php echo '/pages/cart.php'; ?>">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <?php if ($cart_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $cart_count; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if ($current_user): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user"></i> <?php echo $current_user['name']; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="<?php echo '/pages/account.php'; ?>">My Account</a></li>
                                    <li><a class="dropdown-item" href="<?php echo '/pages/orders.php'; ?>">My Orders</a></li>
                                    <?php if (is_staff()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo '/admin/index.php'; ?>">Admin Panel</a></li>
                                    <?php endif; ?>
                                    <?php if (is_vendor()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo '/vendor/dashboard.php'; ?>">Vendor Dashboard</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo '/pages/logout.php'; ?>">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="loginDropdown" role="button" 
                                   data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="loginDropdown">
                                    <li><a class="dropdown-item" href="<?php echo '/pages/login.php'; ?>">Customer Login</a></li>
                                    <li><a class="dropdown-item" href="<?php echo '/vendor/login.php'; ?>">Vendor Login</a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo '/pages/register.php'; ?>">Register</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo '/pages/vendor_register.php'; ?>">Become a Vendor</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
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
