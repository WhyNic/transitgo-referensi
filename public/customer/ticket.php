<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/bookings.php';

$user = require_user('customer');
$id = url_id();
$booking = customer_booking($id, (int) $user['id']);
if ($booking['status'] !== 'paid') {
    flash('E-tiket tersedia setelah pembayaran berhasil.', 'notice');
    redirect('/customer/payment.php?id=' . $id);
}
$passengers = booking_passengers($id);

page_start('E-tiket');
?>
<section class="container page-section narrow">
    <div class="page-heading"><a class="back-link" href="/customer/bookings.php">← Pesanan saya</a><h1>E-tiket</h1></div>
    <article class="panel ticket">
        <div class="ticket-heading"><strong><?= e(booking_code($id)) ?></strong><?php status_badge($booking['status']); ?></div>
        <h2><?= e($booking['origin'] . ' → ' . $booking['destination']) ?></h2>
        <dl class="details">
            <div><dt>Penyedia perjalanan</dt><dd><?= e($booking['provider_name']) ?></dd></div>
            <div><dt>Tanggal berangkat</dt><dd><?= e(tanggal($booking['departure_at'])) ?></dd></div>
            <div><dt>Waktu berangkat</dt><dd><?= e(waktu($booking['departure_at'])) ?> WIB</dd></div>
            <div><dt>Perkiraan tiba</dt><dd><?= e(tanggal($booking['arrival_at']) . ' · ' . waktu($booking['arrival_at'])) ?> WIB</dd></div>
        </dl>
        <?php passenger_list($passengers); ?>
        <div class="total"><span>Total dibayar</span><strong><?= e(rupiah((int) $booking['total'])) ?></strong></div>
        <p class="muted">E-tiket demo · Pembayaran simulasi</p>
    </article>
</section>
<?php page_end(); ?>
