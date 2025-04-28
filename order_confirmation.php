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

// Check if there's a confirmed order
if (!isset($_SESSION['last_order_id'])) {
    // No confirmed order, redirect to home page
    header("Location: index.php");
    exit();
}

$order_id = $_SESSION['last_order_id'];
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$username = $_SESSION['username'] ?? '';

// Fetch order details
try {
    $order_stmt = $conn->prepare("
        SELECT o.order_id, o.total_amount, o.status, o.shipping_address, o.billing_address, 
               o.payment_method, o.created_at
        FROM orders o
        WHERE o.order_id = :order_id AND o.user_id = :user_id
    ");
    $order_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $order_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $order_stmt->execute();
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        // Order not found or doesn't belong to this user
        header("Location: index.php");
        exit();
    }
    
    // Fetch order items
    $items_stmt = $conn->prepare("
        SELECT oi.quantity, oi.price, p.name, p.image_path
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = :order_id
    ");
    $items_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $items_stmt->execute();
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate order totals
    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Set shipping cost
    $shipping_cost = 500.00; // Fixed shipping cost of Rs. 500
    
    // Calculate total
    $total = $order['total_amount'];
    
} catch (PDOException $e) {
    $error_message = "Error loading order details";
    error_log('Error fetching order details: ' . $e->getMessage());
}

// Clear the last order ID from session
// Keeping it for now for demo purposes - in production you might want to clear it
// unset($_SESSION['last_order_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Order Confirmation - Paws & Clows</title>
    <style>
        .order-confirmation {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #198754;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .success-icon i {
            font-size: 40px;
            color: white;
        }
        .product-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .order-details-section {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        .payment-info i {
            font-size: 24px;
            margin-right: 10px;
            color: #198754;
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
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="row">
            <div class="col-12">
                <!-- Checkout Progress -->
                <div class="progress mb-4">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">Step 3 of 3: Order Confirmation</div>
                </div>
                
                <!-- Order Confirmation Message -->
                <div class="order-confirmation text-center">
                    <div class="success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h2 class="mb-3">Thank You for Your Order!</h2>
                    <p class="lead mb-1">Your order has been placed successfully.</p>
                    <p class="mb-4">Order #<?php echo $order_id; ?> was placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="d-grid gap-2">
                                <a href="index.php" class="btn btn-success">Continue Shopping</a>
                                <a href="orders.php" class="btn btn-outline-secondary">View My Orders</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Order Details -->
            <div class="col-lg-8">
                <h4 class="mb-3">Order Details</h4>
                
                <!-- Order Information -->
                <div class="order-details-section mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Order Information</h5>
                            <p><strong>Order Number:</strong> #<?php echo $order_id; ?></p>
                            <p><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                            <p><strong>Order Status:</strong> <span class="badge bg-warning text-dark"><?php echo ucfirst($order['status']); ?></span></p>
                            <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Customer Information</h5>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($first_name); ?></p>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping and Billing -->
                <div class="order-details-section mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Shipping Address</h5>
                            <address>
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </address>
                        </div>
                        <div class="col-md-6">
                            <h5>Billing Address</h5>
                            <address>
                                <?php echo nl2br(htmlspecialchars($order['billing_address'])); ?>
                            </address>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="order-details-section mb-4">
                    <h5>Payment Information</h5>
                    <div class="payment-info d-flex align-items-center">
                        <?php if ($order['payment_method'] == 'credit_card'): ?>
                            <i class="bi bi-credit-card"></i>
                            <div>
                                <p class="mb-0"><strong>Credit/Debit Card</strong></p>
                                <p class="mb-0 text-muted">Payment will be processed securely.</p>
                            </div>
                        <?php elseif ($order['payment_method'] == 'paypal'): ?>
                            <i class="bi bi-paypal"></i>
                            <div>
                                <p class="mb-0"><strong>PayPal</strong></p>
                                <p class="mb-0 text-muted">Payment will be processed through PayPal.</p>
                            </div>
                        <?php elseif ($order['payment_method'] == 'cash_on_delivery'): ?>
                            <i class="bi bi-cash-stack"></i>
                            <div>
                                <p class="mb-0"><strong>Cash on Delivery</strong></p>
                                <p class="mb-0 text-muted">Payment will be collected upon delivery.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <h4 class="mb-3">Order Summary</h4>
                <div class="order-summary">
                    <!-- Order Items -->
                    <h5 class="mb-3">Items Ordered</h5>
                    <?php foreach ($order_items as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-thumbnail me-2">
                                <div>
                                    <div class="text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="small text-muted">Qty: <?php echo $item['quantity']; ?></div>
                                </div>
                            </div>
                            <div class="text-end">
                                Rs.<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <hr>
                    
                    <!-- Order Totals -->
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>Rs.<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <span>Rs.<?php echo number_format($shipping_cost, 2); ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-0">
                        <strong>Total</strong>
                        <strong>Rs.<?php echo number_format($total, 2); ?></strong>
                    </div>
                </div>
                
                <!-- Order Status Timeline -->
                <div class="order-summary mt-4">
                    <h5 class="mb-3">Order Status</h5>
                    <ul class="list-unstyled">
                        <li class="d-flex align-items-center mb-2">
                            <span class="badge bg-success rounded-pill me-2">1</span>
                            <span><strong>Order Placed</strong> - <?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                        </li>
                        <li class="d-flex align-items-center mb-2 text-muted">
                            <span class="badge bg-secondary rounded-pill me-2">2</span>
                            <span>Processing</span>
                        </li>
                        <li class="d-flex align-items-center mb-2 text-muted">
                            <span class="badge bg-secondary rounded-pill me-2">3</span>
                            <span>Shipped</span>
                        </li>
                        <li class="d-flex align-items-center text-muted">
                            <span class="badge bg-secondary rounded-pill me-2">4</span>
                            <span>Delivered</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Support Contact -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6>Need Help?</h6>
                    <p class="small mb-2">If you need assistance with your order, please contact our customer support:</p>
                    <p class="small mb-0"><i class="bi bi-telephone"></i> +94 11 234 5678</p>
                    <p class="small mb-0"><i class="bi bi-envelope"></i> support@pawsandclows.com</p>
                </div>
            </div>
        </div>
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
    
    <!-- Email notification -->
    <script>
        // This would typically be handled server-side
        // This is just a simulation for demonstration purposes
        $(document).ready(function() {
            console.log("Order confirmation email would be sent here");
        });
    </script>
</body>
</html>