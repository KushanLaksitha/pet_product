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

// Fetch all categories from database
try {
    $categories_stmt = $conn->prepare("
        SELECT * FROM categories 
        ORDER BY name ASC
    ");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
    
    // Check if a specific category is selected
    $selected_category = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($selected_category) {
        // Fetch products from the selected category
        $products_stmt = $conn->prepare("
            SELECT * FROM products 
            WHERE category_id = :category_id
            ORDER BY name ASC
        ");
        $products_stmt->bindParam(':category_id', $selected_category, PDO::PARAM_INT);
        $products_stmt->execute();
        $products = $products_stmt->fetchAll();
        
        // Get category name
        $category_name_stmt = $conn->prepare("
            SELECT name FROM categories
            WHERE category_id = :category_id
        ");
        $category_name_stmt->bindParam(':category_id', $selected_category, PDO::PARAM_INT);
        $category_name_stmt->execute();
        $category_row = $category_name_stmt->fetch();
        $category_name = $category_row ? $category_row['name'] : 'Unknown Category';
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>Categories - Paws & Clows</title>
    <style>
        .category-card {
            transition: transform 0.3s;
            cursor: pointer;
            height: 100%;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .category-img {
            height: 180px;
            object-fit: contain;
            padding: 15px;
        }
        .product-card {
            height: 100%;
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .badge-sale {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
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
                Product added to cart successfully!
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
                        <a class="nav-link active" aria-current="page" href="Categories.php">Categories</a>
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
                            <span class="badge bg-success rounded-pill cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!$selected_category): ?>
            <!-- Categories View -->
            <h2 class="mb-4">Pet Categories</h2>
            <p class="text-muted mb-4">Browse our wide range of pet products organized by animal categories.</p>
            
            <div class="row">
                <?php foreach ($categories as $category): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <a href="Categories.php?id=<?php echo $category['category_id']; ?>" class="text-decoration-none text-dark">
                        <div class="card category-card">
                            <img src="<?php echo htmlspecialchars($category['image_path']); ?>" class="card-img-top category-img" alt="<?php echo htmlspecialchars($category['name']); ?>">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <?php if (!empty($category['description'])): ?>
                                <p class="card-text small text-muted"><?php echo htmlspecialchars($category['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Products by Category View -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="Categories.php">All Categories</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($category_name); ?></li>
                </ol>
            </nav>
            
            <h2 class="mb-4"><?php echo htmlspecialchars($category_name); ?> Products</h2>
            
            <?php if (empty($products)): ?>
                <div class="alert alert-info">
                    No products found in this category. Check back later for updates!
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card product-card">
                            <?php if ($product['is_sale'] && $product['sale_price']): 
                                $discount_percentage = round(($product['price'] - $product['sale_price']) / $product['price'] * 100);
                            ?>
                                <span class="badge-sale"><?php echo $discount_percentage; ?>% OFF</span>
                            <?php endif; ?>
                            
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top p-3" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                
                                <?php if ($product['is_sale'] && $product['sale_price']): ?>
                                    <p class="card-text">
                                        <span class="text-decoration-line-through text-muted">Rs.<?php echo number_format($product['price'], 2); ?></span>
                                        <span class="text-danger ms-2">Rs.<?php echo number_format($product['sale_price'], 2); ?></span>
                                    </p>
                                <?php else: ?>
                                    <p class="card-text">Rs.<?php echo number_format($product['price'], 2); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['description'])): ?>
                                    <p class="card-text small text-muted text-truncate"><?php echo htmlspecialchars($product['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                                    <button class="btn btn-success btn-sm add-to-cart" data-product-id="<?php echo $product['product_id']; ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
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
    // Add to cart functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Get toast element
        const toastElement = document.getElementById('notification');
        const toast = new bootstrap.Toast(toastElement, {
            delay: 3000
        });
        
        // Get cart count from server via AJAX
        updateCartCount();
        
        // Add to cart buttons
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                addToCart(productId);
                
                // Visual feedback - change button text temporarily
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check-circle"></i> Added';
                this.classList.add('btn-secondary');
                this.classList.remove('btn-success');
                this.disabled = true;
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.classList.remove('btn-secondary');
                    this.classList.add('btn-success');
                    this.disabled = false;
                }, 1500);
            });
        });
        
        // Function to add product to cart
        function addToCart(productId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1&csrf_token=<?php echo $csrf_token; ?>'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateCartCount();
                    toastElement.querySelector('.toast-body').textContent = 'Product added to cart successfully!';
                    toast.show();
                } else {
                    toastElement.querySelector('.toast-body').textContent = 'Error: ' + data.message;
                    toastElement.classList.remove('bg-success');
                    toastElement.classList.add('bg-danger');
                    toast.show();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastElement.querySelector('.toast-body').textContent = 'Error: Could not add product to cart';
                toastElement.classList.remove('bg-success');
                toastElement.classList.add('bg-danger');
                toast.show();
            });
        }
        
        // Function to update cart count
        function updateCartCount() {
            fetch('get_cart_count.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const cartCountElement = document.querySelector('.cart-count');
                if (cartCountElement) {
                    cartCountElement.textContent = data.count;
                }
            })
            .catch(error => {
                console.error('Error updating cart count:', error);
            });
        }
    });
    </script>
</body>
</html>