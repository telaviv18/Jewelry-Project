<?php
// API endpoint for cart operations
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to manage your cart'
    ]);
    exit;
}

// Get current user
$current_user = get_logged_in_user();

// Connect to database
$database = new Database();
$conn = $database->connect();

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Process request based on action
$action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

switch ($action) {
    case 'add':
        // Add item to cart
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        // Validate inputs
        if ($product_id <= 0) {
            $response['message'] = 'Invalid product ID';
            echo json_encode($response);
            exit;
        }
        
        if ($quantity <= 0) {
            $response['message'] = 'Quantity must be greater than zero';
            echo json_encode($response);
            exit;
        }
        
        try {
            // Check if product exists and has sufficient stock
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND stock > 0");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $response['message'] = 'Product not found or out of stock';
                echo json_encode($response);
                exit;
            }
            
            // Check if quantity is available
            if ($quantity > $product['stock']) {
                $response['message'] = 'Not enough stock available. Only ' . $product['stock'] . ' items left.';
                echo json_encode($response);
                exit;
            }
            
            // Check if product already in cart
            $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$current_user['id'], $product_id]);
            $cart_item = $stmt->fetch();
            
            if ($cart_item) {
                // Update quantity
                $new_quantity = $cart_item['quantity'] + $quantity;
                
                // Check if new quantity exceeds available stock
                if ($new_quantity > $product['stock']) {
                    $response['message'] = 'Not enough stock available. Only ' . $product['stock'] . ' items left.';
                    echo json_encode($response);
                    exit;
                }
                
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $result = $stmt->execute([$new_quantity, $cart_item['id']]);
            } else {
                // Add new item to cart
                $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $result = $stmt->execute([$current_user['id'], $product_id, $quantity]);
            }
            
            if ($result) {
                // Get updated cart count
                $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
                $stmt->execute([$current_user['id']]);
                $result = $stmt->fetch();
                $cart_count = $result['total'] ? $result['total'] : 0;
                
                $response['success'] = true;
                $response['message'] = 'Product added to cart successfully';
                $response['cart_count'] = $cart_count;
            } else {
                $response['message'] = 'Failed to add product to cart';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
        break;
        
    case 'update':
        // Update cart item quantity
        $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        // Validate inputs
        if ($cart_id <= 0) {
            $response['message'] = 'Invalid cart item ID';
            echo json_encode($response);
            exit;
        }
        
        if ($quantity <= 0) {
            $response['message'] = 'Quantity must be greater than zero';
            echo json_encode($response);
            exit;
        }
        
        try {
            // Get cart item and check ownership
            $stmt = $conn->prepare("
                SELECT c.*, p.stock, p.price
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$cart_id, $current_user['id']]);
            $cart_item = $stmt->fetch();
            
            if (!$cart_item) {
                $response['message'] = 'Cart item not found or access denied';
                echo json_encode($response);
                exit;
            }
            
            // Check if quantity is available
            if ($quantity > $cart_item['stock']) {
                $response['message'] = 'Not enough stock available. Only ' . $cart_item['stock'] . ' items left.';
                echo json_encode($response);
                exit;
            }
            
            // Update quantity
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $result = $stmt->execute([$quantity, $cart_id]);
            
            if ($result) {
                // Calculate item subtotal
                $subtotal = $quantity * $cart_item['price'];
                
                // Get updated cart total
                $stmt = $conn->prepare("
                    SELECT SUM(c.quantity * p.price) as total, SUM(c.quantity) as count
                    FROM cart c
                    JOIN products p ON c.product_id = p.id
                    WHERE c.user_id = ?
                ");
                $stmt->execute([$current_user['id']]);
                $result = $stmt->fetch();
                $cart_total = $result['total'] ? $result['total'] : 0;
                $cart_count = $result['count'] ? $result['count'] : 0;
                
                // Apply shipping cost
                $shipping_cost = $cart_total >= 50 ? 0 : 5.99;
                $cart_total += $shipping_cost;
                
                $response['success'] = true;
                $response['message'] = 'Cart updated successfully';
                $response['subtotal'] = format_currency($subtotal);
                $response['cart_total'] = format_currency($cart_total);
                $response['cart_count'] = $cart_count;
            } else {
                $response['message'] = 'Failed to update cart';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
        break;
        
    case 'remove':
        // Remove item from cart
        $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
        
        // Validate inputs
        if ($cart_id <= 0) {
            $response['message'] = 'Invalid cart item ID';
            echo json_encode($response);
            exit;
        }
        
        try {
            // Check ownership
            $stmt = $conn->prepare("SELECT * FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $current_user['id']]);
            
            if ($stmt->rowCount() == 0) {
                $response['message'] = 'Cart item not found or access denied';
                echo json_encode($response);
                exit;
            }
            
            // Remove item
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
            $result = $stmt->execute([$cart_id]);
            
            if ($result) {
                // Get updated cart total
                $stmt = $conn->prepare("
                    SELECT SUM(c.quantity * p.price) as total, SUM(c.quantity) as count
                    FROM cart c
                    JOIN products p ON c.product_id = p.id
                    WHERE c.user_id = ?
                ");
                $stmt->execute([$current_user['id']]);
                $result = $stmt->fetch();
                $cart_total = $result['total'] ? $result['total'] : 0;
                $cart_count = $result['count'] ? $result['count'] : 0;
                
                // Apply shipping cost
                $shipping_cost = $cart_total >= 50 ? 0 : 5.99;
                $cart_total += $shipping_cost;
                
                $response['success'] = true;
                $response['message'] = 'Item removed from cart';
                $response['cart_total'] = format_currency($cart_total);
                $response['cart_count'] = $cart_count;
            } else {
                $response['message'] = 'Failed to remove item from cart';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
        break;
        
    case 'clear':
        // Clear entire cart
        try {
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $result = $stmt->execute([$current_user['id']]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Cart cleared successfully';
                $response['cart_total'] = format_currency(0);
                $response['cart_count'] = 0;
            } else {
                $response['message'] = 'Failed to clear cart';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
        break;
        
    case 'get':
        // Get cart contents
        try {
            $stmt = $conn->prepare("
                SELECT c.id, c.product_id, c.quantity, 
                       p.name, p.price, p.image, p.stock,
                       (c.quantity * p.price) as subtotal
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
            ");
            $stmt->execute([$current_user['id']]);
            $cart_items = $stmt->fetchAll();
            
            // Calculate cart totals
            $cart_subtotal = 0;
            $cart_count = 0;
            
            foreach ($cart_items as $item) {
                $cart_subtotal += $item['subtotal'];
                $cart_count += $item['quantity'];
            }
            
            // Apply shipping cost
            $shipping_cost = $cart_subtotal >= 50 ? 0 : 5.99;
            $cart_total = $cart_subtotal + $shipping_cost;
            
            $response['success'] = true;
            $response['message'] = 'Cart retrieved successfully';
            $response['data'] = [
                'items' => $cart_items,
                'subtotal' => $cart_subtotal,
                'shipping_cost' => $shipping_cost,
                'total' => $cart_total,
                'item_count' => $cart_count
            ];
            $response['cart_total'] = format_currency($cart_total);
            $response['cart_count'] = $cart_count;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
        break;
        
    default:
        $response['message'] = 'Invalid action';
        break;
}

// Return JSON response
echo json_encode($response);
?>
