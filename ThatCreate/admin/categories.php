<?php
// Admin categories management page
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
        if ($bulk_action === 'delete' && is_admin()) {
            // Check if categories have products
            $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
            $stmt = $conn->prepare("
                SELECT category_id, COUNT(*) as product_count 
                FROM products 
                WHERE category_id IN ($placeholders) 
                GROUP BY category_id
            ");
            $stmt->execute($selected_items);
            $categories_with_products = $stmt->fetchAll();
            
            if (!empty($categories_with_products)) {
                $_SESSION['error_message'] = 'Cannot delete categories with associated products. Please reassign products first.';
            } else {
                // Delete categories without products
                $stmt = $conn->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
                $stmt->execute($selected_items);
                $_SESSION['success_message'] = count($selected_items) . ' categories have been deleted.';
            }
        } else {
            $_SESSION['error_message'] = 'Invalid action or insufficient permissions.';
        }
        
        redirect('categories.php');
    } else {
        $_SESSION['error_message'] = 'No items selected.';
    }
}

// Get search query
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM categories $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $limit);

// Get categories with pagination
$sql = "
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    $where_clause
    GROUP BY c.id
    ORDER BY c.name ASC
    LIMIT ?, ?
";

$all_params = array_merge($params, [$offset, $limit]);
$stmt = $conn->prepare($sql);
$stmt->execute($all_params);
$categories = $stmt->fetchAll();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Categories Management</h1>
        <a href="category_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Category
        </a>
    </div>
    
    <!-- Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search categories..." value="<?= $search; ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Categories List -->
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
                                <th>Name</th>
                                <th>Description</th>
                                <th>Products</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No categories found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_items[]" class="form-check-input" 
                                                   value="<?= $category['id']; ?>">
                                        </td>
                                        <td><?= $category['name']; ?></td>
                                        <td><?= truncate_text($category['description'], 80); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $category['product_count']; ?> products</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="products.php?category=<?= $category['id']; ?>" class="btn btn-outline-info" title="View Products">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="category_edit.php?id=<?= $category['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (is_admin()): ?>
                                                    <a href="#" class="btn btn-outline-danger btn-delete" title="Delete"
                                                       onclick="if(confirm('Are you sure you want to delete this category? This action cannot be undone.')) window.location.href='category_delete.php?id=<?= $category['id']; ?>'">
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
                
                <?php if (!empty($categories) && is_admin()): ?>
                    <!-- Bulk Actions -->
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <select name="bulk_action" class="form-select">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
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
                <?= get_pagination($total_pages, $page, 'categories.php?search=' . urlencode($search)); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
