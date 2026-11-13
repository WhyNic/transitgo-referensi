<?php

require_once __DIR__ . '/photos.php';

const VERIFICATION_FILES = [
    'logo' => ['column' => 'company_logo', 'label' => 'Logo perusahaan', 'vehicle' => false, 'private' => false],
    'legal' => ['column' => 'legal_document', 'label' => 'Dokumen Legalitas / Izin Usaha', 'vehicle' => false, 'private' => true],
    'vehicle_photo' => ['column' => 'vehicle_photo', 'label' => 'Foto kendaraan', 'vehicle' => true, 'private' => false],
    'stnk' => ['column' => 'stnk_document', 'label' => 'STNK', 'vehicle' => true, 'private' => true],
    'kir' => ['column' => 'kir_document', 'label' => 'KIR', 'vehicle' => true, 'private' => true],
];

function verification_directory(array $spec): string
{
    return $spec['private'] ? 'provider-documents' : 'provider-images';
}

function verification_file_exists(string $kind, ?string $filename): bool
{
    $path = upload_path(verification_directory(VERIFICATION_FILES[$kind]), $filename);
    return $path !== null && is_file($path);
}

function save_verification_upload(string $kind, array $file): string
{
    $spec = VERIFICATION_FILES[$kind];
    $directory = verification_directory($spec);
    [$temp, $mime] = uploaded_file($file, $spec['private'] ? 5 : 2, 'file');
    // The client filename is used only to reject executable-looking names.
    $original = field($file, 'name');
    if (preg_match('/\.(?:php[0-9]*|phtml|phar|exe|sh|html?|svg|js)(?:[.\s]|\z)/i', $original)) {
        throw new ValidationError('Nama file mengandung ekstensi yang tidak diizinkan.');
    }
    if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return store_uploaded_image($temp, $mime, $directory, $spec['private'] ? 2500 : 1200);
    }
    if (!$spec['private'] || $mime !== 'application/pdf') {
        throw new ValidationError($spec['private'] ? 'Pilih file PDF, JPEG, atau PNG yang valid.' : 'Pilih file JPEG atau PNG yang valid.');
    }
    // Check the header and end marker within the bounded upload. PDFs are served as attachments.
    $bytes = file_get_contents($temp);
    if ($bytes === false || !preg_match('/\A%PDF-[12]\.[0-9][\r\n]/', $bytes)
        || !preg_match('/startxref[ \t\r\n]+([0-9]{1,10})[ \t\r\n]+%%EOF[ \t\r\n\f]*\z/', substr($bytes, -1024), $trailer)) {
        throw new ValidationError('File PDF tidak valid.');
    }
    $offset = (int) $trailer[1];
    if ($offset < 9 || $offset >= strlen($bytes)
        || !preg_match('/\A(?:xref\b|[0-9]+[ \t]+[0-9]+[ \t]+obj\b)/', substr($bytes, $offset, 80))) {
        throw new ValidationError('File PDF tidak valid.');
    }
    return store_upload($directory, 'pdf', static fn ($output): bool => fwrite($output, $bytes) === strlen($bytes));
}

function verification_provider(int $id, bool $lock = false): ?array
{
    return query(
        "SELECT id, full_name, email, phone, company_logo, legal_document, verification_status,
                verification_note, verification_round FROM users WHERE id = ? AND role = 'provider'"
        . ($lock ? ' FOR UPDATE' : ''),
        [$id]
    )->fetch() ?: null;
}

function verification_vehicles(int $owner): array
{
    return query('SELECT * FROM vehicles WHERE provider_id = ? ORDER BY id', [$owner])->fetchAll();
}

function company_files_complete(array $provider): bool
{
    return verification_file_exists('logo', $provider['company_logo'])
        && verification_file_exists('legal', $provider['legal_document']);
}

function vehicle_files_complete(array $vehicle): bool
{
    return verification_file_exists('vehicle_photo', $vehicle['vehicle_photo'])
        && verification_file_exists('stnk', $vehicle['stnk_document'])
        && verification_file_exists('kir', $vehicle['kir_document']);
}

