<?php
// Main entry point for the website
require_once __DIR__ . '/config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Connect to database
$database = new Database();
$conn = $database->connect();

if (!$conn) {
    die("Error: Unable to establish a database connection.");
} else {
    echo "Database connection established successfully.<br>";
}

// Check if tables already exist
$tableCheckStmt = $conn->query("SHOW TABLES LIKE 'categories'");
if ($tableCheckStmt->rowCount() === 0) {
    // Execute SQL file to initialize the database
    $sqlFilePath = __DIR__ . '/sql/schema.sql';
    if (file_exists($sqlFilePath)) {
        try {
            $sql = file_get_contents($sqlFilePath);
            $conn->exec($sql);

            // Add SQL to create the user_addresses table if it doesn't exist
            $conn->exec("
                CREATE TABLE IF NOT EXISTS user_addresses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    address_line1 VARCHAR(255) NOT NULL,
                    address_line2 VARCHAR(255),
                    city VARCHAR(100) NOT NULL,
                    state VARCHAR(100) NOT NULL,
                    postal_code VARCHAR(20) NOT NULL,
                    country VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
            ");

            // Debug output to confirm SQL file execution
            echo "SQL file executed successfully.";
        } catch (PDOException $e) {
            die("Error executing SQL file: " . $e->getMessage());
        }
    } else {
        echo "SQL file not found at: " . $sqlFilePath;
    }
} else {
    echo "Database tables already exist. Skipping SQL file execution.<br>";
}

// Get featured products
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_featured = TRUE AND p.stock > 0
    ORDER BY p.id DESC
    LIMIT 8
");
$stmt->execute();
$featured_products = $stmt->fetchAll();

// Get latest products
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock > 0
    ORDER BY p.created_at DESC
    LIMIT 8
");
$stmt->execute();
$latest_products = $stmt->fetchAll();

// Get popular categories
$stmt = $conn->prepare("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY product_count DESC
    LIMIT 4
");
$stmt->execute();
$popular_categories = $stmt->fetchAll();

// Include header
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container text-center">
        <h1>Exquisite Jewelry Collection</h1>
        <p>Discover our stunning selection of hand-crafted jewelry pieces that radiate elegance and sophistication.</p>
        <a href="pages/products.php" class="btn btn-primary btn-lg">Shop Now</a>
    </div>
</section>

<!-- Featured Products -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">Featured Products</h2>
        <div class="row">
            <?php if (count($featured_products) > 0): ?>
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card product-card h-100">
                            <img src="<?= get_product_image_url($product['image']); ?>" class="card-img-top" alt="<?= $product['name']; ?>">
                            <div class="card-body">
                                <p class="product-category"><?= $product['category_name']; ?></p>
                                <h5 class="card-title"><?= $product['name']; ?></h5>
                                <p class="product-price"><?= format_currency($product['price']); ?></p>
                                <a href="pages/product_detail.php?id=<?= $product['id']; ?>" class="btn btn-outline-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No featured products available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Categories Banner -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-4">Shop by Category</h2>
        <div class="row">
            <?php if (count($popular_categories) > 0): ?>
                <?php foreach ($popular_categories as $category): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-gem fa-3x mb-3"></i>
                                <h5 class="card-title"><?= $category['name']; ?></h5>
                                <p class="card-text"><?= $category['product_count']; ?> Products</p>
                                <a href="pages/products.php?category=<?= $category['id']; ?>" class="btn btn-outline-primary">Browse</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No categories available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Latest Products -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">Latest Arrivals</h2>
        <div class="row">
            <?php if (count($latest_products) > 0): ?>
                <?php foreach ($latest_products as $product): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card product-card h-100">
                            <img src="<?= get_product_image_url($product['image']); ?>" class="card-img-top" alt="<?= $product['name']; ?>">
                            <div class="card-body">
                                <p class="product-category"><?= $product['category_name']; ?></p>
                                <h5 class="card-title"><?= $product['name']; ?></h5>
                                <p class="product-price"><?= format_currency($product['price']); ?></p>
                                <a href="pages/product_detail.php?id=<?= $product['id']; ?>" class="btn btn-outline-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No products available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="pages/products.php" class="btn btn-primary">View All Products</a>
        </div>
    </div>
</section>

<!-- About Us Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2>About Our Jewelry Store</h2>
                <p class="lead">Crafting exquisite pieces since 2005</p>
                <p>We are passionate about creating beautiful jewelry that celebrates life's precious moments. Our master craftsmen select only the finest materials to create pieces that are both stunning and timeless.</p>
                <p>Each creation tells a unique story and is designed to be treasured for generations. We pride ourselves on exceptional quality and attention to detail.</p>
                <a href="#" class="btn btn-outline-primary">Learn More</a>
            </div>
            <div class="col-md-6">
                <div class="text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="350" height="350" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-award">
                        <circle cx="12" cy="8" r="7"></circle>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">What Our Customers Say</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"The engagement ring I purchased exceeded all my expectations. The craftsmanship is impeccable, and my fiancée absolutely loves it!"</p>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <p class="mb-0 fw-bold">Michael K.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="card-text">"I've been a loyal customer for years, and the quality and service are consistently outstanding. Their personalized recommendations are always spot on!"</p>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <p class="mb-0 fw-bold">Sarah L.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="card-text">"The necklace I received as a gift was stunning, and the compliments keep coming! The online ordering process was smooth, and delivery was quick."</p>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <p class="mb-0 fw-bold">Emma R.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter -->
<section class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h3>Subscribe to Our Newsletter</h3>
                <p>Stay updated with our latest collections, exclusive offers, and jewelry care tips.</p>
                <form class="row g-3 justify-content-center">
                    <div class="col-md-7">
                        <input type="email" class="form-control" placeholder="Your email address" required>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-light">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
require_once 'includes/footer.php';
