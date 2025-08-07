-- TeWuNeed Database Setup
-- Run this SQL script in phpMyAdmin or MySQL command line

-- Create database
CREATE DATABASE IF NOT EXISTS db_tewuneed2;
USE db_tewuneed2;

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    NAME VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    STATUS ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    NAME VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) NULL,
    sku VARCHAR(100) UNIQUE,
    stock_quantity INT DEFAULT 0,
    category_id INT,
    image VARCHAR(255),
    gallery TEXT,
    STATUS ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    featured BOOLEAN DEFAULT FALSE,
    weight DECIMAL(8,2) DEFAULT 0,
    dimensions VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_status (STATUS),
    INDEX idx_featured (featured)
);

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    PASSWORD VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    avatar VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    STATUS ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (STATUS)
);

-- User addresses table
CREATE TABLE user_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    TYPE ENUM('home', 'work', 'other') DEFAULT 'home',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company VARCHAR(100),
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(100) DEFAULT 'Indonesia',
    phone VARCHAR(20),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_default (is_default)
);

-- Shopping cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_id VARCHAR(255),
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    INDEX idx_product (product_id)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    STATUS ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    shipping_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (STATUS),
    INDEX idx_order_number (order_number)
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
);

-- Admin users table
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    PASSWORD VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'manager') DEFAULT 'admin',
    STATUS ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Insert sample categories
INSERT INTO categories (NAME, slug, description) VALUES
('Electronics', 'electronics', 'Electronic devices and gadgets'),
('Cosmetics', 'cosmetics', 'Beauty and cosmetic products'),
('Sports', 'sports', 'Sports and fitness equipment'),
('Food & Snacks', 'food-snacks', 'Food items and snacks'),
('Health & Medicine', 'health-medicine', 'Health and medical products'),
('Vegetables', 'vegetables', 'Fresh vegetables and produce');

-- Insert sample products
INSERT INTO products (NAME, slug, description, short_description, price, sale_price, sku, stock_quantity, category_id, image, featured) VALUES
('iPhone 14 Pro', 'iphone-14-pro', 'Latest iPhone with advanced camera system, A16 Bionic chip, and Dynamic Island. Perfect for photography enthusiasts and power users.', 'Premium smartphone with excellent camera and performance', 15000000, 14000000, 'IP14PRO001', 10, 1, 'iPhone 14 Best Seller Edition.jpg', 1),
('Samsung Galaxy S23', 'samsung-galaxy-s23', 'Flagship Android smartphone with exceptional display, camera, and performance. Features the latest Snapdragon processor.', 'High-performance Android device with premium features', 12000000, NULL, 'SGS23001', 15, 1, 'default-product.jpg', 1),
('MacBook Air M2', 'macbook-air-m2', 'Ultra-thin laptop with M2 chip, stunning Retina display, and all-day battery life. Perfect for work and creativity.', 'Lightweight laptop with powerful M2 chip', 18000000, 17000000, 'MBA2001', 8, 1, 'default-product.jpg', 1),
('Serum Vitamin C 20%', 'serum-vitamin-c-20', 'Anti-aging vitamin C serum that brightens skin, reduces dark spots, and provides antioxidant protection.', 'Brightening and anti-aging vitamin C serum', 250000, 200000, 'SVC20001', 50, 2, 'Serum Vitamin C 20%.jpg', 1),
('Lipstick Matte Red', 'lipstick-matte-red', 'Long-lasting matte lipstick in classic red shade. Provides full coverage and comfortable wear all day.', 'Bold red matte finish lipstick', 150000, NULL, 'LMR001', 30, 2, 'Lipstick Matte Red.jpeg', 0),
('BB Cream SPF 50', 'bb-cream-spf-50', 'Multi-functional BB cream with high SPF protection, coverage, and skincare benefits in one product.', 'All-in-one BB cream with sun protection', 180000, 160000, 'BBC50001', 25, 2, 'BB Cream SPF 50 PA+++.jpg', 1),
('Dumbbell Set 20kg', 'dumbbell-set-20kg', 'Professional adjustable dumbbell set perfect for home workouts. Includes multiple weight plates and secure locking system.', 'Adjustable weight dumbbell set for home gym', 800000, 750000, 'DS20001', 5, 3, 'drumbel.jpg', 1),
('Yoga Mat Premium', 'yoga-mat-premium', 'High-quality non-slip yoga mat made from eco-friendly materials. Perfect for yoga, pilates, and fitness exercises.', 'Non-slip premium yoga mat for all exercises', 300000, NULL, 'YMP001', 20, 3, 'matras yoga.jpg', 0),
('Basketball Spalding', 'basketball-spalding', 'Official size basketball with excellent grip and durability. Perfect for indoor and outdoor play.', 'Professional basketball for indoor/outdoor use', 450000, 400000, 'BSP001', 12, 3, 'Bola Basket Spalding NBA.jpg', 0),
('Chitato Snack', 'chitato-snack', 'Popular Indonesian potato chips with various flavors. Crispy and delicious snack for any occasion.', 'Crispy potato chips with authentic Indonesian taste', 15000, NULL, 'CHS001', 100, 4, 'chitato.jpg', 0),
('Oreo Cookies', 'oreo-cookies', 'Classic chocolate sandwich cookies with cream filling. Perfect for snacking or dunking in milk.', 'Classic chocolate sandwich cookies', 25000, 22000, 'ORC001', 80, 4, 'Oreo Original Cookies.jpg', 1),
('Vitamin C Tablets', 'vitamin-c-tablets', 'High-potency vitamin C supplement to boost immune system and overall health. 60 tablets per bottle.', 'Immune-boosting vitamin C supplement', 120000, 100000, 'VCT001', 40, 5, 'Calcium + D3 (60 tabs).jpg', 0),
('Fresh Tomatoes', 'fresh-tomatoes', 'Fresh, ripe tomatoes perfect for cooking, salads, and sauces. Locally sourced and organic.', 'Fresh organic tomatoes for cooking', 8000, NULL, 'FT001', 200, 6, 'Tomato.jpg', 0),
('Fresh Broccoli', 'fresh-broccoli', 'Nutritious fresh broccoli rich in vitamins and minerals. Perfect for healthy meals and side dishes.', 'Fresh organic broccoli rich in nutrients', 12000, 10000, 'FB001', 150, 6, 'brokoli.jpg', 1);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, PASSWORD, first_name, last_name, role) 
VALUES ('admin', 'admin@tewuneed.com', '	', 'Admin', 'User', 'super_admin');

