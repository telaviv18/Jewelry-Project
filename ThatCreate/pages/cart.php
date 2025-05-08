<?php
// Shopping cart page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require user to be logged in
require_login('login.php');

// Get current user
$current_user = get_logged_in_user();

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image, p.stock, 
           (c.quantity * p.price) as subtotal
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$current_user['id']]);
$cart_items = $stmt->fetchAll();

// Calculate cart totals
$cart_subtotal = 0;
$cart_total = 0;
$item_count = 0;

foreach ($cart_items as $item) {
    $cart_subtotal += $item['subtotal'];
    $item_count += $item['quantity'];
}

// Apply shipping cost
$shipping_cost = $cart_subtotal >= 50 ? 0 : 5.99;

// Calculate final total
$cart_total = $cart_subtotal + $shipping_cost;

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Shopping Cart</h1>
    
    <!-- Alerts container for AJAX responses -->
    <div class="alerts-container"></div>
    
    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            Your cart is empty. <a href="../pages/products.php">Continue shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Cart Items (<?= $item_count; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="cart-items-container">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item row align-items-center" data-cart-id="<?= $item['id']; ?>">
                                    <div class="col-md-2">
                                        <img src="<?= get_product_image_url($item['image']); ?>" class="img-fluid" alt="<?= $item['name']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <h5><a href="product_detail.php?id=<?= $item['product_id']; ?>"><?= $item['name']; ?></a></h5>
                                        <p class="text-muted mb-0">Price: <?= format_currency($item['price']); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="input-group input-group-sm">
                                            <button type="button" class="btn btn-outline-secondary quantity-minus">-</button>
                                            <input type="number" class="form-control cart-quantity-input" data-cart-id="<?= $item['id']; ?>" 
                                                   value="<?= $item['quantity']; ?>" min="1" max="<?= $item['stock']; ?>">
                                            <button type="button" class="btn btn-outline-secondary quantity-plus">+</button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <span class="cart-item-subtotal" data-cart-id="<?= $item['id']; ?>"><?= format_currency($item['subtotal']); ?></span>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-cart-item" data-cart-id="<?= $item['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="../pages/products.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                        <button type="button" class="btn btn-outline-secondary float-end" id="update-cart">
                            <i class="fas fa-sync-alt me-2"></i>Update Cart
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card cart-summary">
                    <div class="card-header">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?= format_currency($cart_subtotal); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span><?= $shipping_cost > 0 ? format_currency($shipping_cost) : 'Free'; ?></span>
                        </div>
                        <?php if ($shipping_cost > 0): ?>
                            <small class="text-muted">Free shipping on orders over $50</small>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total:</span>
                            <span class="fw-bold total cart-total"><?= format_currency($cart_total); ?></span>
                        </div>
                        <a href="checkout.php" class="btn btn-primary w-100">
                            <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                        </a>
                    </div>
                </div>
                
                <!-- Coupon code -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Coupon Code</h5>
                        <form>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" placeholder="Enter coupon code">
                                <button class="btn btn-outline-secondary" type="button">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Load cart.js for cart functionality -->
<script src="../assets/js/cart.js"></script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
