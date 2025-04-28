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
$username = $_SESSION['username'] ?? '';  // Use null coalescing operator to provide default
$first_name = $_SESSION['first_name'] ?? 'User';  // Default to 'User' if not set
$last_name = $_SESSION['last_name'] ?? '';  // Default to empty string if not set

// Fetch flash deals products from database
try {
    $flash_deals_stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE is_flash_deal = TRUE 
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $flash_deals_stmt->execute();
    $flash_deals = $flash_deals_stmt->fetchAll();
    
    // Fetch recommended products
    $recommended_stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE is_recommended = TRUE 
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $recommended_stmt->execute();
    $recommended_products = $recommended_stmt->fetchAll();
    
    // Fetch sale products
    $sale_stmt = $conn->prepare("
        SELECT * FROM products 
        WHERE is_sale = TRUE 
        ORDER BY created_at DESC 
        LIMIT 8
    ");
    $sale_stmt->execute();
    $sale_products = $sale_stmt->fetchAll();
    
    // Fetch carousel images
    $carousel_stmt = $conn->prepare("
        SELECT * FROM carousel_images 
        WHERE active = TRUE 
        ORDER BY image_id
    ");
    $carousel_stmt->execute();
    $carousel_images = $carousel_stmt->fetchAll();
    
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
    <title>Paws & Clows</title>
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
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
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
                            <span class="badge bg-success rounded-pill cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Carousel Section -->
    <div id="mainCarousel" class="carousel slide custom-carousel" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php 
            $first = true;
            foreach ($carousel_images as $image): 
            ?>
                <div class="carousel-item <?php echo $first ? 'active' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($image['title']); ?>">
                    <?php if (!empty($image['title']) || !empty($image['description'])): ?>
                    <div class="carousel-caption d-none d-md-block">
                        <h5><?php echo htmlspecialchars($image['title']); ?></h5>
                        <p><?php echo htmlspecialchars($image['description']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php 
            $first = false;
            endforeach; 
            ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <!-- About Section -->
    <div class="container mt-4">
        <p class="text-center" style="max-width: 100%; margin: 0 auto;">
            Paws & Clows is a dedicated pet shop designed to meet all your pet care needs under one roof. We offer a
            variety of high-quality products, including nutritious pet food, fun toys, stylish accessories, grooming supplies, and more.
            Whether you have a playful pup, a curious cat, or a feathered friend, we've got you covered! At Paws & Clows, we prioritize
            your pet's happiness and well-being. Our friendly team is here to help you choose the best products for your beloved companion.
            Explore our categories today and give your pets the love, care, and attention they truly deserve! 🐾❤️
        </p>
    </div>

    <!-- Flash Deals Section -->
    <div class="container mt-5">
        <h3>Flash Deals</h3>
        <div class="row">
            <?php 
            $counter = 0;
            foreach ($flash_deals as $product): 
                if ($counter >= 4) break; // Display only 4 products at once
            ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card">
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body text-center">
                            <p class="card-title"><?php echo htmlspecialchars($product['name']); ?></p>
                            <p class="card-text">Rs.<?php echo number_format($product['price'], 2); ?></p>
                            <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                            <button class="btn btn-success btn-sm add-to-cart" data-product-id="<?php echo $product['product_id']; ?>">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                $counter++;
            endforeach; 
            ?>
        </div>
        <?php if (count($flash_deals) > 4): ?>
        <div class="text-center mt-3">
            <a href="flash-deals.php" class="btn btn-outline-success">View All Flash Deals</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recommended Products Section -->
    <div class="container mt-5">
        <h3>Recommended Products</h3>
        <div class="row">
            <?php 
            $counter = 0;
            foreach ($recommended_products as $product): 
                if ($counter >= 4) break; // Display only 4 products at once
            ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card">
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body text-center">
                            <p class="card-title"><?php echo htmlspecialchars($product['name']); ?></p>
                            <p class="card-text">Rs.<?php echo number_format($product['price'], 2); ?></p>
                            <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                            <button class="btn btn-success btn-sm add-to-cart" data-product-id="<?php echo $product['product_id']; ?>">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                $counter++;
            endforeach; 
            ?>
        </div>
        <?php if (count($recommended_products) > 4): ?>
        <div class="text-center mt-3">
            <a href="recommended-products.php" class="btn btn-outline-success">View All Recommended Products</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- 30% Off Sale Section -->
    <div class="container mt-5">
        <h3>30% Off Sale</h3>
        <div class="row">
            <?php 
            $counter = 0;
            foreach ($sale_products as $product): 
                if ($counter >= 4) break; // Display only 4 products at once
                $discount_percentage = round(($product['price'] - $product['sale_price']) / $product['price'] * 100);
            ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card">
                        <span class="badge-sale"><?php echo $discount_percentage; ?>% OFF</span>
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body text-center">
                            <p class="card-title"><?php echo htmlspecialchars($product['name']); ?></p>
                            <p class="card-text">
                                <span class="text-decoration-line-through text-muted">Rs.<?php echo number_format($product['price'], 2); ?></span>
                                <span class="text-danger">Rs.<?php echo number_format($product['sale_price'], 2); ?></span>
                            </p>
                            <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-success btn-sm">View Details</a>
                            <button class="btn btn-success btn-sm add-to-cart" data-product-id="<?php echo $product['product_id']; ?>">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                $counter++;
            endforeach; 
            ?>
        </div>
        <?php if (count($sale_products) > 4): ?>
        <div class="text-center mt-3">
            <a href="sale-products.php" class="btn btn-outline-success">View All Sale Products</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer Section -->
    <footer class="bg-light text-center text-lg-start">
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
        // Update cart count on page load
        updateCartCount();
        
        // Add to cart form submission
        const addToCartForms = document.querySelectorAll('.add-to-cart-form');
        addToCartForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('add_to_cart.php', {
                    method: 'POST',
                    body: formData
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
                        alert('Product added to cart!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('There was an error adding the product to your cart. Please try again.');
                });
            });
        });
        
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