-- Create a test user (password: user123)
INSERT INTO users (email, PASSWORD, first_name, last_name, phone, email_verified, STATUS) 
VALUES ('user@tewuneed.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'User', '081234567890', 1, 'active');


-- Product reviews table
CREATE TABLE product_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review_text TEXT,
    verified_purchase BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    STATUS ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_user (user_id),
    INDEX idx_rating (rating),
    INDEX idx_status (STATUS)
);

-- Wishlist table
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    INDEX idx_user (user_id),
    INDEX idx_product (product_id)
);

-- Coupons table
CREATE TABLE coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    CODE VARCHAR(50) UNIQUE NOT NULL,
    NAME VARCHAR(255) NOT NULL,
    description TEXT,
    TYPE ENUM('percentage', 'fixed_amount') NOT NULL,
    VALUE DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0,
    maximum_discount DECIMAL(10,2) NULL,
    usage_limit INT NULL,
    used_count INT DEFAULT 0,
    user_limit INT DEFAULT 1,
    valid_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP NULL,
    STATUS ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (CODE),
    INDEX idx_status (STATUS),
    INDEX idx_valid_dates (valid_from, valid_until)
);

-- Coupon usage tracking
CREATE TABLE coupon_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_coupon (coupon_id),
    INDEX idx_user (user_id),
    INDEX idx_order (order_id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    TYPE ENUM('order_update', 'promotion', 'system', 'review_reminder') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    DATA JSON NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (TYPE),
    INDEX idx_read (read_at),
    INDEX idx_created (created_at)
);

-- Product attributes table
CREATE TABLE product_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    attribute_name VARCHAR(100) NOT NULL,
    attribute_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_attribute (attribute_name)
);

-- Shipping methods table
CREATE TABLE shipping_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    NAME VARCHAR(100) NOT NULL,
    description TEXT,
    cost DECIMAL(10,2) NOT NULL,
    estimated_days VARCHAR(50),
    STATUS ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (STATUS)
);

-- Payment methods table
CREATE TABLE payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    NAME VARCHAR(100) NOT NULL,
    TYPE ENUM('bank_transfer', 'credit_card', 'e_wallet', 'cod') NOT NULL,
    description TEXT,
    instructions TEXT,
    fee_percentage DECIMAL(5,2) DEFAULT 0,
    fee_fixed DECIMAL(10,2) DEFAULT 0,
    STATUS ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (TYPE),
    INDEX idx_status (STATUS)
);

