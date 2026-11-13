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
        limit_auth_attempt('login', 300, 30);
        $email = email_value($email);
        $password = field($_POST, 'password');
        if ($password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new ValidationError('Email atau kata sandi tidak cocok.');
        }

        $user = query('SELECT id, role, password_hash, auth_version FROM users WHERE email = ?', [$email])->fetch();
        // Run password verification even when there is no matching email.
        $dummy = '$2y$12$v/AAQFc4bYCSmgf4IU.Fw.RDi4i41zoA.otni4lUBJTB57MoeYVKm';
        if (!password_verify($password, $user['password_hash'] ?? $dummy) || !$user) {
            throw new ValidationError('Email atau kata sandi tidak cocok.');
        }
        session_regenerate_id(true);
        $_SESSION = ['user_id' => (int) $user['id'], 'auth_version' => (int) $user['auth_version']];
        if ($user['role'] === 'customer' && isset($_GET['trip'])) {
            try {
                $tripId = record_id($_GET['trip']);
                $passengers = int_field($_GET['passengers'] ?? '1', 'Jumlah penumpang', 1, 6);
                redirect('/trip.php?id=' . $tripId . '&passengers=' . $passengers);
            } catch (ValidationError) {
                // An invalid optional return target falls back to the customer's home.
            }
        }
        redirect(role_home($user['role']));
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    }
}

page_start('Masuk');
?>
<section class="container page-section auth-width">
    <div class="panel">
        <h1>Masuk</h1>
        <p class="muted">Masuk untuk memesan tiket atau membuka dasbor Anda.</p>
        <?php form_error($error); ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>Email <input type="email" name="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required></label>
            <label>Kata sandi <input type="password" name="password" maxlength="72" autocomplete="current-password" required></label>
            <div class="form-actions"><button class="button">Masuk</button><a href="/forgot-password.php">Lupa kata sandi?</a></div>
        </form>
        <p class="form-footer">Belum punya akun? <a href="/register.php">Daftar sebagai pelanggan</a></p>
    </div>
</section>
<?php page_end(); ?>
