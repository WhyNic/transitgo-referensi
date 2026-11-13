<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

class ValidationError extends RuntimeException
{
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $local = APP_ROOT . '/config/local.php';
    $config = is_file($local) ? require $local : require APP_ROOT . '/config/local.example.php';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}

function query(string $sql, array $values = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($values);
    return $statement;
}

function transaction(callable $operation): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $operation();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mysql_error(PDOException $error, int $code): bool
{
    return (int) ($error->errorInfo[1] ?? 0) === $code;
}

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(int $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

function tanggal(string $date): string
{
    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $time = new DateTimeImmutable($date);
    return $time->format('j') . ' ' . $months[(int) $time->format('n')] . ' ' . $time->format('Y');
}

function waktu(string $date): string
{
    return (new DateTimeImmutable($date))->format('H.i');
}

function field(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? $value : '';
}

function clean_text(string $value, string $label, int $max): string
{
    $value = trim($value);
    if ($value === '' || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max
        || preg_match('/\p{Cc}/u', $value)) {
        throw new ValidationError("$label wajib diisi dan maksimal $max karakter.");
    }
    return $value;
}

function int_field(mixed $value, string $label, int $min, int $max): int
{
    if (!is_string($value) && !is_int($value)) {
        throw new ValidationError("$label tidak valid.");
    }
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($number === false) {
        throw new ValidationError("$label harus antara $min dan $max.");
    }
    return $number;
}

function record_id(mixed $value): int
{
    return int_field($value, 'ID', 1, 4294967295);
}

function strict_date(string $value, string $format, string $label): DateTimeImmutable
{
    $date = str_contains($value, "\0") ? false : DateTimeImmutable::createFromFormat('!' . $format, $value);
    if ($date === false || $date->format($format) !== $value || (int) $date->format('Y') > 9999) {
        throw new ValidationError("$label tidak valid.");
    }
    return $date;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [$message, $type];
}

function fail_page(int $code, string $message): never
{
    http_response_code($code);
    require_once APP_ROOT . '/includes/layout.php';
    // Avoid querying the database a second time while rendering an error.
    page_start('TransitGo', 'error');
    ?>
    <section class="container page-section narrow">
        <div class="panel empty">
            <h1><?= e($code) ?></h1>
            <p><?= e($message) ?></p>
            <a class="button" href="/">Kembali ke beranda</a>
        </div>
    </section>
    <?php
    page_end();
    exit;
}

function url_id(): int
{
    try {
        return record_id($_GET['id'] ?? null);
    } catch (ValidationError) {
        fail_page(404, 'Data tidak ditemukan.');
    }
}

function csrf_field(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        fail_page(405, 'Metode permintaan tidak didukung.');
    }
    $token = $_SESSION['csrf'] ?? '';
    if ($token === '' || !hash_equals($token, field($_POST, 'csrf'))) {
        fail_page(403, 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function current_user(): ?array
{
    static $resolved = false;
    static $user = null;
    if ($resolved) {
        return $user;
    }
    $resolved = true;
    if (!isset($_SESSION['user_id'], $_SESSION['auth_version'])) {
        return null;
    }
    $row = query(
        'SELECT id, role, full_name, email, phone, photo, auth_version FROM users WHERE id = ?',
        [$_SESSION['user_id']]
    )->fetch();
    if (!$row || (int) $row['auth_version'] !== $_SESSION['auth_version']) {
        unset($_SESSION['user_id'], $_SESSION['auth_version']);
        return null;
    }
    return $user = $row;
}

function require_user(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        flash('Silakan masuk untuk melanjutkan.', 'notice');
        redirect('/login.php');
    }
    if ($role !== null && $user['role'] !== $role) {
        fail_page(403, 'Akun Anda tidak memiliki akses ke halaman ini.');
    }
    return $user;
}

function role_home(string $role): string
{
    return match ($role) {
        'provider' => '/provider/',
        'admin' => '/admin/',
        default => '/',
    };
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $error): never {
        error_log('TransitGo request failed: ' . get_class($error) . ' (' . $error->getCode() . ')');
        fail_page(500, 'Terjadi gangguan. Silakan coba lagi nanti.');
    });

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('TransitGoSession');
    session_set_cookie_params([
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
}
