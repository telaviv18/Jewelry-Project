<?php
// Include header
require_once 'header.php';

// Get vendor ID
$vendor_id = $vendor['id'];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update stock level
    if (isset($_POST['action']) && $_POST['action'] === 'update_stock' && isset($_POST['product_id']) && isset($_POST['stock'])) {
        $product_id = (int)$_POST['product_id'];
        $stock = (int)$_POST['stock'];
        
        // Validate input
        if ($stock < 0) {
            $_SESSION['error_message'] = 'Stock cannot be negative.';
        } else {
            try {
                // Verify this product belongs to the vendor
                $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $vendor_id]);
                
                if ($stmt->rowCount() === 0) {
                    $_SESSION['error_message'] = 'You do not have permission to update this product.';
                } else {
                    // Update stock
                    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND vendor_id = ?");
                    $result = $stmt->execute([$stock, $product_id, $vendor_id]);
                    
                    if ($result) {
                        $_SESSION['success_message'] = 'Stock updated successfully.';
                    } else {
                        $_SESSION['error_message'] = 'Failed to update stock. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
            }
        }
        
        redirect('inventory.php');
    }
    
    // Bulk update stock levels
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_update' && isset($_POST['product_ids']) && isset($_POST['new_stocks'])) {
        $product_ids = $_POST['product_ids'];
        $new_stocks = $_POST['new_stocks'];
        
        if (count($product_ids) !== count($new_stocks)) {
            $_SESSION['error_message'] = 'Invalid data format for bulk update.';
        } else {
            try {
                $success_count = 0;
                $conn->beginTransaction();
                
                for ($i = 0; $i < count($product_ids); $i++) {
                    $product_id = (int)$product_ids[$i];
                    $stock = (int)$new_stocks[$i];
                    
                    if ($stock < 0) continue;
                    
                    // Verify this product belongs to the vendor
                    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
                    $stmt->execute([$product_id, $vendor_id]);
                    
                    if ($stmt->rowCount() === 0) continue;
                    
                    // Update stock
                    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND vendor_id = ?");
                    $result = $stmt->execute([$stock, $product_id, $vendor_id]);
                    
                    if ($result) {
                        $success_count++;
                    }
                }
                
                $conn->commit();
                $_SESSION['success_message'] = "Stock updated for $success_count products.";
            } catch (PDOException $e) {
                $conn->rollBack();
                $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
            }
        }
        
        redirect('inventory.php');
    }
}

// Get filter from query string
$filter = isset($_GET['filter']) ? clean_input($_GET['filter']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query based on filters
$query = "
    SELECT p.*, c.name as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.vendor_id = :vendor_id
";

$params = [':vendor_id' => $vendor_id];

if ($filter === 'low-stock') {
    $query .= " AND p.stock <= 5";
} elseif ($filter === 'out-of-stock') {
    $query .= " AND p.stock = 0";
} elseif ($filter === 'in-stock') {
    $query .= " AND p.stock > 0";
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($category_filter > 0) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

$query .= " ORDER BY p.stock ASC, p.name ASC";

// Get total count for pagination
$count_stmt = $conn->prepare(str_replace('p.*, c.name as category_name', 'COUNT(*) as total', $query));
$count_stmt->execute($params);
$total_rows = $count_stmt->fetch()['total'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = ITEMS_PER_PAGE;
$total_pages = ceil($total_rows / $items_per_page);
$offset = ($page - 1) * $items_per_page;

// Add pagination to query
$query .= " LIMIT $offset, $items_per_page";

// Get products
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get inventory stats
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock BETWEEN 1 AND 5 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock > 5 THEN 1 ELSE 0 END) as in_stock
    FROM products
    WHERE vendor_id = ?
");
$stmt->execute([$vendor_id]);
$inventory_stats = $stmt->fetch();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Inventory Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="products.php?action=add" class="btn btn-sm btn-outline-primary me-2">
                <i class="fas fa-plus"></i> Add New Product
            </a>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                <i class="fas fa-edit"></i> Bulk Update
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2 border-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Products</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $inventory_stats['total_products'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2 border-success">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                In Stock</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $inventory_stats['in_stock'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2 border-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Low Stock</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $inventory_stats['low_stock'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2 border-danger">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Out of Stock</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $inventory_stats['out_of_stock'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Inventory</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by name or SKU" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $category_filter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter" class="form-label">Stock Filter</label>
                    <select class="form-select" id="filter" name="filter">
                        <option value="">All Products</option>
                        <option value="low-stock" <?= $filter === 'low-stock' ? 'selected' : '' ?>>Low Stock (≤ 5)</option>
                        <option value="out-of-stock" <?= $filter === 'out-of-stock' ? 'selected' : '' ?>>Out of Stock</option>
                        <option value="in-stock" <?= $filter === 'in-stock' ? 'selected' : '' ?>>In Stock (> 0)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Inventory List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Products (<?= $total_rows ?>)</h6>
        </div>
        <div class="card-body">
            <?php if (count($products) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="inventoryTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th width="80">Image</th>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th width="150">Current Stock</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <img src="<?= get_product_image_url($product['image'] ? '../' . $product['image'] : '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-thumbnail" width="60">
                                    </td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['sku']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><?= format_currency($product['price']) ?></td>
                                    <td>
                                        <form id="stock-form-<?= $product['id'] ?>" method="POST" class="d-flex">
                                            <input type="hidden" name="action" value="update_stock">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <input type="number" name="stock" class="form-control form-control-sm" value="<?= $product['stock'] ?>" min="0">
                                            <button type="submit" class="btn btn-sm btn-primary ms-1">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="products.php?action=edit&id=<?= $product['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-4">
                        <?= get_pagination($total_pages, $page, 'inventory.php?' . http_build_query(array_filter([
                            'search' => $search,
                            'category' => $category_filter ?: null,
                            'filter' => $filter ?: null
                        ])) . '&') ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-center">No products found with the current filters.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUpdateModalLabel">Bulk Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bulkUpdateForm" method="POST">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <div class="mb-3">
                        <label class="form-label">Select products to update:</label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="bulkInventoryTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                            </div>
                                        </th>
                                        <th>Name</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th width="150">Current Stock</th>
                                        <th width="150">New Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all products for bulk update (limited to 100 for performance)
                                    $stmt = $conn->prepare("
                                        SELECT p.*, c.name as category_name
                                        FROM products p
                                        JOIN categories c ON p.category_id = c.id
                                        WHERE p.vendor_id = ?
                                        ORDER BY p.name ASC
                                        LIMIT 100
                                    ");
                                    $stmt->execute([$vendor_id]);
                                    $all_products = $stmt->fetchAll();
                                    
                                    foreach ($all_products as $product):
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input product-checkbox" type="checkbox" name="product_ids[]" value="<?= $product['id'] ?>">
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($product['name']) ?></td>
                                            <td><?= htmlspecialchars($product['sku']) ?></td>
                                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                                            <td><?= $product['stock'] ?></td>
                                            <td>
                                                <input type="number" name="new_stocks[]" class="form-control form-control-sm new-stock" value="<?= $product['stock'] ?>" min="0" disabled>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Enable/disable new stock input fields based on checkbox selection
    document.querySelectorAll('.product-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            const stockInput = row.querySelector('.new-stock');
            stockInput.disabled = !this.checked;
        });
    });
    
    // Toggle all checkboxes
    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
            const row = checkbox.closest('tr');
            const stockInput = row.querySelector('.new-stock');
            stockInput.disabled = !checked;
        });
    }
</script>

<?php
// Include footer
require_once 'footer.php';
?>