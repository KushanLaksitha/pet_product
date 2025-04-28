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

// Initialize variables
$orders = [];
$order_items = [];
$current_order_id = null;
$order_details = null;

// Check if order_id is provided for viewing details
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $current_order_id = $_GET['order_id'];
    
    // Fetch specific order details
    try {
        // Get order information
        $order_stmt = $conn->prepare("
            SELECT * FROM orders 
            WHERE order_id = :order_id AND user_id = :user_id
        ");
        $order_stmt->bindParam(':order_id', $current_order_id);
        $order_stmt->bindParam(':user_id', $user_id);
        $order_stmt->execute();
        $order_details = $order_stmt->fetch();
        
        if ($order_details) {
            // Get order items with product details
            $items_stmt = $conn->prepare("
                SELECT oi.*, p.name, p.image_path 
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = :order_id
            ");
            $items_stmt->bindParam(':order_id', $current_order_id);
            $items_stmt->execute();
            $order_items = $items_stmt->fetchAll();
        } else {
            // Order not found or doesn't belong to current user
            $error = "Order not found";
            $current_order_id = null;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    // No specific order requested, show order history
    try {
        $orders_stmt = $conn->prepare("
            SELECT * FROM orders 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC
        ");
        $orders_stmt->bindParam(':user_id', $user_id);
        $orders_stmt->execute();
        $orders = $orders_stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Define status colors
$status_colors = [
    'pending' => 'text-warning',
    'processing' => 'text-info',
    'shipped' => 'text-primary',
    'delivered' => 'text-success',
    'cancelled' => 'text-danger'
];

// Define status icons
$status_icons = [
    'pending' => 'bi-hourglass',
    'processing' => 'bi-gear',
    'shipped' => 'bi-truck',
    'delivered' => 'bi-check-circle',
    'cancelled' => 'bi-x-circle'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>My Orders - Paws & Clows</title>
    <style>
        .order-card {
            transition: all 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .product-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .breadcrumb-item a {
            text-decoration: none;
        }
        .timeline {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
        }
        .timeline::after {
            content: '';
            position: absolute;
            width: 6px;
            background-color: #e9ecef;
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -3px;
        }
        .timeline-step {
            position: relative;
            width: 25px;
            height: 25px;
            background-color: #fff;
            border: 4px solid #e9ecef;
            border-radius: 50%;
            z-index: 1;
        }
        .timeline-step.active {
            border-color: #28a745;
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
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($first_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item active" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="bi bi-cart"></i> Cart
                            <span class="badge bg-success rounded-pill cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <?php if ($current_order_id): ?>
                    <li class="breadcrumb-item"><a href="orders.php">My Orders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Order #<?php echo $current_order_id; ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">My Orders</li>
                <?php endif; ?>
            </ol>
        </nav>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Order List View -->
        <?php if (!$current_order_id): ?>
            <h1 class="mb-4">My Orders</h1>
            
            <?php if (empty($orders)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-bag-x" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">No Orders Yet</h3>
                        <p class="text-muted">You haven't placed any orders yet.</p>
                        <a href="Categories.php" class="btn btn-success mt-3">Start Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($orders as $order): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card order-card shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Order #<?php echo $order['order_id']; ?></h5>
                                    <span class="badge bg-light <?php echo $status_colors[$order['status']]; ?>">
                                        <i class="bi <?php echo $status_icons[$order['status']]; ?>"></i> 
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                    <p><strong>Total:</strong> Rs.<?php echo number_format($order['total_amount'], 2); ?></p>
                                    <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                                    <div class="text-end">
                                        <a href="orders.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-outline-success">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        
        <!-- Order Details View -->
        <?php else: ?>
            <?php if ($order_details): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="mb-0">Order #<?php echo $current_order_id; ?></h2>
                            <span class="badge bg-light <?php echo $status_colors[$order_details['status']]; ?> px-3 py-2">
                                <i class="bi <?php echo $status_icons[$order_details['status']]; ?>"></i> 
                                <?php echo ucfirst($order_details['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Order Information</h5>
                                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order_details['created_at'])); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($order_details['payment_method']); ?></p>
                                <p><strong>Total Amount:</strong> Rs.<?php echo number_format($order_details['total_amount'], 2); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Shipping Address</h5>
                                <p><?php echo nl2br(htmlspecialchars($order_details['shipping_address'])); ?></p>
                            </div>
                        </div>

                        <!-- Order Status Timeline -->
                        <h5 class="mb-3">Order Status</h5>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="progress" style="height: 5px;">
                                    <?php
                                    $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                                    $status_index = array_search($order_details['status'], $statuses);
                                    if ($status_index !== false) {
                                        $progress = ($status_index + 1) / count($statuses) * 100;
                                    } else {
                                        $progress = 0;
                                    }
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <?php foreach ($statuses as $index => $status): ?>
                                        <?php $is_active = array_search($order_details['status'], $statuses) >= $index; ?>
                                        <div class="text-center">
                                            <div class="timeline-step <?php echo $is_active ? 'active' : ''; ?> mx-auto"></div>
                                            <small class="<?php echo $is_active ? 'text-success' : ''; ?>"><?php echo ucfirst($status); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <h5 class="mb-3">Order Items</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-img me-3">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                    <a href="product.php?id=<?php echo $item['product_id']; ?>" class="text-muted small">View Product</a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>Rs.<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">Rs.<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end"><strong>Rs.<?php echo number_format($order_details['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Orders
                            </a>
                            <?php if ($order_details['status'] !== 'cancelled' && $order_details['status'] !== 'delivered'): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                                <i class="bi bi-x-circle"></i> Cancel Order
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Cancel Order Modal -->
                <?php if ($order_details['status'] !== 'cancelled' && $order_details['status'] !== 'delivered'): ?>
                <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cancelOrderModalLabel">Cancel Order</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel this order?</p>
                                <p class="text-danger"><small>This action cannot be undone.</small></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <form action="cancel_order.php" method="post">
                                    <input type="hidden" name="order_id" value="<?php echo $current_order_id; ?>">
                                    <button type="submit" class="btn btn-danger">Cancel Order</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
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
        // Update cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });
        
        // Function to update cart count
        function updateCartCount() {
            fetch('get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartCountElement = document.querySelector('.cart-count');
                if (cartCountElement) {
                    cartCountElement.textContent = data.count;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>