function verification_complete(array $provider, array $vehicles): bool
{
    if (!company_files_complete($provider)) {
        return false;
    }
    foreach ($vehicles as $vehicle) {
        if (vehicle_files_complete($vehicle)) {
            return true;
        }
    }
    return false;
}

function require_verification_editable(array $provider): void
{
    if ($provider['verification_status'] === 'pending') {
        throw new ValidationError('Data verifikasi sedang ditinjau. Tunggu keputusan admin sebelum mengubahnya.');
    }
}

function invalidate_verification(array $provider): void
{
    if ($provider['verification_status'] === 'approved') {
        query("UPDATE users SET verification_status = 'incomplete', verification_note = NULL WHERE id = ?", [$provider['id']]);
    }
}

function change_verification_file(int $owner, ?int $vehicleId, string $kind, ?string $new): void
{
    $spec = VERIFICATION_FILES[$kind];
    $column = $spec['column'];
    $directory = verification_directory($spec);
    try {
        $old = transaction(static function () use ($owner, $vehicleId, $spec, $column, $new): ?string {
            $provider = verification_provider($owner, true);
            if (!$provider) {
                throw new ValidationError('Penyedia tidak ditemukan.');
            }
            require_verification_editable($provider);
            if ($spec['vehicle']) {
                $vehicle = query('SELECT * FROM vehicles WHERE id = ? AND provider_id = ? FOR UPDATE', [$vehicleId, $owner])->fetch();
                if (!$vehicle) {
                    throw new ValidationError('Kendaraan tidak ditemukan.');
                }
                $old = $vehicle[$column];
                query("UPDATE vehicles SET $column = ? WHERE id = ? AND provider_id = ?", [$new, $vehicleId, $owner]);
            } else {
                $old = $provider[$column];
                query("UPDATE users SET $column = ? WHERE id = ? AND role = 'provider'", [$new, $owner]);
            }
            if ($old !== $new) {
                invalidate_verification($provider);
            }
            return $old;
        });
    } catch (Throwable $error) {
        remove_upload($directory, $new);
        throw $error;
    }
    remove_upload($directory, $old);
}

function submit_verification(int $owner): void
{
    transaction(static function () use ($owner): void {
        $provider = verification_provider($owner, true);
        if (!$provider || !in_array($provider['verification_status'], ['incomplete', 'rejected'], true)) {
            throw new ValidationError('Status saat ini tidak dapat diajukan.');
        }
        if (!verification_complete($provider, verification_vehicles($owner))) {
            throw new ValidationError('Lengkapi berkas perusahaan dan semua berkas untuk minimal satu kendaraan.');
        }
        query(
            "UPDATE users SET verification_status = 'pending', verification_note = NULL,
                    verification_round = verification_round + 1 WHERE id = ?",
            [$owner]
        );
    });
}

function review_verification(int $id, int $round, string $decision, ?string $note): void
{
    if (!in_array($decision, ['approved', 'rejected'], true) || ($decision === 'rejected' && $note === null)) {
        throw new ValidationError('Keputusan verifikasi tidak valid.');
    }
    transaction(static function () use ($id, $round, $decision, $note): void {
        $provider = verification_provider($id, true);
        // Compare the review round so an older open tab cannot decide a later resubmission.
        if (!$provider || $provider['verification_status'] !== 'pending' || (int) $provider['verification_round'] !== $round) {
            throw new ValidationError('Pengajuan telah berubah. Buka kembali pengajuan terbaru.');
        }
        if ($decision === 'approved' && !verification_complete($provider, verification_vehicles($id))) {
            throw new ValidationError('Berkas pengajuan belum lengkap.');
        }
        query('UPDATE users SET verification_status = ?, verification_note = ? WHERE id = ?', [$decision, $note, $id]);
    });
}

function verification_label(string $status): string
{
    return match ($status) {
        'incomplete' => 'Belum lengkap',
        'pending' => 'Menunggu verifikasi',
        'approved' => 'Terverifikasi',
        'rejected' => 'Ditolak',
    };
}

function verification_file_url(string $kind, int $id): string
{
    return '/provider-file.php?type=' . $kind . '&id=' . $id;
}
