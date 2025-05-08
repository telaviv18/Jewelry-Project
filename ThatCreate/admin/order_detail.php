<?php
// Admin order detail page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require staff access
require_staff('../index.php');

// Connect to database
$database = new Database();
$conn = $database->connect();

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid order ID.';
    redirect('orders.php');
}

$order_id = (int)$_GET['id'];

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
           oa.address_line1, oa.address_line2, oa.city, oa.state, oa.postal_code, oa.country
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_addresses oa ON o.id = oa.order_id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// If order not found
if (!$order) {
    $_SESSION['error_message'] = 'Order not found.';
    redirect('orders.php');
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.sku, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Handle status updates
if (isset($_GET['action'])) {
    $action = clean_input($_GET['action']);
    $new_status = '';
    
    switch ($action) {
        case 'process':
            if ($order['status'] === ORDER_PENDING) {
                $new_status = ORDER_PROCESSING;
            }
            break;
        case 'ship':
            if ($order['status'] === ORDER_PROCESSING) {
                $new_status = ORDER_SHIPPED;
            }
            break;
        case 'deliver':
            if ($order['status'] === ORDER_SHIPPED) {
                $new_status = ORDER_DELIVERED;
            }
            break;
        case 'cancel':
            if (is_admin() || is_manager()) {
                if ($order['status'] !== ORDER_DELIVERED && $order['status'] !== ORDER_CANCELLED) {
                    $new_status = ORDER_CANCELLED;
                }
            }
            break;
    }
    
    if (!empty($new_status)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $result = $stmt->execute([$new_status, $order_id]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Order status updated successfully.';
            // Update the order variable
            $order['status'] = $new_status;
        } else {
            $_SESSION['error_message'] = 'Failed to update order status.';
        }
    }
}

// Process update tracking number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    $tracking_number = clean_input($_POST['tracking_number']);
    
    $stmt = $conn->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?");
    $result = $stmt->execute([$tracking_number, $order_id]);
    
    if ($result) {
        $_SESSION['success_message'] = 'Tracking number updated successfully.';
        // Update the order variable
        $order['tracking_number'] = $tracking_number;
    } else {
        $_SESSION['error_message'] = 'Failed to update tracking number.';
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Order #<?= $order_id; ?></h1>
        <a href="orders.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>
    
    <!-- Order Status -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title">Order Status</h5>
                    <h3><?= format_order_status($order['status']); ?></h3>
                </div>
                <div class="col-md-8 text-md-end">
                    <!-- Status Update Buttons -->
                    <?php if ($order['status'] === ORDER_PENDING): ?>
                        <a href="?id=<?= $order_id; ?>&action=process" class="btn btn-primary">
                            <i class="fas fa-check"></i> Mark as Processing
                        </a>
                    <?php elseif ($order['status'] === ORDER_PROCESSING): ?>
                        <a href="?id=<?= $order_id; ?>&action=ship" class="btn btn-primary">
                            <i class="fas fa-shipping-fast"></i> Mark as Shipped
                        </a>
                    <?php elseif ($order['status'] === ORDER_SHIPPED): ?>
                        <a href="?id=<?= $order_id; ?>&action=deliver" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Mark as Delivered
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] !== ORDER_DELIVERED && $order['status'] !== ORDER_CANCELLED && (is_admin() || is_manager())): ?>
                        <a href="?id=<?= $order_id; ?>&action=cancel" class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to cancel this order?')">
                            <i class="fas fa-times"></i> Cancel Order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Order Details -->
        <div class="col-lg-8">
            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="80">Image</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <img src="<?= get_product_image_url($item['image']); ?>" alt="<?= $item['product_name']; ?>" 
                                                 width="50" height="50" style="object-fit: cover;">
                                        </td>
                                        <td><?= $item['product_name']; ?></td>
                                        <td><?= $item['sku']; ?></td>
                                        <td><?= format_currency($item['unit_price']); ?></td>
                                        <td><?= $item['quantity']; ?></td>
                                        <td class="text-end"><?= format_currency($item['subtotal']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end"><?= format_currency($order['subtotal']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Shipping:</strong></td>
                                    <td class="text-end"><?= format_currency($order['shipping_cost']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                                    <td class="text-end"><?= format_currency($order['tax_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end"><strong><?= format_currency($order['total_amount']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Tracking Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tracking Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($order['status'] === ORDER_PROCESSING || $order['status'] === ORDER_SHIPPED): ?>
                        <form action="" method="POST">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <label for="tracking_number" class="col-form-label">Tracking Number:</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" id="tracking_number" name="tracking_number" class="form-control" 
                                           value="<?= $order['tracking_number']; ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" name="update_tracking" class="btn btn-primary">Update Tracking</button>
                                </div>
                            </div>
                        </form>
                    <?php elseif (!empty($order['tracking_number'])): ?>
                        <p class="mb-0"><strong>Tracking Number:</strong> <?= $order['tracking_number']; ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">No tracking information available.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Notes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Notes</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <textarea class="form-control" rows="3" readonly><?= $order['notes'] ?? 'No notes for this order.'; ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="col-lg-4">
            <!-- Order Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Information</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Order Date:</strong> <?= format_date($order['created_at']); ?></p>
                    <p class="mb-1"><strong>Payment Method:</strong> <?= ucfirst($order['payment_method']); ?></p>
                    <p class="mb-0"><strong>Order Status:</strong> <?= format_order_status($order['status']); ?></p>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Name:</strong> <?= $order['customer_name']; ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?= $order['customer_email']; ?></p>
                    <p class="mb-0"><strong>Phone:</strong> <?= $order['customer_phone'] ?: 'N/A'; ?></p>
                </div>
            </div>
            
            <!-- Shipping Address -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Shipping Address</h5>
                </div>
                <div class="card-body">
                    <address>
                        <?= $order['address_line1']; ?><br>
                        <?= $order['address_line2'] ? $order['address_line2'] . '<br>' : ''; ?>
                        <?= $order['city']; ?>, <?= $order['state']; ?> <?= $order['postal_code']; ?><br>
                        <?= $order['country']; ?>
                    </address>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Order
                        </button>
                        <a href="mailto:<?= $order['customer_email']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-2"></i>Email Customer
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
