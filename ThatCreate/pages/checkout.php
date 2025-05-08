<?php
// Checkout page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login
require_login('login.php');

// Get current user
$current_user = get_logged_in_user();

// Connect to database
$database = new Database();
$conn = $database->connect();

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image, p.stock, 
           (c.quantity * p.price) as subtotal
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$current_user['id']]);
$cart_items = $stmt->fetchAll();

// Redirect if cart is empty
if (empty($cart_items)) {
    $_SESSION['error_message'] = 'Your cart is empty. Add some products before checking out.';
    redirect('cart.php');
}

// Calculate cart totals
$cart_subtotal = 0;
$cart_total = 0;
$item_count = 0;

foreach ($cart_items as $item) {
    $cart_subtotal += $item['subtotal'];
    $item_count += $item['quantity'];
}

// Apply shipping cost
$shipping_cost = $cart_subtotal >= 50 ? 0 : 5.99;

// Calculate tax (assuming 7% tax rate)
$tax_rate = 0.07;
$tax_amount = $cart_subtotal * $tax_rate;

// Calculate final total
$cart_total = $cart_subtotal + $shipping_cost + $tax_amount;

// Get user's default address
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1");
$stmt->execute([$current_user['id']]);
$address = $stmt->fetch();

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $payment_method = clean_input($_POST['payment_method']);
    $name_on_card = isset($_POST['name_on_card']) ? clean_input($_POST['name_on_card']) : '';
    $card_number = isset($_POST['card_number']) ? clean_input($_POST['card_number']) : '';
    $expiry_date = isset($_POST['expiry_date']) ? clean_input($_POST['expiry_date']) : '';
    $cvv = isset($_POST['cvv']) ? clean_input($_POST['cvv']) : '';
    
    $address_line1 = clean_input($_POST['address_line1']);
    $address_line2 = clean_input($_POST['address_line2']);
    $city = clean_input($_POST['city']);
    $state = clean_input($_POST['state']);
    $postal_code = clean_input($_POST['postal_code']);
    $country = clean_input($_POST['country']);
    
    $errors = [];
    
    // Validate required fields
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required.';
    }
    
    if ($payment_method === 'credit_card') {
        if (empty($name_on_card)) {
            $errors[] = 'Name on card is required.';
        }
        
        if (empty($card_number)) {
            $errors[] = 'Card number is required.';
        } elseif (!preg_match('/^\d{16}$/', str_replace(' ', '', $card_number))) {
            $errors[] = 'Invalid card number.';
        }
        
        if (empty($expiry_date)) {
            $errors[] = 'Expiry date is required.';
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry_date)) {
            $errors[] = 'Invalid expiry date. Use MM/YY format.';
        }
        
        if (empty($cvv)) {
            $errors[] = 'CVV is required.';
        } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
            $errors[] = 'Invalid CVV.';
        }
    }
    
    if (empty($address_line1)) {
        $errors[] = 'Address line 1 is required.';
    }
    
    if (empty($city)) {
        $errors[] = 'City is required.';
    }
    
    if (empty($state)) {
        $errors[] = 'State is required.';
    }
    
    if (empty($postal_code)) {
        $errors[] = 'Postal code is required.';
    }
    
    if (empty($country)) {
        $errors[] = 'Country is required.';
    }
    
    // Process order if no errors
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Create order
            $stmt = $conn->prepare("
                INSERT INTO orders 
                (user_id, subtotal, shipping_cost, tax_amount, total_amount, payment_method, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_user['id'], 
                $cart_subtotal, 
                $shipping_cost, 
                $tax_amount, 
                $cart_total, 
                $payment_method, 
                ORDER_PENDING
            ]);
            
            $order_id = $conn->lastInsertId();
            
            // Save shipping address
            $stmt = $conn->prepare("
                INSERT INTO order_addresses 
                (order_id, address_line1, address_line2, city, state, postal_code, country) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id, 
                $address_line1, 
                $address_line2, 
                $city, 
                $state, 
                $postal_code, 
                $country
            ]);
            
            // Save order items
            $stmt = $conn->prepare("
                INSERT INTO order_items 
                (order_id, product_id, quantity, unit_price, subtotal) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $stmt->execute([
                    $order_id, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['price'], 
                    $item['subtotal']
                ]);
                
                // Update product stock
                $update_stock = $conn->prepare("
                    UPDATE products 
                    SET stock = stock - ? 
                    WHERE id = ?
                ");
                $update_stock->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Clear user's cart
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$current_user['id']]);
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to order confirmation
            $_SESSION['success_message'] = 'Order placed successfully!';
            redirect('order_detail.php?id=' . $order_id);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $errors[] = 'An error occurred while processing your order. Please try again.';
            
            // Log the error (in a real system)
            error_log('Checkout error: ' . $e->getMessage());
        }
    }
}