-- Order status history table
CREATE TABLE order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    STATUS ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL,
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_status (STATUS),
    INDEX idx_created (created_at)
);

-- Website settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- Insert more sample products
INSERT INTO products (NAME, slug, description, short_description, price, sale_price, sku, stock_quantity, category_id, image, featured) VALUES
-- Electronics
('Sony Headphones WH-1000XM4', 'sony-headphones-wh1000xm4', 'Industry-leading noise canceling wireless headphones with 30-hour battery life and premium sound quality.', 'Premium noise-canceling wireless headphones', 4500000, 4200000, 'SH1000001', 15, 1, 'default-product.jpg', 1),
('iPad Air 5th Gen', 'ipad-air-5th-gen', 'Powerful tablet with M1 chip, stunning 10.9-inch display, and support for Apple Pencil and Magic Keyboard.', 'Powerful tablet with M1 chip and beautiful display', 8500000, NULL, 'IPA5001', 12, 1, 'default-product.jpg', 0),
('Gaming Mouse Logitech', 'gaming-mouse-logitech', 'High-precision gaming mouse with customizable RGB lighting and programmable buttons for competitive gaming.', 'Professional gaming mouse with RGB lighting', 850000, 750000, 'GML001', 25, 1, 'default-product.jpg', 0),

-- Cosmetics
('Foundation Liquid Natural', 'foundation-liquid-natural', 'Full-coverage liquid foundation with natural finish that lasts all day. Available in multiple shades.', 'Long-lasting liquid foundation with natural finish', 320000, 280000, 'FLN001', 40, 2, 'default-product.jpg', 1),
('Mascara Waterproof Black', 'mascara-waterproof-black', 'Waterproof mascara that lengthens and volumizes lashes without smudging or flaking all day.', 'Waterproof mascara for dramatic lashes', 180000, NULL, 'MWB001', 35, 2, 'Mascara Volume X5.jpg', 0),
('Skincare Set Complete', 'skincare-set-complete', 'Complete skincare routine set including cleanser, toner, serum, and moisturizer for healthy glowing skin.', 'Complete skincare routine for glowing skin', 650000, 580000, 'SSC001', 20, 2, 'Skincare Set Best Seller.jpg', 1),
('Lip Balm Moisturizing', 'lip-balm-moisturizing', 'Nourishing lip balm with natural ingredients that provides long-lasting moisture and protection.', 'Moisturizing lip balm with natural ingredients', 45000, NULL, 'LBM001', 60, 2, 'default-product.jpg', 0),

-- Sports
('Treadmill Electric Home', 'treadmill-electric-home', 'Professional electric treadmill for home use with multiple speed settings and built-in workout programs.', 'Electric treadmill with multiple workout programs', 12000000, 11000000, 'TEH001', 3, 3, 'treadmill.jpeg', 1),
('Protein Powder Whey', 'protein-powder-whey', 'High-quality whey protein powder for muscle building and recovery. Available in chocolate and vanilla flavors.', 'Premium whey protein for muscle building', 450000, 400000, 'PPW001', 30, 3, 'default-product.jpg', 0),
('Running Shoes Nike', 'running-shoes-nike', 'Lightweight running shoes with advanced cushioning technology for comfort during long runs.', 'Comfortable running shoes with advanced cushioning', 1200000, 1050000, 'RSN001', 18, 3, 'default-product.jpg', 1),
('Resistance Bands Set', 'resistance-bands-set', 'Complete set of resistance bands with different resistance levels for full-body workouts at home.', 'Complete resistance bands set for home workouts', 250000, NULL, 'RBS001', 25, 3, 'Resistance Band.jpg', 0),

-- Food & Snacks
('Premium Coffee Beans', 'premium-coffee-beans', 'Single-origin arabica coffee beans with rich flavor and aroma. Perfect for coffee enthusiasts.', 'Premium single-origin arabica coffee beans', 180000, 160000, 'PCB001', 50, 4, 'Coffee Premium Best Seller.jpg', 1),
('Chocolate Dark Premium', 'chocolate-dark-premium', 'Premium dark chocolate with 70% cocoa content. Rich, smooth, and perfect for chocolate lovers.', 'Premium dark chocolate with rich cocoa flavor', 85000, NULL, 'CDP001', 40, 4, 'Cokelat Dark Premium Advanced.jpg', 0),
('Honey Pure Natural', 'honey-pure-natural', 'Pure natural honey harvested from local beekeepers. Rich in antioxidants and natural sweetness.', 'Pure natural honey with health benefits', 120000, 100000, 'HPN001', 35, 4, 'Madu Hutan Murni Max.jpg', 1),
('Nuts Mixed Premium', 'nuts-mixed-premium', 'Premium mixed nuts including almonds, cashews, and walnuts. Perfect healthy snack option.', 'Premium mixed nuts for healthy snacking', 95000, 85000, 'NMP001', 45, 4, 'default-product.jpg', 0),

