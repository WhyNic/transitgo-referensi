<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/auth.php';

if ($argc !== 2) {
    fwrite(STDERR, "Pemakaian: php scripts/reset-code.php customer@example.com\n");
    exit(1);
}

try {
    $email = email_value($argv[1]);
    $token = bin2hex(random_bytes(32));
    $count = query(
        'UPDATE password_resets SET token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         WHERE user_id = (SELECT id FROM users WHERE email = ?) AND expires_at > NOW()',
        [hash('sha256', $token, true), $email]
    )->rowCount();
    if ($count !== 1) {
        throw new ValidationError('Tidak ada permintaan aktif untuk akun tersebut.');
    }
    echo "Masukkan kode ini pada /reset-password.php. Berlaku 15 menit dan sekali pakai.\n$token\n";
} catch (ValidationError $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Gagal membuat kode. Periksa konfigurasi database.\n");
    exit(1);
}
