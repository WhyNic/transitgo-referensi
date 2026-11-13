<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/provider.php';
require APP_ROOT . '/includes/layout.php';

$user = require_user('provider');
$owner = (int) $user['id'];
$type = field($_GET, 'type');
$spec = provider_spec($type);
$listUrl = '/provider/manage.php?type=' . $type;
$editId = null;
$form = [];
$error = '';
$provider = verification_provider($owner);
$publicationReady = $type === 'schedules' && $provider['verification_status'] === 'approved' && company_files_complete($provider);

if (is_post()) {
    require_post();
    $action = field($_POST, 'action');
    $publicationAction = $type === 'schedules' && in_array($action, ['publish', 'unpublish'], true);
    if (!$publicationAction && !in_array($action, ['create', 'update', 'delete'], true)) {
        fail_page(400, 'Tindakan tidak valid.');
    }
    $form = $_POST;
    try {
        if ($action !== 'create') {
            $editId = record_id($_POST['id'] ?? null);
            if (!owned_record($type, $editId, $owner)) {
                fail_page(404, 'Data tidak ditemukan.');
            }
        }
        if ($publicationAction) {
            $saved = provider_set_publication($owner, $editId, $action === 'publish');
        } else {
            $values = $action === 'delete' ? [] : provider_values($type, $form);
            $saved = provider_save($type, $owner, $editId, $action, $values);
        }
        if (!$saved) {
            fail_page(404, 'Data tidak ditemukan.');
        }
        flash(match ($action) {
            'publish' => 'Jadwal berhasil diterbitkan.',
            'unpublish' => 'Jadwal disembunyikan dari pelanggan.',
            'delete' => 'Data berhasil dihapus.',
            default => 'Data berhasil disimpan.',
        });
        redirect($listUrl);
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    } catch (PDOException $failure) {
        if (mysql_error($failure, 1451)) {
            $error = 'Data masih dipakai oleh jadwal atau pesanan.';
        } elseif ($type === 'vehicles' && mysql_error($failure, 1062)) {
            $error = 'Nomor polisi sudah terdaftar.';
        } elseif (mysql_error($failure, 4025) || mysql_error($failure, 3819)) {
            $error = 'Nilai yang dimasukkan tidak memenuhi aturan data.';
        } else {
            throw $failure;
        }
    }
    if ($action === 'delete' || $publicationAction) {
        $editId = null;
        $form = [];
    }
} elseif (isset($_GET['edit'])) {
    try {
        $editId = record_id($_GET['edit']);
    } catch (ValidationError) {
        fail_page(404, 'Data tidak ditemukan.');
    }
    $form = owned_record($type, $editId, $owner);
    if ($form === null) {
        fail_page(404, 'Data tidak ditemukan.');
    }
    if ($type === 'schedules') {
        foreach (['departure_at', 'arrival_at'] as $key) {
            $form[$key] = (new DateTimeImmutable($form[$key]))->format('Y-m-d\TH:i');
        }
    }
}