-- Health & Medicine
('Multivitamin Complete', 'multivitamin-complete', 'Complete multivitamin supplement with essential vitamins and minerals for daily health support.', 'Complete daily multivitamin supplement', 150000, 130000, 'MVC001', 60, 5, 'Vitamin Complete Best Seller.jpg', 1),
('Fish Oil Omega-3', 'fish-oil-omega3', 'High-quality fish oil supplement rich in omega-3 fatty acids for heart and brain health.', 'Omega-3 fish oil for heart and brain health', 200000, NULL, 'FO3001', 40, 5, 'default-product.jpg', 0),
('Probiotics Digestive', 'probiotics-digestive', 'Advanced probiotic supplement to support digestive health and immune system function.', 'Probiotic supplement for digestive health', 180000, 160000, 'PD001', 35, 5, 'default-product.jpg', 0),

-- Vegetables
('Organic Spinach Fresh', 'organic-spinach-fresh', 'Fresh organic spinach leaves rich in iron and vitamins. Perfect for salads and cooking.', 'Fresh organic spinach rich in nutrients', 15000, NULL, 'OSF001', 100, 6, 'bayam.jpg', 0),
('Carrots Organic Bundle', 'carrots-organic-bundle', 'Fresh organic carrots bundle, sweet and crunchy. Great source of beta-carotene and fiber.', 'Fresh organic carrots rich in beta-carotene', 18000, 15000, 'COB001', 80, 6, 'default-product.jpg', 1),
('Bell Peppers Mixed', 'bell-peppers-mixed', 'Colorful mix of fresh bell peppers - red, yellow, and green. Perfect for stir-fries and salads.', 'Fresh colorful bell peppers for cooking', 25000, NULL, 'BPM001', 60, 6, 'default-product.jpg', 0),
('Cucumber Fresh Organic', 'cucumber-fresh-organic', 'Fresh organic cucumbers, crisp and refreshing. Perfect for salads and healthy snacking.', 'Fresh organic cucumbers for salads', 12000, 10000, 'CFO001', 90, 6, 'timun.jpg', 0);

-- Insert shipping methods
INSERT INTO shipping_methods (NAME, description, cost, estimated_days, STATUS) VALUES
('Standard Shipping', 'Regular delivery within city', 15000, '3-5 business days', 'active'),
('Express Shipping', 'Fast delivery within city', 25000, '1-2 business days', 'active'),
('Same Day Delivery', 'Delivery within same day', 50000, 'Same day', 'active'),
('Free Shipping', 'Free delivery for orders above Rp 100,000', 0, '5-7 business days', 'active');

-- Insert payment methods
INSERT INTO payment_methods (NAME, TYPE, description, instructions, fee_percentage, fee_fixed, STATUS) VALUES
('Bank Transfer BCA', 'bank_transfer', 'Transfer to BCA account', 'Transfer to account: 1234567890 (TeWuNeed)', 0, 0, 'active'),
('Bank Transfer Mandiri', 'bank_transfer', 'Transfer to Mandiri account', 'Transfer to account: 0987654321 (TeWuNeed)', 0, 0, 'active'),
('Credit Card', 'credit_card', 'Pay with Visa/Mastercard', 'Secure payment with credit card', 2.5, 0, 'active'),
('GoPay', 'e_wallet', 'Pay with GoPay e-wallet', 'Scan QR code or enter phone number', 1.5, 0, 'active'),
('OVO', 'e_wallet', 'Pay with OVO e-wallet', 'Scan QR code or enter phone number', 1.5, 0, 'active'),
('Dana', 'e_wallet', 'Pay with Dana e-wallet', 'Scan QR code or enter phone number', 1.5, 0, 'active'),
('Cash on Delivery', 'cod', 'Pay when item is delivered', 'Pay cash to delivery person', 0, 5000, 'active');

