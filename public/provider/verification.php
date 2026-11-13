<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/provider.php';
require_once APP_ROOT . '/includes/verification.php';
require APP_ROOT . '/includes/verification-layout.php';

$user = require_user('provider');
$owner = (int) $user['id'];
$provider = verification_provider($owner);
$vehicleId = isset($_GET['id']) ? url_id() : null;
$vehicle = $vehicleId === null ? null : owned_record('vehicles', $vehicleId, $owner);
if ($vehicleId !== null && !$vehicle) {
    fail_page(404, 'Kendaraan tidak ditemukan.');
}
$url = '/provider/verification.php' . ($vehicleId === null ? '' : '?id=' . $vehicleId);
$error = '';
if (is_post()) {
    require_post();
    $action = field($_POST, 'action');
    try {
        if ($action === 'submit' && $vehicle === null) {
            submit_verification($owner);
            flash('Pengajuan telah dikirim ke admin.');
            redirect('/provider/');
        }
        $kind = field($_POST, 'kind');
        $spec = VERIFICATION_FILES[$kind] ?? null;
        if (!$spec || $spec['vehicle'] !== ($vehicle !== null) || !in_array($action, ['upload', 'remove'], true)) {
            fail_page(400, 'Tindakan tidak valid.');
        }
        require_verification_editable($provider);
        $upload = $action === 'upload' ? save_verification_upload($kind, $_FILES['upload'] ?? []) : null;
        change_verification_file($owner, $vehicleId, $kind, $upload);
        flash($action === 'upload' ? 'Berkas berhasil disimpan.' : 'Berkas berhasil dihapus.');
        redirect($url);
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    }
}

$editable = $provider['verification_status'] !== 'pending';
$title = $vehicle === null ? 'Berkas perusahaan' : 'Berkas kendaraan';
page_start($title);
?>
<section class="container page-section narrow">
    <div class="page-heading">
        <a class="back-link" href="/provider/">← Dasbor penyedia</a>
        <h1><?= e($title) ?></h1>
        <p class="muted"><?= e($vehicle['vehicle_name'] ?? $provider['full_name']) ?></p>
    </div>
    <?php form_error($error); ?>
    <div class="panel">
        <?php if (!$editable): ?>
            <p class="alert notice">Berkas sedang ditinjau admin. Anda dapat mengubahnya setelah keputusan diberikan.</p>
        <?php elseif ($provider['verification_status'] === 'approved'): ?>
            <p class="alert notice">Perubahan berkas memerlukan verifikasi ulang. Jadwal publik akan disembunyikan sementara.</p>
        <?php endif; ?>
        <?php verification_files_panel($vehicle ?? $provider, $vehicle !== null, $editable ? $url : null); ?>
    </div>
</section>
<script src="/assets/app.js" defer></script>
<?php page_end(); ?>
