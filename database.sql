-- Create the database
CREATE DATABASE paws_and_clows;
USE paws_and_clows;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    image_path VARCHAR(255)
);

-- Products table
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2),
    stock_quantity INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255),
    is_featured BOOLEAN DEFAULT FALSE,
    is_flash_deal BOOLEAN DEFAULT FALSE,
    is_recommended BOOLEAN DEFAULT FALSE,
    is_sale BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Orders table
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    billing_address TEXT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Order Items table
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
);

-- Cart table
CREATE TABLE cart (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- Reviews table
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Carousel images table
CREATE TABLE carousel_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    description TEXT,
    active BOOLEAN DEFAULT TRUE
);

-- Insert sample categories
INSERT INTO categories (name, description, image_path) VALUES
('Dogs', 'Products for dogs', 'reso/p1.png'),
('Cats', 'Products for cats', 'reso/p2.png'),
('Rabbits', 'Products for rabbits', 'reso/p3.png'),
('Parrots', 'Products for parrots', 'reso/p4.png'),
('Fish', 'Products for fish', 'reso/p5.png'),
('Pigeons', 'Products for pigeons', 'reso/p6.png'),
('Squirrels', 'Products for squirrels', 'reso/p7.png'),
('Pigs', 'Products for pigs', 'reso/p8.png');

-- Insert sample products
INSERT INTO products (category_id, name, description, price, sale_price, stock_quantity, image_path, is_featured, is_flash_deal, is_recommended, is_sale) VALUES
(2, 'ZooRoyal Minka Chicken and Salmon Tray 100g', 'High-quality cat food with chicken and salmon', 1350.00, NULL, 50, 'reso/chiken.png', FALSE, TRUE, FALSE, FALSE),
(2, 'Macceral JosiCat Salmon in Sauce 415g', 'Premium cat food with salmon in sauce', 850.00, NULL, 45, 'reso/saman.png', FALSE, TRUE, FALSE, FALSE),
(1, 'FURR Fresh 2 in 1 dog and cat Shampoo 300 ml', 'Gentle shampoo for both dogs and cats', 1480.00, NULL, 30, 'reso/shampo.png', FALSE, TRUE, FALSE, FALSE),
(1, 'Trixie Premium Stop-the-pull Collar', 'Training collar for dogs', 650.00, NULL, 20, 'reso/chain.png', FALSE, TRUE, FALSE, FALSE),
(2, 'JosiCat Chicken in Jelly 400g', 'Chicken flavored cat food in jelly', 2350.00, NULL, 40, 'reso/jelly.png', FALSE, TRUE, FALSE, FALSE),
(2, 'Josi Cat Crunchy Chicken', 'Dry cat food with crunchy chicken pieces', 4350.00, NULL, 35, 'reso/catfood.png', FALSE, TRUE, FALSE, FALSE),
(2, 'JosiCat Beef in Jelly 400g', 'Beef flavored cat food in jelly', 1190.00, NULL, 40, 'reso/fd7.png', FALSE, TRUE, FALSE, FALSE),
(2, 'JosiCat Kitten', 'Special formula for kittens', 950.00, NULL, 25, 'reso/fd8.png', FALSE, TRUE, FALSE, FALSE),
(1, 'Josera High Energy', 'High energy dog food for active dogs', 1500.00, NULL, 30, 'reso/rp1.png', FALSE, FALSE, TRUE, FALSE),
(1, 'Trixie Rope Dumbbell', 'Durable rope toy for dogs', 1120.00, NULL, 15, 'reso/rp2.png', FALSE, FALSE, TRUE, FALSE),
(1, 'Dog Toy Playing Rope with Ball', 'Interactive dog toy with rope and ball', 2300.00, NULL, 20, 'reso/rp3.png', FALSE, FALSE, TRUE, FALSE),
(1, 'Trixie Premio Omega Stripes', 'Treats for dogs rich in omega fatty acids', 980.00, NULL, 40, 'reso/rp4.png', FALSE, FALSE, TRUE, FALSE),
(1, 'Trixie Soft Snack Bony Mix 500g', 'Mixed soft treats for dogs', 650.00, 455.00, 25, 'reso/off1.png', FALSE, FALSE, FALSE, TRUE),
(1, 'Trixie Junior Soft Snack Dots', 'Soft treats for puppies', 1230.00, 861.00, 20, 'reso/off2.png', FALSE, FALSE, FALSE, TRUE),
(2, 'Malt Paste', 'Hairball remedy paste for cats', 700.00, 490.00, 30, 'reso/off3.png', FALSE, FALSE, FALSE, TRUE),
(2, 'Trixie Salmon', 'Salmon treats for cats', 960.00, 672.00, 35, 'reso/off4.png', FALSE, FALSE, FALSE, TRUE);

-- Insert sample carousel images
INSERT INTO carousel_images (image_path, title, description) VALUES
('reso/c1.png', 'Welcome to Paws & Clows', 'Your one-stop shop for all pet needs'),
('reso/c2.png', 'Quality Pet Products', 'We offer the best products for your furry friends'),
('reso/c3.png', 'Special Offers', 'Check out our current deals and discounts');