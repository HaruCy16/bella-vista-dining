<?php
/*
 * Landing page (Figma: "Bella Vista · Home"). Marketing only: every booking
 * action leads into the booking flow at /public/reserve.php.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$tableTypes = (new TableTypeRepository($pdo))->allWithTableCounts();
$tables = (new TableTypeRepository($pdo))->tables();
$itemsById = [];
foreach ((new MenuRepository($pdo))->categoriesWithItems() as $category) {
    foreach ($category['items'] as $item) {
        $itemsById[$item['item_id']] = $item;
    }
}
$pick = static fn (array $ids): array => array_values(array_filter(array_map(static fn (int $id) => $itemsById[$id] ?? null, $ids)));
$dishes = $pick([25, 28, 29]);   // Chicken Inasal, Caldereta de Res, Ginataang Seafood
$gallery = $pick([23, 41, 43]);  // stand-ins until venue photos arrive

$now = Config::now();
$slots = Config::reservationSlots();
$opens = Config::displayTime(Config::OPENS_AT);
$closes = Config::displayTime(Config::CLOSES_AT);
$lastSlotPassed = $now->format('H:i:s') >= end($slots);
$firstOpen = Config::nextOpenDates(1, $lastSlotPassed ? $now->modify('+1 day') : $now)[0];
$week = array_map(static fn (int $i) => $firstOpen->modify('monday this week')->modify("+{$i} days"), range(0, 6));

// "S1–S4", "F1–F3", "Room 1–2" from the actual tables.
$tableRange = static function (string $typeName) use ($tables): string {
    $ids = array_column(array_filter($tables, static fn (array $t) => $t['type_name'] === $typeName), 'table_id');
    if ($ids === []) {
        return '';
    }
    $first = reset($ids);
    $last = end($ids);
    return str_starts_with($first, 'VIP') ? 'Room ' . substr($first, 3) . '–' . substr($last, 3) : "{$first}–{$last}";
};
$peso = static fn (string $amount): string => Money::format(Money::fromPesos($amount));

$pageTitle = 'Bella Vista Dining · Good food. Beautiful views. Better company.';
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="home">
    <!-- Phones: photo card with the call to action -->
    <section class="wrap hero-m" aria-labelledby="hero-m-title">
        <div class="label-row"><p class="label label-clay">Filipino roots, garden soul</p><p class="label label-muted">Est. 2021</p></div>
        <div class="hero-card">
            <img src="<?= e(venue_image('hero', '/public/assets/images/menu/items/beef-kare-kare.jpg')) ?>" alt="" width="720" height="900" fetchpriority="high">
            <div class="hero-card-body">
                <h1 id="hero-m-title">Good food. Beautiful moments.</h1>
                <a class="button button-rust button-block button-split" href="/public/reserve.php">Reserve now <?= icon('arrow-right') ?></a>
            </div>
        </div>
        <p class="hero-m-hours"><?= icon('clock') ?>Tue–Sun, <?= e($opens) ?>–<?= e($closes) ?> · Closed Mondays</p>
    </section>

    <!-- Desktop hero -->
    <section class="wrap hero-d" aria-labelledby="hero-title">
        <div>
            <p class="label label-clay">Filipino roots, garden soul.</p>
            <h1 class="display" id="hero-title">Good food.<br>Beautiful views.<br>Better company.</h1>
            <p class="lede">A warm welcome, a garden setting, and familiar Filipino flavors with a continental touch. Make a little room for Bella Vista.</p>
            <div class="hero-actions">
                <a class="button button-forest" href="/public/reserve.php">Reserve a table <?= icon('arrow-up-right') ?></a>
                <a class="button button-paper" href="/public/menu.php">Explore our menu</a>
            </div>
            <p class="label label-muted hero-note">Open Tuesday–Sunday · <?= e($opens) ?>–<?= e($closes) ?></p>
        </div>
        <figure class="hero-media">
            <img src="<?= e(venue_image('hero', '/public/assets/images/menu/items/beef-kare-kare.jpg')) ?>" alt="A table set on the Bella Vista garden terrace" width="960" height="800" fetchpriority="high">
        </figure>
    </section>

    <div class="band">
        <ul class="wrap band-list">
            <li>Rooted in Filipino comfort</li>
            <li>Made for slow gatherings</li>
            <li>A garden escape, at your table</li>
        </ul>
    </div>

    <section class="wrap about" id="about" aria-labelledby="about-title">
        <div class="about-media reveal"><img src="<?= e(venue_image('dining-room', '/public/assets/images/menu/items/pancit-canton-special.jpg')) ?>" alt="The Bella Vista dining room opening onto the garden" loading="lazy" width="720" height="540"></div>
        <div class="reveal">
            <p class="label label-clay">About us</p>
            <h2 class="title" id="about-title">A place to linger.<br>A table to come home to.</h2>
            <p class="about-copy">Bella Vista Dining brings local Filipino comfort food and continental specialties to a cozy, ambient garden setting. Come as a couple, bring the family, or gather your favorite people around something delicious.</p>
            <p class="about-copy about-copy-strong">Our garden-to-table spirit is simple: thoughtful food, scenic views, and genuine hospitality.</p>
        </div>
    </section>

    <section class="wrap history" aria-labelledby="history-title">
        <div class="reveal">
            <p class="label label-clay">Company history</p>
            <h2 class="heading-lg" id="history-title">From estate table to destination.</h2>
        </div>
        <div class="reveal">
            <p class="history-year">2021</p>
            <h3>A beginning at Bella Vista estate</h3>
            <p>Established alongside the estate, our first tables welcomed in-house guests with signature dishes and garden views.</p>
        </div>
        <div class="reveal">
            <p class="history-year">A wider welcome</p>
            <h3>A destination in its own right</h3>
            <p>Public demand opened a new chapter: an independent restaurant where everyone can enjoy the Bella Vista experience.</p>
        </div>
    </section>

    <section class="tint kitchen" id="menu" aria-labelledby="menu-title">
        <div class="wrap">
            <div class="section-head section-head-row reveal">
                <div>
                    <p class="label label-clay">From our kitchen</p>
                    <h2 class="title" id="menu-title">Familiar flavors. A fresh perspective.</h2>
                </div>
                <a class="button button-paper button-small" href="/public/menu.php">View menu &amp; preorder <?= icon('arrow-up-right') ?></a>
            </div>
            <div class="dish-grid reveal">
                <?php foreach ($dishes as $dish): ?>
                    <article class="dish-card">
                        <img src="<?= e(menu_image($dish['image'])) ?>" alt="<?= e($dish['name']) ?>" loading="lazy" width="720" height="540">
                        <div class="dish-card-head"><h3><?= e($dish['name']) ?></h3><span><?= e($peso($dish['price'])) ?></span></div>
                        <p><?= e($dish['description']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="wrap preorder" aria-labelledby="preorder-title">
        <p class="label label-clay">Less planning at the table</p>
        <h2 class="heading-lg" id="preorder-title">Choose your favorites before you arrive.</h2>
        <p class="hint">Pair a preorder with your reservation. We’ll have your food chosen and table details together, so you can focus on the company.</p>
    </section>

    <section class="wrap gallery-section" aria-labelledby="gallery-title">
        <div class="section-head section-head-row">
            <h2 class="title" id="gallery-title">A little taste of the good life.</h2>
            <p class="label label-muted">The Bella Vista table</p>
        </div>
        <div class="gallery reveal">
            <?php foreach ($gallery as $i => $photo): ?>
                <img src="<?= e(venue_image('gallery-' . ($i + 1), $photo['image'])) ?>" alt="<?= e($photo['name']) ?> at Bella Vista" loading="lazy" width="720" height="540">
            <?php endforeach; ?>
        </div>
    </section>

    <section class="paper-band" id="tables" aria-labelledby="tables-title">
        <div class="wrap">
            <div class="section-head reveal">
                <p class="label label-clay">Make yourself at home</p>
                <h2 class="title" id="tables-title">Your people. Your kind of table.</h2>
            </div>
            <div class="table-types reveal">
                <?php foreach ($tableTypes as $type):
                    $name = $type['type_name'];
                    $fee = Money::fromPesos($type['reservation_fee']);
                    $extra = Money::fromPesos($type['extra_charge']);
                    $isVip = $extra > 0;
                ?>
                    <article class="table-type">
                        <h3><?= e($isVip ? 'VIP rooms' : $name) ?></h3>
                        <p class="capacity"><?= e($tableRange($name)) ?> · <?= $isVip
                            ? 'up to ' . e($type['base_capacity']) . ' guests'
                            : e($type['capacity_min']) . '–' . e($type['capacity_max']) . ' guests' ?></p>
                        <p class="table-type-fee"><?= $fee === 0 ? 'No reservation fee' : e(Money::format($fee)) . ($isVip ? ' fully consumable' : ' consumable fee') ?></p>
                        <p class="table-type-note"><?php if ($isVip): ?>Maximum <?= e($type['capacity_max']) ?>. Add <?= e(Money::format($extra)) ?> per guest above <?= e($type['base_capacity']) ?>.<?php elseif ($fee === 0): ?><?= e($peso($type['minimum_order'])) ?> minimum food order upon seating<?php else: ?>Credited toward your final food bill.<?php endif; ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pick a day, then a block: opens the booking flow with both filled in. -->
    <section class="wrap gathering-section" aria-labelledby="gathering-title">
        <form class="gathering reveal" action="/public/reserve.php" method="get">
            <div>
                <p class="label label-clay">A little planning, a lovely visit</p>
                <h2 class="title" id="gathering-title">There’s a time for every gathering.</h2>
                <p class="gathering-hours">Tuesday–Sunday · <?= e($opens) ?>–<?= e($closes) ?></p>
                <p class="hint">Closed Mondays. Each reservation is for two hours.</p>
            </div>
            <fieldset class="gathering-times">
                <legend>Choose one of six daily dining blocks</legend>
                <div class="slot-grid">
                    <?php foreach ($slots as $slot): ?>
                        <button class="slot" type="submit" name="time" value="<?= e($slot) ?>"><?= e(Config::slotLabel($slot)) ?></button>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="card gathering-days">
                <legend class="sr-only">Day</legend>
                <div class="cal-head"><p class="label m-0"><?= e($firstOpen->format('F Y')) ?></p></div>
                <div class="cal-week">
                    <?php foreach ($week as $day):
                        $iso = $day->format('Y-m-d');
                        $closed = (int) $day->format('N') === Config::CLOSED_WEEKDAY;
                        $off = $closed || $iso < $firstOpen->format('Y-m-d');
                    ?>
                        <label class="cal-day<?= $closed ? ' is-closed' : '' ?><?= $off ? ' is-off' : '' ?>">
                            <input type="radio" name="date" value="<?= e($iso) ?>"<?= $iso === $firstOpen->format('Y-m-d') ? ' checked' : '' ?><?= $off ? ' disabled' : '' ?>>
                            <small><?= e($day->format('D')) ?></small><b><?= e($day->format('j')) ?></b>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="cal-note">Mondays unavailable · Restaurant closed</p>
            </fieldset>
        </form>
    </section>

    <section class="cta-band" aria-labelledby="cta-title">
        <div class="wrap cta-band-inner">
            <div>
                <h2 class="title" id="cta-title">We’ll save you a seat.</h2>
                <p>A family lunch, a quiet dinner, or a celebration worth gathering for.</p>
            </div>
            <a class="button button-paper" href="/public/reserve.php">Reserve your table <?= icon('arrow-up-right') ?></a>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