$rows = provider_rows($type, $owner);
$routes = $type === 'schedules' ? provider_rows('routes', $owner) : [];
$vehicles = $type === 'schedules' ? provider_rows('vehicles', $owner) : [];
$value = static fn (string $key): string => is_scalar($form[$key] ?? null) ? (string) $form[$key] : '';
page_start($spec['title']);
?>
<section class="container page-section">
    <div class="page-heading">
        <a class="back-link" href="/provider/">← Dasbor penyedia</a>
        <h1><?= e($spec['title']) ?></h1>
    </div>
    <?php if ($type === 'schedules'): ?>
        <p class="alert notice">Jadwal baru atau yang diubah disimpan sebagai draf. Penerbitan memerlukan <a href="/provider/">verifikasi penyedia</a> dan berkas kendaraan lengkap.</p>
    <?php elseif ($type === 'vehicles' && $provider['verification_status'] === 'pending'): ?>
        <p class="alert notice">Data kendaraan sedang ditinjau admin dan sementara terkunci.</p>
    <?php endif; ?>
    <div class="panel section-gap">
        <h2><?= $editId === null ? 'Tambah' : 'Ubah' ?> <?= e(strtolower($spec['title'])) ?></h2>
        <?php form_error($error); ?>
        <?php if ($type === 'vehicles' && $provider['verification_status'] === 'pending'): ?>
            <p class="muted">Perubahan dapat dilakukan setelah keputusan diberikan.</p>
        <?php elseif ($type === 'schedules' && (!$routes || !$vehicles)): ?>
            <p class="muted">Tambahkan <a href="/provider/manage.php?type=routes">rute</a> dan <a href="/provider/manage.php?type=vehicles">kendaraan</a> sebelum membuat jadwal.</p>
        <?php else: ?>
            <form method="post" action="<?= e($listUrl) ?>" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editId === null ? 'create' : 'update' ?>">
                <?php if ($editId !== null): ?><input type="hidden" name="id" value="<?= e($editId) ?>"><?php endif; ?>
                <div class="field-grid">
                    <?php if ($type === 'routes'): ?>
                        <label>Kota asal <input name="origin" maxlength="80" value="<?= e($value('origin')) ?>" required></label>
                        <label>Kota tujuan <input name="destination" maxlength="80" value="<?= e($value('destination')) ?>" required></label>
                        <label>Estimasi (menit) <input type="number" name="duration_minutes" min="1" max="2880" value="<?= e($value('duration_minutes')) ?>" required></label>
                    <?php elseif ($type === 'vehicles'): ?>
                        <label>Nama kendaraan <input name="vehicle_name" maxlength="80" value="<?= e($value('vehicle_name')) ?>" required></label>
                        <label>Jenis kendaraan <select name="vehicle_type" required>
                            <?php foreach (['Bus', 'Minibus', 'Shuttle'] as $option): ?>
                                <option <?= $value('vehicle_type') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Nomor polisi <input name="plate_number" maxlength="16" value="<?= e($value('plate_number')) ?>" placeholder="B 4101 TG" required></label>
                        <label>Kapasitas kursi <input type="number" name="seat_capacity" min="1" max="60" value="<?= e($value('seat_capacity')) ?>" required></label>
                    <?php else: ?>
                        <label>Rute <select name="route_id" required>
                            <option value="">Pilih rute</option>
                            <?php foreach ($routes as $route): ?>
                                <option value="<?= e($route['id']) ?>" <?= $value('route_id') === (string) $route['id'] ? 'selected' : '' ?>><?= e($route['origin'] . ' → ' . $route['destination']) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Kendaraan <select name="vehicle_id" required>
                            <option value="">Pilih kendaraan</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= e($vehicle['id']) ?>" <?= $value('vehicle_id') === (string) $vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['vehicle_name']) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Berangkat (WIB) <input type="datetime-local" name="departure_at" value="<?= e($value('departure_at')) ?>" required></label>
                        <label>Tiba (WIB) <input type="datetime-local" name="arrival_at" value="<?= e($value('arrival_at')) ?>" required></label>
                        <label>Harga per kursi (Rp) <input type="number" name="price" min="1000" max="10000000" value="<?= e($value('price')) ?>" required></label>
                    <?php endif; ?>
                </div>
                <div class="form-actions"><button class="button">Simpan</button><?php if ($editId !== null): ?><a href="<?= e($listUrl) ?>">Batal</a><?php endif; ?></div>
            </form>
        <?php endif; ?>
    </div>
    <div class="panel table-panel">
        <h2>Daftar <?= e(strtolower($spec['title'])) ?></h2>
        <div class="table-scroll"><table>
            <thead><tr><?php foreach ($spec['columns'] as $column): ?><th scope="col"><?= e($column) ?></th><?php endforeach; ?>
                <?php if ($type === 'schedules'): ?><th scope="col">Publikasi</th><?php endif; ?><th scope="col">Tindakan</th></tr></thead>
            <tbody>
                <?php if (!$rows) table_empty(count($spec['columns']) + ($type === 'schedules' ? 2 : 1)); ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (provider_cells($type, $row) as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?>
                        <?php if ($type === 'schedules'):
                            $canPublish = $publicationReady && vehicle_files_complete($row) && $row['departure_at'] > date('Y-m-d H:i:s');
                            $published = (bool) $row['is_published'];
                            ?>
                            <td>
                                <span class="badge"><?= $published ? ($canPublish ? 'Terbit' : 'Tersembunyi') : 'Draf' ?></span>
                                <?php if ($published || $canPublish): ?>
                                    <form method="post" action="<?= e($listUrl) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                        <button class="button button-small button-outline" name="action" value="<?= $published ? 'unpublish' : 'publish' ?>"><?= $published ? 'Sembunyikan' : 'Terbitkan' ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td><div class="row-actions">
                            <?php if ($type === 'vehicles'): ?><a href="<?= e('/provider/verification.php?id=' . $row['id']) ?>">Berkas</a><?php endif; ?>
                            <?php if ($type !== 'vehicles' || $provider['verification_status'] !== 'pending'): ?>
                            <a href="<?= e($listUrl . '&edit=' . $row['id']) ?>">Ubah</a>
                            <form method="post" action="<?= e($listUrl) ?>" data-confirm="Hapus data ini?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <button class="text-danger">Hapus</button>
                            </form>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</section>
<script src="/assets/app.js" defer></script>
<?php page_end(); ?>
