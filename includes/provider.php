<?php

require_once __DIR__ . '/verification.php';

function provider_spec(string $type): array
{
    // Only these hardcoded table names can reach the shared CRUD queries.
    return match ($type) {
        'routes' => ['title' => 'Rute', 'columns' => ['Kota asal', 'Kota tujuan', 'Estimasi']],
        'schedules' => ['title' => 'Jadwal', 'columns' => ['Rute', 'Kendaraan', 'Berangkat', 'Tiba', 'Harga']],
        'vehicles' => ['title' => 'Kendaraan', 'columns' => ['Nama kendaraan', 'Jenis', 'Nomor polisi', 'Kursi']],
        default => fail_page(404, 'Data tidak ditemukan.'),
    };
}

function provider_rows(string $type, int $owner): array
{
    provider_spec($type);
    if ($type === 'schedules') {
        return query(
            'SELECT s.*, r.origin, r.destination, v.vehicle_name, v.vehicle_photo, v.stnk_document, v.kir_document
             FROM schedules s JOIN routes r ON r.id = s.route_id JOIN vehicles v ON v.id = s.vehicle_id
             WHERE s.provider_id = ? ORDER BY s.departure_at DESC, s.id DESC',
            [$owner]
        )->fetchAll();
    }
    return query("SELECT * FROM $type WHERE provider_id = ? ORDER BY id DESC", [$owner])->fetchAll();
}

function owned_record(string $type, int $id, int $owner, bool $lock = false): ?array
{
    provider_spec($type);
    $suffix = $lock ? ' FOR UPDATE' : '';
    return query("SELECT * FROM $type WHERE id = ? AND provider_id = ?$suffix", [$id, $owner])->fetch() ?: null;
}

function provider_values(string $type, array $input): array
{
    if ($type === 'routes') {
        $origin = clean_text(field($input, 'origin'), 'Kota asal', 80);
        $destination = clean_text(field($input, 'destination'), 'Kota tujuan', 80);
        if (mb_strtolower($origin) === mb_strtolower($destination)) {
            throw new ValidationError('Kota asal dan tujuan harus berbeda.');
        }
        return [
            'origin' => $origin,
            'destination' => $destination,
            'duration_minutes' => int_field(field($input, 'duration_minutes'), 'Estimasi perjalanan', 1, 2880),
        ];
    }
    if ($type === 'vehicles') {
        $vehicleType = field($input, 'vehicle_type');
        $plate = strtoupper(trim(field($input, 'plate_number')));
        if (!in_array($vehicleType, ['Bus', 'Minibus', 'Shuttle'], true)) {
            throw new ValidationError('Pilih jenis kendaraan yang valid.');
        }
        if (!preg_match('/\A[A-Z0-9]+(?: [A-Z0-9]+)*\z/', $plate) || strlen($plate) > 16) {
            throw new ValidationError('Masukkan nomor polisi yang valid, misalnya B 4101 TG.');
        }
        return [
            'vehicle_name' => clean_text(field($input, 'vehicle_name'), 'Nama kendaraan', 80),
            'vehicle_type' => $vehicleType,
            'plate_number' => $plate,
            'seat_capacity' => int_field(field($input, 'seat_capacity'), 'Kapasitas kursi', 1, 60),
        ];
    }

    $departure = strict_date(field($input, 'departure_at'), 'Y-m-d\TH:i', 'Waktu berangkat');
    $arrival = strict_date(field($input, 'arrival_at'), 'Y-m-d\TH:i', 'Waktu tiba');
    if ($departure <= new DateTimeImmutable() || $arrival <= $departure) {
        throw new ValidationError('Jadwal harus berangkat di masa depan dan tiba setelah berangkat.');
    }
    return [
        'route_id' => record_id(field($input, 'route_id')),
        'vehicle_id' => record_id(field($input, 'vehicle_id')),
        'departure_at' => $departure->format('Y-m-d H:i:s'),
        'arrival_at' => $arrival->format('Y-m-d H:i:s'),
        'price' => int_field(field($input, 'price'), 'Harga', 1000, 10000000),
    ];
}