-- Insert sample coupons
INSERT INTO coupons (CODE, NAME, description, TYPE, VALUE, minimum_amount, usage_limit, valid_until, STATUS) VALUES
('WELCOME10', 'Welcome Discount', 'Get 10% off on your first order', 'percentage', 10.00, 100000, 100, DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
('SAVE50K', 'Save 50K', 'Get Rp 50,000 off on orders above Rp 500,000', 'fixed_amount', 50000.00, 500000, 50, DATE_ADD(NOW(), INTERVAL 15 DAY), 'active'),
('FREESHIP', 'Free Shipping', 'Free shipping on any order', 'fixed_amount', 25000.00, 0, 200, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active');

-- Insert website settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'TeWuNeed', 'text', 'Website name'),
('site_description', 'Your One-Stop Shopping Destination', 'text', 'Website description'),
('contact_email', 'info@tewuneed.com', 'text', 'Contact email address'),
('contact_phone', '+62 21 1234 5678', 'text', 'Contact phone number'),
('free_shipping_minimum', '100000', 'number', 'Minimum amount for free shipping'),
('tax_rate', '10', 'number', 'Tax rate percentage'),
('currency', 'IDR', 'text', 'Default currency'),
('items_per_page', '12', 'number', 'Products per page'),
('enable_reviews', 'true', 'boolean', 'Enable product reviews'),
('enable_wishlist', 'true', 'boolean', 'Enable wishlist feature');

-- Create additional test users
INSERT INTO users (email, PASSWORD, first_name, last_name, phone, email_verified, STATUS) VALUES
('user@tewuneed.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'User', '081234567890', 1, 'active'),
('john.doe@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', '081234567891', 1, 'active'),
('jane.smith@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', '081234567892', 1, 'active');

-- Insert sample product reviews
INSERT INTO product_reviews (product_id, user_id, rating, title, review_text, verified_purchase, STATUS) VALUES
(1, 1, 5, 'Amazing iPhone!', 'The iPhone 14 Pro exceeded my expectations. Camera quality is outstanding and battery life is excellent.', 1, 'approved'),
(1, 2, 4, 'Great phone but expensive', 'Love the features and performance, but the price is quite high. Overall satisfied with the purchase.', 1, 'approved'),
(4, 1, 5, 'Best serum ever!', 'This vitamin C serum has transformed my skin. Visible results in just 2 weeks. Highly recommended!', 1, 'approved'),
(4, 3, 5, 'Glowing skin guaranteed', 'My skin looks brighter and more radiant after using this serum. Will definitely repurchase.', 1, 'approved'),
(7, 2, 4, 'Good quality dumbbells', 'Solid build quality and easy to adjust weights. Perfect for home workouts.', 1, 'approved'),
(11, 1, 5, 'Delicious cookies!', 'Classic Oreo taste that never gets old. Perfect for snacking and sharing with family.', 1, 'approved'),
(15, 3, 5, 'Excellent headphones', 'Noise cancellation is incredible and sound quality is top-notch. Worth every penny.', 1, 'approved'),
(18, 2, 4, 'Great foundation', 'Good coverage and natural finish. Lasts all day without looking cakey.', 1, 'approved');

-- Insert sample product attributes
INSERT INTO product_attributes (product_id, attribute_name, attribute_value) VALUES
-- iPhone 14 Pro attributes
(1, 'Storage', '128GB'),
(1, 'Color', 'Deep Purple'),
(1, 'Screen Size', '6.1 inch'),
(1, 'Camera', '48MP Main Camera'),
(1, 'Processor', 'A16 Bionic'),
-- Samsung Galaxy S23 attributes
(2, 'Storage', '256GB'),
(2, 'Color', 'Phantom Black'),
(2, 'Screen Size', '6.1 inch'),
(2, 'Camera', '50MP Triple Camera'),
(2, 'Processor', 'Snapdragon 8 Gen 2'),
-- Serum attributes
(4, 'Volume', '30ml'),
(4, 'Skin Type', 'All Skin Types'),
(4, 'Key Ingredient', 'Vitamin C 20%'),
(4, 'Origin', 'South Korea'),
-- Dumbbell attributes
(7, 'Weight Range', '5kg - 20kg'),
(7, 'Material', 'Cast Iron'),
(7, 'Adjustable', 'Yes'),
(7, 'Warranty', '1 Year');

-- Insert sample orders
INSERT INTO orders (order_number, user_id, STATUS, total_amount, shipping_amount, payment_method, payment_status, shipping_address, billing_address) VALUES
('ORD-2024-001', 1, 'delivered', 14025000, 25000, 'Bank Transfer BCA', 'paid',
'Test User\nJl. Sudirman No. 123\nJakarta Pusat, DKI Jakarta 10110\nPhone: 081234567890',
'Test User\nJl. Sudirman No. 123\nJakarta Pusat, DKI Jakarta 10110\nPhone: 081234567890'),
('ORD-2024-002', 2, 'shipped', 4225000, 25000, 'GoPay', 'paid',
'John Doe\nJl. Thamrin No. 456\nJakarta Pusat, DKI Jakarta 10230\nPhone: 081234567891',
'John Doe\nJl. Thamrin No. 456\nJakarta Pusat, DKI Jakarta 10230\nPhone: 081234567891'),
('ORD-2024-003', 3, 'processing', 780000, 15000, 'OVO', 'paid',
'Jane Smith\nJl. Gatot Subroto No. 789\nJakarta Selatan, DKI Jakarta 12930\nPhone: 081234567892',
'Jane Smith\nJl. Gatot Subroto No. 789\nJakarta Selatan, DKI Jakarta 12930\nPhone: 081234567892');

-- Insert order items
INSERT INTO order_items (order_id, product_id, quantity, price, total) VALUES
-- Order 1 items
(1, 1, 1, 14000000, 14000000),
-- Order 2 items
(2, 15, 1, 4200000, 4200000),
-- Order 3 items
(3, 7, 1, 750000, 750000),
(3, 4, 1, 200000, 200000);

-- Insert order status history
INSERT INTO order_status_history (order_id, STATUS, notes) VALUES
(1, 'pending', 'Order placed successfully'),
(1, 'processing', 'Payment confirmed, preparing items'),
(1, 'shipped', 'Order shipped via JNE Express'),
(1, 'delivered', 'Order delivered successfully'),
(2, 'pending', 'Order placed successfully'),
(2, 'processing', 'Payment confirmed, preparing items'),
(2, 'shipped', 'Order shipped via JNT Express'),
(3, 'pending', 'Order placed successfully'),
(3, 'processing', 'Payment confirmed, preparing items');

-- Insert sample notifications
INSERT INTO notifications (user_id, TYPE, title, message) VALUES
(1, 'order_update', 'Order Delivered', 'Your order ORD-2024-001 has been delivered successfully. Thank you for shopping with us!'),
(1, 'promotion', 'Special Discount', 'Get 20% off on all electronics this weekend. Use code TECH20 at checkout.'),
(2, 'order_update', 'Order Shipped', 'Your order ORD-2024-002 has been shipped and is on the way to your address.'),
(2, 'review_reminder', 'Review Your Purchase', 'How was your experience with Sony Headphones WH-1000XM4? Leave a review and help other customers.'),
(3, 'order_update', 'Order Processing', 'Your order ORD-2024-003 is being processed. We will notify you once it ships.'),
(3, 'promotion', 'Welcome Bonus', 'Welcome to TeWuNeed! Enjoy 10% off your first order with code WELCOME10.');

-- Insert sample wishlist items
INSERT INTO wishlist (user_id, product_id) VALUES
(1, 2), -- User 1 likes Samsung Galaxy S23
(1, 16), -- User 1 likes iPad Air
(1, 19), -- User 1 likes Skincare Set
(2, 1), -- User 2 likes iPhone 14 Pro
(2, 20), -- User 2 likes Treadmill
(2, 23), -- User 2 likes Premium Coffee
(3, 4), -- User 3 likes Serum Vitamin C
(3, 7), -- User 3 likes Dumbbell Set
(3, 18); -- User 3 likes Foundation

-- Update database name in config file
UPDATE settings SET setting_value = 'db_tewuneed2' WHERE setting_key = 'database_name';

-- Reviews table (simplified version for admin dashboard)
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    COMMENT TEXT,
    STATUS ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_user (user_id),
    INDEX idx_rating (rating),
    INDEX idx_status (STATUS)
);

-- Coupons table (updated structure for admin dashboard)
CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    CODE VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    TYPE ENUM('percentage', 'fixed') NOT NULL,
    VALUE DECIMAL(10,2) NOT NULL,
    min_amount DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT 0,
    used_count INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    STATUS ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (CODE),
    INDEX idx_status (STATUS),
    INDEX idx_expires (expires_at)
);

