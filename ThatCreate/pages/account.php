<?php
// User account page
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

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$current_user['id']]);
$user = $stmt->fetch();

// Get user address
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1");
$stmt->execute([$current_user['id']]);
$address = $stmt->fetch();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = clean_input($_POST['name']);
        $email = clean_input($_POST['email']);
        $phone = clean_input($_POST['phone']);
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        // Check if email exists and it's not the current user's email
        if ($email !== $user['email']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $current_user['id']]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Email already in use by another account.';
            }
        }
        
        // Update user if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            $result = $stmt->execute([$name, $email, $phone, $current_user['id']]);
            
            if ($result) {
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $_SESSION['success_message'] = 'Profile updated successfully.';
                redirect('account.php');
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    } elseif (isset($_POST['update_password'])) {
        // Update password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        $errors = [];
        
        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
        
        // Update password if no errors
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashed_password, $current_user['id']]);
            
            if ($result) {
                $_SESSION['success_message'] = 'Password updated successfully.';
                redirect('account.php');
            } else {
                $errors[] = 'Failed to update password. Please try again.';
            }
        }
    } elseif (isset($_POST['update_address'])) {
        // Update or add address
        $address_line1 = clean_input($_POST['address_line1']);
        $address_line2 = clean_input($_POST['address_line2']);
        $city = clean_input($_POST['city']);
        $state = clean_input($_POST['state']);
        $postal_code = clean_input($_POST['postal_code']);
        $country = clean_input($_POST['country']);
        
        // Validate inputs
        $errors = [];
        
        if (empty($address_line1)) {
            $errors[] = 'Address line 1 is required.';
        }
        
        if (empty($city)) {
            $errors[] = 'City is required.';
        }
        
        if (empty($state)) {
            $errors[] = 'State/Province is required.';
        }
        
        if (empty($postal_code)) {
            $errors[] = 'Postal code is required.';
        }
        
        if (empty($country)) {
            $errors[] = 'Country is required.';
        }
        
        // Update or insert address if no errors
        if (empty($errors)) {
            if ($address) {
                // Update existing address
                $stmt = $conn->prepare("
                    UPDATE user_addresses 
                    SET address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ? 
                    WHERE user_id = ? AND is_default = 1
                ");
                $result = $stmt->execute([
                    $address_line1, $address_line2, $city, $state, $postal_code, $country, $current_user['id']
                ]);
            } else {
                // Insert new address
                $stmt = $conn->prepare("
                    INSERT INTO user_addresses 
                    (user_id, address_line1, address_line2, city, state, postal_code, country, is_default) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $result = $stmt->execute([
                    $current_user['id'], $address_line1, $address_line2, $city, $state, $postal_code, $country
                ]);
            }
            
            if ($result) {
                $_SESSION['success_message'] = 'Address updated successfully.';
                redirect('account.php');
            } else {
                $errors[] = 'Failed to update address. Please try again.';
            }
        }
    }
}

// Page title
$page_title = 'My Account';

// Include header
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Account Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="account-sidebar">
                <h4 class="mb-3">My Account</h4>
                <div class="list-group">
                    <a href="account.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-bag me-2"></i> Orders
                    </a>
                    <a href="cart.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart me-2"></i> Cart
                    </a>
                    <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Account Content -->
        <div class="col-lg-9">
            <!-- Profile Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && isset($_POST['update_profile'])): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= $user['name']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $user['email']; ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= $user['phone'] ?? ''; ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && isset($_POST['update_password'])): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" 
                                   name="new_password" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Address Information</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors) && isset($_POST['update_address'])): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="address_line1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="address_line1" name="address_line1" 
                                   value="<?= $address['address_line1'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="address_line2" class="form-label">Address Line 2</label>
                            <input type="text" class="form-control" id="address_line2" name="address_line2" 
                                   value="<?= $address['address_line2'] ?? ''; ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?= $address['city'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?= $address['state'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?= $address['postal_code'] ?? ''; ?>" required>
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
                            </div>
                        </div>
                        <button type="submit" name="update_address" class="btn btn-primary">Update Address</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Custom validation for matching passwords
document.getElementById('confirm_password')?.addEventListener('input', function() {
    if (this.value !== document.getElementById('new_password').value) {
        this.setCustomValidity('Passwords do not match.');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
