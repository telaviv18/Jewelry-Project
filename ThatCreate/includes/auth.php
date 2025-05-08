<?php
// Authentication and authorization functions

/**
 * Start session if not already started
 */
function start_session_if_not_started() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Register a new user
 */
function register_user($name, $email, $password, $pdo) {
    try {
        // Check if email already exists
        if (email_exists($email, $pdo)) {
            return [
                'success' => false,
                'message' => 'Email already exists. Please use a different email or login.'
            ];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$name, $email, $hashed_password, ROLE_CUSTOMER]);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'Registration successful. You can now login.',
                'user_id' => $pdo->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Authenticate user
 */
function login_user($email, $password, $pdo) {
    try {
        // Get user with the provided email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Start session and store user information
            start_session_if_not_started();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Logout user
 */
function logout_user() {
    start_session_if_not_started();
    
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    return [
        'success' => true,
        'message' => 'Logout successful'
    ];
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}

/**
 * Get current logged in user
 */
function get_logged_in_user() {
    start_session_if_not_started();
    
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ];
    }
    
    return null;
}

/**
 * Get current user's role
 */
function get_user_role() {
    start_session_if_not_started();
    
    if (isset($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    
    return null;
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    $user = get_logged_in_user();
    return $user && $user['role'] == $role;
}

/**
 * Check if user is admin
 */
function is_admin() {
    return has_role(ROLE_ADMIN);
}

/**
 * Check if user is manager
 */
function is_manager() {
    $user = get_logged_in_user();
    return $user && ($user['role'] == ROLE_ADMIN || $user['role'] == ROLE_MANAGER);
}

/**
 * Check if user is staff
 */
function is_staff() {
    $user = get_logged_in_user();
    return $user && ($user['role'] == ROLE_ADMIN || $user['role'] == ROLE_MANAGER || $user['role'] == ROLE_STAFF);
}

/**
 * Require authentication or redirect
 */
function require_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        $_SESSION['error_message'] = 'Please login to access this page';
        redirect($redirect_url);
    }
}

/**
 * Require admin access or redirect
 */
function require_admin($redirect_url = 'index.php') {
    require_login();
    
    if (!is_admin()) {
        $_SESSION['error_message'] = 'You do not have permission to access this page';
        redirect($redirect_url);
    }
}

/**
 * Require manager access or redirect
 */
function require_manager($redirect_url = 'index.php') {
    require_login();
    
    if (!is_manager()) {
        $_SESSION['error_message'] = 'You do not have permission to access this page';
        redirect($redirect_url);
    }
}

/**
 * Require staff access or redirect
 */
function require_staff($redirect_url = 'index.php') {
    require_login();
    
    if (!is_staff()) {
        $_SESSION['error_message'] = 'You do not have permission to access this page';
        redirect($redirect_url);
    }
}

/**
 * Check if user is vendor
 */
function is_vendor() {
    return has_role(ROLE_VENDOR);
}

/**
 * Require vendor access or redirect
 */
function require_vendor($redirect_url = 'index.php') {
    require_login();
    
    if (!is_vendor()) {
        $_SESSION['error_message'] = 'You do not have permission to access this page';
        redirect($redirect_url);
    }
}

/**
 * Get vendor details
 */
function get_vendor_details($pdo) {
    $user = get_logged_in_user();
    
    if (!$user || $user['role'] != ROLE_VENDOR) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user['id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Register a new vendor
 */
function register_vendor($company_name, $business_email, $password) {
    global $conn;
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("
        INSERT INTO vendors (company_name, business_email, password, verified) 
        VALUES (:company_name, :business_email, :password, 1)
    ");
    $stmt->bindParam(':company_name', $company_name);
    $stmt->bindParam(':business_email', $business_email);
    $stmt->bindParam(':password', $hashed_password);
    return $stmt->execute();
}

/**
 * Authenticate vendor
 */
function login_vendor($email, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE business_email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $vendor = $stmt->fetch();

    if ($vendor) {
        // Remove the verification check since vendors are automatically verified
        if (password_verify($password, $vendor['password'])) {
            $_SESSION['vendor_id'] = $vendor['id'];
            $_SESSION['user_name'] = $vendor['company_name'];
            return [
                'success' => true,
                'message' => 'Login successful'
            ];
        }
    }

    return [
        'success' => false,
        'message' => 'Invalid email or password'
    ];
}

/**
 * Ensure admin user exists
 */
function ensure_admin_user($pdo) {
    $admin_email = 'admin@gmail.com';
    $admin_password = 'email';
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$admin_email]);
    $admin = $stmt->fetch();

    if ($admin) {
        // Update the existing admin user to ensure correct role and password
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE email = ?");
        $stmt->execute([$hashed_password, 'ROLE_ADMIN', $admin_email]);
    } else {
        // Insert a new admin user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Admin', $admin_email, $hashed_password, 'ROLE_ADMIN']);
    }
}
?>
