<?php

function upload_path(string $directory, ?string $filename): ?string
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|pdf)\z/', $filename)) {
        return null;
    }
    return APP_ROOT . '/storage/' . $directory . '/' . $filename;
}

function photo_path(?string $filename): ?string
{
    if ($filename === null || !str_ends_with($filename, '.jpg')) {
        return null;
    }
    return upload_path('profiles', $filename);
}

function remove_upload(string $directory, ?string $filename): void
{
    $path = upload_path($directory, $filename);
    if ($path !== null && is_file($path) && !@unlink($path)) {
        error_log('TransitGo could not remove a stored upload.');
    }
}

function remove_photo(?string $filename): void
{
    remove_upload('profiles', $filename);
}

function uploaded_file(array $file, int $maxMb, string $label): array
{
    $sizeError = "Ukuran $label maksimal $maxMb MB.";
    if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new ValidationError($sizeError);
    }
    $temp = $file['tmp_name'] ?? '';
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($temp) || !is_uploaded_file($temp)) {
        throw new ValidationError("Pilih $label yang dapat diunggah.");
    }
    $size = filesize($temp);
    if ($size === false || $size < 1 || $size > $maxMb * 1024 * 1024) {
        throw new ValidationError($sizeError);
    }
    return [$temp, (new finfo(FILEINFO_MIME_TYPE))->file($temp)];
}

function store_upload(string $directory, string $extension, callable $write): string
{
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $path = upload_path($directory, $filename);
    $folder = dirname($path);
    if (!is_dir($folder) && !@mkdir($folder, 0700, true) && !is_dir($folder)) {
        throw new RuntimeException('Upload storage unavailable');
    }
    $output = @fopen($path, 'xb');
    if (!$output) {
        throw new RuntimeException('Upload could not be created');
    }
    try {
        try {
            if (!$write($output) || !fflush($output) || !ftell($output)) {
                throw new RuntimeException('Upload could not be written');
            }
        } finally {
            fclose($output);
        }
    } catch (Throwable $error) {
        remove_upload($directory, $filename);
        throw $error;
    }
    return $filename;
}

function store_uploaded_image(string $temp, string|false $mime, string $directory, int $maxEdge): string
{
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        throw new ValidationError('Foto harus berupa gambar JPEG atau PNG yang valid.');
    }
    $dimensions = @getimagesize($temp);
    if (!$dimensions || $dimensions['mime'] !== $mime) {
        throw new ValidationError('Foto harus berupa gambar JPEG atau PNG yang valid.');
    }
    [$width, $height] = $dimensions;
    if ($width < 1 || $height < 1 || $width > 3000 || $height > 3000 || $width * $height > 6000000) {
        throw new ValidationError('Resolusi foto terlalu besar. Pilih foto yang lebih kecil.');
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('GD unavailable');
    }
    $source = $mime === 'image/png' ? @imagecreatefrompng($temp) : @imagecreatefromjpeg($temp);
    if (!$source) {
        throw new ValidationError('File gambar tidak dapat dibaca.');
    }
    $scale = min(1, $maxEdge / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    // Encode pixels into a fresh JPEG to discard metadata and appended data.
    $image = imagecreatetruecolor($targetWidth, $targetHeight);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    return store_upload($directory, 'jpg', static fn ($output): bool => imagejpeg($image, $output, 88));
}

function save_uploaded_photo(array $file): string
{
    [$temp, $mime] = uploaded_file($file, 2, 'foto');
    return store_uploaded_image($temp, $mime, 'profiles', 512);
}
