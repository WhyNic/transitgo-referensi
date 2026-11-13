<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/verification.php';
require APP_ROOT . '/includes/layout.php';

$user = require_user();
$isProvider = $user['role'] === 'provider';
$nameLabel = $isProvider ? 'Nama perusahaan' : 'Nama lengkap';
$name = $user['full_name'];
$phone = $user['phone'];
$profileError = $photoError = '';
if (is_post()) {
    require_post();
    $action = field($_POST, 'action');
    if ($action === 'profile') {
        try {
            $name = field($_POST, 'full_name');
            $phone = trim(field($_POST, 'phone'));
            $name = clean_text($name, $nameLabel, 100);
            if ($phone !== '' && (!preg_match('/\A\+?[0-9][0-9 ()-]{5,22}\z/', $phone)
                || !preg_match('/\A[0-9]{7,16}\z/', preg_replace('/[^0-9]/', '', $phone)))) {
                throw new ValidationError('Masukkan nomor telepon yang valid.');
            }
            if ($isProvider) {
                transaction(static function () use ($user, $name, $phone): void {
                    $provider = verification_provider((int) $user['id'], true);
                    if ($name !== $provider['full_name']) {
                        require_verification_editable($provider);
                        invalidate_verification($provider);
                    }
                    query('UPDATE users SET full_name = ?, phone = ? WHERE id = ?', [$name, $phone, $user['id']]);
                });
            } else {
                query('UPDATE users SET full_name = ?, phone = ? WHERE id = ?', [$name, $phone, $user['id']]);
            }
            flash('Profil berhasil disimpan.');
            redirect('/profile.php');
        } catch (ValidationError $failure) {
            $profileError = $failure->getMessage();
        }
    } elseif ($action === 'photo') {
        try {
            $filename = save_uploaded_photo($_FILES['photo'] ?? []);
            try {
                $old = transaction(static function () use ($user, $filename): ?string {
                    // Concurrent uploads for the same user replace the latest committed image.
                    $old = query('SELECT photo FROM users WHERE id = ? FOR UPDATE', [$user['id']])->fetchColumn();
                    query('UPDATE users SET photo = ? WHERE id = ?', [$filename, $user['id']]);
                    return $old;
                });
            } catch (Throwable $failure) {
                remove_photo($filename);
                throw $failure;
            }
            remove_photo($old);
            flash('Foto profil berhasil diganti.');
            redirect('/profile.php');
        } catch (ValidationError $failure) {
            $photoError = $failure->getMessage();
        }
    } else {
        fail_page(400, 'Tindakan tidak valid.');
    }
}

page_start('Profil');
?>
<section class="container page-section">
    <div class="page-heading"><h1>Profil saya</h1><p class="muted">Kelola informasi dan foto akun Anda.</p></div>
    <div class="two-column">
        <div class="panel">
            <h2>Informasi akun</h2>
            <?php form_error($profileError); ?>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <label><?= e($nameLabel) ?> <input name="full_name" value="<?= e($name) ?>" maxlength="100" autocomplete="<?= $isProvider ? 'organization' : 'name' ?>" required>
                    <?php if ($isProvider): ?><small>Perubahan nama perusahaan memerlukan verifikasi ulang.</small><?php endif; ?>
                </label>
                <label>Email <input type="email" value="<?= e($user['email']) ?>" readonly></label>
                <label>Nomor telepon <input type="tel" name="phone" value="<?= e($phone) ?>" maxlength="24" autocomplete="tel"></label>
                <button class="button">Simpan profil</button>
            </form>
        </div>
        <div class="panel">
            <h2>Foto profil</h2>
            <?php if ($user['photo'] !== null): ?>
                <img class="avatar" src="/profile-photo.php" alt="Foto profil saat ini" width="96" height="96">
            <?php else: ?>
                <div class="avatar avatar-placeholder" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
            <?php endif; ?>
            <?php form_error($photoError); ?>
            <form method="post" enctype="multipart/form-data" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo">
                <label>Pilih foto <input type="file" name="photo" accept="image/jpeg,image/png" required><small>JPEG atau PNG, maksimal 2 MB.</small></label>
                <button class="button button-outline">Ganti foto</button>
            </form>
        </div>
    </div>
</section>
<?php page_end(); ?>
