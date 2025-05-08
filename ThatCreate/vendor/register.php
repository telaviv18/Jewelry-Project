<?php
// Include necessary files
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in as vendor
if (is_logged_in() && get_user_role() == 5) {
    redirect('vendor/dashboard.php');
}

// Initialize variables
$name = '';
$email = '';
$password = '';
$confirm_password = '';
$phone = '';
$company_name = '';
$business_phone = '';
$business_email = '';
$errors = [];
$success = false;

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $business_phone = trim($_POST['business_phone'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    // Fields removed: tax_id, business_address, description
    
    // Validate form
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email is invalid';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($company_name)) {
        $errors['company_name'] = 'Company name is required';
    }
    
    if (empty($business_email)) {
        $errors['business_email'] = 'Business email is required';
    } elseif (!filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
        $errors['business_email'] = 'Business email is invalid';
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        // Connect to database
        $database = new Database();
        $conn = $database->connect();
        
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors['email'] = 'Email is already taken';
            } else {
                // Start transaction
                $conn->beginTransaction();
                
                // Insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 5; // Vendor role
                
                $stmt = $conn->prepare("
                    INSERT INTO users (name, email, password, phone, role) 
                    VALUES (:name, :email, :password, :phone, :role)
                ");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':role', $role);
                $stmt->execute();
                
                $user_id = $conn->lastInsertId();
                
                // Insert vendor
                $stmt = $conn->prepare("
                    INSERT INTO vendors (user_id, company_name, business_phone, business_email) 
                    VALUES (:user_id, :company_name, :business_phone, :business_email)
                ");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':company_name', $company_name);
                $stmt->bindParam(':business_phone', $business_phone);
                $stmt->bindParam(':business_email', $business_email);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Registration successful
                $success = true;
            }
        } catch(PDOException $e) {
            // Rollback transaction
            $conn->rollBack();
            $errors['database'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Include a custom header for vendor pages
$page_title = 'Vendor Registration';
require_once 'includes/vendor_header.php';
?>

<!-- Vendor Registration Section -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Vendor Registration</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <h5>Registration Successful!</h5>
                            <p>Your vendor account has been created. Our team will review your application and you will be notified once approved.</p>
                            <p>You can <a href="login.php">login</a> to check your application status.</p>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form action="<?= $_SERVER['PHP_SELF']; ?>" method="POST">
                            <h5 class="mb-3">Personal Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone); ?>">
                            </div>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Business Information</h5>
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?= htmlspecialchars($company_name); ?>" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="business_phone" class="form-label">Business Phone</label>
                                    <input type="text" class="form-control" id="business_phone" name="business_phone" value="<?= htmlspecialchars($business_phone); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="business_email" class="form-label">Business Email</label>
                                    <input type="email" class="form-control" id="business_email" name="business_email" value="<?= htmlspecialchars($business_email); ?>" required>
                                </div>
                            </div>
                            
                            <!-- Fields removed as requested: Tax ID, Business Address, Business Description -->
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Register as Vendor</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="text-center">
                        <p>Already have a vendor account? <a href="login.php">Login here</a></p>
                        <p><a href="../index.php">Back to Store</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include vendor footer
require_once 'includes/vendor_footer.php';
?>