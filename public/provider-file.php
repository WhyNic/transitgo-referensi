<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/verification.php';

$kind = field($_GET, 'type');
$spec = VERIFICATION_FILES[$kind] ?? null;
if ($spec === null) {
    fail_page(404, 'Berkas tidak ditemukan.');
}
$user = $spec['private'] ? require_user() : current_user();
if ($spec['private'] && !in_array($user['role'], ['provider', 'admin'], true)) {
    fail_page(403, 'Akun Anda tidak memiliki akses ke berkas ini.');
}
$id = url_id();
$column = $spec['column'];
$sql = $spec['vehicle']
    ? "SELECT v.$column AS filename FROM vehicles v JOIN users u ON u.id = v.provider_id WHERE v.id = ?"
    : "SELECT u.$column AS filename FROM users u WHERE u.id = ?";
$sql .= " AND u.role = 'provider'";
$values = [$id];
if (($user['role'] ?? null) !== 'admin') {
    if ($spec['private']) {
        $sql .= ' AND u.id = ?';
        $values[] = $user['id'];
    } else {
        $sql .= " AND (u.id = ? OR u.verification_status = 'approved')";
        $values[] = $user['id'] ?? 0;
    }
}
$filename = query($sql, $values)->fetchColumn();
$path = is_string($filename) && ($spec['private'] || str_ends_with($filename, '.jpg'))
    ? upload_path(verification_directory($spec), $filename)
    : null;
$stream = $path === null ? false : @fopen($path, 'rb');
if ($stream === false) {
    fail_page(404, 'Berkas tidak ditemukan.');
}
$isPdf = str_ends_with($filename, '.pdf');
header('Content-Type: ' . ($isPdf ? 'application/pdf' : 'image/jpeg'));
header('Content-Disposition: ' . ($spec['private'] ? 'attachment' : 'inline')
    . '; filename="' . $kind . ($isPdf ? '.pdf' : '.jpg') . '"');
if ($spec['private']) {
    header("Content-Security-Policy: sandbox; default-src 'none'; frame-ancestors 'none'");
}
session_write_close();
fpassthru($stream);
fclose($stream);
