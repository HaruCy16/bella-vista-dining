    <footer class="footer">
        <div class="wrap footer-row">
            <a href="/index.php" class="brand" aria-label="Bella Vista Dining, home"><?= brand_logo(48, '') ?></a>
            <div class="footer-middle">
                <p>Filipino roots, a garden table · Established 2021</p>
                <nav aria-label="Footer"><a href="/public/visit.php">Visit &amp; policies</a><a href="/public/booking.php">Your booking</a><a href="/public/contact.php">Contact</a></nav>
            </div>
            <p>Tue–Sun · <?= e(Config::displayTime(Config::OPENS_AT)) ?>–<?= e(Config::displayTime(Config::CLOSES_AT)) ?></p>
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
