<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/verification.php';
require APP_ROOT . '/includes/verification-layout.php';

$user = require_user('provider');
$provider = verification_provider((int) $user['id']);
$vehicles = verification_vehicles((int) $user['id']);
$status = $provider['verification_status'];
$canSubmit = in_array($status, ['incomplete', 'rejected'], true) && verification_complete($provider, $vehicles);
$stats = [
    ['Rute', '/provider/manage.php?type=routes', 'SELECT COUNT(*) FROM routes WHERE provider_id = ?'],
    ['Jadwal', '/provider/manage.php?type=schedules', 'SELECT COUNT(*) FROM schedules WHERE provider_id = ?'],
    ['Kendaraan', '/provider/manage.php?type=vehicles', 'SELECT COUNT(*) FROM vehicles WHERE provider_id = ?'],
    ['Pesanan', '/provider/bookings.php', 'SELECT COUNT(*) FROM bookings b JOIN schedules s ON s.id = b.schedule_id WHERE s.provider_id = ?'],
];
foreach ($stats as &$stat) {
    $stat[2] = (int) query($stat[2], [$user['id']])->fetchColumn();
}
unset($stat);

page_start('Dasbor penyedia');
?>
<section class="container page-section">
    <div class="page-heading"><h1>Dasbor penyedia</h1><p class="muted"><?= e($user['full_name']) ?></p></div>
    <div class="panel section-gap">
        <div class="verification-row">
            <h2>Status verifikasi penyedia</h2>
            <span class="badge <?= e(match ($status) { 'pending' => 'status-pending', 'rejected' => 'status-expired', default => '' }) ?>"><?= e(verification_label($status)) ?></span>
        </div>
        <?php if ($status === 'rejected'): ?>
            <p class="alert danger">Alasan penolakan · <?= e($provider['verification_note']) ?></p>
        <?php elseif ($status === 'pending'): ?>
            <p class="muted">Admin sedang meninjau berkas perusahaan dan kendaraan Anda.</p>
        <?php elseif ($status === 'approved'): ?>
            <p class="muted">Jadwal dengan berkas kendaraan lengkap dapat diterbitkan.</p>
        <?php else: ?>
            <p class="muted">Lengkapi berkas perusahaan dan semua berkas untuk minimal satu kendaraan.</p>
        <?php endif; ?>
        <?php verification_files_panel($provider, false); ?>
        <p><a href="/provider/verification.php">Kelola berkas perusahaan</a></p>
        <div class="verification-file">
            <div class="verification-row"><strong>Kendaraan</strong><span class="badge"><?= $vehicles ? 'Tersedia' : 'Belum tersedia' ?></span></div>
            <?php if (!$vehicles): ?><p><a href="/provider/manage.php?type=vehicles">Tambah kendaraan</a></p><?php endif; ?>
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="verification-file">
                    <div class="verification-row"><strong><?= e($vehicle['vehicle_name']) ?></strong>
                        <a href="<?= e('/provider/verification.php?id=' . $vehicle['id']) ?>">Kelola berkas kendaraan</a></div>
                    <ul class="verification-checklist">
                        <?php foreach (['vehicle_photo', 'stnk', 'kir'] as $kind): ?>
                            <?php $item = VERIFICATION_FILES[$kind]; ?>
                            <li><?= e($item['label']) ?> · <?= verification_file_exists($kind, $vehicle[$item['column']]) ? 'Terunggah' : 'Belum diunggah' ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($canSubmit): ?>
            <form method="post" action="/provider/verification.php" class="form-actions">
                <?= csrf_field() ?>
                <button class="button" name="action" value="submit">Ajukan verifikasi</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="stats-grid">
        <?php foreach ($stats as [$label, $url, $total]): ?>
            <a class="panel stat-card" href="<?= e($url) ?>"><span><?= e($label) ?></span><strong><?= e($total) ?></strong></a>
        <?php endforeach; ?>
    </div>
</section>
<?php page_end(); ?>
