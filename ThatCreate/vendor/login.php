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
$email = '';
$password = '';
$errors = [];

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validate form
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        // Connect to database
        $database = new Database();
        $conn = $database->connect();
        
        try {
            // Check if user exists and is a vendor (role = 5)
            $stmt = $conn->prepare("
                SELECT u.*, v.id as vendor_id, v.company_name, v.status as vendor_status 
                FROM users u
                JOIN vendors v ON u.id = v.user_id 
                WHERE u.email = :email AND u.role = 5
            ");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if user account is active
                if ($user['status'] !== 'active') {
                    $errors['account'] = 'Your account is not active. Please contact admin.';
                } else {
                    // Login successful - set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['vendor_id'] = $user['vendor_id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['company_name'] = $user['company_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Update last login time
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET last_login = CURRENT_TIMESTAMP 
                        WHERE id = :id
                    ");
                    $stmt->bindParam(':id', $user['id']);
                    $stmt->execute();
                    
                    // Redirect to vendor dashboard
                    redirect('vendor/dashboard.php');
                }
            } else {
                $errors['login'] = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            $errors['database'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Include a custom header for vendor pages
$page_title = 'Vendor Login';
require_once 'includes/vendor_header.php';
?>

<!-- Vendor Login Section -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Vendor Login</h4>
                </div>
                <div class="card-body">
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
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <div class="text-center">
                        <p>Don't have a vendor account? <a href="register.php">Register here</a></p>
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