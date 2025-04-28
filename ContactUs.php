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

// Process contact form submission
$message_sent = false;
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // In a real application, you would insert into a database or send an email
        // For now, we'll just simulate a successful submission
        $message_sent = true;
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
    <title>Contact Us - Paws & Clows</title>
    <style>
        .contact-section {
            padding: 60px 0;
        }
        .contact-info-item {
            margin-bottom: 20px;
        }
        .contact-icon {
            font-size: 1.5rem;
            color: #198754;
            margin-right: 10px;
        }
        .map-container {
            height: 400px;
            width: 100%;
            margin-bottom: 30px;
        }
        .form-container {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
                        <a class="nav-link active" aria-current="page" href="ContactUs.php">Contact Us</a>
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

    <!-- Page Title -->
    <div class="bg-light py-4">
        <div class="container">
            <h1 class="text-center">Contact Us</h1>
        </div>
    </div>

    <!-- Contact Section -->
    <div class="contact-section">
        <div class="container">
            <?php if ($message_sent): ?>
                <div class="alert alert-success mb-4" role="alert">
                    Thank you for your message! We'll get back to you soon.
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger mb-4" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <h3 class="mb-4">Get in Touch</h3>
                    <p>Have questions about our products or services? Need help with an order? Our customer service team is here to help!</p>
                    
                    <div class="contact-info-item d-flex align-items-center">
                        <i class="bi bi-geo-alt contact-icon"></i>
                        <div>
                            <h5 class="mb-0">Address</h5>
                            <p class="mb-0">123 Main Street, City<br>State Province, Country</p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item d-flex align-items-center">
                        <i class="bi bi-telephone contact-icon"></i>
                        <div>
                            <h5 class="mb-0">Phone</h5>
                            <p class="mb-0">+94767692128<br>+94741230346</p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item d-flex align-items-center">
                        <i class="bi bi-envelope contact-icon"></i>
                        <div>
                            <h5 class="mb-0">Email</h5>
                            <p class="mb-0">contact@pawsandclows.com<br>support@pawsandclows.com</p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item d-flex align-items-center">
                        <i class="bi bi-clock contact-icon"></i>
                        <div>
                            <h5 class="mb-0">Opening Hours</h5>
                            <p class="mb-0">Monday - Friday: 9:00 AM - 8:00 PM<br>Saturday - Sunday: 10:00 AM - 6:00 PM</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-container">
                        <h3 class="mb-4">Send us a Message</h3>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($first_name . ' ' . $last_name); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Map Section -->
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4">Find Us</h3>
                    <div class="map-container">
                        <!-- Placeholder for Google Maps -->
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3960.7985117576!2d79.97005061477567!3d6.914677195003334!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae256db1a6771c5%3A0x2c63e344ab9a7536!2sSri%20Lanka%20Institute%20of%20Information%20Technology!5e0!3m2!1sen!2slk!4v1650450283499!5m2!1sen!2slk" 
                            width="100%" 
                            height="100%" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="mb-4">Frequently Asked Questions</h3>
                    <div class="accordion" id="contactFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How can I track my order?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    You can track your order by logging into your account and visiting the "My Orders" section. There you will find all your orders along with their current status.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    What is your return policy?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    We offer a 30-day return policy for most products in their original condition. Food items and opened consumables cannot be returned for hygiene reasons. Please contact our customer service team for specific return instructions.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    Do you offer delivery services?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    Yes, we offer delivery services nationwide. Delivery charges may vary based on your location and the size of your order. Free delivery is available for orders above Rs.5000.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    How can I get advice about pet care?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#contactFAQ">
                                <div class="accordion-body">
                                    Our team includes trained pet care specialists who can provide advice about pet nutrition, grooming, and general care. You can contact us via phone, email, or visit our store for personalized assistance.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
    // Add to cart functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Get cart count from server via AJAX
        updateCartCount();
        
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
    });
    </script>
</body>
</html>