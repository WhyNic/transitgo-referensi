<?php

function guest_only(): void
{
    $user = current_user();
    if ($user) {
        redirect(role_home($user['role']));
    }
}

function email_value(string $value): string
{
    $email = strtolower(trim($value));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationError('Masukkan alamat email yang valid.');
    }
    return $email;
}

function new_password(string $password, string $confirmation): string
{
    // PHP's default bcrypt implementation accepts up to 72 bytes.
    if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password) < 12
        || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new ValidationError('Kata sandi harus minimal 12 karakter dan maksimal 72 byte.');
    }
    if ($password !== $confirmation) {
        throw new ValidationError('Konfirmasi kata sandi belum cocok.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function limit_auth_attempt(string $action, int $seconds, int $limit): void
{
    // The socket address is server-provided. Forwarded headers are deliberately ignored.
    $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local', true);
    $window = intdiv(time(), $seconds) * $seconds;
    query(
        'INSERT INTO auth_limits (action, client_key, window_start, attempts) VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE attempts = attempts + 1',
        [$action, $key, $window]
    );
    $attempts = query(
        'SELECT attempts FROM auth_limits WHERE action = ? AND client_key = ? AND window_start = ?',
        [$action, $key, $window]
    )->fetchColumn();
    if (random_int(1, 100) === 1) {
        query('DELETE FROM auth_limits WHERE window_start < ?', [time() - 3600]);
    }
    if ($attempts > $limit) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $window + $seconds - time()));
        throw new ValidationError('Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
    }
}
