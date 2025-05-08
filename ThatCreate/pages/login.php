<?php
// Login page
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Connect to database
$database = new Database();
$conn = $database->connect();

// Check if user is already logged in
if (is_logged_in()) {
    redirect('../index.php');
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate email and password
    $errors = [];
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }
    
    // Attempt login if no validation errors
    if (empty($errors)) {
        $result = login_user($email, $password, $conn);
        
        if ($result['success']) {
            // Check if user is staff and redirect to admin panel
            if (is_staff()) {
                redirect('../admin/index.php');
            } else {
                // Redirect to previous page or home
                $redirect_to = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : '../index.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect_to);
            }
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Remember the page that referred the user to login
if (isset($_SERVER['HTTP_REFERER']) && !strpos($_SERVER['HTTP_REFERER'], 'login.php') && !strpos($_SERVER['HTTP_REFERER'], 'register.php')) {
    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'];
}

// Page title
$page_title = 'Login';

// Include header
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card form-container">
                <div class="card-body">
                    <h2 class="text-center mb-4">Login</h2>
                    
                    <?php if (!empty($errors)): ?>
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
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= isset($email) ? $email : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p>Don't have an account? <a href="register.php">Register</a></p>
                        <p><a href="#">Forgot your password?</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
