    </div> <!-- Close container -->
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>RAD Music</h3>
                <p>Connect with music lovers around the world. Discover new tracks, share your favorites, and stay updated with music news.</p>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="/about">About Us</a></li>
                    <li><a href="/terms">Terms of Service</a></li>
                    <li><a href="/privacy">Privacy Policy</a></li>
                    <li><a href="/contact">Contact</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Connect</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-spotify"></i></a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> RAD Music Network. All rights reserved.
        </div>
    </footer>
    
    <script src="/RAD-music/assets/js/main.js"></script>
    <?php if (isset($jsFiles)): ?>
        <?php foreach ($jsFiles as $jsFile): ?>
            <script src="/RAD-music/assets/js/<?= htmlspecialchars($jsFile) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>