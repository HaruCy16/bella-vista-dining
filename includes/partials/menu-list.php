<?php
/*
 * Menu as category tabs + dish rows. Used by the menu page (read only) and the
 * booking flow ($menuOrderable = true adds quantity steppers). Prices on the page
 * are for display; the server prices every pre-order itself.
 *
 * Expects: $menuCategories (MenuRepository::categoriesWithItems()), optional $menuOrderable.
 */
$menuOrderable = $menuOrderable ?? false;
$shortNames = ['Main Courses' => 'Mains', 'Appetizers & Soups' => 'Starters & soups', 'Desserts & Drinks' => 'Desserts & drinks'];
?>
<div class="menu-browser" data-tabs>
    <div class="menu-tabs" role="tablist" aria-label="Menu categories">
        <?php foreach ($menuCategories as $index => $category): ?>
            <button class="menu-tab" type="button" role="tab" id="menu-tab-<?= e($category['category_id']) ?>"
                    aria-controls="menu-panel-<?= e($category['category_id']) ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                    tabindex="<?= $index === 0 ? '0' : '-1' ?>"><?= e($shortNames[$category['category_name']] ?? $category['category_name']) ?></button>
        <?php endforeach; ?>
        <span class="menu-tab-indicator" aria-hidden="true"></span>
    </div>
    <?php foreach ($menuCategories as $index => $category): ?>
        <section class="menu-panel" role="tabpanel" id="menu-panel-<?= e($category['category_id']) ?>" aria-labelledby="menu-tab-<?= e($category['category_id']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
            <h2 class="menu-panel-title"><?= e($category['category_name']) ?></h2>
            <ul class="menu-rows">
                <?php foreach ($category['items'] as $item):
                    $price = Money::fromPesos($item['price']);
                ?>
                    <li class="menu-row" data-item data-item-id="<?= e($item['item_id']) ?>" data-price="<?= e($price) ?>" data-name="<?= e($item['name']) ?>">
                        <div class="menu-row-photo"><img src="<?= e(menu_image($item['image'])) ?>" alt="" loading="lazy" width="112" height="112"></div>
                        <div class="menu-row-body">
                            <h3><?= e($item['name']) ?></h3>
                            <p><?= e($item['description']) ?></p>
                            <div class="menu-row-foot">
                                <span class="menu-row-price"><?= e(Money::format($price)) ?></span>
                                <?php if ($menuOrderable): ?>
                                    <div class="qty" role="group" aria-label="<?= e($item['name']) ?> quantity">
                                        <button type="button" data-qty="-1" aria-label="Remove one <?= e($item['name']) ?>"><?= icon('minus') ?></button>
                                        <output data-qty-value aria-live="polite">0</output>
                                        <button type="button" data-qty="1" aria-label="Add one <?= e($item['name']) ?>"><?= icon('plus') ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>
