    </main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container-lg">
            <div class="row">
                <!-- Company Info -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-gem me-2 text-warning"></i>
                        <?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-light opacity-75 mb-4">
                        Your trusted online marketplace for quality products at affordable prices.
                        Shop electronics, fashion, home goods and more with confidence.
                    </p>

                    <!-- Contact Info -->
                    <div class="contact-info mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-map-marker-alt me-3 text-warning"></i>
                            <span class="text-light opacity-75">Jakarta, Indonesia</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-phone me-3 text-warning"></i>
                            <span class="text-light opacity-75">+62 21 1234 5678</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-envelope me-3 text-warning"></i>
                            <span class="text-light opacity-75">support@tewuneed.com</span>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="social-links">
                        <h6 class="fw-bold mb-3">Follow Us</h6>
                        <a href="#" class="btn btn-outline-light btn-sm me-2 mb-2">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm me-2 mb-2">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm me-2 mb-2">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm me-2 mb-2">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="#" class="btn btn-outline-light btn-sm me-2 mb-2">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3 text-warning">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/products.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-shopping-cart me-2"></i>Products
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/about.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-info-circle me-2"></i>About Us
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/contact.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-envelope me-2"></i>Contact
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Categories -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3 text-warning">Categories</h6>
                    <ul class="list-unstyled">
                        <?php
                        try {
                            $stmt = getDBConnection()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name LIMIT 5");
                            while ($category = $stmt->fetch()) {
                                echo '<li class="mb-2">
                                        <a href="' . SITE_URL . '/products.php?category=' . $category['slug'] . '" class="text-light opacity-75 text-decoration-none hover-link">
                                            <i class="fas fa-tag me-2"></i>' . htmlspecialchars($category['name']) . '
                                        </a>
                                      </li>';
                            }
                        } catch (Exception $e) {
                            echo '<li class="mb-2">
                                    <a href="' . SITE_URL . '/products.php" class="text-light opacity-75 text-decoration-none hover-link">
                                        <i class="fas fa-tag me-2"></i>All Products
                                    </a>
                                  </li>';
                        }
                        ?>
                    </ul>
                </div>

                <!-- Customer Service -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3 text-warning">Customer Service</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/my-orders.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-shopping-bag me-2"></i>Track Your Order
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/help.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-question-circle me-2"></i>Help Center
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/shipping.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-shipping-fast me-2"></i>Shipping Info
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/returns.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-undo-alt me-2"></i>Returns & Exchanges
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo SITE_URL; ?>/faq.php" class="text-light opacity-75 text-decoration-none hover-link">
                                <i class="fas fa-comments me-2"></i>FAQ
                            </a>
                        </li>
                    </ul>
                </div>
            </div>



            <!-- Footer Bottom -->
            <div class="row align-items-center pt-4 border-top border-secondary">
                <div class="col-md-6 mb-3 mb-md-0">
                    <p class="text-light opacity-75 mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                    </p>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/privacy.php" class="text-light opacity-75 text-decoration-none me-3 small hover-link">Privacy Policy</a>
                        <a href="<?php echo SITE_URL; ?>/terms.php" class="text-light opacity-75 text-decoration-none me-3 small hover-link">Terms of Service</a>
                        <a href="<?php echo SITE_URL; ?>/cookies.php" class="text-light opacity-75 text-decoration-none small hover-link">Cookie Policy</a>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="payment-methods mb-2">
                        <span class="text-light opacity-75 small me-3">We Accept:</span>
                        <i class="fab fa-cc-visa text-warning me-2 fa-lg"></i>
                        <i class="fab fa-cc-mastercard text-warning me-2 fa-lg"></i>
                        <i class="fab fa-cc-paypal text-warning me-2 fa-lg"></i>
                        <i class="fas fa-university text-warning fa-lg"></i>
                    </div>
                    <div class="security-badges">
                        <span class="text-light opacity-75 small me-2">Secured by:</span>
                        <i class="fas fa-shield-alt text-success me-2"></i>
                        <i class="fas fa-lock text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/js/main.js"></script>
    
    <!-- Additional JS -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?php echo SITE_URL; ?>/js/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Cart Update Script -->
    <script>
        // Update cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });

        function updateCartCount() {
            fetch('<?php echo SITE_URL; ?>/ajax/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartBadge = document.getElementById('cart-count');
                if (cartBadge) {
                    if (data.count > 0) {
                        cartBadge.textContent = data.count;
                        cartBadge.style.display = 'inline';
                    } else {
                        cartBadge.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error updating cart count:', error);
                const cartBadge = document.getElementById('cart-count');
                if (cartBadge) {
                    cartBadge.style.display = 'none';
                }
            });
        }

        // Make updateCartCount globally available
        window.updateCartCount = updateCartCount;
    </script>

    <!-- Wishlist JavaScript -->
    <script src="<?php echo SITE_URL; ?>/js/wishlist.js"></script>
</body>
</html>