// Page title
$page_title = 'Checkout';

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Checkout</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" class="needs-validation" novalidate>
        <div class="row">
            <div class="col-md-8">
                <!-- Shipping Address -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="address_line1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="address_line1" name="address_line1" 
                                   value="<?= $address['address_line1'] ?? ''; ?>" required>
                            <div class="invalid-feedback">
                                Please enter your address.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address_line2" class="form-label">Address Line 2 (Optional)</label>
                            <input type="text" class="form-control" id="address_line2" name="address_line2" 
                                   value="<?= $address['address_line2'] ?? ''; ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?= $address['city'] ?? ''; ?>" required>
                                <div class="invalid-feedback">
                                    Please enter your city.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?= $address['state'] ?? ''; ?>" required>
                                <div class="invalid-feedback">
                                    Please enter your state/province.
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?= $address['postal_code'] ?? ''; ?>" required>
                                <div class="invalid-feedback">
                                    Please enter your postal code.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="">Select a country</option>
                                    <option value="USA" <?= ($address && $address['country'] == 'USA') ? 'selected' : ''; ?>>United States</option>
                                    <option value="CAN" <?= ($address && $address['country'] == 'CAN') ? 'selected' : ''; ?>>Canada</option>
                                    <option value="GBR" <?= ($address && $address['country'] == 'GBR') ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="AUS" <?= ($address && $address['country'] == 'AUS') ? 'selected' : ''; ?>>Australia</option>
                                    <!-- Add more countries as needed -->
                                </select>
                                <div class="invalid-feedback">
                                    Please select your country.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_credit_card" value="credit_card" checked>
                                <label class="form-check-label" for="payment_credit_card">
                                    <i class="fas fa-credit-card me-2"></i>Credit/Debit Card
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_paypal" value="paypal">
                                <label class="form-check-label" for="payment_paypal">
                                    <i class="fab fa-paypal me-2"></i>PayPal
                                </label>
                            </div>
                        </div>
                        
                        <!-- Credit Card Form -->
                        <div id="credit_card_form">
                            <div class="mb-3">
                                <label for="name_on_card" class="form-label">Name on Card</label>
                                <input type="text" class="form-control" id="name_on_card" name="name_on_card" required>
                                <div class="invalid-feedback">
                                    Please enter the name on your card.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="card_number" class="form-label">Card Number</label>
                                <input type="text" class="form-control" id="card_number" name="card_number" 
                                       placeholder="XXXX XXXX XXXX XXXX" maxlength="19" required>
                                <div class="invalid-feedback">
                                    Please enter a valid card number.
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="expiry_date" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="expiry_date" name="expiry_date" 
                                           placeholder="MM/YY" maxlength="5" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid expiry date.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="cvv" name="cvv" 
                                           placeholder="XXX" maxlength="4" required>
                                    <div class="invalid-feedback">
                                        Please enter your CVV.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PayPal section (would be implemented with PayPal API in a real system) -->
                        <div id="paypal_form" style="display: none;">
                            <p>You will be redirected to PayPal to complete your payment after reviewing your order.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Order Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Items (<?= $item_count; ?>)</h6>
                        
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?= $item['name']; ?> × <?= $item['quantity']; ?></span>
                                <span><?= format_currency($item['subtotal']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?= format_currency($cart_subtotal); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span><?= $shipping_cost > 0 ? format_currency($shipping_cost) : 'Free'; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (<?= ($tax_rate * 100); ?>%):</span>
                            <span><?= format_currency($tax_amount); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Total:</span>
                            <span class="fw-bold"><?= format_currency($cart_total); ?></span>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock me-2"></i>Place Order
                            </button>
                            <a href="cart.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Return to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle payment method forms
    const creditCardRadio = document.getElementById('payment_credit_card');
    const paypalRadio = document.getElementById('payment_paypal');
    const creditCardForm = document.getElementById('credit_card_form');
    const paypalForm = document.getElementById('paypal_form');
    
    creditCardRadio.addEventListener('change', function() {
        if (this.checked) {
            creditCardForm.style.display = 'block';
            paypalForm.style.display = 'none';
            
            // Re-enable validation for credit card fields
            document.getElementById('name_on_card').required = true;
            document.getElementById('card_number').required = true;
            document.getElementById('expiry_date').required = true;
            document.getElementById('cvv').required = true;
        }
    });
    
    paypalRadio.addEventListener('change', function() {
        if (this.checked) {
            creditCardForm.style.display = 'none';
            paypalForm.style.display = 'block';
            
            // Disable validation for credit card fields
            document.getElementById('name_on_card').required = false;
            document.getElementById('card_number').required = false;
            document.getElementById('expiry_date').required = false;
            document.getElementById('cvv').required = false;
        }
    });
    
    // Format credit card number with spaces
    const cardNumberInput = document.getElementById('card_number');
    cardNumberInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let formattedValue = '';
        
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) {
                formattedValue += ' ';
            }
            formattedValue += value[i];
        }
        
        e.target.value = formattedValue;
    });
    
    // Format expiry date with slash
    const expiryDateInput = document.getElementById('expiry_date');
    expiryDateInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        
        if (value.length > 2) {
            value = value.substring(0, 2) + '/' + value.substring(2);
        }
        
        e.target.value = value;
    });
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
