    <footer class="footer">
        <div class="wrap">
            <div class="footer-grid">
                <div>
                    <a href="/index.php" class="brand" aria-label="Bella Vista Dining, home"><?= brand_logo(88, '') ?></a>
                    <p class="mt-3 max-w-[22rem]">Filipino comfort food, served slow in the garden of the Bella Vista estate since 2021.</p>
                </div>
                <div>
                    <h2>Visit</h2>
                    <p>Tuesday to Sunday<br><?= e(Config::displayTime(Config::OPENS_AT)) ?> to <?= e(Config::displayTime(Config::CLOSES_AT)) ?></p>
                    <p class="mt-1">Closed Mondays</p>
                </div>
                <div>
                    <h2>Explore</h2>
                    <a href="/public/menu.php">Menu</a>
                    <a href="/index.php#tables">Tables &amp; fees</a>
                    <a href="/public/reserve.php">Reserve a table</a>
                    <a href="/index.php#policies">Booking policies</a>
                    <a href="/public/about.php">Our story</a>
                </div>
                <div>
                    <h2>Contact</h2>
                    <a href="/public/booking.php">Manage your booking</a>
                    <a href="/public/contact.php">Contact details</a>
                    <a href="/public/visit.php">Directions</a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© <?= e(Config::now()->format('Y')) ?> Bella Vista Dining</span>
                <span>Sample menu and prices for a school project.</span>
            </div>
        </div>
    </footer>

    <?php if (empty($flowPage)):
        $tabs = [
            'home' => ['Discover', '/index.php', 'home'],
            'menu' => ['Menu', '/public/menu.php', 'utensils'],
            'reserve' => ['Reserve', '/public/reserve.php', 'calendar'],
            'booking' => ['Booking', '/public/booking.php', 'ticket'],
        ];
    ?>
        <nav class="tabbar" aria-label="Quick links">
            <?php foreach ($tabs as $key => [$label, $href, $iconName]): ?>
                <a href="<?= e($href) ?>"<?= ($activePage ?? '') === $key ? ' aria-current="page"' : '' ?>><?= icon($iconName) ?><span><?= e($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <script src="/public/assets/js/main.js?v=20261113b"></script>
</body>
</html>
