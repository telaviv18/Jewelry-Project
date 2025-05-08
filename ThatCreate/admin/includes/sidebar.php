<?php
// Admin sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark admin-sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="px-3 py-4 text-white">
            <h5><i class="fas fa-gem me-2"></i> <?php echo SITE_NAME; ?></h5>
            <hr>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'index.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'products.php' || $current_page == 'product_add.php' || $current_page == 'product_edit.php') ? 'active' : ''; ?>" href="products.php">
                    <i class="fas fa-gem me-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'categories.php' || $current_page == 'category_add.php' || $current_page == 'category_edit.php') ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-folder me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'orders.php' || $current_page == 'order_detail.php') ? 'active' : ''; ?>" href="orders.php">
                    <i class="fas fa-shopping-bag me-2"></i> Orders
                </a>
            </li>
            <?php if (is_admin()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users me-2"></i> Users
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i> Settings
                </a>
            </li>
        </ul>
        
        <hr class="text-white-50">
        
        <div class="px-3 mb-3 text-white-50">
            <small>Logged in as: <?php echo get_role_name($current_user['role']); ?></small>
        </div>
    </div>
</nav>
