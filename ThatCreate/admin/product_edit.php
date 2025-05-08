<?php
// Admin edit product page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require staff access
require_staff('../index.php');

// Connect to database
$database = new Database();
$conn = $database->connect();

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid product ID.';
    redirect('products.php');
}

$product_id = (int)$_GET['id'];

// Get product details
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

// If product not found
if (!$product) {
    $_SESSION['error_message'] = 'Product not found.';
    redirect('products.php');
}

// Get all categories
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $name = clean_input($_POST['name']);
    $sku = clean_input($_POST['sku']);
    $description = $_POST['description'];
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category_id = (int)$_POST['category_id'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $material = clean_input($_POST['material']);
    $dimensions = clean_input($_POST['dimensions']);
    $weight = clean_input($_POST['weight']);
    
    // Validate form data
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }
    
    if (empty($sku)) {
        $errors[] = 'SKU is required.';
    } else {
        // Check if SKU is unique (excluding current product)
        $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $stmt->execute([$sku, $product_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = 'This SKU is already in use. Please enter a unique SKU.';
        }
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than zero.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative.';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Please select a valid category.';
    }
    
    // Handle image upload if a new image is provided
    $image_path = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
        $result = upload_file($_FILES['image'], '../' . UPLOAD_DIR);
        
        if ($result['success']) {
            $image_path = UPLOAD_DIR . $result['filename'];
        } else {
            $errors[] = $result['message'];
        }
    }
    
    // Update product if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE products 
            SET name = ?, sku = ?, description = ?, price = ?, stock = ?, category_id = ?, 
                is_featured = ?, image = ?, material = ?, dimensions = ?, weight = ?
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $name, $sku, $description, $price, $stock, $category_id, $is_featured, 
            $image_path, $material, $dimensions, $weight, $product_id
        ]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Product updated successfully.';
            redirect('products.php');
        } else {
            $errors[] = 'Failed to update product. Please try again.';
        }
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Edit Product</h1>
        <a href="products.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-8">
                        <!-- Basic Information -->
                        <h5 class="mb-3">Basic Information</h5>
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= $product['name']; ?>" required>
                            <div class="invalid-feedback">
                                Please enter a product name.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?= $product['sku']; ?>" required>
                            <div class="invalid-feedback">
                                Please enter a SKU.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?= $product['description']; ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?= $product['price']; ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid price.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="stock" class="form-label">Stock</label>
                                <input type="number" min="0" class="form-control" id="stock" name="stock" value="<?= $product['stock']; ?>" required>
                                <div class="invalid-feedback">
                                    Please enter a valid stock quantity.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id']; ?>" <?= ($product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?= $category['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a category.
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <h5 class="mb-3 mt-4">Additional Information</h5>
                        <div class="mb-3">
                            <label for="material" class="form-label">Material</label>
                            <input type="text" class="form-control" id="material" name="material" value="<?= $product['material']; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="dimensions" class="form-label">Dimensions</label>
                            <input type="text" class="form-control" id="dimensions" name="dimensions" value="<?= $product['dimensions']; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="weight" class="form-label">Weight</label>
                            <input type="text" class="form-control" id="weight" name="weight" value="<?= $product['weight']; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Image Upload -->
                        <h5 class="mb-3">Product Image</h5>
                        <div class="mb-3">
                            <label for="image" class="form-label">Upload Image</label>
                            <input type="file" class="form-control image-input" id="image" name="image" data-preview="#imagePreview">
                            <div class="mt-2">
                                <img id="imagePreview" src="<?= get_product_image_url($product['image']); ?>" alt="Preview" class="img-thumbnail" style="max-width: 200px; display: block;">
                            </div>
                            <small class="text-muted">
                                Max file size: <?= MAX_FILE_SIZE / 1024 / 1024; ?>MB. Accepted formats: <?= implode(', ', ALLOWED_EXTENSIONS); ?>.
                            </small>
                        </div>
                        
                        <!-- Options -->
                        <h5 class="mb-3 mt-4">Options</h5>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" <?= $product['is_featured'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">Featured Product</label>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
