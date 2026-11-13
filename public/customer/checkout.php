<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/trips.php';
require APP_ROOT . '/includes/bookings.php';

$user = require_user('customer');
require_post();
$id = url_id();
$trip = trip_by_id($id);
$action = field($_POST, 'action');
if (!in_array($action, ['review', 'book'], true)) {
    fail_page(400, 'Tindakan tidak valid.');
}
$count = 1;
try {
    $count = int_field($_POST['passengers'] ?? null, 'Jumlah penumpang', 1, 6);
    $seats = selected_seats($_POST['seats'] ?? null, (int) $trip['seat_capacity']);
    if (count($seats) !== $count) {
        throw new ValidationError('Jumlah kursi harus sama dengan jumlah penumpang.');
    }
    if ($trip['departure_at'] <= date('Y-m-d H:i:s') || array_intersect($seats, active_seats($id))) {
        throw new ValidationError('Kursi pilihan Anda tidak tersedia. Silakan pilih kembali.');
    }
} catch (ValidationError $failure) {
    flash($failure->getMessage(), 'danger');
    redirect('/trip.php?id=' . $id . '&passengers=' . $count);
}

$error = '';
$names = [];
if ($action === 'book') {
    $input = is_array($_POST['passenger'] ?? null) ? $_POST['passenger'] : [];
    foreach ($seats as $seat) {
        $names[$seat] = field($input, (string) $seat);
    }
    try {
        foreach ($names as $seat => $name) {
            $names[$seat] = clean_text($name, 'Nama penumpang kursi ' . $seat, 100);
        }
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    }
    if ($error === '') {
        try {
            $bookingId = create_booking($id, (int) $trip['provider_id'], (int) $user['id'], $names);
            redirect('/customer/payment.php?id=' . $bookingId);
        } catch (ValidationError $failure) {
            flash($failure->getMessage(), 'danger');
        } catch (PDOException $failure) {
            if (!mysql_error($failure, 1062)) {
                throw $failure;
            }
            flash('Kursi pilihan Anda baru saja dipesan. Silakan pilih kembali.', 'danger');
        }
        redirect('/trip.php?id=' . $id . '&passengers=' . $count);
    }
} else {
    $names[$seats[0]] = $user['full_name'];
}

page_start('Checkout');
?>
<section class="container page-section">
    <div class="page-heading"><a class="back-link" href="<?= e('/trip.php?id=' . $id . '&passengers=' . $count) ?>">← Ubah pilihan kursi</a><h1>Checkout</h1></div>
    <div class="booking-layout">
        <div class="panel"><?php trip_summary($trip); ?></div>
        <div class="panel">
            <h2>Data penumpang</h2>
            <?php form_error($error); ?>
            <form method="post" action="<?= e('/customer/checkout.php?id=' . $id) ?>" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="book">
                <input type="hidden" name="passengers" value="<?= e($count) ?>">
                <?php foreach ($seats as $seat): ?>
                    <input type="hidden" name="seats[]" value="<?= e($seat) ?>">
                    <label>Nama penumpang · Kursi <?= e($seat) ?>
                        <input name="passenger[<?= e($seat) ?>]" value="<?= e($names[$seat] ?? '') ?>" maxlength="100" required>
                    </label>
                <?php endforeach; ?>
                <div class="total"><span>Total untuk <?= e($count) ?> kursi</span><strong><?= e(rupiah((int) $trip['price'] * $count)) ?></strong></div>
                <button class="button">Buat pesanan</button>
            </form>
        </div>
    </div>
</section>
<?php page_end(); ?>
