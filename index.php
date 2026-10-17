<?php
$pageTitle = 'Farm-to-table flavors';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero-section">
    <div class="hero-media" style="background-image: url('/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-2.webp');"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
        <div class="max-w-3xl hero-copy">
            <p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span> Bella Vista Estate · Tagaytay</p>
            <h1 class="display-heading text-white">Farm-to-Table Flavors, <em>Unforgettable Moments.</em></h1>
            <p class="hero-lead">A garden-side table for Filipino comfort food, continental craft, and the kind of afternoon that asks you to stay a little longer.</p>
            <div class="flex flex-wrap gap-3 mt-8">
                <a class="btn btn-light" href="/pages/reservations.php">Reserve a table <span aria-hidden="true">↗</span></a>
                <a class="btn btn-ghost-light" href="/pages/menu.php">Explore our menu <span aria-hidden="true">↓</span></a>
            </div>
        </div>
        <div class="hero-note hidden xl:flex"><span class="hero-note-line"></span><span>Rooted in the estate<br>since 2021</span></div>
    </div>
    <div class="hero-scroll"><span>Scroll to wander</span><span class="hero-scroll-line"></span></div>
</section>

<section class="section-pad intro-section">
    <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
        <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr] items-start">
            <div>
                <p class="eyebrow"><span class="eyebrow-rule"></span> A seat at our table</p>
                <h2 class="section-title mt-5">Good food feels even better when there’s a story behind it.</h2>
            </div>
            <div class="lg:pt-2">
                <p class="large-copy">Established alongside the Bella Vista estate in 2021, our dining hall began as a quiet table for in-house guests. Today, it is an independent destination for families, couples, and friends looking for a delicious escape in the garden.</p>
                <a href="/pages/about.php" class="text-link mt-7">Read our story <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </div>
</section>

<section class="feature-band">
    <div class="feature-band-image" style="background-image: url('/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp');"></div>
    <div class="feature-band-content">
        <p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span> The Bella Vista feeling</p>
        <h2 class="section-title text-white">Come for the view.<br><em>Stay for the warmth.</em></h2>
        <p class="text-white/70 mt-6 max-w-md leading-8">Every plate moves between the familiar and the unexpected — local comfort, continental technique, and ingredients that have a sense of place.</p>
        <div class="grid grid-cols-2 gap-5 mt-10 max-w-md">
            <div class="metric-card"><span class="metric-value">01</span><span class="metric-label">Garden-side dining</span></div>
            <div class="metric-card"><span class="metric-value">02</span><span class="metric-label">Made with local hands</span></div>
        </div>
    </div>
</section>

<section class="section-pad bg-cream">
    <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
        <div class="flex flex-wrap justify-between items-end gap-6 mb-10">
            <div>
                <p class="eyebrow"><span class="eyebrow-rule"></span> From our kitchen</p>
                <h2 class="section-title mt-4">A few reasons to<br><em>come hungry.</em></h2>
            </div>
            <a href="/pages/menu.php" class="text-link">See the full menu <span aria-hidden="true">↗</span></a>
        </div>
        <div class="dish-grid">
            <?php foreach (array_slice($menuItems, 0, 3) as $index => $item): ?>
                <article class="dish-card <?= $index === 1 ? 'dish-card-offset' : '' ?>">
                    <div class="dish-image-wrap"><img src="<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy"><span class="dish-number">0<?= $index + 1 ?></span></div>
                    <div class="pt-5 pr-4"><p class="card-kicker"><?= htmlspecialchars($item['category']) ?></p><h3 class="card-title mt-2"><?= htmlspecialchars($item['name']) ?></h3><p class="card-description mt-2"><?= htmlspecialchars($item['description']) ?></p><div class="flex justify-between items-center mt-5"><span class="price"><?= peso($item['price']) ?></span><button class="icon-button" type="button" data-add-to-cart data-item-id="<?= $item['id'] ?>" aria-label="Add <?= htmlspecialchars($item['name']) ?> to cart">+</button></div></div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-pad bg-beige/35">
    <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
        <div class="grid gap-12 lg:grid-cols-[.9fr_1.1fr] items-center">
            <div class="relative gallery-feature">
                <img src="/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp" alt="Chef plating a dish at Bella Vista Dining" loading="lazy">
                <div class="gallery-stamp"><span>BV</span><small>made with<br>good things</small></div>
            </div>
            <div class="lg:pl-8">
                <p class="eyebrow"><span class="eyebrow-rule"></span> The kitchen, in season</p>
                <h2 class="section-title mt-5">A little bit of<br><em>the garden, on every plate.</em></h2>
                <p class="large-copy mt-6">Our menu follows the rhythm of the estate. Young greens, bright citrus, local seafood, and familiar flavors find their way to your table with a little more care.</p>
                <div class="flex flex-wrap gap-3 mt-8"><a href="/pages/gallery.php" class="btn btn-primary">See the gallery <span aria-hidden="true">↗</span></a><a href="/pages/policies.php" class="btn btn-outline">Plan your visit</a></div>
            </div>
        </div>
    </div>
</section>

<section class="section-pad hours-section">
    <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
        <div class="hours-card">
            <div><p class="eyebrow"><span class="eyebrow-rule"></span> Make an afternoon of it</p><h2 class="section-title mt-4">Your table is<br><em>waiting.</em></h2></div>
            <div class="hours-details"><p class="card-kicker">Opening hours</p><div class="hours-row"><span>Tuesday – Sunday</span><strong>10:00 AM – 10:00 PM</strong></div><div class="hours-row muted"><span>Monday</span><span>Closed for maintenance</span></div><p class="text-sm text-walnut/65 leading-7 mt-6 max-w-sm">Reservations are held for 15 minutes after the scheduled time. Outside food and beverages are not permitted.</p><a href="/pages/reservations.php" class="btn btn-primary mt-7">Find your table <span aria-hidden="true">↗</span></a></div>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12 flex flex-col lg:flex-row justify-between gap-8 items-start lg:items-end">
        <div><p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span> An invitation from the estate</p><h2 class="section-title text-white mt-5 max-w-2xl">Bring your favorite people.<br><em>We’ll take care of the rest.</em></h2></div>
        <a href="/pages/reservations.php" class="btn btn-light shrink-0">Reserve a table <span aria-hidden="true">↗</span></a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
