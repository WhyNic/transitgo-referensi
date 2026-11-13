<?php

require_once __DIR__ . '/verification.php';

const PUBLISHED_TRIP_JOINS = "
    FROM schedules s
    JOIN routes r ON r.id = s.route_id AND r.provider_id = s.provider_id
    JOIN vehicles v ON v.id = s.vehicle_id AND v.provider_id = s.provider_id
    JOIN users u ON u.id = s.provider_id AND u.role = 'provider' AND u.verification_status = 'approved'
    AND u.company_logo IS NOT NULL AND u.legal_document IS NOT NULL
    AND s.is_published = 1 AND v.vehicle_photo IS NOT NULL AND v.stnk_document IS NOT NULL AND v.kir_document IS NOT NULL";

const TRIP_SELECT = "SELECT s.*, r.origin, r.destination, v.vehicle_name, v.vehicle_type,
        v.seat_capacity, u.full_name AS provider_name,
        v.seat_capacity - (
            SELECT COUNT(*) FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
            WHERE bs.schedule_id = s.id AND (b.status = 'paid' OR (b.status = 'pending' AND b.expires_at > NOW()))
        ) AS available_seats" . PUBLISHED_TRIP_JOINS;

function trip_by_id(int $id): array
{
    $trip = query(TRIP_SELECT . ' WHERE s.id = ?', [$id])->fetch();
    if (!$trip) {
        fail_page(404, 'Jadwal tidak ditemukan.');
    }
    return $trip;
}

function active_seats(int $scheduleId): array
{
    $seats = query(
        "SELECT bs.seat_number FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
         WHERE bs.schedule_id = ? AND (b.status = 'paid' OR (b.status = 'pending' AND b.expires_at > NOW()))",
        [$scheduleId]
    )->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $seats);
}

function selected_seats(mixed $raw, int $capacity): array
{
    if (!is_array($raw) || count($raw) < 1 || count($raw) > 6) {
        throw new ValidationError('Pilih 1 sampai 6 kursi.');
    }
    $seats = [];
    foreach ($raw as $value) {
        $seats[] = int_field($value, 'Nomor kursi', 1, $capacity);
    }
    if (count(array_unique($seats)) !== count($seats)) {
        throw new ValidationError('Setiap kursi hanya boleh dipilih satu kali.');
    }
    sort($seats);
    return $seats;
}

function search_form(array $values, array $cities): void
{
    ?>
    <form action="/search.php" method="get" class="search-grid">
        <label>Kota asal <input name="origin" list="cities" value="<?= e($values['origin']) ?>" maxlength="80" required></label>
        <label>Kota tujuan <input name="destination" list="cities" value="<?= e($values['destination']) ?>" maxlength="80" required></label>
        <label>Tanggal berangkat <input name="date" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($values['date']) ?>" required></label>
        <label>Jumlah penumpang <input name="passengers" type="number" min="1" max="6" value="<?= e($values['passengers']) ?>" required></label>
        <button class="button">Cari tiket</button>
        <datalist id="cities"><?php foreach ($cities as $city): ?><option value="<?= e($city) ?>"><?php endforeach; ?></datalist>
    </form>
    <?php
}

function trip_cities(): array
{
    $rows = query('SELECT DISTINCT r.origin, r.destination' . PUBLISHED_TRIP_JOINS . ' WHERE s.departure_at > NOW()')->fetchAll();
    $cities = array_unique(array_merge(array_column($rows, 'origin'), array_column($rows, 'destination')));
    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
    return $cities;
}

function trip_card(array $trip, int $passengers = 1): void
{
    $url = '/trip.php?id=' . $trip['id'] . '&passengers=' . $passengers;
    ?>
    <article class="panel trip-card">
        <div>
            <div class="trip-meta">
                <img class="trip-logo" src="<?= e(verification_file_url('logo', (int) $trip['provider_id'])) ?>" alt="" width="34" height="34">
                <span><?= e($trip['provider_name']) ?></span><span class="badge"><?= e($trip['vehicle_type']) ?></span>
            </div>
            <h3><?= e($trip['origin']) ?> <span class="route-arrow" aria-hidden="true">→</span><span class="sr-only"> ke </span> <?= e($trip['destination']) ?></h3>
            <p class="muted"><?= e($trip['vehicle_name']) ?> · <?= e(tanggal($trip['departure_at'])) ?></p>
        </div>
        <div class="trip-time">
            <div><strong><?= e(waktu($trip['departure_at'])) ?></strong><small>Berangkat</small></div>
            <span class="time-line" aria-hidden="true"></span>
            <div><strong><?= e(waktu($trip['arrival_at'])) ?></strong><small>Tiba<?= substr($trip['departure_at'], 0, 10) !== substr($trip['arrival_at'], 0, 10) ? ' ' . e(tanggal($trip['arrival_at'])) : '' ?></small></div>
        </div>
        <div class="trip-action">
            <p><strong><?= e(rupiah((int) $trip['price'])) ?></strong><small>per kursi · <?= e($trip['available_seats']) ?> tersedia</small></p>
            <a class="button button-small button-outline" href="<?= e($url) ?>">Lihat perjalanan</a>
        </div>
    </article>
    <?php
}

function trip_summary(array $trip): void
{
    ?>
    <p class="trip-meta">
        <img class="trip-logo" src="<?= e(verification_file_url('logo', (int) $trip['provider_id'])) ?>" alt="" width="34" height="34">
        <?= e($trip['provider_name']) ?><span class="badge"><?= e($trip['vehicle_type']) ?></span>
    </p>
    <h2><?= e($trip['origin']) ?> <span class="route-arrow" aria-hidden="true">→</span><span class="sr-only"> ke </span> <?= e($trip['destination']) ?></h2>
    <dl class="details">
        <div><dt>Berangkat</dt><dd><?= e(tanggal($trip['departure_at']) . ' · ' . waktu($trip['departure_at'])) ?> WIB</dd></div>
        <div><dt>Tiba</dt><dd><?= e(tanggal($trip['arrival_at']) . ' · ' . waktu($trip['arrival_at'])) ?> WIB</dd></div>
        <div><dt>Kendaraan</dt><dd><?= e($trip['vehicle_name']) ?></dd></div>
        <div><dt>Harga per kursi</dt><dd><?= e(rupiah((int) $trip['price'])) ?></dd></div>
    </dl>
    <?php
}