function provider_save(string $type, int $owner, ?int $id, string $action, array $values): bool
{
    provider_spec($type);
    $deletedVehicle = null;
    $saved = transaction(static function () use ($type, $owner, $id, $action, $values, &$deletedVehicle): bool {
        // Serialize provider changes so dependencies and vehicle overlaps are checked consistently.
        $provider = verification_provider($owner, true);
        if (!$provider) {
            return false;
        }
        if ($type === 'vehicles') {
            require_verification_editable($provider);
        }
        $record = $id === null ? null : owned_record($type, $id, $owner, true);
        if ($id !== null && $record === null) {
            return false;
        }
        if ($type === 'schedules' && $id !== null
            && query('SELECT id FROM bookings WHERE schedule_id = ? LIMIT 1 FOR UPDATE', [$id])->fetchColumn() !== false) {
            throw new ValidationError('Jadwal yang sudah memiliki riwayat pesanan tidak dapat diubah atau dihapus.');
        }
        if ($action === 'delete') {
            query("DELETE FROM $type WHERE id = ? AND provider_id = ?", [$id, $owner]);
            if ($type === 'vehicles') {
                $deletedVehicle = $record;
                if ($record['vehicle_photo'] !== null || $record['stnk_document'] !== null || $record['kir_document'] !== null) {
                    invalidate_verification($provider);
                }
            }
            return true;
        }

        if ($type === 'routes' && $record !== null
            && ($values['origin'] !== $record['origin'] || $values['destination'] !== $record['destination'])
            && query('SELECT id FROM schedules WHERE route_id = ? LIMIT 1', [$id])->fetchColumn() !== false) {
            throw new ValidationError('Kota pada rute yang sudah terjadwal tidak dapat diubah.');
        }
        if ($type === 'vehicles' && $record !== null && $values['seat_capacity'] !== (int) $record['seat_capacity']
            && query('SELECT id FROM schedules WHERE vehicle_id = ? LIMIT 1', [$id])->fetchColumn() !== false) {
            throw new ValidationError('Kapasitas kendaraan yang sudah terjadwal tidak dapat diubah.');
        }
        if ($type === 'vehicles' && $record !== null
            && ($values['plate_number'] !== $record['plate_number'] || $values['vehicle_type'] !== $record['vehicle_type']
                || $values['seat_capacity'] !== (int) $record['seat_capacity'])
            && ($record['vehicle_photo'] !== null || $record['stnk_document'] !== null || $record['kir_document'] !== null)) {
            invalidate_verification($provider);
        }
        if ($type === 'schedules') {
            if (!owned_record('routes', $values['route_id'], $owner)
                || !owned_record('vehicles', $values['vehicle_id'], $owner)) {
                throw new ValidationError('Pilih rute dan kendaraan milik akun Anda.');
            }
            $overlap = query(
                'SELECT id FROM schedules WHERE vehicle_id = ? AND id <> ? AND departure_at < ? AND arrival_at > ? LIMIT 1',
                [$values['vehicle_id'], $id ?? 0, $values['arrival_at'], $values['departure_at']]
            )->fetchColumn();
            if ($overlap !== false) {
                throw new ValidationError('Kendaraan sudah terpakai pada jam tersebut.');
            }
            // A changed timetable is saved as a draft and must be explicitly published again.
            $values['is_published'] = 0;
        }

        // Column names come from server-defined fields.
        $columns = array_keys($values);
        if ($id === null) {
            $names = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            query("INSERT INTO $type (provider_id, $names) VALUES (?, $placeholders)", [$owner, ...array_values($values)]);
        } else {
            $assignments = implode(' = ?, ', $columns) . ' = ?';
            query("UPDATE $type SET $assignments WHERE id = ? AND provider_id = ?", [...array_values($values), $id, $owner]);
        }
        return true;
    });
    if ($deletedVehicle !== null) {
        foreach (VERIFICATION_FILES as $spec) {
            if ($spec['vehicle']) {
                remove_upload(verification_directory($spec), $deletedVehicle[$spec['column']]);
            }
        }
    }
    return $saved;
}

function provider_set_publication(int $owner, int $scheduleId, bool $publish): bool
{
    return transaction(static function () use ($owner, $scheduleId, $publish): bool {
        $provider = verification_provider($owner, true);
        $schedule = owned_record('schedules', $scheduleId, $owner, true);
        if (!$provider || !$schedule) {
            return false;
        }
        if ($publish) {
            if ($provider['verification_status'] !== 'approved' || !company_files_complete($provider)) {
                throw new ValidationError('Hanya penyedia terverifikasi yang dapat menerbitkan jadwal.');
            }
            $vehicle = owned_record('vehicles', (int) $schedule['vehicle_id'], $owner);
            if (!$vehicle || !vehicle_files_complete($vehicle)) {
                throw new ValidationError('Lengkapi foto kendaraan, STNK, dan KIR sebelum menerbitkan jadwal.');
            }
            if ($schedule['departure_at'] <= date('Y-m-d H:i:s')) {
                throw new ValidationError('Jadwal yang sudah berangkat tidak dapat diterbitkan.');
            }
        }
        query('UPDATE schedules SET is_published = ? WHERE id = ? AND provider_id = ?', [(int) $publish, $scheduleId, $owner]);
        return true;
    });
}

function provider_cells(string $type, array $row): array
{
    return match ($type) {
        'routes' => [$row['origin'], $row['destination'], $row['duration_minutes'] . ' menit'],
        'vehicles' => [$row['vehicle_name'], $row['vehicle_type'], $row['plate_number'], $row['seat_capacity']],
        'schedules' => [
            $row['origin'] . ' → ' . $row['destination'],
            $row['vehicle_name'],
            tanggal($row['departure_at']) . ' · ' . waktu($row['departure_at']),
            tanggal($row['arrival_at']) . ' · ' . waktu($row['arrival_at']),
            rupiah((int) $row['price']),
        ],
    };
}
