<?php
/* Map + address + directions. Used on the About and Visit pages. */
?>
<div class="location<?= !empty($locationStacked) ? ' location-stacked' : '' ?>">
    <div class="map-frame">
        <iframe src="<?= e(Config::mapEmbedUrl()) ?>" title="Map showing Bella Vista Dining in Santo Tomas, Batangas" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>
    <div class="location-details">
        <p class="location-address"><?= icon('pin') ?><span><?php foreach (Config::ADDRESS_LINES as $i => $line): ?><?= $i ? '<br>' : '' ?><?= e($line) ?><?php endforeach; ?></span></p>
        <a class="button button-light" href="<?= e(Config::directionsUrl()) ?>" target="_blank" rel="noopener">Get directions <?= icon('arrow-right') ?></a>
    </div>
</div>
