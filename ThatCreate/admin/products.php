<?php
// Admin products management page
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
            case 'delete':
                // Only admin can delete products
                if (is_admin()) {
                    $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                    $stmt = $conn->prepare("DELETE FROM products WHERE id IN ($placeholders)");
                    $stmt->execute($selected_items);
                    $_SESSION['success_message'] = count($selected_items) . ' products have been deleted.';
                } else {
                    $_SESSION['error_message'] = 'You do not have permission to delete products.';
                }
                break;
                
            case 'feature':
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE products SET is_featured = 1 WHERE id IN ($placeholders)");
                $stmt->execute($selected_items);
                $_SESSION['success_message'] = count($selected_items) . ' products have been set as featured.';
                break;
                
            case 'unfeature':
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $stmt = $conn->prepare("UPDATE products SET is_featured = 0 WHERE id IN ($placeholders)");
                $stmt->execute($selected_items);
                $_SESSION['success_message'] = count($selected_items) . ' products have been removed from featured.';
                break;
        }
        
        redirect('products.php');
    } else {
        $_SESSION['error_message'] = 'No items selected.';
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stock_filter = isset($_GET['stock']) ? clean_input($_GET['stock']) : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR description LIKE ? OR sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category > 0) {
    $conditions[] = "category_id = ?";
    $params[] = $category;
}

if ($stock_filter === 'low') {
    $conditions[] = "stock < 10";
} elseif ($stock_filter === 'out') {
    $conditions[] = "stock = 0";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM products $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $limit);

// Get products with pagination
$sql = "
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $where_clause
    ORDER BY p.id DESC
    LIMIT ?, ?
";

$all_params = array_merge($params, [$offset, $limit]);
$stmt = $conn->prepare($sql);
$stmt->execute($all_params);
$products = $stmt->fetchAll();

// Get all categories for filter
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Products Management</h1>
        <a href="product_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Product
        </a>
    </div>
    
    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?= $search; ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?= $cat['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="stock" class="form-select">
                        <option value="">All Stock Levels</option>
                        <option value="low" <?= $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock (< 10)</option>
                        <option value="out" <?= $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products List -->
    <div class="card">
        <div class="card-body">
            <form id="bulkActionForm" action="" method="POST">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Featured</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No products found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_items[]" class="form-check-input" 
                                                   value="<?= $product['id']; ?>">
                                        </td>
                                        <td>
                                            <img src="<?= get_product_image_url($product['image']); ?>" alt="<?= $product['name']; ?>" 
                                                 width="50" height="50" style="object-fit: cover;">
                                        </td>
                                        <td>
                                            <?= $product['name']; ?>
                                            <div class="small text-muted">SKU: <?= $product['sku']; ?></div>
                                        </td>
                                        <td><?= $product['category_name']; ?></td>
                                        <td><?= format_currency($product['price']); ?></td>
                                        <td>
                                            <?php if ($product['stock'] <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($product['stock'] < 10): ?>
                                                <span class="badge bg-warning"><?= $product['stock']; ?> left</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= $product['stock']; ?> in stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($product['is_featured']): ?>
                                                <span class="badge bg-primary">Featured</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="product_edit.php?id=<?= $product['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (is_admin()): ?>
                                                    <a href="#" class="btn btn-outline-danger btn-delete" 
                                                       onclick="if(confirm('Are you sure you want to delete this product?')) window.location.href='product_delete.php?id=<?= $product['id']; ?>'">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($products)): ?>
                    <!-- Bulk Actions -->
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <select name="bulk_action" class="form-select">
                                <option value="">Bulk Actions</option>
                                <?php if (is_admin()): ?>
                                    <option value="delete">Delete Selected</option>
                                <?php endif; ?>
                                <option value="feature">Mark as Featured</option>
                                <option value="unfeature">Remove from Featured</option>
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
                <?= get_pagination($total_pages, $page, 'products.php?search=' . urlencode($search) . '&category=' . $category . '&stock=' . $stock_filter); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
