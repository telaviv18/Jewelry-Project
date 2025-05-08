-- Database schema for Jewelry Online Management System
-- MySQL version

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zipcode VARCHAR(20),
    country VARCHAR(50),
    role INT NOT NULL DEFAULT 4, -- 1=Admin, 2=Manager, 3=Staff, 4=Customer, 5=Vendor
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Vendors table
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    business_phone VARCHAR(20),
    business_email VARCHAR(100),
    tax_id VARCHAR(50),
    business_address TEXT,
    logo VARCHAR(255),
    description TEXT,
    commission_rate DECIMAL(5,2) DEFAULT 10.00, -- Default 10% commission
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, approved, suspended
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    category_id INT NOT NULL,
    vendor_id INT NULL, -- NULL indicates shop's own product, otherwise vendor's product
    sku VARCHAR(50) UNIQUE,
    is_featured TINYINT(1) DEFAULT 0,
    image VARCHAR(255),
    material VARCHAR(100),
    dimensions VARCHAR(100),
    weight VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active', -- active, inactive, pending_approval
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

-- Cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE(user_id, product_id)
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(50) NOT NULL,
    shipping_state VARCHAR(50),
    shipping_zipcode VARCHAR(20) NOT NULL,
    shipping_country VARCHAR(50) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Wishlist table
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE(user_id, product_id)
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Vendor Order Items table (to track vendor's products in orders)
CREATE TABLE IF NOT EXISTS vendor_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    order_id INT NOT NULL,
    order_item_id INT NOT NULL,
    commission_amount DECIMAL(10, 2) NOT NULL,
    vendor_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', -- pending, processing, shipped, delivered, cancelled
    tracking_number VARCHAR(100),
    shipping_provider VARCHAR(100),
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

-- Vendor Payouts table
CREATE TABLE IF NOT EXISTS vendor_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending', -- pending, processed, failed
    notes TEXT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Admin: Insert default admin user (password: admin123)
INSERT IGNORE INTO users (name, email, password, role, status)
VALUES ('Admin User', 'admin@jewelryshop.com', '$2y$10$T7BVU2HPjH0UPD2pq8.Qc.dcw9hX10APXTmI0JQcvXDiAQO0e7Bk2', 1, 'active');

-- Insert sample vendor user (password: vendor123)
INSERT IGNORE INTO users (name, email, password, role, status)
VALUES ('Vendor User', 'vendor@jewelryshop.com', '$2y$10$ZlVFDunFfbm2Y0LnYvqnG.CQhZlzPQgn6VnLrUYzRmQrCEeRmXPJ.', 5, 'active');

-- Insert vendor details for the sample vendor
INSERT IGNORE INTO vendors (user_id, company_name, business_phone, business_email, tax_id, business_address, description, status)
SELECT id, 'Luxury Gems Inc.', '555-123-4567', 'sales@luxurygemsinc.com', 'TX12345678', '123 Jewelry Lane, Diamond District, New York, NY 10001', 'Specializing in high-end diamond and precious stone jewelry since 1995', 'approved'
FROM users
WHERE email = 'vendor@jewelryshop.com';

-- Insert default product categories
INSERT IGNORE INTO categories (name, description)
VALUES 
('Rings', 'Beautiful rings for all occasions'),
('Necklaces', 'Elegant necklaces to complement any outfit'),
('Earrings', 'Stunning earrings from casual to formal'),
('Bracelets', 'Handcrafted bracelets for every style'),
('Watches', 'Luxury timepieces for men and women'),
('Pendants', 'Unique pendants to express your style');

-- Insert sample products (only if products table is empty)
INSERT INTO products (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight)
SELECT 'Diamond Engagement Ring', 
       'A beautiful 1 carat diamond solitaire engagement ring set in 14k white gold.', 
       1999.99, 
       10, 
       (SELECT id FROM categories WHERE name = 'Rings' LIMIT 1), 
       'RNG-DER-001', 
       1, 
       'diamond_ring.jpg', 
       '14k White Gold, Diamond', 
       'Size: 7', 
       '3.5g'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products LIMIT 1);

INSERT INTO products (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight)
SELECT 'Pearl Necklace', 
       'Elegant freshwater pearl necklace with sterling silver clasp.', 
       249.99, 
       15, 
       (SELECT id FROM categories WHERE name = 'Necklaces' LIMIT 1), 
       'NCK-PRL-002', 
       1, 
       'pearl_necklace.jpg', 
       'Freshwater Pearl, Sterling Silver', 
       'Length: 18 inches', 
       '25g'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'NCK-PRL-002');

INSERT INTO products (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight)
SELECT 'Sapphire Stud Earrings', 
       'Beautiful sapphire stud earrings set in 18k yellow gold.', 
       599.99, 
       8, 
       (SELECT id FROM categories WHERE name = 'Earrings' LIMIT 1), 
       'EAR-SPH-003', 
       1, 
       'sapphire_earrings.jpg', 
       '18k Yellow Gold, Sapphire', 
       '8mm diameter', 
       '2g'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'EAR-SPH-003');

INSERT INTO products (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight)
SELECT 'Gold Tennis Bracelet', 
       'Classic diamond tennis bracelet with 2 carats of diamonds set in 14k yellow gold.', 
       1499.99, 
       5, 
       (SELECT id FROM categories WHERE name = 'Bracelets' LIMIT 1), 
       'BRC-GLD-004', 
       1, 
       'gold_bracelet.jpg', 
       '14k Yellow Gold, Diamond', 
       'Length: 7 inches', 
       '15g'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'BRC-GLD-004');

INSERT INTO products (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight)
SELECT 'Luxury Watch', 
       'Swiss-made luxury automatic watch with leather strap.', 
       2999.99, 
       3, 
       (SELECT id FROM categories WHERE name = 'Watches' LIMIT 1), 
       'WTC-LUX-005', 
       1, 
       'luxury_watch.jpg', 
       'Stainless Steel, Leather', 
       'Case: 42mm', 
       '85g'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'WTC-LUX-005');

INSERT INTO products (name, description, price, stock, category_id, sku, is_featured, image, material, dimensions, weight)
SELECT 'Heart Pendant', 
       'Beautiful heart-shaped pendant with ruby center and diamond accents.', 
       799.99, 
       12, 
       (SELECT id FROM categories WHERE name = 'Pendants' LIMIT 1), 
       'PND-HRT-006', 
       1, 
       'heart_pendant.jpg', 
       '18k White Gold, Ruby, Diamond', 
       '15mm x 15mm', 
       '4g'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'PND-HRT-006');

-- Add a sample vendor product
INSERT INTO products (name, description, price, stock, category_id, vendor_id, sku, is_featured, image, material, dimensions, weight, status)
SELECT 
    'Emerald Drop Earrings', 
    'Stunning emerald drop earrings with diamond accents set in 18k white gold.', 
    1299.99, 
    7, 
    (SELECT id FROM categories WHERE name = 'Earrings' LIMIT 1),
    (SELECT id FROM vendors WHERE company_name = 'Luxury Gems Inc.' LIMIT 1),
    'VND-EMR-007', 
    1, 
    'emerald_earrings.jpg', 
    '18k White Gold, Emerald, Diamond', 
    '30mm drop length', 
    '4.5g',
    'active'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM products WHERE sku = 'VND-EMR-007');