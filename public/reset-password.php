<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/layout.php';

guest_only();
$error = '';
if (is_post()) {
    require_post();
    try {
        limit_auth_attempt('reset', 900, 20);
        $token = strtolower(trim(field($_POST, 'token')));
        if (!preg_match('/\A[0-9a-f]{64}\z/', $token)) {
            throw new ValidationError('Kode pemulihan tidak valid atau sudah kedaluwarsa.');
        }
        $hash = new_password(field($_POST, 'password'), field($_POST, 'confirmation'));
        transaction(static function () use ($token, $hash): void {
            $id = query(
                'SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() FOR UPDATE',
                [hash('sha256', $token, true)]
            )->fetchColumn();
            if ($id === false) {
                throw new ValidationError('Kode pemulihan tidak valid atau sudah kedaluwarsa.');
            }
            query('UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?', [$hash, $id]);
            query('DELETE FROM password_resets WHERE user_id = ?', [$id]);
        });
        flash('Kata sandi berhasil diubah. Silakan masuk kembali.');
        redirect('/login.php');
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    }
}

page_start('Atur ulang kata sandi');
?>
<section class="container page-section auth-width">
    <div class="panel">
        <h1>Atur ulang kata sandi</h1>
        <?php form_error($error); ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>Kode pemulihan <input name="token" minlength="64" maxlength="64" autocomplete="off" spellcheck="false" required></label>
            <label>Kata sandi baru <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required><small>Minimal 12 karakter dan maksimal 72 byte.</small></label>
            <label>Konfirmasi kata sandi <input type="password" name="confirmation" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <button class="button">Simpan kata sandi</button>
        </form>
        <p class="form-footer"><a href="/forgot-password.php">Minta kode pemulihan</a></p>
    </div>
</section>
<?php page_end(); ?>
