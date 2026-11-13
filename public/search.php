<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/trips.php';
require APP_ROOT . '/includes/layout.php';

$values = [
    'origin' => field($_GET, 'origin'),
    'destination' => field($_GET, 'destination'),
    'date' => field($_GET, 'date'),
    'passengers' => field($_GET, 'passengers'),
];
$error = '';
$trips = [];
try {
    $origin = clean_text($values['origin'], 'Kota asal', 80);
    $destination = clean_text($values['destination'], 'Kota tujuan', 80);
    $date = strict_date($values['date'], 'Y-m-d', 'Tanggal berangkat');
    $passengers = int_field($values['passengers'], 'Jumlah penumpang', 1, 6);
    if ($date < new DateTimeImmutable('today') || mb_strtolower($origin) === mb_strtolower($destination)) {
        throw new ValidationError('Pilih tanggal hari ini atau setelahnya serta dua kota yang berbeda.');
    }
    $day = $date->format('Y-m-d');
    $trips = query(
        TRIP_SELECT . ' WHERE r.origin = ? AND r.destination = ? AND s.departure_at BETWEEN ? AND ? AND s.departure_at > NOW()
        HAVING available_seats >= ? ORDER BY s.departure_at, s.id',
        [$origin, $destination, $day . ' 00:00:00', $day . ' 23:59:59', $passengers]
    )->fetchAll();
} catch (ValidationError $failure) {
    $error = $failure->getMessage();
}

page_start('Hasil pencarian');
?>
<section class="container page-section">
    <div class="page-heading"><h1>Hasil pencarian</h1></div>
    <div class="panel section-gap"><?php search_form($values, trip_cities()); ?></div>
    <?php form_error($error); ?>
    <?php if ($error === '' && !$trips): ?><div class="panel empty"><h2>Jadwal belum tersedia</h2><p class="muted">Coba tanggal lain atau kurangi jumlah penumpang.</p></div><?php endif; ?>
    <div class="card-list"><?php foreach ($trips as $trip) trip_card($trip, $passengers); ?></div>
</section>
<?php page_end(); ?>
