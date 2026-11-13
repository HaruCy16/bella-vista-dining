<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$tableTypes = (new TableTypeRepository($pdo))->allWithTableCounts();
$categories = (new MenuRepository($pdo))->categoriesWithItems();

$itemsById = [];
foreach ($categories as $category) {
    foreach ($category['items'] as $item) {
        $itemsById[$item['item_id']] = $item + ['category' => $category['category_name']];
    }
}
$pick = static fn (array $ids): array => array_values(array_filter(array_map(static fn (int $id) => $itemsById[$id] ?? null, $ids)));
$highlights = $pick([25, 29, 22]);         // Chicken Inasal, Ginataang Seafood, Chicken Adobo
$gallery = $pick([23, 41, 43]);            // Crispy Pata, Halo-Halo, Calamansi Juice (until venue photos arrive)
$shareDish = $itemsById[11] ?? null;       // Lumpiang Shanghai

$now = Config::now();
$slots = Config::reservationSlots();
$lastSlotPassed = $now->format('H:i:s') >= end($slots);
$openDates = Config::nextOpenDates(7, $lastSlotPassed ? $now->modify('+1 day') : $now);
$opens = Config::displayTime(Config::OPENS_AT);
$closes = Config::displayTime(Config::CLOSES_AT);
$defaultGuests = ['Standard' => 2, 'Family' => 6, 'VIP' => 15];
$reserve = static fn (array $params = []): string => '/public/reserve.php' . ($params ? '?' . http_build_query($params) : '');

