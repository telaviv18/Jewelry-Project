<?php
// Admin reports page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require staff access
require_staff('../index.php');

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get selected report type
$report_type = isset($_GET['report']) ? clean_input($_GET['report']) : 'sales';

// Get date filters
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : date('Y-m-d');

// Function to get sales data
function get_sales_data($conn, $date_from, $date_to) {
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, SUM(total_amount) as total
        FROM orders
        WHERE status != ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([ORDER_CANCELLED, $date_from, $date_to]);
    return $stmt->fetchAll();
}

// Function to get product sales data
function get_product_sales_data($conn, $date_from, $date_to) {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.sku, c.name as category_name, SUM(oi.quantity) as total_quantity, 
               SUM(oi.subtotal) as total_amount
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != ? AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY total_quantity DESC
    ");
    $stmt->execute([ORDER_CANCELLED, $date_from, $date_to]);
    return $stmt->fetchAll();
}

// Function to get category sales data
function get_category_sales_data($conn, $date_from, $date_to) {
    $stmt = $conn->prepare("
        SELECT c.id, c.name, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_amount
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != ? AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total_amount DESC
    ");
    $stmt->execute([ORDER_CANCELLED, $date_from, $date_to]);
    return $stmt->fetchAll();
}

// Function to get order status distribution
function get_order_status_data($conn, $date_from, $date_to) {
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll();
}

// Function to get customer orders data
function get_customer_orders_data($conn, $date_from, $date_to) {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.status != ? AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 20
    ");
    $stmt->execute([ORDER_CANCELLED, $date_from, $date_to]);
    return $stmt->fetchAll();
}

// Function to get inventory status
function get_inventory_status($conn) {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.sku, p.stock, p.price, c.name as category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        ORDER BY p.stock ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get data based on report type
switch ($report_type) {
    case 'sales':
        $data = get_sales_data($conn, $date_from, $date_to);
        break;
    case 'products':
        $data = get_product_sales_data($conn, $date_from, $date_to);
        break;
    case 'categories':
        $data = get_category_sales_data($conn, $date_from, $date_to);
        break;
    case 'orders':
        $data = get_order_status_data($conn, $date_from, $date_to);
        break;
    case 'customers':
        $data = get_customer_orders_data($conn, $date_from, $date_to);
        break;
    case 'inventory':
        $data = get_inventory_status($conn);
        break;
    default:
        $data = get_sales_data($conn, $date_from, $date_to);
        $report_type = 'sales';
        break;
}

// Calculate totals for summary (sales, orders, products sold)
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT o.id) as total_orders, 
           SUM(o.total_amount) as total_sales,
           SUM(oi.quantity) as total_products
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status != ? AND DATE(o.created_at) BETWEEN ? AND ?
");
$stmt->execute([ORDER_CANCELLED, $date_from, $date_to]);
$summary = $stmt->fetch();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Reports</h1>
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
    </div>
    
    <!-- Report Type Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="report" class="form-label">Report Type</label>
                    <select name="report" id="report" class="form-select" onchange="this.form.submit()">
                        <option value="sales" <?= $report_type === 'sales' ? 'selected' : ''; ?>>Sales Over Time</option>
                        <option value="products" <?= $report_type === 'products' ? 'selected' : ''; ?>>Product Sales</option>
                        <option value="categories" <?= $report_type === 'categories' ? 'selected' : ''; ?>>Category Sales</option>
                        <option value="orders" <?= $report_type === 'orders' ? 'selected' : ''; ?>>Order Status</option>
                        <option value="customers" <?= $report_type === 'customers' ? 'selected' : ''; ?>>Top Customers</option>
                        <option value="inventory" <?= $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory Status</option>
                    </select>
                </div>
                
                <?php if ($report_type !== 'inventory'): ?>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mb-3">Generate Report</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if ($report_type !== 'inventory'): ?>
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-number"><?= format_currency($summary['total_sales'] ?? 0); ?></div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-number"><?= $summary['total_orders'] ?? 0; ?></div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <div class="stat-label">Products Sold</div>
                            <div class="stat-number"><?= $summary['total_products'] ?? 0; ?></div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Report Content -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <?php 
                switch ($report_type) {
                    case 'sales':
                        echo 'Sales Report';
                        break;
                    case 'products':
                        echo 'Product Sales Report';
                        break;
                    case 'categories':
                        echo 'Category Sales Report';
                        break;
                    case 'orders':
                        echo 'Order Status Report';
                        break;
                    case 'customers':
                        echo 'Top Customers Report';
                        break;
                    case 'inventory':
                        echo 'Inventory Status Report';
                        break;
                }
                ?>
                <?php if ($report_type !== 'inventory'): ?>
                    <small class="text-muted ms-2"><?= format_date($date_from); ?> to <?= format_date($date_to); ?></small>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($data)): ?>
                <div class="alert alert-info">No data available for the selected criteria.</div>
            <?php else: ?>
                <?php if ($report_type === 'sales'): ?>
                    <!-- Sales Chart -->
                    <div class="mb-4" style="height: 300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                    
                    <!-- Sales Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Sales Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= format_date($row['date']); ?></td>
                                        <td class="text-end"><?= format_currency($row['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('salesChart').getContext('2d');
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: [<?= implode(', ', array_map(function($item) { return "'" . format_date($item['date']) . "'"; }, $data)); ?>],
                                    datasets: [{
                                        label: 'Sales Amount ($)',
                                        data: [<?= implode(', ', array_map(function($item) { return $item['total']; }, $data)); ?>],
                                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                                        borderColor: 'rgba(78, 115, 223, 1)',
                                        borderWidth: 2,
                                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                                        pointBorderColor: '#fff',
                                        pointRadius: 4,
                                        tension: 0.3
                                    }]
                                },
                                options: {
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                callback: function(value) {
                                                    return '$' + value;
                                                }
                                            }
                                        }
                                    },
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return 'Sales: $' + context.parsed.y;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php elseif ($report_type === 'products'): ?>
                    <!-- Product Sales Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover datatable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th class="text-end">Quantity Sold</th>
                                    <th class="text-end">Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= $row['name']; ?></td>
                                        <td><?= $row['sku']; ?></td>
                                        <td><?= $row['category_name']; ?></td>
                                        <td class="text-end"><?= $row['total_quantity']; ?></td>
                                        <td class="text-end"><?= format_currency($row['total_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($report_type === 'categories'): ?>
                    <!-- Categories Chart -->
                    <div class="row">
                        <div class="col-md-6">
                            <div style="height: 300px;">
                                <canvas id="categoriesChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Categories Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-end">Quantity Sold</th>
                                            <th class="text-end">Total Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $row): ?>
                                            <tr>
                                                <td><?= $row['name']; ?></td>
                                                <td class="text-end"><?= $row['total_quantity']; ?></td>
                                                <td class="text-end"><?= format_currency($row['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('categoriesChart').getContext('2d');
                            new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: [<?= implode(', ', array_map(function($item) { return "'" . $item['name'] . "'"; }, $data)); ?>],
                                    datasets: [{
                                        data: [<?= implode(', ', array_map(function($item) { return $item['total_amount']; }, $data)); ?>],
                                        backgroundColor: [
                                            'rgba(78, 115, 223, 0.8)',
                                            'rgba(28, 200, 138, 0.8)',
                                            'rgba(246, 194, 62, 0.8)',
                                            'rgba(231, 74, 59, 0.8)',
                                            'rgba(54, 185, 204, 0.8)',
                                            'rgba(133, 135, 150, 0.8)'
                                        ]
                                    }]
                                },
                                options: {
                                    maintainAspectRatio: false,
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    const label = context.label || '';
                                                    const value = context.parsed || 0;
                                                    return label + ': $' + value.toFixed(2);
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php elseif ($report_type === 'orders'): ?>
                    <!-- Order Status Chart -->
                    <div class="row">
                        <div class="col-md-6">
                            <div style="height: 300px;">
                                <canvas id="orderStatusChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Order Status Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th class="text-end">Count</th>
                                            <th class="text-end">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_orders = array_sum(array_column($data, 'count'));
                                        foreach ($data as $row): 
                                            $percentage = $total_orders > 0 ? round(($row['count'] / $total_orders) * 100, 2) : 0;
                                        ?>
                                            <tr>
                                                <td><?= format_order_status($row['status']); ?></td>
                                                <td class="text-end"><?= $row['count']; ?></td>
                                                <td class="text-end"><?= $percentage; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('orderStatusChart').getContext('2d');
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: [
                                        <?php 
                                        foreach ($data as $row) {
                                            echo "'" . ucfirst($row['status']) . "', ";
                                        }
                                        ?>
                                    ],
                                    datasets: [{
                                        data: [
                                            <?php 
                                            foreach ($data as $row) {
                                                echo $row['count'] . ", ";
                                            }
                                            ?>
                                        ],
                                        backgroundColor: [
                                            'rgba(255, 193, 7, 0.8)',  // Pending
                                            'rgba(13, 110, 253, 0.8)',  // Processing
                                            'rgba(23, 162, 184, 0.8)',  // Shipped
                                            'rgba(40, 167, 69, 0.8)',  // Delivered
                                            'rgba(220, 53, 69, 0.8)'   // Cancelled
                                        ]
                                    }]
                                },
                                options: {
                                    maintainAspectRatio: false,
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    const label = context.label || '';
                                                    const value = context.parsed || 0;
                                                    const dataset = context.dataset.data;
                                                    const total = dataset.reduce((acc, data) => acc + data, 0);
                                                    const percentage = Math.round((value / total) * 100);
                                                    return label + ': ' + context.parsed + ' (' + percentage + '%)';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php elseif ($report_type === 'customers'): ?>
                    <!-- Top Customers Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end">Total Spent</th>
                                    <th class="text-end">Average Order Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= $row['name']; ?></td>
                                        <td><?= $row['email']; ?></td>
                                        <td class="text-end"><?= $row['order_count']; ?></td>
                                        <td class="text-end"><?= format_currency($row['total_spent']); ?></td>
                                        <td class="text-end"><?= format_currency($row['total_spent'] / $row['order_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($report_type === 'inventory'): ?>
                    <!-- Inventory Status Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover datatable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Stock Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><?= $row['name']; ?></td>
                                        <td><?= $row['sku']; ?></td>
                                        <td><?= $row['category_name']; ?></td>
                                        <td class="text-end"><?= $row['stock']; ?></td>
                                        <td class="text-end"><?= format_currency($row['price']); ?></td>
                                        <td class="text-end"><?= format_currency($row['stock'] * $row['price']); ?></td>
                                        <td>
                                            <?php if ($row['stock'] <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($row['stock'] < 10): ?>
                                                <span class="badge bg-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
