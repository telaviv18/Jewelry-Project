<?php
// API endpoint for product operations
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Connect to database
$database = new Database();
$conn = $database->connect();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Process based on request method
switch ($method) {
    case 'GET':
        // Get products - accessible to anyone
        
        // Get parameters
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        $featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : false;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;
        
        try {
            // If ID is provided, get specific product
            if ($id > 0) {
                $stmt = $conn->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM products p
                    JOIN categories c ON p.category_id = c.id
                    WHERE p.id = ? AND p.stock > 0
                ");
                $stmt->execute([$id]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $response['success'] = true;
                    $response['data'] = $product;
                    $response['message'] = 'Product retrieved successfully';
                } else {
                    $response['message'] = 'Product not found';
                    http_response_code(404);
                }
            } else {
                // Build query conditions
                $conditions = [];
                $params = [];
                
                if ($category_id > 0) {
                    $conditions[] = "p.category_id = ?";
                    $params[] = $category_id;
                }
                
                if (!empty($search)) {
                    $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }
                
                if ($featured) {
                    $conditions[] = "p.is_featured = 1";
                }
                
                // Only show products with stock
                $conditions[] = "p.stock > 0";
                
                $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
                
                // Get total count
                $count_sql = "
                    SELECT COUNT(*) as total 
                    FROM products p
                    $where_clause
                ";
                $stmt = $conn->prepare($count_sql);
                $stmt->execute($params);
                $total = $stmt->fetch()['total'];
                $total_pages = ceil($total / $limit);
                
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
                
                $response['success'] = true;
                $response['data'] = $products;
                $response['pagination'] = [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'total_pages' => $total_pages
                ];
                $response['message'] = 'Products retrieved successfully';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            http_response_code(500);
        }
        break;
        
    case 'POST':
        // Create a new product - requires staff access
        if (!is_staff()) {
            $response['message'] = 'Access denied. Staff access required.';
            http_response_code(403);
            echo json_encode($response);
            exit;
        }
        
        // Get JSON input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $response['message'] = 'Invalid JSON data';
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        // Extract and validate product data
        $name = isset($input['name']) ? clean_input($input['name']) : '';
        $description = isset($input['description']) ? $input['description'] : '';
        $price = isset($input['price']) ? (float)$input['price'] : 0;
        $stock = isset($input['stock']) ? (int)$input['stock'] : 0;
        $category_id = isset($input['category_id']) ? (int)$input['category_id'] : 0;
        $sku = isset($input['sku']) ? clean_input($input['sku']) : '';
        $is_featured = isset($input['is_featured']) ? (bool)$input['is_featured'] : false;
        $image = isset($input['image']) ? clean_input($input['image']) : '';
        $material = isset($input['material']) ? clean_input($input['material']) : '';
        $dimensions = isset($input['dimensions']) ? clean_input($input['dimensions']) : '';
        $weight = isset($input['weight']) ? clean_input($input['weight']) : '';
        
        // Validate required fields
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Product name is required';
        }
        
        if (empty($sku)) {
            $errors[] = 'SKU is required';
        }
        
        if ($price <= 0) {
            $errors[] = 'Price must be greater than zero';
        }
        
        if ($stock < 0) {
            $errors[] = 'Stock cannot be negative';
        }
        
        if ($category_id <= 0) {
            $errors[] = 'Valid category is required';
        }
        
        if (!empty($errors)) {
            $response['message'] = 'Validation errors';
            $response['errors'] = $errors;
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        try {
            // Check if SKU already exists
            $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$sku]);
            if ($stmt->rowCount() > 0) {
                $response['message'] = 'SKU already exists';
                http_response_code(400);
                echo json_encode($response);
                exit;
            }
            
            // Insert new product
            $stmt = $conn->prepare("
                INSERT INTO products 
                (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $name, $description, $price, $stock, $category_id, $sku, 
                $is_featured ? 1 : 0, $image, $material, $dimensions, $weight
            ]);
            
            if ($result) {
                $product_id = $conn->lastInsertId();
                
                // Get the created product
                $stmt = $conn->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM products p
                    JOIN categories c ON p.category_id = c.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                $response['success'] = true;
                $response['data'] = $product;
                $response['message'] = 'Product created successfully';
                http_response_code(201); // Created
            } else {
                $response['message'] = 'Failed to create product';
                http_response_code(500);
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            http_response_code(500);
        }
        break;
        
    case 'PUT':
        // Update an existing product - requires staff access
        if (!is_staff()) {
            $response['message'] = 'Access denied. Staff access required.';
            http_response_code(403);
            echo json_encode($response);
            exit;
        }
        
        // Get product ID
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            $response['message'] = 'Product ID is required';
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        // Get JSON input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $response['message'] = 'Invalid JSON data';
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        // Check if product exists
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $response['message'] = 'Product not found';
            http_response_code(404);
            echo json_encode($response);
            exit;
        }
        
        // Extract and validate product data
        $name = isset($input['name']) ? clean_input($input['name']) : $product['name'];
        $description = isset($input['description']) ? $input['description'] : $product['description'];
        $price = isset($input['price']) ? (float)$input['price'] : $product['price'];
        $stock = isset($input['stock']) ? (int)$input['stock'] : $product['stock'];
        $category_id = isset($input['category_id']) ? (int)$input['category_id'] : $product['category_id'];
        $sku = isset($input['sku']) ? clean_input($input['sku']) : $product['sku'];
        $is_featured = isset($input['is_featured']) ? (bool)$input['is_featured'] : $product['is_featured'];
        $image = isset($input['image']) ? clean_input($input['image']) : $product['image'];
        $material = isset($input['material']) ? clean_input($input['material']) : $product['material'];
        $dimensions = isset($input['dimensions']) ? clean_input($input['dimensions']) : $product['dimensions'];
        $weight = isset($input['weight']) ? clean_input($input['weight']) : $product['weight'];
        
        // Validate required fields
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Product name is required';
        }
        
        if (empty($sku)) {
            $errors[] = 'SKU is required';
        }
        
        if ($price <= 0) {
            $errors[] = 'Price must be greater than zero';
        }
        
        if ($stock < 0) {
            $errors[] = 'Stock cannot be negative';
        }
        
        if ($category_id <= 0) {
            $errors[] = 'Valid category is required';
        }
        
        if (!empty($errors)) {
            $response['message'] = 'Validation errors';
            $response['errors'] = $errors;
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        try {
            // Check if SKU already exists (excluding current product)
            $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $stmt->execute([$sku, $id]);
            if ($stmt->rowCount() > 0) {
                $response['message'] = 'SKU already exists';
                http_response_code(400);
                echo json_encode($response);
                exit;
            }
            
            // Update product
            $stmt = $conn->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, 
                    sku = ?, is_featured = ?, image = ?, material = ?, dimensions = ?, weight = ?
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $name, $description, $price, $stock, $category_id, $sku, 
                $is_featured ? 1 : 0, $image, $material, $dimensions, $weight, $id
            ]);
            
            if ($result) {
                // Get the updated product
                $stmt = $conn->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM products p
                    JOIN categories c ON p.category_id = c.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$id]);
                $updated_product = $stmt->fetch();
                
                $response['success'] = true;
                $response['data'] = $updated_product;
                $response['message'] = 'Product updated successfully';
            } else {
                $response['message'] = 'Failed to update product';
                http_response_code(500);
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            http_response_code(500);
        }
        break;
        
    case 'DELETE':
        // Delete a product - requires admin access
        if (!is_admin()) {
            $response['message'] = 'Access denied. Admin access required.';
            http_response_code(403);
            echo json_encode($response);
            exit;
        }
        
        // Get product ID
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            $response['message'] = 'Product ID is required';
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        try {
            // Check if product exists
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() == 0) {
                $response['message'] = 'Product not found';
                http_response_code(404);
                echo json_encode($response);
                exit;
            }
            
            // Delete product
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Product deleted successfully';
            } else {
                $response['message'] = 'Failed to delete product';
                http_response_code(500);
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            http_response_code(500);
        }
        break;
        
    default:
        $response['message'] = 'Method not allowed';
        http_response_code(405);
        break;
}

// Return JSON response
echo json_encode($response);
?>
