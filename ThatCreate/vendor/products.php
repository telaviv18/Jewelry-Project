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
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$total_products = 0;

// Process product deletion
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    
    // Check if product belongs to this vendor
    $stmt = $conn->prepare("
        SELECT id FROM products 
        WHERE id = :product_id AND vendor_id = :vendor_id
    ");
    $stmt->bindParam(':product_id', $product_id);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Delete product
        $stmt = $conn->prepare("DELETE FROM products WHERE id = :product_id");
        $stmt->bindParam(':product_id', $product_id);
        
        if ($stmt->execute()) {
            $success_message = "Product deleted successfully";
        } else {
            $error_message = "Failed to delete product";
        }
    } else {
        $error_message = "You do not have permission to delete this product";
    }
}

// Build query based on filter and search
$query = "
    SELECT p.*, c.name as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.vendor_id = :vendor_id
";

$params = [':vendor_id' => $vendor_id];

if (!empty($search)) {
    $query .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter === 'low_stock') {
    $query .= " AND p.stock < 5 AND p.stock > 0";
} elseif ($filter === 'out_of_stock') {
    $query .= " AND p.stock = 0";
} elseif ($filter === 'active') {
    $query .= " AND p.status = 'active'";
} elseif ($filter === 'inactive') {
    $query .= " AND p.status = 'inactive'";
} elseif ($filter === 'pending') {
    $query .= " AND p.status = 'pending_approval'";
}

// Count total products for pagination
$stmt = $conn->prepare("SELECT COUNT(*) FROM ($query) as count_table");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_products = $stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Add sorting and pagination to the query
$query .= " ORDER BY p.created_at DESC LIMIT :offset, :per_page";
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
$products = $stmt->fetchAll();

// Include header
$page_title = 'Manage Products';
require_once 'includes/vendor_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/vendor_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Products</h1>
                <a href="add-product.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Add New Product
                </a>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Filter and Search -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="btn-group">
                        <a href="products.php" class="btn btn-outline-secondary <?= empty($filter) ? 'active' : ''; ?>">All</a>
                        <a href="products.php?filter=low_stock" class="btn btn-outline-secondary <?= $filter === 'low_stock' ? 'active' : ''; ?>">Low Stock</a>
                        <a href="products.php?filter=out_of_stock" class="btn btn-outline-secondary <?= $filter === 'out_of_stock' ? 'active' : ''; ?>">Out of Stock</a>
                        <a href="products.php?filter=active" class="btn btn-outline-secondary <?= $filter === 'active' ? 'active' : ''; ?>">Active</a>
                        <a href="products.php?filter=inactive" class="btn btn-outline-secondary <?= $filter === 'inactive' ? 'active' : ''; ?>">Inactive</a>
                        <a href="products.php?filter=pending" class="btn btn-outline-secondary <?= $filter === 'pending' ? 'active' : ''; ?>">Pending Approval</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <form action="products.php" method="GET" class="d-flex">
                        <?php if (!empty($filter)): ?>
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                        <?php endif; ?>
                        <input type="text" name="search" class="form-control me-2" placeholder="Search products..." value="<?= htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-outline-primary">Search</button>
                    </form>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (count($products) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <img src="<?= get_product_image_url($product['image']); ?>" class="img-thumbnail" width="50" alt="<?= $product['name']; ?>">
                                            </td>
                                            <td><?= htmlspecialchars($product['name']); ?></td>
                                            <td><?= htmlspecialchars($product['sku']); ?></td>
                                            <td><?= htmlspecialchars($product['category_name']); ?></td>
                                            <td><?= format_currency($product['price']); ?></td>
                                            <td>
                                                <?php if ($product['stock'] <= 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($product['stock'] < 5): ?>
                                                    <span class="badge bg-warning"><?= $product['stock']; ?> left</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?= $product['stock']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($product['status']) {
                                                    case 'active': $status_class = 'bg-success'; break;
                                                    case 'inactive': $status_class = 'bg-secondary'; break;
                                                    case 'pending_approval': $status_class = 'bg-warning'; break;
                                                    default: $status_class = 'bg-info';
                                                }
                                                ?>
                                                <span class="badge <?= $status_class; ?>"><?= ucfirst(str_replace('_', ' ', $product['status'])); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="../pages/product_detail.php?id=<?= $product['id']; ?>" class="btn btn-sm btn-outline-info" target="_blank" data-bs-toggle="tooltip" title="View"><i class="fas fa-eye"></i></a>
                                                    <a href="product-edit.php?id=<?= $product['id']; ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-edit"></i></a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $product['id']; ?>" data-bs-toggle="tooltip" title="Delete"><i class="fas fa-trash"></i></button>
                                                </div>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?= $product['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $product['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?= $product['id']; ?>">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete <strong><?= htmlspecialchars($product['name']); ?></strong>? This action cannot be undone.
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST">
                                                                    <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                                                                    <button type="submit" name="delete_product" class="btn btn-danger">Delete</button>
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
                            <nav aria-label="Product pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1; ?><?= !empty($filter) ? '&filter=' . $filter : ''; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?= $i; ?><?= !empty($filter) ? '&filter=' . $filter : ''; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?= $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1; ?><?= !empty($filter) ? '&filter=' . $filter : ''; ?><?= !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            No products found. <a href="add-product.php" class="alert-link">Add your first product</a>.
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