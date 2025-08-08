    </main>

    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-camera"></i> Camagru</h5>
                    <p>Create and share amazing photos with filters and effects.</p>
                </div>
                <div class="col-md-6">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="/gallery.php" class="text-light">Gallery</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/editor.php" class="text-light">Create Photo</a></li>
                            <li><a href="/my-images.php" class="text-light">My Photos</a></li>
                        <?php else: ?>
                            <li><a href="/login.php" class="text-light">Login</a></li>
                            <li><a href="/register.php" class="text-light">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> Camagru. A 42 School project.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/app.js"></script>
</body>
</html>
