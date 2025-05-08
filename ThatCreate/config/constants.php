<?php
// Application constants
define('ROOT_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/ThatCreate/');
define('SITE_NAME', 'Jewelry Online Management System');
define('ADMIN_EMAIL', 'admin@jewelryshop.com');

// User roles
define('ROLE_ADMIN', 1);
define('ROLE_MANAGER', 2);
define('ROLE_STAFF', 3);
define('ROLE_CUSTOMER', 4);
define('ROLE_VENDOR', 5);

// Order statuses
define('ORDER_PENDING', 'pending');
define('ORDER_PROCESSING', 'processing');
define('ORDER_SHIPPED', 'shipped');
define('ORDER_DELIVERED', 'delivered');
define('ORDER_CANCELLED', 'cancelled');

// Pagination
define('ITEMS_PER_PAGE', 12);

// File upload
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);  // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Product categories (match with database categories)
$PRODUCT_CATEGORIES = [
    1 => 'Rings',
    2 => 'Necklaces',
    3 => 'Earrings',
    4 => 'Bracelets',
    5 => 'Watches',
    6 => 'Pendants'
];
?>
