    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-gem me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p>Your one-stop destination for premium jewelry. We offer a stunning collection of hand-picked pieces to celebrate life's precious moments.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <h5>Shop</h5>
                    <ul class="list-unstyled">
                        <?php foreach ($categories as $category): ?>
                            <li><a class="text-white-50" href="/pages/products.php?category=<?php echo $category['id']; ?>"><?php echo $category['name']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h5>Account</h5>
                    <ul class="list-unstyled">
                        <li><a class="text-white-50" href="/pages/login.php">Login</a></li>
                        <li><a class="text-white-50" href="/pages/register.php">Register</a></li>
                        <li><a class="text-white-50" href="/pages/account.php">My Account</a></li>
                        <li><a class="text-white-50" href="/pages/orders.php">Order History</a></li>
                        <li><a class="text-white-50" href="/pages/cart.php">Shopping Cart</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Stay Updated</h5>
                    <p>Subscribe to our newsletter for the latest products and offers.</p>
                    <form action="#" method="post" class="d-flex">
                        <input type="email" class="form-control me-2" placeholder="Your Email" required>
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </form>
                </div>
            </div>
            <hr class="bg-light">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white-50 me-3">Privacy Policy</a>
                    <a href="#" class="text-white-50 me-3">Terms of Service</a>
                    <a href="#" class="text-white-50">Contact Us</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
</body>
</html>