require __DIR__ . '/templates/header.php';
?>
<main id="main">
    <!-- Mobile hero: photo card with the call to action -->
    <section class="wrap hero-m" aria-labelledby="hero-m-title">
        <div class="label-row"><p class="label">From our farm to your table</p><p class="label label-muted">Est. 2021</p></div>
        <div class="hero-card">
            <img src="<?= e(venue_image('hero', '/public/assets/images/menu/items/beef-kare-kare.jpg')) ?>" alt="" width="720" height="900" fetchpriority="high">
            <div class="hero-card-body">
                <h1 id="hero-m-title">Good food. Beautiful moments.</h1>
                <a class="button button-rust button-block button-split" href="<?= e($reserve()) ?>">Reserve now <?= icon('arrow-right') ?></a>
            </div>
        </div>
        <p class="hero-m-hours"><?= icon('clock') ?>Tue–Sun, <?= e($opens) ?>–<?= e($closes) ?> · Closed Mondays</p>
    </section>

    <!-- Desktop hero -->
    <section class="wrap hero-d" aria-labelledby="hero-title">
        <div>
            <p class="label">From our farm to your table</p>
            <h1 class="display" id="hero-title">Good food.<br>Beautiful views.<br>Better company.</h1>
            <p class="lede">Filipino comfort food and continental favourites, served slow in the garden of the Bella Vista estate. Book a two-hour table and pre-order your favourites.</p>
            <div class="hero-actions">
                <a class="button button-forest" href="<?= e($reserve()) ?>">Reserve a table</a>
                <a class="button button-outline" href="/public/menu.php">See the menu</a>
            </div>
            <p class="hero-note">Tuesday to Sunday · <?= e($opens) ?> to <?= e($closes) ?> · Closed Mondays</p>
        </div>
        <figure class="hero-media">
            <img src="<?= e(venue_image('hero', '/public/assets/images/menu/items/beef-kare-kare.jpg')) ?>" alt="A table set in the Bella Vista garden" width="960" height="720">
        </figure>
    </section>

    <div class="band" role="presentation">
        <ul class="wrap band-list">
            <li>Filipino comfort, farm to table</li>
            <li>Tables for 2 to 20 guests</li>
            <li>A garden view, Santo Tomas, Batangas</li>
        </ul>
    </div>

    <section class="section section-top wrap about" aria-labelledby="about-title">
        <div class="about-media reveal"><img src="<?= e(venue_image('dining-room', '/public/assets/images/menu/items/pancit-canton-special.jpg')) ?>" alt="The Bella Vista dining room opening onto the garden" loading="lazy" width="720" height="560"></div>
        <div class="reveal">
            <p class="label label-clay">Rooted in Bella Vista</p>
            <h2 class="title" id="about-title">A place to linger. A table to come home to.</h2>
            <p class="lede">Born at the Bella Vista estate in 2021 for in-house guests, our table now welcomes everyone for Filipino comfort food and continental specialties.</p>
            <p class="about-copy">We cook the way families do on a long Sunday: generous plates meant for sharing, ingredients from nearby farms, and no rush to leave.</p>
            <a class="text-link" href="/public/about.php">Read our story <?= icon('arrow-right') ?></a>
        </div>
    </section>

    <section class="wrap section history" aria-label="Our history">
        <div class="reveal">
            <p class="label label-clay">Our history</p>
            <h2 class="heading-lg">From estate table to destination.</h2>
        </div>
        <div class="reveal">
            <p class="history-year">2021</p>
            <h3>The estate’s dining hall</h3>
            <p>We opened to feed in-house guests of the Bella Vista estate, cooking from the farms around us.</p>
        </div>
        <div class="reveal">
            <p class="history-year">A wider welcome</p>
            <h3>Open to everyone</h3>
            <p>Guests kept coming back for the food and the garden, so we opened our doors to all.</p>
        </div>
    </section>

    <section class="tint section section-top" id="menu" aria-labelledby="menu-title">
        <div class="wrap">
            <div class="section-head section-head-row reveal">
                <div>
                    <p class="label">Our menu</p>
                    <h2 class="title" id="menu-title">Familiar flavors. A fresh perspective.</h2>
                </div>
                <a class="button button-paper button-small" href="/public/menu.php">See the full menu</a>
            </div>
            <div class="dish-grid reveal">
                <?php foreach ($highlights as $dish): ?>
                    <article class="dish-card">
                        <img src="<?= e(menu_image($dish['image'])) ?>" alt="<?= e($dish['name']) ?>" loading="lazy" width="720" height="540">
                        <div class="dish-card-head"><h3><?= e($dish['name']) ?></h3><span><?= e(Money::format(Money::fromPesos($dish['price']))) ?></span></div>
                        <p><?= e($dish['description']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="wrap section section-top" aria-labelledby="preorder-title">
        <div class="preorder reveal">
            <div>
                <p class="label label-clay">Pre-order</p>
                <h2 class="heading-lg" id="preorder-title">Choose your favorites before you arrive.</h2>
                <p class="lede">Add dishes while you book and the kitchen times them to your table. It’s optional: you can always order when you sit down.</p>
            </div>
            <?php if ($shareDish): ?>
                <a class="share-card" href="/public/menu.php">
                    <img src="<?= e(menu_image($shareDish['image'])) ?>" alt="" loading="lazy" width="96" height="96">
                    <span><strong>Made to be shared</strong><small>Explore our menu</small></span>
                    <?= icon('arrow-up-right') ?>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="wrap section" aria-labelledby="gallery-title">
        <div class="section-head reveal"><h2 class="title" id="gallery-title">A little taste of the good life.</h2></div>
        <div class="gallery reveal">
            <?php foreach ($gallery as $i => $photo): ?>
                <img src="<?= e(venue_image('gallery-' . ($i + 1), $photo['image'])) ?>" alt="<?= e($photo['name']) ?> at Bella Vista" loading="lazy" width="720" height="540">
            <?php endforeach; ?>
        </div>
    </section>

    <section class="paper-band section section-top" id="tables" aria-labelledby="tables-title">
        <div class="wrap">
            <div class="section-head reveal">
                <p class="label label-clay">Our tables</p>
                <h2 class="title" id="tables-title">Your people. Your kind of table.</h2>
            </div>
            <div class="table-types reveal">
                <?php foreach ($tableTypes as $type):
                    $fee = Money::fromPesos($type['reservation_fee']);
                    $minimum = Money::fromPesos($type['minimum_order']);
                    $extra = Money::fromPesos($type['extra_charge']);
                    $isVip = $extra > 0;
                    $count = (int) $type['table_count'];
                ?>
                    <article class="table-type">
                        <h3><?= e($isVip ? 'VIP rooms' : $type['type_name']) ?></h3>
                        <p class="capacity"><?= e($type['capacity_min']) ?>–<?= e($type['capacity_max']) ?> guests · <?= e($count) ?> <?= e($isVip ? ($count === 1 ? 'room' : 'rooms') : ($count === 1 ? 'table' : 'tables')) ?></p>
                        <dl>
                            <div><dt>Reservation</dt><dd><?= $fee === 0 ? 'No fee' : e(Money::format($fee)) . ' consumable' ?></dd></div>
                            <div><dt><?= $isVip ? 'Extra guests' : 'Minimum order' ?></dt><dd><?= $isVip
                                ? e(Money::format($extra)) . ' each above ' . e($type['base_capacity'])
                                : ($minimum > 0 ? e(Money::format($minimum)) . ' upon seating' : 'None') ?></dd></div>
                        </dl>
                        <a class="text-link" href="<?= e($reserve(['guests' => $defaultGuests[$type['type_name']] ?? (int) $type['capacity_min']])) ?>">Book <?= e($isVip ? 'a VIP room' : 'a ' . strtolower($type['type_name']) . ' table') ?> <?= icon('arrow-right') ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="wrap section section-top" aria-labelledby="gathering-title">
        <form class="gathering reveal" action="/public/reserve.php" method="get">
            <div>
                <p class="label label-clay">Reserve a table</p>
                <h2 class="title" id="gathering-title">There’s a table for every gathering.</h2>
                <p class="gathering-hours">Tuesday–Sunday · <?= e($opens) ?>–<?= e($closes) ?></p>
                <p class="hint">Pick a day and a two-hour block. You’ll choose your table next.</p>
            </div>
            <fieldset class="gathering-times">
                <legend class="label">Choose a time</legend>
                <div class="slot-grid">
                    <?php foreach ($slots as $slot): ?>
                        <label class="slot"><input type="radio" name="time" value="<?= e($slot) ?>"<?= $slot === '18:00:00' ? ' checked' : '' ?>><span><?= e(Config::slotLabel($slot)) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="card gathering-days">
                <legend class="label"><?= e($openDates[0]->format('F Y')) ?></legend>
                <div class="cal-week">
                    <?php foreach ($openDates as $i => $date): ?>
                        <label class="cal-day"><input type="radio" name="date" value="<?= e($date->format('Y-m-d')) ?>"<?= $i === 0 ? ' checked' : '' ?>><small><?= e($date->format('D')) ?></small><b><?= e($date->format('j')) ?></b></label>
                    <?php endforeach; ?>
                </div>
                <button class="button button-forest button-block" type="submit">See open tables</button>
            </fieldset>
        </form>
    </section>

    <section class="visit section" id="visit" aria-labelledby="visit-title">
        <div class="wrap visit-grid">
            <div class="reveal">
                <h2 class="title" id="visit-title">Find us at the Bella Vista estate.</h2>
                <p class="lede text-cream/90">A short drive south of Manila, in the garden of the Bella Vista estate.</p>
            </div>
            <div class="reveal">
                <table class="hours-table">
                    <caption class="sr-only">Opening hours</caption>
                    <tbody>
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $i => $day):
                            $isClosed = $i + 1 === Config::CLOSED_WEEKDAY;
                            $isToday = (int) $now->format('N') === $i + 1;
                        ?>
                            <tr class="<?= $isToday ? 'is-today' : '' ?><?= $isClosed ? ' is-closed' : '' ?>">
                                <th scope="row"><?= e($day) ?><?= $isToday ? ' (today)' : '' ?></th>
                                <td><?= $isClosed ? 'Closed' : e("$opens – $closes") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wrap mt-10 reveal"><?php require __DIR__ . '/templates/partials/location.php'; ?></div>
    </section>

    <section class="section section-top wrap" id="policies" aria-labelledby="policies-title">
        <div class="policies">
            <div class="reveal">
                <h2 class="heading-lg" id="policies-title">Good to know before you book.</h2>
                <p class="lede">The short version of our booking policies. You’ll confirm them at checkout.</p>
            </div>
            <dl class="policy-list reveal">
                <div><dt>Running late</dt><dd>We hold your table for <?= e(Config::GRACE_MINUTES) ?> minutes past your start time, then release it.</dd></div>
                <div><dt>Cancelling</dt><dd>Cancel at least <?= e(Config::CANCEL_NOTICE_HOURS) ?> hours ahead on the <a href="/public/booking.php">Your booking</a> page. Family and VIP fees aren’t refunded for later or same-day cancellations.</dd></div>
                <div><dt>Fees</dt><dd>Family and VIP fees are credited to your food bill. If your food comes to less than the fee, the difference isn’t refunded.</dd></div>
                <div><dt>Paying</dt><dd>Pay a downpayment or the full amount online, or pay at the restaurant in cash or by card.</dd></div>
                <div><dt>Outside food</dt><dd>Please don’t bring outside food or drinks.</dd></div>
            </dl>
        </div>
    </section>

    <section class="cta-band" aria-labelledby="cta-title">
        <div class="wrap cta-band-inner">
            <div>
                <h2 class="title" id="cta-title">We’ll save you a seat.</h2>
                <p>Book a two-hour table in under a minute. Pre-ordering is optional.</p>
            </div>
            <a class="button button-paper" href="<?= e($reserve()) ?>">Reserve a table</a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/templates/footer.php'; ?>
