<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/verification.php';
require APP_ROOT . '/includes/verification-layout.php';

require_user('admin');
$provider = null;
$vehicles = [];
$error = '';
if (isset($_GET['id'])) {
    $id = url_id();
    $provider = verification_provider($id);
    if (!$provider || $provider['verification_status'] !== 'pending') {
        fail_page(404, 'Pengajuan verifikasi tidak ditemukan.');
    }
    $vehicles = verification_vehicles($id);
}
if (is_post()) {
    require_post();
    if ($provider === null) {
        fail_page(400, 'Pilih pengajuan yang akan ditinjau.');
    }
    try {
        $round = int_field($_POST['round'] ?? null, 'Pengajuan', 1, 4294967295);
        $decision = field($_POST, 'decision');
        $note = $decision === 'rejected' ? clean_text(field($_POST, 'note'), 'Alasan penolakan', 250) : null;
        review_verification($id, $round, $decision, $note);
        flash($decision === 'approved' ? 'Penyedia berhasil diverifikasi.' : 'Pengajuan ditolak.');
        redirect('/admin/verification.php');
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
        // Keep the round from the failed form so a stale review cannot be retried on new evidence.
        $provider['verification_round'] = field($_POST, 'round');
    }
}
$pending = $provider === null
    ? query("SELECT id, full_name, email FROM users WHERE role = 'provider' AND verification_status = 'pending' ORDER BY id")->fetchAll()
    : [];

page_start('Verifikasi penyedia');
?>
<section class="container page-section">
    <div class="page-heading">
        <a class="back-link" href="<?= $provider ? '/admin/verification.php' : '/admin/' ?>"><?= $provider ? '← Antrean verifikasi' : '← Dasbor admin' ?></a>
        <h1>Verifikasi penyedia</h1>
    </div>
    <?php if (!$provider): ?>
        <div class="panel table-panel">
            <h2>Menunggu verifikasi</h2>
            <div class="table-scroll"><table>
                <thead><tr><th scope="col">Penyedia</th><th scope="col">Email</th><th scope="col">Tindakan</th></tr></thead>
                <tbody>
                    <?php if (!$pending) table_empty(3, 'Tidak ada pengajuan yang menunggu.'); ?>
                    <?php foreach ($pending as $entry): ?>
                        <tr><td><?= e($entry['full_name']) ?></td><td><?= e($entry['email']) ?></td>
                            <td><a href="<?= e('/admin/verification.php?id=' . $entry['id']) ?>">Tinjau pengajuan</a></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    <?php else: ?>
        <div class="two-column section-gap">
            <div class="panel">
                <h2><?= e($provider['full_name']) ?></h2>
                <p class="muted"><?= e($provider['email']) ?><?= $provider['phone'] !== '' ? ' · ' . e($provider['phone']) : '' ?></p>
                <?php verification_files_panel($provider, false); ?>
            </div>
            <div class="panel">
                <h2>Keputusan verifikasi</h2>
                <?php form_error($error); ?>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="round" value="<?= e($provider['verification_round']) ?>">
                    <label>Alasan penolakan <input name="note" maxlength="250" value="<?= e(field($_POST, 'note')) ?>" placeholder="Wajib diisi jika menolak"></label>
                    <div class="form-actions">
                        <button class="button" name="decision" value="approved">Setujui</button>
                        <button class="button button-outline" name="decision" value="rejected">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="two-column">
            <?php foreach ($vehicles as $vehicle): ?>
                <div class="panel">
                    <h2><?= e($vehicle['vehicle_name']) ?></h2>
                    <p class="muted"><?= e($vehicle['vehicle_type'] . ' · ' . $vehicle['plate_number'] . ' · ' . $vehicle['seat_capacity'] . ' kursi') ?></p>
                    <?php verification_files_panel($vehicle, true); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_end(); ?>
