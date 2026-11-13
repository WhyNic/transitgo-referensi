<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/layout.php';

guest_only();
$role = ($registerProvider ?? false) ? 'provider' : 'customer';
$roleName = $role === 'provider' ? 'penyedia' : 'pelanggan';
$nameLabel = $role === 'provider' ? 'Nama perusahaan' : 'Nama lengkap';
$name = field($_POST, 'full_name');
$email = field($_POST, 'email');
$error = '';
if (is_post()) {
    require_post();
    try {
        limit_auth_attempt('register', 300, 15);
        $name = clean_text($name, $nameLabel, 100);
        $email = email_value($email);
        $hash = new_password(field($_POST, 'password'), field($_POST, 'confirmation'));
        query('INSERT INTO users (role, full_name, email, password_hash) VALUES (?, ?, ?, ?)', [$role, $name, $email, $hash]);
        flash("Akun $roleName berhasil dibuat. Silakan masuk.");
        redirect('/login.php');
    } catch (ValidationError $failure) {
        $error = $failure->getMessage();
    } catch (PDOException $failure) {
        if (!mysql_error($failure, 1062)) {
            throw $failure;
        }
        $error = 'Email tersebut sudah terdaftar.';
    }
}

page_start('Daftar');
?>
<section class="container page-section auth-width">
    <div class="panel">
        <h1>Buat akun <?= e($roleName) ?></h1>
        <?php form_error($error); ?>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label><?= e($nameLabel) ?> <input name="full_name" value="<?= e($name) ?>" maxlength="100" autocomplete="<?= $role === 'provider' ? 'organization' : 'name' ?>" required></label>
            <label>Email <input type="email" name="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required></label>
            <label>Kata sandi <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required><small>Minimal 12 karakter dan maksimal 72 byte.</small></label>
            <label>Konfirmasi kata sandi <input type="password" name="confirmation" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <button class="button">Daftar</button>
        </form>
        <p class="form-footer">Sudah punya akun? <a href="/login.php">Masuk</a></p>
        <?php if ($role === 'customer'): ?><p class="form-footer"><a href="/provider/register.php">Daftar sebagai penyedia perjalanan</a></p><?php endif; ?>
    </div>
</section>
<?php page_end(); ?>
