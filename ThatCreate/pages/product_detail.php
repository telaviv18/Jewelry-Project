<?php
// Product detail page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Connect to database
$database = new Database();
$conn = $database->connect();

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid product ID.';
    redirect('../index.php');
}

$product_id = (int)$_GET['id'];

// Get product details
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

// If product not found or out of stock
if (!$product || $product['stock'] <= 0) {
    $_SESSION['error_message'] = 'Product not found or currently unavailable.';
    redirect('../index.php');
}

// Get related products
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND p.stock > 0
    ORDER BY RAND()
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php">Shop</a></li>
            <li class="breadcrumb-item"><a href="products.php?category=<?= $product['category_id']; ?>"><?= $product['category_name']; ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $product['name']; ?></li>
        </ol>
    </nav>

    <!-- Alerts container for AJAX responses -->
    <div class="alerts-container"></div>

    <!-- Product Details -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="product-image-container">
                <img src="<?= get_product_image_url($product['image']); ?>" class="img-fluid product-main-image" alt="<?= $product['name']; ?>">
            </div>
        </div>

        <div class="col-md-6 product-details">
            <h1><?= $product['name']; ?></h1>
            <p class="product-price"><?= format_currency($product['price']); ?></p>
            
            <div class="product-meta mb-3">
                <span class="badge bg-info me-2"><?= $product['category_name']; ?></span>
                <?php if ($product['stock'] < 10): ?>
                    <span class="badge bg-warning me-2">Only <?= $product['stock']; ?> left</span>
                <?php else: ?>
                    <span class="badge bg-success me-2">In Stock</span>
                <?php endif; ?>
                <?php if ($product['is_featured']): ?>
                    <span class="badge bg-danger">Featured</span>
                <?php endif; ?>
            </div>
            
            <div class="product-description">
                <?= $product['description']; ?>
            </div>
            
            <!-- Add to Cart Form -->
            <form id="add-to-cart-form" action="../api/cart.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                
                <div class="quantity-selector">
                    <label for="quantity">Quantity:</label>
                    <button type="button" class="btn btn-outline-secondary quantity-minus">-</button>
                    <input type="number" name="quantity" class="form-control quantity-input" value="1" min="1" max="<?= $product['stock']; ?>" required>
                    <button type="button" class="btn btn-outline-secondary quantity-plus">+</button>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" id="add-to-cart-button" class="btn btn-primary">
                        <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                    </button>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="fas fa-heart me-1"></i> Add to Wishlist
                    </button>
                </div>
            </form>
            
            <!-- Additional Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="productInfo" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab" aria-controls="shipping" aria-selected="false">Shipping</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="returns-tab" data-bs-toggle="tab" data-bs-target="#returns" type="button" role="tab" aria-controls="returns" aria-selected="false">Returns</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="productInfoContent">
                        <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                            <p><strong>SKU:</strong> <?= $product['sku']; ?></p>
                            <p><strong>Material:</strong> <?= $product['material'] ?? 'Premium quality materials'; ?></p>
                            <p><strong>Dimensions:</strong> <?= $product['dimensions'] ?? 'Various sizes available'; ?></p>
                            <p><strong>Weight:</strong> <?= $product['weight'] ?? 'Lightweight and comfortable to wear'; ?></p>
                        </div>
                        <div class="tab-pane fade" id="shipping" role="tabpanel" aria-labelledby="shipping-tab">
                            <p>Free shipping on all orders over $50.</p>
                            <p>Standard delivery: 3-5 business days.</p>
                            <p>Express delivery: 1-2 business days (additional fee).</p>
                            <p>International shipping available to select countries.</p>
                        </div>
                        <div class="tab-pane fade" id="returns" role="tabpanel" aria-labelledby="returns-tab">
                            <p>30-day return policy for unworn items in original condition.</p>
                            <p>Please contact our customer service team to initiate a return.</p>
                            <p>Customized items are non-returnable.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
    <section class="mt-5">
        <h3 class="mb-4">You May Also Like</h3>
        <div class="row">
            <?php foreach ($related_products as $related): ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card h-100">
                        <img src="<?= get_product_image_url($related['image']); ?>" class="card-img-top" alt="<?= $related['name']; ?>">
                        <div class="card-body">
                            <p class="product-category"><?= $related['category_name']; ?></p>
                            <h5 class="card-title"><?= $related['name']; ?></h5>
                            <p class="product-price"><?= format_currency($related['price']); ?></p>
                            <a href="product_detail.php?id=<?= $related['id']; ?>" class="btn btn-outline-primary">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Load cart.js for add to cart functionality -->
<script src="../assets/js/cart.js"></script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
