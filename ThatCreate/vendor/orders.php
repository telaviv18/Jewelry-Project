<?php
// Include necessary files
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a vendor
if (!is_logged_in() || get_user_role() != 5) {
    redirect('vendor/login.php');
}

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get vendor ID from session
$vendor_id = $_SESSION['vendor_id'];

// Initialize variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$total_orders = 0;

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    $new_status = $_POST['new_status'];
    $tracking_number = trim($_POST['tracking_number'] ?? '');
    $shipping_provider = trim($_POST['shipping_provider'] ?? '');
    
    // Validate input
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        $error_message = "Invalid status";
    } else {
        // Check if order item belongs to this vendor
        $stmt = $conn->prepare("
            SELECT id FROM vendor_order_items 
            WHERE id = :order_item_id AND vendor_id = :vendor_id
        ");
        $stmt->bindParam(':order_item_id', $order_item_id);
        $stmt->bindParam(':vendor_id', $vendor_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update order item status
                $stmt = $conn->prepare("
                    UPDATE vendor_order_items 
                    SET status = :status,
                        tracking_number = :tracking_number,
                        shipping_provider = :shipping_provider,
                        processed_at = CURRENT_TIMESTAMP
                    WHERE id = :order_item_id
                ");
                $stmt->bindParam(':status', $new_status);
                $stmt->bindParam(':tracking_number', $tracking_number);
                $stmt->bindParam(':shipping_provider', $shipping_provider);
                $stmt->bindParam(':order_item_id', $order_item_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Order status updated successfully";
            } catch (PDOException $e) {
                // Rollback transaction
                $conn->rollBack();
                $error_message = "Database error: " . $e->getMessage();
            }
        } else {
            $error_message = "You do not have permission to update this order";
        }
    }
}

// Build query based on filter and search
$query = "
    SELECT 
        voi.id, 
        voi.order_id,
        voi.status,
        voi.tracking_number,
        voi.shipping_provider,
        voi.vendor_amount,
        voi.commission_amount,
        voi.created_at,
        voi.processed_at,
        o.total_amount,
        o.shipping_address,
        o.shipping_city,
        o.shipping_state,
        o.shipping_zipcode,
        o.shipping_country,
        p.id as product_id,
        p.name as product_name,
        p.image as product_image,
        oi.quantity,
        oi.price,
        u.name as customer_name,
        u.email as customer_email
    FROM vendor_order_items voi
    JOIN orders o ON voi.order_id = o.id
    JOIN order_items oi ON voi.order_item_id = oi.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.user_id = u.id
    WHERE voi.vendor_id = :vendor_id
";

$params = [':vendor_id' => $vendor_id];

if (!empty($status_filter)) {
    $query .= " AND voi.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE :search OR o.id LIKE :search OR u.name LIKE :search OR voi.tracking_number LIKE :search)";
    $params[':search'] = "%$search%";
}

// Count total orders for pagination
$count_query = "SELECT COUNT(*) FROM ($query) as count_table";
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// Add sorting and pagination to the query
$query .= " ORDER BY voi.created_at DESC LIMIT :offset, :per_page";
$params[':offset'] = $offset;
$params[':per_page'] = $per_page;

// Execute the query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    if (in_array($key, [':offset', ':per_page'])) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$orders = $stmt->fetchAll();

// Get order status counts
$stmt = $conn->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM vendor_order_items
    WHERE vendor_id = :vendor_id
    GROUP BY status
");
$stmt->bindParam(':vendor_id', $vendor_id);
$stmt->execute();
$status_counts = [];
foreach ($stmt->fetchAll() as $row) {
    $status_counts[$row['status']] = $row['count'];
}
$total_count = array_sum($status_counts);

// Include header
$page_title = 'Manage Orders';
require_once 'includes/vendor_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/vendor_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Orders</h1>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Order Status Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title">All Orders</h5>
                            <h2 class="display-6"><?= $total_count; ?></h2>
                            <a href="orders.php" class="stretched-link"></a>
                        </div>
                        <div class="card-footer <?= empty($status_filter) ? 'bg-primary text-white' : ''; ?>">
                            View All
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title">Pending</h5>
                            <h2 class="display-6"><?= $status_counts['pending'] ?? 0; ?></h2>
                            <a href="orders.php?status=pending" class="stretched-link"></a>
                        </div>
                        <div class="card-footer <?= $status_filter === 'pending' ? 'bg-primary text-white' : ''; ?>">
                            View
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title">Processing</h5>
                            <h2 class="display-6"><?= $status_counts['processing'] ?? 0; ?></h2>
                            <a href="orders.php?status=processing" class="stretched-link"></a>
                        </div>
                        <div class="card-footer <?= $status_filter === 'processing' ? 'bg-primary text-white' : ''; ?>">
                            View
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title">Shipped</h5>
                            <h2 class="display-6"><?= $status_counts['shipped'] ?? 0; ?></h2>
                            <a href="orders.php?status=shipped" class="stretched-link"></a>
                        </div>
                        <div class="card-footer <?= $status_filter === 'shipped' ? 'bg-primary text-white' : ''; ?>">
                            View
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title">Delivered</h5>
                            <h2 class="display-6"><?= $status_counts['delivered'] ?? 0; ?></h2>
                            <a href="orders.php?status=delivered" class="stretched-link"></a>
                        </div>
                        <div class="card-footer <?= $status_filter === 'delivered' ? 'bg-primary text-white' : ''; ?>">
                            View
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h5 class="card-title">Cancelled</h5>
                            <h2 class="display-6"><?= $status_counts['cancelled'] ?? 0; ?></h2>
                            <a href="orders.php?status=cancelled" class="stretched-link"></a>
                        </div>
                        <div class="card-footer <?= $status_filter === 'cancelled' ? 'bg-primary text-white' : ''; ?>">
                            View
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <form action="orders.php" method="GET" class="d-flex">
                        <?php if (!empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        <input type="text" name="search" class="form-control me-2" placeholder="Search by order ID, customer name, product..." value="<?= htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-outline-primary">Search</button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <?php if (!empty($status_filter) || !empty($search)): ?>
                        <a href="orders.php" class="btn btn-outline-secondary">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Orders Table -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Product</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['order_id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= get_product_image_url($order['product_image']); ?>" class="img-thumbnail me-2" width="50" alt="<?= $order['product_name']; ?>">
                                                    <div>
                                                        <div><?= htmlspecialchars($order['product_name']); ?></div>
                                                        <small class="text-muted">Qty: <?= $order['quantity']; ?> × <?= format_currency($order['price']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($order['customer_name']); ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($order['customer_email']); ?></small>
                                            </td>
                                            <td>
                                                <?= format_currency($order['vendor_amount']); ?>
                                                <small class="text-muted d-block">Commission: <?= format_currency($order['commission_amount']); ?></small>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($order['created_at'])); ?></td>
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
                                                <?php if (!empty($order['tracking_number'])): ?>
                                                    <small class="d-block text-muted">
                                                        Tracking: <?= htmlspecialchars($order['tracking_number']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderModal<?= $order['id']; ?>">
                                                    Manage
                                                </button>
                                                
                                                <!-- Order Modal -->
                                                <div class="modal fade" id="orderModal<?= $order['id']; ?>" tabindex="-1" aria-labelledby="orderModalLabel<?= $order['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="orderModalLabel<?= $order['id']; ?>">Order #<?= $order['order_id']; ?> Details</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row mb-4">
                                                                    <div class="col-md-6">
                                                                        <h6>Product Information</h6>
                                                                        <div class="d-flex mt-2">
                                                                            <img src="<?= get_product_image_url($order['product_image']); ?>" class="img-thumbnail me-3" width="100" alt="<?= $order['product_name']; ?>">
                                                                            <div>
                                                                                <p class="mb-1"><strong><?= htmlspecialchars($order['product_name']); ?></strong></p>
                                                                                <p class="mb-1">Quantity: <?= $order['quantity']; ?></p>
                                                                                <p class="mb-1">Price: <?= format_currency($order['price']); ?></p>
                                                                                <p class="mb-0">Subtotal: <?= format_currency($order['price'] * $order['quantity']); ?></p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <h6>Shipping Address</h6>
                                                                        <address class="mt-2">
                                                                            <?= htmlspecialchars($order['customer_name']); ?><br>
                                                                            <?= nl2br(htmlspecialchars($order['shipping_address'])); ?><br>
                                                                            <?= htmlspecialchars($order['shipping_city']); ?>, 
                                                                            <?= htmlspecialchars($order['shipping_state']); ?> 
                                                                            <?= htmlspecialchars($order['shipping_zipcode']); ?><br>
                                                                            <?= htmlspecialchars($order['shipping_country']); ?><br>
                                                                            Email: <?= htmlspecialchars($order['customer_email']); ?>
                                                                        </address>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="row mb-4">
                                                                    <div class="col-md-6">
                                                                        <h6>Financial Details</h6>
                                                                        <table class="table table-sm mt-2">
                                                                            <tr>
                                                                                <td>Product Total:</td>
                                                                                <td class="text-end"><?= format_currency($order['price'] * $order['quantity']); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Commission (<?= number_format(($order['commission_amount'] / ($order['vendor_amount'] + $order['commission_amount'])) * 100, 2); ?>%):</td>
                                                                                <td class="text-end">-<?= format_currency($order['commission_amount']); ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <th>Your Earnings:</th>
                                                                                <th class="text-end"><?= format_currency($order['vendor_amount']); ?></th>
                                                                            </tr>
                                                                        </table>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <h6>Order Timeline</h6>
                                                                        <ul class="list-group mt-2">
                                                                            <li class="list-group-item">
                                                                                <i class="fas fa-shopping-cart me-2"></i> 
                                                                                Order Placed: <?= date('M d, Y, h:i A', strtotime($order['created_at'])); ?>
                                                                            </li>
                                                                            <?php if (!empty($order['processed_at'])): ?>
                                                                                <li class="list-group-item">
                                                                                    <i class="fas fa-clipboard-check me-2"></i> 
                                                                                    Last Updated: <?= date('M d, Y, h:i A', strtotime($order['processed_at'])); ?>
                                                                                </li>
                                                                            <?php endif; ?>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                                
                                                                <form action="<?= $_SERVER['PHP_SELF']; ?>" method="POST">
                                                                    <input type="hidden" name="order_item_id" value="<?= $order['id']; ?>">
                                                                    
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <div class="mb-3">
                                                                                <label for="new_status<?= $order['id']; ?>" class="form-label">Update Status</label>
                                                                                <select class="form-select" id="new_status<?= $order['id']; ?>" name="new_status" required>
                                                                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <div class="mb-3">
                                                                                <label for="tracking_number<?= $order['id']; ?>" class="form-label">Tracking Number</label>
                                                                                <input type="text" class="form-control" id="tracking_number<?= $order['id']; ?>" name="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? ''); ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label for="shipping_provider<?= $order['id']; ?>" class="form-label">Shipping Provider</label>
                                                                        <input type="text" class="form-control" id="shipping_provider<?= $order['id']; ?>" name="shipping_provider" value="<?= htmlspecialchars($order['shipping_provider'] ?? ''); ?>">
                                                                    </div>
                                                                    
                                                                    <div class="d-grid">
                                                                        <button type="submit" name="update_status" class="btn btn-primary">Update Order</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Order pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1; ?><?= !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?= $i; ?><?= !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?= $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1; ?><?= !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            No orders found. 
                            <?php if (!empty($status_filter) || !empty($search)): ?>
                                <a href="orders.php" class="alert-link">Clear filters</a> to see all orders.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
// Include footer
require_once 'includes/vendor_footer.php';
?>