-- Settings table for website configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- Insert sample reviews
INSERT INTO reviews (product_id, user_id, rating, COMMENT, STATUS) VALUES
(1, 1, 5, 'Amazing iPhone! The camera quality is outstanding and battery life is excellent. Highly recommended for anyone looking for a premium smartphone.', 'approved'),
(1, 2, 4, 'Great phone but quite expensive. The features are impressive and performance is smooth. Overall satisfied with the purchase.', 'approved'),
(1, 3, 5, 'Best iPhone I have ever used. The Dynamic Island feature is innovative and the build quality is top-notch.', 'pending'),
(2, 1, 4, 'Excellent Android phone with great camera system. The display is vibrant and the performance is very smooth.', 'approved'),
(2, 2, 5, 'Samsung Galaxy S23 exceeded my expectations. Fast processor, beautiful design, and amazing camera quality.', 'approved'),
(4, 1, 5, 'This vitamin C serum is incredible! My skin looks brighter and more radiant after just 2 weeks of use.', 'approved'),
(4, 3, 5, 'Best serum I have ever tried. Visible results in reducing dark spots and improving skin texture.', 'approved'),
(4, 2, 4, 'Good quality serum with noticeable results. A bit pricey but worth the investment for healthy skin.', 'pending'),
(7, 2, 4, 'Solid dumbbell set perfect for home workouts. Easy to adjust weights and good build quality.', 'approved'),
(7, 3, 5, 'Excellent dumbbells for strength training. The weight adjustment mechanism is smooth and secure.', 'approved'),
(11, 1, 5, 'Classic Oreo cookies that never disappoint. Perfect for snacking and sharing with family.', 'approved'),
(11, 2, 4, 'Delicious cookies with the perfect balance of chocolate and cream. Always a favorite treat.', 'approved'),
(15, 1, 5, 'Sony headphones are amazing! Noise cancellation is incredible and sound quality is top-notch.', 'approved'),
(15, 3, 5, 'Best headphones for music lovers. Comfortable to wear for long periods and excellent audio quality.', 'approved'),
(18, 2, 4, 'Great foundation with good coverage and natural finish. Lasts all day without looking cakey.', 'approved'),
(19, 1, 5, 'Complete skincare set that transformed my skin routine. All products work well together.', 'approved'),
(23, 2, 5, 'Premium coffee beans with rich flavor and aroma. Perfect for coffee enthusiasts who appreciate quality.', 'approved'),
(26, 3, 4, 'Effective multivitamin supplement. Feel more energetic since I started taking these daily.', 'approved'),
(13, 1, 5, 'Fresh and organic tomatoes with great taste. Perfect for cooking and making fresh salads.', 'approved'),
(14, 2, 4, 'Nutritious broccoli that stays fresh for days. Great addition to healthy meals and stir-fries.', 'approved');

