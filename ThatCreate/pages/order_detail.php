<?php
// Order detail page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login
require_login('login.php');

// Get current user
$current_user = get_logged_in_user();

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid order ID.';
    redirect('orders.php');
}

$order_id = (int)$_GET['id'];

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get order details (and check if it belongs to the current user)
$stmt = $conn->prepare("
    SELECT o.*, oa.address_line1, oa.address_line2, oa.city, oa.state, oa.postal_code, oa.country
    FROM orders o
    LEFT JOIN order_addresses oa ON o.id = oa.order_id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $current_user['id']]);
$order = $stmt->fetch();

// If order not found or doesn't belong to current user
if (!$order) {
    $_SESSION['error_message'] = 'Order not found or access denied.';
    redirect('orders.php');
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Page title
$page_title = 'Order #' . $order_id;

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="account.php">My Account</a></li>
            <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">Order #<?= $order_id; ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-0">Order #<?= $order_id; ?></h2>
                            <p class="text-muted mb-0">Placed on <?= format_date($order['created_at']); ?></p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-primary"><?= format_order_status($order['status']); ?></span>
                            <?php if ($order['status'] === ORDER_PENDING || $order['status'] === ORDER_PROCESSING): ?>
                                <a href="#" class="btn btn-sm btn-outline-danger ms-2">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <h5>Items</h5>
                    <?php foreach ($order_items as $item): ?>
                        <div class="row align-items-center mb-3">
                            <div class="col-md-2">
                                <img src="<?= get_product_image_url($item['image']); ?>" class="img-fluid" alt="<?= $item['name']; ?>">
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-0"><?= $item['name']; ?></h6>
                                <p class="text-muted mb-0">Quantity: <?= $item['quantity']; ?></p>
                                <p class="text-muted mb-0">Price: <?= format_currency($item['unit_price']); ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <strong><?= format_currency($item['subtotal']); ?></strong>
                            </div>
                        </div>
                        <hr>
                    <?php endforeach; ?>
                    
                    <div class="row mt-3">
                        <div class="col-md-8 text-end">
                            <p>Subtotal:</p>
                            <p>Shipping:</p>
                            <p>Tax:</p>
                            <p><strong>Total:</strong></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <p><?= format_currency($order['subtotal']); ?></p>
                            <p><?= format_currency($order['shipping_cost']); ?></p>
                            <p><?= format_currency($order['tax_amount']); ?></p>
                            <p><strong><?= format_currency($order['total_amount']); ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Order Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Information</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Order ID:</strong> #<?= $order_id; ?></p>
                    <p class="mb-1"><strong>Order Date:</strong> <?= format_date($order['created_at']); ?></p>
                    <p class="mb-1"><strong>Payment Method:</strong> <?= $order['payment_method']; ?></p>
                    <p class="mb-0"><strong>Status:</strong> <?= format_order_status($order['status']); ?></p>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Shipping Information</h5>
                </div>
                <div class="card-body">
                    <address>
                        <?= $order['address_line1']; ?><br>
                        <?= $order['address_line2'] ? $order['address_line2'] . '<br>' : ''; ?>
                        <?= $order['city']; ?>, <?= $order['state']; ?> <?= $order['postal_code']; ?><br>
                        <?= $order['country']; ?>
                    </address>
                    
                    <?php if ($order['tracking_number']): ?>
                        <p class="mb-0"><strong>Tracking Number:</strong><br>
                        <?= $order['tracking_number']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Return to Orders
                        </a>
                        <button class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i>Print Order
                        </button>
                        <button class="btn btn-outline-info">
                            <i class="fas fa-question-circle me-2"></i>Need Help?
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
