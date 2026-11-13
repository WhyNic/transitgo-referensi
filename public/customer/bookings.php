<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/bookings.php';

$user = require_user('customer');
$bookings = query(BOOKING_SELECT . ' WHERE b.customer_id = ? ORDER BY b.created_at DESC, b.id DESC', [$user['id']])->fetchAll();

page_start('Pesanan saya');
?>
<section class="container page-section">
    <div class="page-heading"><h1>Pesanan saya</h1></div>
    <div class="panel table-panel"><?php booking_table($bookings, 'customer'); ?></div>
</section>
<?php page_end(); ?>
