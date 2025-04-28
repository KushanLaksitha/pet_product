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

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle cart item removal
if (isset($_POST['remove_item']) && isset($_POST['cart_id'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token";
    } else {
        $cart_id = intval($_POST['cart_id']);
        
        try {
            // Verify the cart item belongs to the current user
            $verify_stmt = $conn->prepare("
                SELECT cart_id FROM cart 
                WHERE cart_id = :cart_id AND user_id = :user_id
            ");
            $verify_stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $verify_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $verify_stmt->execute();
            
            if ($verify_stmt->rowCount() > 0) {
                // Delete the cart item
                $delete_stmt = $conn->prepare("
                    DELETE FROM cart 
                    WHERE cart_id = :cart_id
                ");
                $delete_stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
                $delete_stmt->execute();
                
                $success_message = "Item removed from cart";
            } else {
                $error_message = "Invalid cart item";
            }
        } catch (PDOException $e) {
            $error_message = "Database error occurred";
            error_log('Error removing cart item: ' . $e->getMessage());
        }
    }
}

// Handle quantity update
if (isset($_POST['update_quantity']) && isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token";
    } else {
        $cart_id = intval($_POST['cart_id']);
        $quantity = intval($_POST['quantity']);
        
        // Validate quantity
        if ($quantity <= 0) {
            $error_message = "Quantity must be greater than zero";
        } else {
            try {
                // Get the product ID for the cart item
                $cart_item_stmt = $conn->prepare("
                    SELECT product_id FROM cart 
                    WHERE cart_id = :cart_id AND user_id = :user_id
                ");
                $cart_item_stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
                $cart_item_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $cart_item_stmt->execute();
                $cart_item = $cart_item_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cart_item) {
                    // Check stock availability
                    $product_stmt = $conn->prepare("
                        SELECT stock_quantity FROM products 
                        WHERE product_id = :product_id
                    ");
                    $product_stmt->bindParam(':product_id', $cart_item['product_id'], PDO::PARAM_INT);
                    $product_stmt->execute();
                    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($product && $quantity <= $product['stock_quantity']) {
                        // Update the quantity
                        $update_stmt = $conn->prepare("
                            UPDATE cart 
                            SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP 
                            WHERE cart_id = :cart_id AND user_id = :user_id
                        ");
                        $update_stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                        $update_stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
                        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $update_stmt->execute();
                        
                        $success_message = "Cart updated successfully";
                    } else {
                        $error_message = "Not enough stock available";
                    }
                } else {
                    $error_message = "Invalid cart item";
                }
            } catch (PDOException $e) {
                $error_message = "Database error occurred";
                error_log('Error updating cart quantity: ' . $e->getMessage());
            }
        }
    }
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
    
    // Set shipping cost (could be variable based on location or order total)
    $shipping_cost = $subtotal > 0 ? 500.00 : 0; // Example: Fixed shipping cost of Rs. 500
    
    // Calculate total
    $total = $subtotal + $shipping_cost;
    
} catch (PDOException $e) {
    $error_message = "Error loading cart items";
    error_log('Error fetching cart items: ' . $e->getMessage());
    $cart_items = [];
    $subtotal = 0;
    $discount = 0;
    $shipping_cost = 0;
    $total = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>Shopping Cart - Paws & Clows</title>
    <style>
        .cart-item-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .quantity-input {
            width: 70px;
        }
        .product-title {
            font-weight: 500;
        }
        .cart-item {
            transition: background-color 0.3s;
        }
        .cart-item:hover {
            background-color: #f8f9fa;
        }
        .cart-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        #notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 300px;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Notification toast -->
    <div id="notification" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                Cart updated successfully!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

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
                        <a class="nav-link active" aria-current="page" href="cart.php">
                            <i class="bi bi-cart"></i> Cart
                            <span class="badge bg-success rounded-pill cart-count"><?php echo count($cart_items); ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <h2 class="mb-4">Shopping Cart</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($cart_items)): ?>
            <div class="text-center py-5">
                <i class="bi bi-cart-x" style="font-size: 4rem; color: #adb5bd;"></i>
                <h3 class="mt-3">Your cart is empty</h3>
                <p class="text-muted">Browse our products and add some items to your cart!</p>
                <a href="Categories.php" class="btn btn-success mt-3">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <div class="row">
                                <div class="col-md-5">Product</div>
                                <div class="col-md-2 text-center">Price</div>
                                <div class="col-md-2 text-center">Quantity</div>
                                <div class="col-md-2 text-center">Subtotal</div>
                                <div class="col-md-1"></div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item p-3 border-bottom">
                                    <div class="row align-items-center">
                                        <!-- Product details -->
                                        <div class="col-md-5">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-img me-3">
                                                <div>
                                                    <h6 class="product-title mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                    <?php if (!empty($item['description'])): ?>
                                                        <p class="small text-muted mb-0 text-truncate"><?php echo htmlspecialchars($item['description']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($item['stock_quantity'] < 10): ?>
                                                        <p class="small text-danger mb-0">Only <?php echo $item['stock_quantity']; ?> left in stock</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Price -->
                                        <div class="col-md-2 text-center">
                                            <?php if ($item['is_sale'] && $item['sale_price']): ?>
                                                <span class="text-decoration-line-through small text-muted d-block">Rs.<?php echo number_format($item['price'], 2); ?></span>
                                                <span class="text-danger">Rs.<?php echo number_format($item['sale_price'], 2); ?></span>
                                            <?php else: ?>
                                                <span>Rs.<?php echo number_format($item['price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Quantity controls -->
                                        <div class="col-md-2 text-center">
                                            <form class="update-quantity-form" method="post">
                                                <input type="hidden" name="update_quantity" value="1">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <div class="input-group input-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary qty-decrease">-</button>
                                                    <input type="number" name="quantity" class="form-control text-center quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>">
                                                    <button type="button" class="btn btn-outline-secondary qty-increase">+</button>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <!-- Subtotal -->
                                        <div class="col-md-2 text-center">
                                            <?php 
                                                $item_price = $item['is_sale'] && $item['sale_price'] ? $item['sale_price'] : $item['price'];
                                                $item_total = $item_price * $item['quantity'];
                                                echo 'Rs.' . number_format($item_total, 2);
                                            ?>
                                        </div>
                                        
                                        <!-- Remove button -->
                                        <div class="col-md-1 text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="remove_item" value="1">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove item">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between">
                            <a href="Categories.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Continue Shopping
                            </a>
                            <button id="clear-cart-btn" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary shadow-sm">
                        <h5 class="mb-4">Order Summary</h5>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span>Rs.<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Discount</span>
                            <span>-Rs.<?php echo number_format($discount, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span>Rs.<?php echo number_format($shipping_cost, 2); ?></span>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Total</strong>
                            <strong>Rs.<?php echo number_format($total, 2); ?></strong>
                        </div>
                        
                        <!-- Coupon code form -->
                        <div class="mb-4">
                            <form class="input-group">
                                <input type="text" class="form-control" placeholder="Coupon code">
                                <button class="btn btn-outline-success" type="button">Apply</button>
                            </form>
                        </div>
                        
                        <!-- Checkout button -->
                        <a href="checkout.php" class="btn btn-success d-block">
                            Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Clear Cart Modal -->
    <div class="modal fade" id="clearCartModal" tabindex="-1" aria-labelledby="clearCartModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clearCartModalLabel">Clear Shopping Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to remove all items from your cart? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" id="clear-cart-form">
                        <input type="hidden" name="clear_cart" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-danger">Clear Cart</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="bg-light text-center text-lg-start mt-5">
        <div class="container p-4">
            <div class="row">
                <!-- Logo Section -->
                <div class="col-lg-12 text-left mb-4">
                    <img src="reso/logo.png" alt="Logo" style="height: 50px;">
                </div>

                <!-- Address Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Address</h5>
                    <p>
                        123 Main Street, City<br>
                        State Province, Country
                    </p>
                </div>

                <!-- Call Us Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Call us</h5>
                    <ul class="list-unstyled">
                        <li>+94767692128</li>
                        <li>+94741230346</li>
                    </ul>
                </div>

                <!-- Main Menu Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Main menu</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-dark">Home</a></li>
                        <li><a href="Categories.php" class="text-dark">Categories</a></li>
                        <li><a href="AboutUs.php" class="text-dark">About us</a></li>
                        <li><a href="ContactUs.php" class="text-dark">Contact us</a></li>
                    </ul>
                </div>

                <!-- Find Us On Section -->
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="text-uppercase">Find us on</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-dark">Facebook</a></li>
                        <li><a href="#" class="text-dark">X / Twitter</a></li>
                        <li><a href="#" class="text-dark">Instagram</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Copyright and Links Section -->
        <div class="text-center p-3" style="background-color: rgba(0, 0, 0, 0.2);">
            © 2025 Paws & Clows. All rights reserved.
            <a class="text-dark" href="#">Privacy Policy</a>
            <a class="text-dark" href="#">Terms of Service</a>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get toast element
        const toastElement = document.getElementById('notification');
        const toast = new bootstrap.Toast(toastElement, {
            delay: 3000
        });
        
        // Clear cart button
        const clearCartBtn = document.getElementById('clear-cart-btn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', function() {
                const clearCartModal = new bootstrap.Modal(document.getElementById('clearCartModal'));
                clearCartModal.show();
            });
        }
        
        // Quantity adjustment buttons
        const qtyDecreaseBtns = document.querySelectorAll('.qty-decrease');
        const qtyIncreaseBtns = document.querySelectorAll('.qty-increase');
        
        qtyDecreaseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input[name="quantity"]');
                if (parseInt(input.value) > 1) {
                    input.value = parseInt(input.value) - 1;
                    this.closest('form').submit();
                }
            });
        });
        
        qtyIncreaseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input[name="quantity"]');
                const max = parseInt(input.getAttribute('max'));
                if (parseInt(input.value) < max) {
                    input.value = parseInt(input.value) + 1;
                    this.closest('form').submit();
                }
            });
        });
        
        // Auto-submit form when quantity changes
        const quantityInputs = document.querySelectorAll('.quantity-input');
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
        
        <?php if (isset($success_message)): ?>
        // Show success toast if there's a success message
        toastElement.querySelector('.toast-body').textContent = "<?php echo addslashes($success_message); ?>";
        toastElement.classList.remove('bg-danger');
        toastElement.classList.add('bg-success');
        toast.show();
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        // Show error toast if there's an error message
        toastElement.querySelector('.toast-body').textContent = "<?php echo addslashes($error_message); ?>";
        toastElement.classList.remove('bg-success');
        toastElement.classList.add('bg-danger');
        toast.show();
        <?php endif; ?>
    });
    </script>
</body>
</html>