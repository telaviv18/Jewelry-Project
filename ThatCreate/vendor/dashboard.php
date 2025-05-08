<?php
// Start session
session_start();

// Include the database configuration file
require_once '../config/database.php';

// Dashboard for vendor - Shows stats, recent orders, and product info

// Connect to database
$database = new Database();
$conn = $database->connect();

// Check if vendor ID is set in the session
if (!isset($_SESSION['vendor_id'])) {
    die("Error: Vendor not logged in. Please log in to access the dashboard.");
}

// Ensure user_name is set in the session
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Unknown User'; // Default value if not set
}

$vendor_id = $_SESSION['vendor_id'];

// Get vendor information
$stmt = $conn->prepare("
    SELECT v.*, u.email, u.phone, u.last_login 
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    WHERE v.id = :vendor_id
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$vendor = $stmt->fetch();

// Get total products count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_products
    FROM products
    WHERE vendor_id = :vendor_id
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$product_stats = $stmt->fetch();

// Get orders statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
    FROM vendor_order_items
    WHERE vendor_id = :vendor_id
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$order_stats = $stmt->fetch();

// Get revenue statistics
$stmt = $conn->prepare("
    SELECT 
        SUM(vendor_amount) as total_revenue,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE) THEN vendor_amount ELSE 0 END) as monthly_revenue
    FROM vendor_order_items
    WHERE vendor_id = :vendor_id AND status != 'cancelled'
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$revenue_stats = $stmt->fetch();

// Get low stock products (less than 5 items)
$stmt = $conn->prepare("
    SELECT id, name, stock, sku, image
    FROM products
    WHERE vendor_id = :vendor_id AND stock < 5 AND stock > 0
    ORDER BY stock ASC
    LIMIT 5
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$low_stock_products = $stmt->fetchAll();

// Get recent orders
$stmt = $conn->prepare("
    SELECT 
        voi.id, 
        voi.status, 
        voi.created_at,
        o.id as order_id,
        p.name as product_name,
        p.image,
        oi.quantity,
        oi.price,
        voi.vendor_amount,
        u.name as customer_name
    FROM vendor_order_items voi
    JOIN orders o ON voi.order_id = o.id
    JOIN order_items oi ON voi.order_item_id = oi.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.user_id = u.id
    WHERE voi.vendor_id = :vendor_id
    ORDER BY voi.created_at DESC
    LIMIT 5
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Include header
$page_title = 'Vendor Dashboard';
require_once 'includes/vendor_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/vendor_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <span class="text-muted">Welcome, <?= htmlspecialchars($vendor['company_name']); ?></span>
            </div>
            
            <!-- Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Products</h5>
                            <h3 class="card-text"><?= $product_stats['total_products'] ?? 0; ?></h3>
                            <a href="products.php" class="text-white">View Details</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Total Revenue</h5>
                            <h3 class="card-text"><?= format_currency($revenue_stats['total_revenue'] ?? 0); ?></h3>
                            <span class="text-white">Monthly: <?= format_currency($revenue_stats['monthly_revenue'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Orders</h5>
                            <h3 class="card-text"><?= $order_stats['total_orders'] ?? 0; ?></h3>
                            <a href="orders.php" class="text-white">View Details</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Pending Orders</h5>
                            <h3 class="card-text"><?= $order_stats['pending_orders'] ?? 0; ?></h3>
                            <a href="orders.php?status=pending" class="text-white">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <!-- Low Stock Products -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Low Stock Products</h5>
                            <a href="products.php?filter=low_stock" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($low_stock_products) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Stock</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?= get_product_image_url($product['image']); ?>" class="img-thumbnail me-2" width="50" alt="<?= $product['name']; ?>">
                                                            <span><?= $product['name']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?= $product['sku']; ?></td>
                                                    <td>
                                                        <span class="badge bg-warning"><?= $product['stock']; ?> left</span>
                                                    </td>
                                                    <td>
                                                        <a href="product-edit.php?id=<?= $product['id']; ?>" class="btn btn-sm btn-outline-primary">Update</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No low stock products found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Orders</h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_orders) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Product</th>
                                                <th>Status</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <a href="order-detail.php?id=<?= $order['id']; ?>">#<?= $order['order_id']; ?></a>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?= get_product_image_url($order['image']); ?>" class="img-thumbnail me-2" width="50" alt="<?= $order['product_name']; ?>">
                                                            <span><?= $order['product_name']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $status_class = '';
                                                        switch ($order['status']) {
                                                            case 'pending': $status_class = 'bg-warning'; break;
                                                            case 'processing': $status_class = 'bg-info'; break;
                                                            case 'shipped': $status_class = 'bg-primary'; break;
                                                            case 'delivered': $status_class = 'bg-success'; break;
                                                            case 'cancelled': $status_class = 'bg-danger'; break;
                                                            default: $status_class = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $status_class; ?>"><?= ucfirst($order['status']); ?></span>
                                                    </td>
                                                    <td><?= format_currency($order['vendor_amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    No recent orders found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Account Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Company Name:</strong> <?= htmlspecialchars($vendor['company_name']); ?></p>
                                    <p><strong>Contact Name:</strong> <?= htmlspecialchars($_SESSION['user_name']); ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($vendor['email']); ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($vendor['phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Business Email:</strong> <?= htmlspecialchars($vendor['business_email']); ?></p>
                                    <p><strong>Business Phone:</strong> <?= htmlspecialchars($vendor['business_phone']); ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($vendor['business_address']); ?></p>
                                    <p><strong>Commission Rate:</strong> <?= $vendor['commission_rate']; ?>%</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="profile.php" class="btn btn-primary">Edit Profile</a>
                                <a href="change-password.php" class="btn btn-outline-secondary">Change Password</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>

<?php
// Include footer
require_once 'includes/vendor_footer.php';
?>