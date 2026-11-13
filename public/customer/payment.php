<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/bookings.php';

$user = require_user('customer');
$id = url_id();
$booking = customer_booking($id, (int) $user['id']);
if (is_post()) {
    require_post();
    if (field($_POST, 'action') !== 'simulate') {
        fail_page(400, 'Tindakan pembayaran tidak valid.');
    }
    simulate_payment($id, (int) $user['id'], (int) $booking['schedule_id']);
    $booking = customer_booking($id, (int) $user['id']);
    if ($booking['status'] === 'paid') {
        redirect('/customer/ticket.php?id=' . $id);
    }
    flash('Batas waktu pembayaran sudah berakhir.', 'notice');
    redirect('/customer/payment.php?id=' . $id);
}
$passengers = $booking['status'] === 'expired' ? [] : booking_passengers($id);

page_start('Pembayaran');
?>
<section class="container page-section narrow">
    <div class="page-heading"><a class="back-link" href="/customer/bookings.php">← Pesanan saya</a><h1>Pembayaran</h1></div>
    <div class="panel">
        <div class="ticket-heading"><strong><?= e(booking_code($id)) ?></strong><?php status_badge($booking['status']); ?></div>
        <h2><?= e($booking['origin'] . ' → ' . $booking['destination']) ?></h2>
        <p class="muted"><?= e($booking['provider_name'] . ' · ' . tanggal($booking['departure_at']) . ' · ' . waktu($booking['departure_at'])) ?> WIB</p>
        <?php passenger_list($passengers); ?>
        <div class="total"><span>Total pesanan</span><strong><?= e(rupiah((int) $booking['total'])) ?></strong></div>
        <?php if ($booking['status'] === 'pending'): ?>
            <p class="muted">Bayar sebelum <?= e(waktu($booking['expires_at'])) ?> WIB. Pembayaran ini simulasi dan tidak menarik dana.</p>
            <form method="post" class="form-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="simulate">
                <button class="button">Simulasikan pembayaran</button>
            </form>
        <?php elseif ($booking['status'] === 'paid'): ?>
            <a class="button" href="<?= e('/customer/ticket.php?id=' . $id) ?>">Lihat e-tiket</a>
        <?php else: ?>
            <p class="muted">Kursi sudah dilepas. Cari perjalanan untuk membuat pesanan baru.</p>
            <a class="button button-outline" href="/">Cari perjalanan</a>
        <?php endif; ?>
    </div>
</section>
<?php page_end(); ?>
