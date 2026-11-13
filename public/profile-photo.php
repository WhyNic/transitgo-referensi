<?php

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/photos.php';

$user = require_user();
$path = photo_path($user['photo']);
$image = $path === null ? false : @fopen($path, 'rb');
if ($image === false) {
    fail_page(404, 'Foto profil tidak ditemukan.');
}
header('Content-Type: image/jpeg');
header('Content-Disposition: inline; filename="profil.jpg"');
session_write_close();
fpassthru($image);
fclose($image);
