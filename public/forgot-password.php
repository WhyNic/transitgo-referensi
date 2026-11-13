<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/layout.php';

guest_only();
$email = field($_POST, 'email');
$error = '';
if (is_post()) {
    require_post();
    try {
        limit_auth_attempt('forgot', 900, 15);
        $email = email_value($email);
        $id = query('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
        if ($id !== false) {
            query(
                'INSERT INTO password_resets (user_id, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                 ON DUPLICATE KEY UPDATE
                    token_hash = IF(expires_at <= NOW(), NULL, token_hash),
                    expires_at = IF(expires_at <= NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), expires_at)',
                [$id]
            );
        }
        flash('Jika akun terdaftar, permintaan telah dicatat. Hubungi operator demo untuk mendapatkan kode.');
        redirect('/reset-password.php');
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    }
}

page_start('Lupa kata sandi');
?>
<section class="container page-section auth-width">
    <div class="panel">
        <h1>Lupa kata sandi</h1>
        <p class="muted">Masukkan email akun. Operator demo memberikan kode pemulihan melalui konsol lokal.</p>
        <?php form_error($error); ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>Email <input type="email" name="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required></label>
            <button class="button">Minta kode</button>
        </form>
        <p class="form-footer"><a href="/reset-password.php">Sudah punya kode pemulihan?</a></p>
    </div>
</section>
<?php page_end(); ?>
