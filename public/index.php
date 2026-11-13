<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/trips.php';
require APP_ROOT . '/includes/layout.php';

$values = ['origin' => 'Jakarta', 'destination' => 'Bandung', 'date' => date('Y-m-d', strtotime('tomorrow')), 'passengers' => 1];
$cities = trip_cities();
$trips = query(TRIP_SELECT . ' WHERE s.departure_at > NOW() HAVING available_seats > 0 ORDER BY s.departure_at LIMIT 3')->fetchAll();
page_start('Cari tiket bus dan travel');
?>
<section class="hero" id="cari">
    <div class="container hero-inner">
        <div class="hero-copy">
            <p class="hero-label"><span class="route-dot" aria-hidden="true"></span> Bus dan travel antarkota</p>
            <h1>Pesan tiket perjalanan Anda</h1>
            <p>Bandingkan jadwal, pilih kursi, dan simpan e-tiket di akun Anda.</p>
        </div>
        <div class="hero-graphic" aria-hidden="true">
            <div class="graphic-grid"></div><div class="graphic-route"></div>
            <span class="graphic-stop stop-origin"></span><span class="graphic-stop stop-destination"></span>
        </div>
        <div class="panel search-panel"><?php search_form($values, $cities); ?></div>
    </div>
</section>
<section class="container page-section departures">
    <div class="page-heading"><h2>Keberangkatan terdekat</h2></div>
    <?php if (!$trips): ?><div class="panel empty">Belum ada keberangkatan yang tersedia.</div><?php endif; ?>
    <div class="card-list"><?php foreach ($trips as $trip) trip_card($trip); ?></div>
</section>
<?php page_end(); ?>
