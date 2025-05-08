<?php
// User orders page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login
require_login('login.php');

// Get current user
$current_user = get_logged_in_user();

// Connect to database
$database = new Database();
$conn = $database->connect();

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total order count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $limit);

// Get orders with pagination
$stmt = $conn->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ?, ?
");
$stmt->execute([$current_user['id'], $offset, $limit]);
$orders = $stmt->fetchAll();

// Page title
$page_title = 'My Orders';

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Account Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="account-sidebar">
                <h4 class="mb-3">My Account</h4>
                <div class="list-group">
                    <a href="account.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-shopping-bag me-2"></i> Orders
                    </a>
                    <a href="cart.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart me-2"></i> Cart
                    </a>
                    <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Orders Content -->
        <div class="col-lg-9">
            <h2 class="mb-4">Order History</h2>
            
            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    You haven't placed any orders yet. <a href="../pages/products.php">Start shopping</a>
                </div>
            <?php else: ?>
                <!-- Orders List -->
                <?php foreach ($orders as $order): ?>
                    <div class="card order-card mb-3">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0">Order #<?= $order['id']; ?></h5>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <span class="text-muted">Placed on <?= format_date($order['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <p class="mb-1"><strong>Items:</strong></p>
                                    <p><?= $order['item_count']; ?> items</p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-1"><strong>Total:</strong></p>
                                    <p><?= format_currency($order['total_amount']); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-1"><strong>Status:</strong></p>
                                    <p><?= format_order_status($order['status']); ?></p>
                                </div>
                                <div class="col-md-3 text-md-end">
                                    <a href="order_detail.php?id=<?= $order['id']; ?>" class="btn btn-outline-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <?= get_pagination($total_pages, $page, 'orders.php'); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
