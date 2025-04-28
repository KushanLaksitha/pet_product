<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header("Location: auth/login.php");
    exit();
}

// Fetch user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$email = $_SESSION['email'] ?? '';
$phone = $_SESSION['phone'] ?? '';
$address = $_SESSION['address'] ?? '';

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if there are items in the cart
try {
    $cart_check_stmt = $conn->prepare("
        SELECT COUNT(*) as item_count FROM cart 
        WHERE user_id = :user_id
    ");
    $cart_check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $cart_check_stmt->execute();
    $cart_count = $cart_check_stmt->fetch(PDO::FETCH_ASSOC)['item_count'];
    
    if ($cart_count == 0) {
        // No items in cart, redirect to cart page
        header("Location: cart.php");
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Error checking cart";
    error_log('Error checking cart: ' . $e->getMessage());
}

// Fetch cart items with product details
try {
    $cart_stmt = $conn->prepare("
        SELECT c.cart_id, c.product_id, c.quantity, 
               p.name, p.description, p.price, p.sale_price, p.is_sale, p.image_path, p.stock_quantity
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = :user_id
        ORDER BY c.created_at DESC
    ");
    $cart_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $cart_stmt->execute();
    $cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate cart totals
    $subtotal = 0;
    $discount = 0;
    
    foreach ($cart_items as $item) {
        $item_price = $item['is_sale'] && $item['sale_price'] ? $item['sale_price'] : $item['price'];
        $item_total = $item_price * $item['quantity'];
        $subtotal += $item_total;
        
        if ($item['is_sale'] && $item['sale_price']) {
            $discount += ($item['price'] - $item['sale_price']) * $item['quantity'];
        }
    }
    
    // Set shipping cost
    $shipping_cost = 500.00; // Fixed shipping cost of Rs. 500
    
    // Calculate total
    $total = $subtotal + $shipping_cost;
    
} catch (PDOException $e) {
    $error_message = "Error loading cart items";
    error_log('Error fetching cart items: ' . $e->getMessage());
    header("Location: cart.php");
    exit();
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token";
    } else {
        // Sanitize and validate form inputs
        $shipping_first_name = trim($_POST['shipping_first_name']);
        $shipping_last_name = trim($_POST['shipping_last_name']);
        $shipping_email = trim($_POST['shipping_email']);
        $shipping_phone = trim($_POST['shipping_phone']);
        $shipping_address = trim($_POST['shipping_address']);
        $shipping_city = trim($_POST['shipping_city']);
        $shipping_postal_code = trim($_POST['shipping_postal_code']);
        
        // Use same billing address as shipping?
        $same_address = isset($_POST['same_address']) ? true : false;
        
        // If not the same, get billing details
        if (!$same_address) {
            $billing_first_name = trim($_POST['billing_first_name']);
            $billing_last_name = trim($_POST['billing_last_name']);
            $billing_email = trim($_POST['billing_email']);
            $billing_phone = trim($_POST['billing_phone']);
            $billing_address = trim($_POST['billing_address']);
            $billing_city = trim($_POST['billing_city']);
            $billing_postal_code = trim($_POST['billing_postal_code']);
        } else {
            // Use shipping details for billing
            $billing_first_name = $shipping_first_name;
            $billing_last_name = $shipping_last_name;
            $billing_email = $shipping_email;
            $billing_phone = $shipping_phone;
            $billing_address = $shipping_address;
            $billing_city = $shipping_city;
            $billing_postal_code = $shipping_postal_code;
        }
        
        // Get payment method
        $payment_method = $_POST['payment_method'] ?? '';
        
        // Validate required fields
        $validation_errors = [];
        
        if (empty($shipping_first_name) || empty($shipping_last_name) || empty($shipping_email) || 
            empty($shipping_phone) || empty($shipping_address) || empty($shipping_city) || 
            empty($shipping_postal_code)) {
            $validation_errors[] = "All shipping fields are required";
        }
        
        if (!$same_address && (empty($billing_first_name) || empty($billing_last_name) || 
            empty($billing_email) || empty($billing_phone) || empty($billing_address) || 
            empty($billing_city) || empty($billing_postal_code))) {
            $validation_errors[] = "All billing fields are required";
        }
        
        if (empty($payment_method)) {
            $validation_errors[] = "Please select a payment method";
        }
        
        // Validate email format
        if (!filter_var($shipping_email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Invalid shipping email format";
        }
        
        if (!$same_address && !filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Invalid billing email format";
        }
        
        // Process the order if there are no validation errors
        if (empty($validation_errors)) {
            try {
                // Start transaction
                $conn->beginTransaction();
                
                // Format shipping and billing addresses
                $shipping_address_full = "$shipping_first_name $shipping_last_name\n$shipping_address\n$shipping_city\n$shipping_postal_code\nPhone: $shipping_phone\nEmail: $shipping_email";
                $billing_address_full = "$billing_first_name $billing_last_name\n$billing_address\n$billing_city\n$billing_postal_code\nPhone: $billing_phone\nEmail: $billing_email";
                
                // Create the order
                $order_stmt = $conn->prepare("
                    INSERT INTO orders (user_id, total_amount, status, shipping_address, billing_address, payment_method)
                    VALUES (:user_id, :total_amount, 'pending', :shipping_address, :billing_address, :payment_method)
                ");
                $order_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $order_stmt->bindParam(':total_amount', $total, PDO::PARAM_STR);
                $order_stmt->bindParam(':shipping_address', $shipping_address_full, PDO::PARAM_STR);
                $order_stmt->bindParam(':billing_address', $billing_address_full, PDO::PARAM_STR);
                $order_stmt->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
                $order_stmt->execute();
                
                // Get the order ID
                $order_id = $conn->lastInsertId();
                
                // Add order items
                foreach ($cart_items as $item) {
                    $item_price = $item['is_sale'] && $item['sale_price'] ? $item['sale_price'] : $item['price'];
                    
                    $item_stmt = $conn->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price)
                        VALUES (:order_id, :product_id, :quantity, :price)
                    ");
                    $item_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                    $item_stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                    $item_stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $item_stmt->bindParam(':price', $item_price, PDO::PARAM_STR);
                    $item_stmt->execute();
                    
                    // Update product stock
                    $new_stock = $item['stock_quantity'] - $item['quantity'];
                    $stock_stmt = $conn->prepare("
                        UPDATE products
                        SET stock_quantity = :stock_quantity
                        WHERE product_id = :product_id
                    ");
                    $stock_stmt->bindParam(':stock_quantity', $new_stock, PDO::PARAM_INT);
                    $stock_stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                    $stock_stmt->execute();
                }
                
                // Clear the user's cart
                $clear_cart_stmt = $conn->prepare("
                    DELETE FROM cart
                    WHERE user_id = :user_id
                ");
                $clear_cart_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $clear_cart_stmt->execute();
                
                // Commit the transaction
                $conn->commit();
                
                // Store order ID in session for order complete page
                $_SESSION['last_order_id'] = $order_id;
                
                // Redirect to order confirmation page
                header("Location: order_confirmation.php");
                exit();
                
            } catch (PDOException $e) {
                // Roll back the transaction if something failed
                $conn->rollBack();
                $error_message = "Order processing failed";
                error_log('Error processing order: ' . $e->getMessage());
            }
        } else {
            // Display validation errors
            $error_message = implode("<br>", $validation_errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>Checkout - Paws & Clows</title>
    <style>
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        .product-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .checkout-section {
            margin-bottom: 30px;
        }
        .payment-method-option {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-method-option:hover {
            border-color: #6c757d;
        }
        .payment-method-option.selected {
            border-color: #198754;
            background-color: rgba(25, 135, 84, 0.05);
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg bg-light shadow-sm">
        <div class="container-fluid">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="reso/logo.png" alt="Logo" class="logo">
            </a>

            <!-- Navbar Toggler for Mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Search Bar -->
            <form class="d-flex ms-auto search-bar">
                <input class="form-control me-2" type="search" placeholder="Search..." aria-label="Search">
                <button class="btn btn-success" type="submit">Search</button>
            </form>

            <!-- Navbar Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AboutUs.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ContactUs.php">Contact Us</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($first_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="bi bi-cart"></i> Cart
                            <span class="badge bg-success rounded-pill cart-count"><?php echo count($cart_items); ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">Checkout</h2>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Checkout Progress -->
                <div class="progress mb-4">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">Step 2 of 3: Checkout</div>
                </div>
            </div>
        </div>
        
        <form method="post" id="checkout-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
                <!-- Shipping and Payment Details -->
                <div class="col-lg-8">
                    <!-- Shipping Information -->
                    <div class="card shadow-sm mb-4 checkout-section">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Shipping Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="shipping_first_name" name="shipping_first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="shipping_last_name" name="shipping_last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_email" class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" id="shipping_email" name="shipping_email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_phone" class="form-label required-field">Phone</label>
                                    <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="shipping_address" class="form-label required-field">Address</label>
                                <input type="text" class="form-control" id="shipping_address" name="shipping_address" value="<?php echo htmlspecialchars($address); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_city" class="form-label required-field">City</label>
                                    <input type="text" class="form-control" id="shipping_city" name="shipping_city" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="shipping_postal_code" class="form-label required-field">Postal Code</label>
                                    <input type="text" class="form-control" id="shipping_postal_code" name="shipping_postal_code" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Billing Information -->
                    <div class="card shadow-sm mb-4 checkout-section">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Billing Information</h5>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="same_address" name="same_address" checked>
                                <label class="form-check-label" for="same_address">
                                    Same as shipping address
                                </label>
                            </div>
                        </div>
                        <div class="card-body" id="billing_form" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_first_name" class="form-label required-field">First Name</label>
                                    <input type="text" class="form-control" id="billing_first_name" name="billing_first_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_last_name" class="form-label required-field">Last Name</label>
                                    <input type="text" class="form-control" id="billing_last_name" name="billing_last_name">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_email" class="form-label required-field">Email</label>
                                    <input type="email" class="form-control" id="billing_email" name="billing_email">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_phone" class="form-label required-field">Phone</label>
                                    <input type="tel" class="form-control" id="billing_phone" name="billing_phone">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="billing_address" class="form-label required-field">Address</label>
                                <input type="text" class="form-control" id="billing_address" name="billing_address">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_city" class="form-label required-field">City</label>
                                    <input type="text" class="form-control" id="billing_city" name="billing_city">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_postal_code" class="form-label required-field">Postal Code</label>
                                    <input type="text" class="form-control" id="billing_postal_code" name="billing_postal_code">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="card shadow-sm mb-4 checkout-section">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <div class="payment-method-option" data-payment="credit_card">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                    <label class="form-check-label" for="credit_card">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-credit-card me-2"></i>
                                            <div>
                                                <strong>Credit/Debit Card</strong>
                                                <div class="text-muted small">Pay securely with your card</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="payment-method-option" data-payment="paypal">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal">
                                    <label class="form-check-label" for="paypal">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-paypal me-2"></i>
                                            <div>
                                                <strong>PayPal</strong>
                                                <div class="text-muted small">Pay securely with PayPal</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="payment-method-option" data-payment="cash_on_delivery">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash_on_delivery" value="cash_on_delivery">
                                    <label class="form-check-label" for="cash_on_delivery">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-cash-stack me-2"></i>
                                            <div>
                                                <strong>Cash on Delivery</strong>
                                                <div class="text-muted small">Pay when you receive your order</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Credit Card Form (will be shown when credit_card is selected) -->
                            <div id="credit_card_form" class="mt-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="card_number" class="form-label">Card Number</label>
                                        <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="text" class="form-control" id="expiry_date" placeholder="MM/YY">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cvv" class="form-label">CVV</label>
                                        <input type="text" class="form-control" id="cvv" placeholder="123">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="card_name" class="form-label">Name on Card</label>
                                    <input type="text" class="form-control" id="card_name" placeholder="John Doe">
                                </div>
                                <div class="form-text text-muted">
                                    <i class="bi bi-lock"></i> Your payment information is secure. We don't store your card details.
                                </div>
                            </div>
                            
                            <!-- PayPal instructions (will be shown when PayPal is selected) -->
                            <div id="paypal_form" class="mt-3 p-3 border rounded" style="display: none;">
                                <p class="mb-0">You will be redirected to PayPal to complete your payment after reviewing your order.</p>
                            </div>
                            
                            <!-- Cash on Delivery instructions (will be shown when COD is selected) -->
                            <div id="cash_on_delivery_form" class="mt-3 p-3 border rounded" style="display: none;">
                                <p class="mb-0">You will pay for your order when it is delivered to your shipping address.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="mb-4">
                        <label for="order_notes" class="form-label">Order Notes (Optional)</label>
                        <textarea class="form-control" id="order_notes" name="order_notes" rows="3" placeholder="Special instructions for delivery"></textarea>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="terms.php">Terms and Conditions</a>
                        </label>
                    </div>

                    <!-- Place Order Button -->
                    <button type="submit" name="place_order" class="btn btn-success btn-lg w-100">Place Order</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light mt-5 pt-4 pb-3">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5>About Us</h5>
                    <p>Paws & Clows is dedicated to providing premium products for all your pet needs.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light">Home</a></li>
                        <li><a href="Categories.php" class="text-light">Shop</a></li>
                        <li><a href="AboutUs.php" class="text-light">About Us</a></li>
                        <li><a href="ContactUs.php" class="text-light">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Customer Service</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">FAQs</a></li>
                        <li><a href="#" class="text-light">Shipping Policy</a></li>
                        <li><a href="#" class="text-light">Return Policy</a></li>
                        <li><a href="#" class="text-light">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-geo-alt"></i> 123 Pet Street, Colombo, Sri Lanka</li>
                        <li><i class="bi bi-telephone"></i> +94 11 234 5678</li>
                        <li><i class="bi bi-envelope"></i> info@pawsandclows.com</li>
                    </ul>
                    <div class="social-icons mt-2">
                        <a href="#" class="text-light me-2"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-light me-2"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-light me-2"><i class="bi bi-twitter"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 Paws & Clows. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap and JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toggle billing form based on "Same as shipping" checkbox
        document.getElementById('same_address').addEventListener('change', function() {
            document.getElementById('billing_form').style.display = this.checked ? 'none' : 'block';
            
            // Toggle required attribute on billing fields
            const billingInputs = document.querySelectorAll('#billing_form input');
            billingInputs.forEach(input => {
                input.required = !this.checked;
            });
        });
        
        // Payment method selection
        const paymentOptions = document.querySelectorAll('.payment-method-option');
        const paymentForms = {
            'credit_card': document.getElementById('credit_card_form'),
            'paypal': document.getElementById('paypal_form'),
            'cash_on_delivery': document.getElementById('cash_on_delivery_form')
        };
        
        // Initialize selected payment method
        updatePaymentForms('credit_card');
        
        // Add click event listeners to payment options
        paymentOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            option.addEventListener('click', function() {
                // Check the radio button
                radio.checked = true;
                
                // Add selected class to clicked option and remove from others
                paymentOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                
                // Show/hide relevant payment forms
                updatePaymentForms(radio.value);
            });
        });
        
        function updatePaymentForms(selectedMethod) {
            // Hide all payment forms
            Object.values(paymentForms).forEach(form => {
                if (form) form.style.display = 'none';
            });
            
            // Show the selected payment form
            if (paymentForms[selectedMethod]) {
                paymentForms[selectedMethod].style.display = 'block';
            }
        }
        
        // Initialize form validation
        (function() {
            'use strict';
            
            const form = document.getElementById('checkout-form');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
        })();
    </script>
</body>
</html>