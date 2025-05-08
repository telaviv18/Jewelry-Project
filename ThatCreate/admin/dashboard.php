<?php
// Admin dashboard page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require staff access
require_staff('../index.php');

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get statistics
// Total products count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products");
$stmt->execute();
$total_products = $stmt->fetch()['count'];

// Total orders count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders");
$stmt->execute();
$total_orders = $stmt->fetch()['count'];

// Total revenue
$stmt = $conn->prepare("SELECT SUM(total_amount) as revenue FROM orders WHERE status != ?");
$stmt->execute([ORDER_CANCELLED]);
$total_revenue = $stmt->fetch()['revenue'] ?? 0;

// Total customers (users with customer role)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
$stmt->execute([ROLE_CUSTOMER]);
$total_customers = $stmt->fetch()['count'];

// Recent orders
$stmt = $conn->prepare("
    SELECT o.*, u.name as customer_name, COUNT(oi.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Low stock products
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock < 10
    ORDER BY p.stock ASC
    LIMIT 5
");
$stmt->execute();
$low_stock_products = $stmt->fetchAll();

// Top selling products
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.image, SUM(oi.quantity) as total_sold
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status != ?
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt->execute([ORDER_CANCELLED]);
$top_selling_products = $stmt->fetchAll();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Dashboard</h1>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-download"></i> Export Report
            </button>
            <span class="text-muted">Last updated: <?= date('M d, Y H:i'); ?></span>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="stat-card primary">
                <div class="row align-items-center">
                    <div class="col-8">
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-number"><?= format_currency($total_revenue); ?></div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="stat-card success">
                <div class="row align-items-center">
                    <div class="col-8">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-number"><?= $total_orders; ?></div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="stat-card warning">
                <div class="row align-items-center">
                    <div class="col-8">
                        <div class="stat-label">Products</div>
                        <div class="stat-number"><?= $total_products; ?></div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="stat-icon">
                            <i class="fas fa-gem"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="stat-card danger">
                <div class="row align-items-center">
                    <div class="col-8">
                        <div class="stat-label">Customers</div>
                        <div class="stat-number"><?= $total_customers; ?></div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Sales Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Category Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Orders -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Orders</h5>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-center text-muted">No orders found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><a href="order_detail.php?id=<?= $order['id']; ?>">#<?= $order['id']; ?></a></td>
                                            <td><?= $order['customer_name']; ?></td>
                                            <td><?= format_currency($order['total_amount']); ?></td>
                                            <td><?= format_order_status($order['status']); ?></td>
                                            <td><?= format_date($order['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Low Stock Products -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Low Stock Products</h5>
                    <a href="products.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($low_stock_products)): ?>
                        <p class="text-center text-muted">No low stock products found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <tr>
                                            <td><?= $product['name']; ?></td>
                                            <td><?= $product['category_name']; ?></td>
                                            <td><?= format_currency($product['price']); ?></td>
                                            <td>
                                                <span class="badge bg-<?= $product['stock'] <= 5 ? 'danger' : 'warning'; ?>">
                                                    <?= $product['stock']; ?> left
                                                </span>
                                            </td>
                                            <td>
                                                <a href="product_edit.php?id=<?= $product['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    Update
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Selling Products -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Top Selling Products</h5>
                    <a href="reports.php" class="btn btn-sm btn-outline-primary">View All Reports</a>
                </div>
                <div class="card-body">
                    <?php if (empty($top_selling_products)): ?>
                        <p class="text-center text-muted">No sales data available.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($top_selling_products as $product): ?>
                                <div class="col-md-4 col-lg-2 mb-3">
                                    <div class="card h-100 text-center">
                                        <img src="<?= get_product_image_url($product['image']); ?>" class="card-img-top p-2" alt="<?= $product['name']; ?>" style="height: 120px; object-fit: contain;">
                                        <div class="card-body p-3">
                                            <h6 class="card-title"><?= $product['name']; ?></h6>
                                            <p class="card-text text-primary fw-bold"><?= format_currency($product['price']); ?></p>
                                            <p class="card-text text-muted"><small>Sold: <?= $product['total_sold']; ?> units</small></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