-- Insert sample coupons
INSERT INTO coupons (CODE, description, TYPE, VALUE, min_amount, max_uses, expires_at, STATUS) VALUES
('WELCOME10', 'Welcome discount for new customers - Get 10% off on your first order', 'percentage', 10.00, 100000, 100, DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
('SAVE50K', 'Save Rp 50,000 on orders above Rp 500,000', 'fixed', 50000.00, 500000, 50, DATE_ADD(NOW(), INTERVAL 15 DAY), 'active'),
('FREESHIP', 'Free shipping coupon - Get free delivery on any order', 'fixed', 25000.00, 0, 200, DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'),
('ELECTRONICS20', 'Special discount for electronics category - 20% off', 'percentage', 20.00, 200000, 75, DATE_ADD(NOW(), INTERVAL 10 DAY), 'active'),
('COSMETICS15', 'Beauty products discount - 15% off on all cosmetics', 'percentage', 15.00, 150000, 60, DATE_ADD(NOW(), INTERVAL 20 DAY), 'active'),
('SPORTS25K', 'Sports equipment discount - Rp 25,000 off', 'fixed', 25000.00, 300000, 40, DATE_ADD(NOW(), INTERVAL 12 DAY), 'active'),
('HEALTH100K', 'Health products mega discount - Rp 100,000 off', 'fixed', 100000.00, 800000, 25, DATE_ADD(NOW(), INTERVAL 5 DAY), 'active'),
('WEEKEND30', 'Weekend special - 30% off on selected items', 'percentage', 30.00, 250000, 30, DATE_ADD(NOW(), INTERVAL 3 DAY), 'active'),
('EXPIRED10', 'Expired test coupon', 'percentage', 10.00, 50000, 50, DATE_SUB(NOW(), INTERVAL 5 DAY), 'expired'),
('INACTIVE20', 'Inactive test coupon', 'percentage', 20.00, 100000, 100, DATE_ADD(NOW(), INTERVAL 30 DAY), 'inactive');

-- Insert website settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'TeWuNeed'),
('site_description', 'Your trusted online shopping destination'),
('site_email', 'admin@tewuneed.com'),
('site_phone', '+62 123 456 7890'),
('site_address', 'Jakarta, Indonesia'),
('currency', 'IDR'),
('tax_rate', '10'),
('shipping_fee', '15000'),
('free_shipping_min', '100000'),
('order_prefix', 'TWN'),
('items_per_page', '12'),
('maintenance_mode', '0'),
('allow_guest_checkout', '1'),
('email_notifications', '1'),
('auto_approve_reviews', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Update some sample orders to have more realistic data
UPDATE orders SET 
    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY),
    updated_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 25) DAY)
