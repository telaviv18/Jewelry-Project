<?php
// Products listing page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get category filter
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get search query
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Get sorting preference
$sort = isset($_GET['sort']) ? clean_input($_GET['sort']) : 'newest';

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query based on filters
$sql_conditions = [];
$params = [];

if ($category_id > 0) {
    $sql_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $sql_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Only show products with stock
$sql_conditions[] = "p.stock > 0";

$where_clause = !empty($sql_conditions) ? "WHERE " . implode(" AND ", $sql_conditions) : "";

// Determine sorting
$order_by = "ORDER BY ";
switch ($sort) {
    case 'price_low':
        $order_by .= "p.price ASC";
        break;
    case 'price_high':
        $order_by .= "p.price DESC";
        break;
    case 'name_asc':
        $order_by .= "p.name ASC";
        break;
    case 'name_desc':
        $order_by .= "p.name DESC";
        break;
    case 'newest':
    default:
        $order_by .= "p.created_at DESC";
        break;
}

// Count total products matching the filter
$count_sql = "
    SELECT COUNT(*) as total 
    FROM products p
    $where_clause
";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $limit);

// Get products with pagination
$product_sql = "
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $where_clause
    $order_by
    LIMIT :offset, :limit
";

// Clone the params array for the second query
$product_params = $params;
$stmt = $conn->prepare($product_sql);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
foreach ($product_params as $index => $param) {
    $stmt->bindValue($index + 1, $param);
}
$stmt->execute();
$products = $stmt->fetchAll();

// Get all categories for filter sidebar
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get category name if filter is applied
$category_name = '';
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    $category_name = $result ? $result['name'] : '';
}

// Set page title
$page_title = !empty($category_name) ? $category_name : 'All Products';
if (!empty($search)) {
    $page_title = 'Search Results for "' . $search . '"';
}

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar with filters -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <!-- Categories Filter -->
                    <h6>Categories</h6>
                    <div class="list-group mb-4">
                        <a href="products.php" class="list-group-item list-group-item-action <?= ($category_id == 0) ? 'active' : ''; ?>">
                            All Categories
                        </a>
                        <?php foreach ($categories as $category): ?>
                        <a href="products.php?category=<?= $category['id']; ?>" 
                           class="list-group-item list-group-item-action <?= ($category_id == $category['id']) ? 'active' : ''; ?>">
                            <?= $category['name']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sort By Filter -->
                    <h6>Sort By</h6>
                    <form action="products.php" method="GET">
                        <?php if ($category_id > 0): ?>
                        <input type="hidden" name="category" value="<?= $category_id; ?>">
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?= $search; ?>">
                        <?php endif; ?>
                        <select name="sort" class="form-select mb-3" onchange="this.form.submit()">
                            <option value="newest" <?= ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?= ($sort == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?= ($sort == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name_asc" <?= ($sort == 'name_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                            <option value="name_desc" <?= ($sort == 'name_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                        </select>
                    </form>

                    <!-- Price Range Filter -->
                    <h6>Price Range</h6>
                    <form action="products.php" method="GET" class="mb-3">
                        <?php if ($category_id > 0): ?>
                        <input type="hidden" name="category" value="<?= $category_id; ?>">
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?= $search; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="sort" value="<?= $sort; ?>">
                        
                        <div class="input-group mb-2">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="min_price" placeholder="Min" min="0" 
                                   value="<?= isset($_GET['min_price']) ? $_GET['min_price'] : ''; ?>">
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="max_price" placeholder="Max" min="0" 
                                   value="<?= isset($_GET['max_price']) ? $_GET['max_price'] : ''; ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                    </form>

                    <?php if (!empty($search) || $category_id > 0 || isset($_GET['min_price']) || isset($_GET['max_price'])): ?>
                    <a href="products.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times-circle"></i> Clear All Filters
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?= $page_title; ?></h2>
                <span><?= $total_count; ?> products found</span>
            </div>

            <?php if (empty($products)): ?>
                <div class="alert alert-info">
                    <?php if (!empty($search)): ?>
                        No products found matching "<?= $search; ?>". Try different search terms or browse our categories.
                    <?php else: ?>
                        No products available in this category at the moment. Please check back later or browse other categories.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card product-card h-100">
                                <img src="<?= get_product_image_url($product['image']); ?>" class="card-img-top" alt="<?= $product['name']; ?>">
                                <div class="card-body">
                                    <p class="product-category"><?= $product['category_name']; ?></p>
                                    <h5 class="card-title"><?= $product['name']; ?></h5>
                                    <p class="product-price"><?= format_currency($product['price']); ?></p>
                                    <a href="product_detail.php?id=<?= $product['id']; ?>" class="btn btn-outline-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <?= get_pagination($total_pages, $page, 'products.php?category=' . $category_id . '&search=' . urlencode($search) . '&sort=' . $sort); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
