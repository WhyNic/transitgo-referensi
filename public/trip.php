<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/trips.php';
require APP_ROOT . '/includes/layout.php';

$id = url_id();
try {
    $passengers = int_field($_GET['passengers'] ?? '1', 'Jumlah penumpang', 1, 6);
} catch (ValidationError $failure) {
    fail_page(400, $failure->getMessage());
}
$trip = trip_by_id($id);
$user = current_user();
$taken = active_seats($id);
$capacity = (int) $trip['seat_capacity'];
$available = $capacity - count($taken);
$canBook = $trip['departure_at'] > date('Y-m-d H:i:s') && $available >= $passengers;
page_start('Detail perjalanan');
?>
<section class="container page-section">
    <div class="page-heading"><a class="back-link" href="/">← Cari tiket</a><h1>Detail perjalanan</h1></div>
    <div class="booking-layout">
        <div class="panel">
            <img class="trip-vehicle-photo" src="<?= e(verification_file_url('vehicle_photo', (int) $trip['vehicle_id'])) ?>" alt="<?= e('Foto ' . $trip['vehicle_name']) ?>">
            <?php trip_summary($trip); ?>
            <p class="muted"><?= e($available) ?> dari <?= e($capacity) ?> kursi tersedia.</p>
        </div>
        <div class="panel">
            <h2>Pilih kursi</h2>
            <?php if (!$canBook): ?>
                <p class="muted">Perjalanan sudah berangkat atau kursi yang tersedia kurang dari <?= e($passengers) ?>.</p>
            <?php elseif (!$user): ?>
                <p class="muted">Masuk sebagai pelanggan untuk memilih kursi.</p>
                <a class="button" href="<?= e('/login.php?trip=' . $id . '&passengers=' . $passengers) ?>">Masuk untuk memesan</a>
            <?php elseif ($user['role'] !== 'customer'): ?>
                <p class="muted">Pemesanan tersedia untuk akun pelanggan.</p>
            <?php else: ?>
                <form method="post" action="<?= e('/customer/checkout.php?id=' . $id) ?>" class="form-stack" data-seat-form data-seat-limit="<?= e($passengers) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="passengers" value="<?= e($passengers) ?>">
                    <p class="muted">Pilih <?= e($passengers) ?> kursi. Kursi abu-abu sudah dipesan.</p>
                    <fieldset class="seat-fieldset">
                        <legend class="sr-only">Nomor kursi</legend>
                        <div class="seat-grid">
                            <?php for ($number = 1; $number <= $capacity; $number++): ?>
                                <label class="seat">
                                    <input type="checkbox" name="seats[]" value="<?= e($number) ?>" aria-label="<?= e('Kursi ' . $number) ?>" <?= in_array($number, $taken, true) ? 'disabled' : '' ?>>
                                    <span><?= e($number) ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                    <div class="form-actions"><button class="button">Lanjut ke checkout</button><span class="muted" data-seat-count aria-live="polite"></span></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="/assets/app.js" defer></script>
<?php page_end(); ?>