WHERE id IN (1, 2, 3);

-- Add more sample orders for better dashboard statistics
INSERT INTO orders (order_number, user_id, STATUS, total_amount, shipping_amount, tax_amount, payment_method, payment_status, shipping_address, created_at) VALUES
('TWN-2024-004', 1, 'pending', 350000, 15000, 35000, 'Bank Transfer BCA', 'pending', 'Test User\nJl. Sudirman No. 123\nJakarta Pusat, DKI Jakarta 10110', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('TWN-2024-005', 2, 'processing', 1250000, 0, 125000, 'GoPay', 'paid', 'John Doe\nJl. Thamrin No. 456\nJakarta Pusat, DKI Jakarta 10230', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('TWN-2024-006', 3, 'shipped', 580000, 25000, 58000, 'OVO', 'paid', 'Jane Smith\nJl. Gatot Subroto No. 789\nJakarta Selatan, DKI Jakarta 12930', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('TWN-2024-007', 1, 'delivered', 4200000, 0, 420000, 'Credit Card', 'paid', 'Test User\nJl. Sudirman No. 123\nJakarta Pusat, DKI Jakarta 10110', DATE_SUB(NOW(), INTERVAL 5 DAY)),
('TWN-2024-008', 2, 'cancelled', 180000, 15000, 18000, 'Dana', 'refunded', 'John Doe\nJl. Thamrin No. 456\nJakarta Pusat, DKI Jakarta 10230', DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Insert order items for the new orders
INSERT INTO order_items (order_id, product_id, quantity, price, total) VALUES
-- Order 4 items
(4, 4, 1, 200000, 200000),
(4, 11, 6, 22000, 132000),
-- Order 5 items  
(5, 15, 1, 4200000, 4200000),
(5, 18, 3, 280000, 840000),
-- Order 6 items
(6, 7, 1, 750000, 750000),
(6, 19, 1, 580000, 580000),
-- Order 7 items
(7, 15, 1, 4200000, 4200000),
-- Order 8 items
(8, 4, 1, 200000, 200000);

-- Update coupon usage count for some coupons
UPDATE coupons SET used_count = FLOOR(RAND() * 10) + 1 WHERE id IN (1, 2, 3, 4, 5);

-- Add some product attributes for better product details
INSERT INTO product_attributes (product_id, attribute_name, attribute_value) VALUES
-- More iPhone attributes
(1, 'Operating System', 'iOS 16'),
(1, 'Battery Life', '23 hours video playback'),
(1, 'Water Resistance', 'IP68'),
(1, 'Wireless Charging', 'Yes'),
-- Samsung Galaxy attributes
(2, 'Operating System', 'Android 13'),
(2, 'Battery Capacity', '3900mAh'),
(2, 'Fast Charging', '25W'),
(2, 'Water Resistance', 'IP68'),
-- Serum attributes
(4, 'Ingredients', 'Vitamin C, Hyaluronic Acid, Vitamin E'),
(4, 'Usage', 'Apply morning and evening'),
(4, 'Shelf Life', '12 months after opening'),
(4, 'Cruelty Free', 'Yes');

-- Create indexes for better performance
CREATE INDEX idx_orders_created ON orders(created_at);
CREATE INDEX idx_reviews_created ON reviews(created_at);
CREATE INDEX idx_products_featured ON products(featured);
CREATE INDEX idx_users_created ON users(created_at);
 
-- Update some product stock quantities to show low stock alerts
UPDATE products SET stock_quantity = 2 WHERE id IN (3, 8, 20);
UPDATE products SET stock_quantity = 1 WHERE id IN (16, 21);
UPDATE products SET stock_quantity = 0 WHERE id IN (17, 22);

COMMIT;












