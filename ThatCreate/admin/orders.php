<?php
// Admin orders management page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require staff access
require_staff('../index.php');

// Connect to database
$database = new Database();
$conn = $database->connect();

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = clean_input($_POST['bulk_action']);
    $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
    
    if (!empty($selected_items)) {
        switch ($bulk_action) {
            case 'process':
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders) AND status = ?");
                $params = array_merge([ORDER_PROCESSING], $selected_items, [ORDER_PENDING]);
                $stmt->execute($params);
                $_SESSION['success_message'] = count($selected_items) . ' orders have been marked as processing.';
                break;
                
            case 'ship':
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders) AND status = ?");
                $params = array_merge([ORDER_SHIPPED], $selected_items, [ORDER_PROCESSING]);
                $stmt->execute($params);
                $_SESSION['success_message'] = count($selected_items) . ' orders have been marked as shipped.';
                break;
                
            case 'deliver':
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders) AND status = ?");
                $params = array_merge([ORDER_DELIVERED], $selected_items, [ORDER_SHIPPED]);
                $stmt->execute($params);
                $_SESSION['success_message'] = count($selected_items) . ' orders have been marked as delivered.';
                break;
                
            case 'cancel':
                if (is_admin() || is_manager()) {
                    $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders) AND status != ?");
                    $params = array_merge([ORDER_CANCELLED], $selected_items, [ORDER_DELIVERED]);
                    $stmt->execute($params);
                    $_SESSION['success_message'] = count($selected_items) . ' orders have been cancelled.';
                } else {
                    $_SESSION['error_message'] = 'You do not have permission to cancel orders.';
                }
                break;
                
            default:
                $_SESSION['error_message'] = 'Invalid action.';
                break;
        }
        
        redirect('orders.php');
    } else {
        $_SESSION['error_message'] = 'No items selected.';
    }
}

// Handle filters
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($status)) {
    $conditions[] = "o.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $conditions[] = "(o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    $where_clause
";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $limit);

// Get orders with pagination
$sql = "
    SELECT o.*, u.name as customer_name, u.email as customer_email, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $where_clause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ?, ?
";

$all_params = array_merge($params, [$offset, $limit]);
$stmt = $conn->prepare($sql);
$stmt->execute($all_params);
$orders = $stmt->fetchAll();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Orders Management</h1>
        <div>
            <a href="reports.php?report=orders" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar"></i> Orders Report
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Order Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="<?= ORDER_PENDING ?>" <?= $status === ORDER_PENDING ? 'selected' : ''; ?>>Pending</option>
                        <option value="<?= ORDER_PROCESSING ?>" <?= $status === ORDER_PROCESSING ? 'selected' : ''; ?>>Processing</option>
                        <option value="<?= ORDER_SHIPPED ?>" <?= $status === ORDER_SHIPPED ? 'selected' : ''; ?>>Shipped</option>
                        <option value="<?= ORDER_DELIVERED ?>" <?= $status === ORDER_DELIVERED ? 'selected' : ''; ?>>Delivered</option>
                        <option value="<?= ORDER_CANCELLED ?>" <?= $status === ORDER_CANCELLED ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Order ID, customer name..." value="<?= $search; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Orders List -->
    <div class="card">
        <div class="card-body">
            <form id="bulkActionForm" action="" method="POST">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No orders found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_items[]" class="form-check-input" 
                                                   value="<?= $order['id']; ?>">
                                        </td>
                                        <td>#<?= $order['id']; ?></td>
                                        <td>
                                            <?= $order['customer_name']; ?>
                                            <div class="small text-muted"><?= $order['customer_email']; ?></div>
                                        </td>
                                        <td><?= $order['item_count']; ?></td>
                                        <td><?= format_currency($order['total_amount']); ?></td>
                                        <td><?= format_order_status($order['status']); ?></td>
                                        <td><?= format_date($order['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="order_detail.php?id=<?= $order['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($order['status'] === ORDER_PENDING): ?>
                                                        <li><a class="dropdown-item" href="order_detail.php?id=<?= $order['id']; ?>&action=process">Mark as Processing</a></li>
                                                    <?php elseif ($order['status'] === ORDER_PROCESSING): ?>
                                                        <li><a class="dropdown-item" href="order_detail.php?id=<?= $order['id']; ?>&action=ship">Mark as Shipped</a></li>
                                                    <?php elseif ($order['status'] === ORDER_SHIPPED): ?>
                                                        <li><a class="dropdown-item" href="order_detail.php?id=<?= $order['id']; ?>&action=deliver">Mark as Delivered</a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($order['status'] !== ORDER_DELIVERED && $order['status'] !== ORDER_CANCELLED && (is_admin() || is_manager())): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="order_detail.php?id=<?= $order['id']; ?>&action=cancel"
                                                              onclick="return confirm('Are you sure you want to cancel this order?')">Cancel Order</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($orders)): ?>
                    <!-- Bulk Actions -->
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <select name="bulk_action" class="form-select">
                                <option value="">Bulk Actions</option>
                                <option value="process">Mark as Processing</option>
                                <option value="ship">Mark as Shipped</option>
                                <option value="deliver">Mark as Delivered</option>
                                <?php if (is_admin() || is_manager()): ?>
                                    <option value="cancel">Cancel Orders</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary">Apply</button>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <?= get_pagination($total_pages, $page, 'orders.php?status=' . urlencode($status) . '&search=' . urlencode($search) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to)); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
