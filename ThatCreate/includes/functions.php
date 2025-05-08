<?php
// General utility functions used across the application

/**
 * Clean input to prevent XSS
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format currency
 */
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Format date
 */
function format_date($date, $format = 'M d, Y') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Generate random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: " . ROOT_URL . $url);
    exit;
}

/**
 * Display error message
 */
function display_error($message) {
    return '<div class="alert alert-danger" role="alert">' . $message . '</div>';
}

/**
 * Display success message
 */
function display_success($message) {
    return '<div class="alert alert-success" role="alert">' . $message . '</div>';
}

/**
 * Display pagination 
 */
function get_pagination($total_pages, $current_page, $base_url) {
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . ($current_page - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active" aria-current="page"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . ($current_page + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">Next</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get product image URL (with default fallback)
 */
function get_product_image_url($image_path) {
    if (!empty($image_path) && file_exists($image_path)) {
        return $image_path;
    }
    // Return default jewelry image SVG
    return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMjgiIGhlaWdodD0iMTI4IiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9Im5vbmUiIHN0cm9rZT0iY3VycmVudENvbG9yIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgY2xhc3M9ImZlYXRoZXIgZmVhdGhlci1kaWFtb25kIj48cGF0aCBkPSJNNi43MyAxNWE2IDYgMCAwIDEtMS4xLTMuMzQiPjwvcGF0aD48cGF0aCBkPSJNMi45MiA5LjIzQTcuOTQgNy45NCAwIDAgMSAxMiA0Ij48L3BhdGg+PHBhdGggZD0iTTE1LjQxIDIyYzEuMzMtMS4yMyAyLjQyLTIuNzkgMy4yLTQuNTEiPjwvcGF0aD48cGF0aCBkPSJNMjEuMDggMTRjLjMyLTEuNDcuMjUtMy4wMS0uMi00LjQ0Ij48L3BhdGg+PHBhdGggZD0iTTE0Ljk2IDJhOCA4IDAgMCAxIDQuODQgNS42II48L3BhdGg+PC9zdmc+';
}

/**
 * Get user role name
 */
function get_role_name($role_id) {
    switch($role_id) {
        case ROLE_ADMIN:
            return 'Administrator';
        case ROLE_MANAGER:
            return 'Manager';
        case ROLE_STAFF:
            return 'Staff';
        case ROLE_CUSTOMER:
            return 'Customer';
        case ROLE_VENDOR:
            return 'Vendor';
        default:
            return 'Unknown';
    }
}

/**
 * Format order status with color
 */
function format_order_status($status) {
    switch($status) {
        case ORDER_PENDING:
            return '<span class="badge bg-warning">Pending</span>';
        case ORDER_PROCESSING:
            return '<span class="badge bg-primary">Processing</span>';
        case ORDER_SHIPPED:
            return '<span class="badge bg-info">Shipped</span>';
        case ORDER_DELIVERED:
            return '<span class="badge bg-success">Delivered</span>';
        case ORDER_CANCELLED:
            return '<span class="badge bg-danger">Cancelled</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

/**
 * Truncate text
 */
function truncate_text($text, $length = 100) {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * Check if email exists
 */
function email_exists($email, $pdo) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}

/**
 * Upload file
 */
function upload_file($file, $destination) {
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return [
            'success' => false,
            'message' => 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'
        ];
    }
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Allowed types: ' . implode(', ', ALLOWED_EXTENSIONS)
        ];
    }
    
    // Generate unique filename
    $new_filename = time() . '_' . generate_random_string(8) . '.' . $file_extension;
    $upload_path = $destination . $new_filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $upload_path
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to upload file'
        ];
    }
}
?>
