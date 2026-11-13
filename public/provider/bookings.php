<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/bookings.php';

$user = require_user('provider');
$bookings = query(BOOKING_SELECT . ' WHERE s.provider_id = ? ORDER BY b.created_at DESC, b.id DESC', [$user['id']])->fetchAll();

page_start('Pesanan penyedia');
?>
<section class="container page-section">
    <div class="page-heading"><a class="back-link" href="/provider/">← Dasbor penyedia</a><h1>Pesanan penyedia</h1></div>
    <div class="panel table-panel"><?php booking_table($bookings, 'provider'); ?></div>
</section>
<?php page_end(); ?>
