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
$name = '';
$description = '';
$price = '';
$stock = '';
$category_id = '';
$sku = '';
$material = '';
$dimensions = '';
$weight = '';
$errors = [];
$success = false;

// Get categories for dropdown
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $material = trim($_POST['material'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = 'pending_approval'; // New products need admin approval
    
    // Validate form data
    if (empty($name)) {
        $errors['name'] = 'Product name is required';
    }
    
    if (empty($description)) {
        $errors['description'] = 'Product description is required';
    }
    
    if (empty($price)) {
        $errors['price'] = 'Price is required';
    } elseif (!is_numeric($price) || $price <= 0) {
        $errors['price'] = 'Price must be a positive number';
    }
    
    if (empty($stock)) {
        $errors['stock'] = 'Stock quantity is required';
    } elseif (!is_numeric($stock) || $stock < 0) {
        $errors['stock'] = 'Stock must be a non-negative number';
    }
    
    if (empty($category_id)) {
        $errors['category_id'] = 'Category is required';
    }
    
    if (empty($sku)) {
        $errors['sku'] = 'SKU is required';
    } else {
        // Check if SKU already exists
        $stmt = $conn->prepare("SELECT id FROM products WHERE sku = :sku");
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $errors['sku'] = 'This SKU is already in use';
        }
    }
    
    // Handle image upload
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['image']['name'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors['image'] = 'Only JPG, JPEG, PNG, and GIF files are allowed';
        } else {
            // Generate unique file name
            $image_name = 'product_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_dir = '../assets/images/products/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $upload_path = $upload_dir . $image_name;
            
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $errors['image'] = 'Failed to upload image';
            }
        }
    } else {
        $errors['image'] = 'Product image is required';
    }
    
    // If no validation errors, insert product
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO products (
                    name, description, price, stock, category_id, vendor_id, 
                    sku, is_featured, image, material, dimensions, weight, status
                ) VALUES (
                    :name, :description, :price, :stock, :category_id, :vendor_id, 
                    :sku, :is_featured, :image, :material, :dimensions, :weight, :status
                )
            ");
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':vendor_id', $vendor_id);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':is_featured', $is_featured);
            $stmt->bindParam(':image', $image_name);
            $stmt->bindParam(':material', $material);
            $stmt->bindParam(':dimensions', $dimensions);
            $stmt->bindParam(':weight', $weight);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $success = true;
                
                // Reset form fields after successful submission
                $name = '';
                $description = '';
                $price = '';
                $stock = '';
                $category_id = '';
                $sku = '';
                $material = '';
                $dimensions = '';
                $weight = '';
            } else {
                $errors['database'] = 'Failed to add product';
            }
        } catch (PDOException $e) {
            $errors['database'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Include header
$page_title = 'Add New Product';
require_once 'includes/vendor_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once 'includes/vendor_sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Add New Product</h1>
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                </a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle me-2"></i> Product Added Successfully!</h5>
                    <p>Your product has been submitted for review. Once approved by the admin, it will be listed in the store.</p>
                    <div class="mt-3">
                        <a href="add-product.php" class="btn btn-success me-2">Add Another Product</a>
                        <a href="products.php" class="btn btn-primary">View All Products</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="<?= $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="sku" name="sku" value="<?= htmlspecialchars($sku); ?>" required>
                                    <div class="form-text">Unique product identifier</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($description); ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($price); ?>" step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="stock" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stock" name="stock" value="<?= htmlspecialchars($stock); ?>" min="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id']; ?>" <?= $category_id == $category['id'] ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="material" class="form-label">Material</label>
                                    <input type="text" class="form-control" id="material" name="material" value="<?= htmlspecialchars($material); ?>">
                                    <div class="form-text">E.g., "14k Gold, Diamond"</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="dimensions" class="form-label">Dimensions</label>
                                    <input type="text" class="form-control" id="dimensions" name="dimensions" value="<?= htmlspecialchars($dimensions); ?>">
                                    <div class="form-text">E.g., "Length: 18 inches"</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="weight" class="form-label">Weight</label>
                                    <input type="text" class="form-control" id="weight" name="weight" value="<?= htmlspecialchars($weight); ?>">
                                    <div class="form-text">E.g., "3.5g"</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                                <div class="form-text">Upload a high-quality image of your product (JPG, PNG, or GIF)</div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?= isset($is_featured) && $is_featured ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">
                                        Request to be featured on homepage (subject to admin approval)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i> Your product will be reviewed by our team before it appears on the store. This usually takes 1-2 business days.
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-outline-secondary me-2">Reset</button>
                                <button type="submit" class="btn btn-primary">Add Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php
// Include footer
require_once 'includes/vendor_footer.php';
?>