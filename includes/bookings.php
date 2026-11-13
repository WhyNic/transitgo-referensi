<?php

require_once __DIR__ . '/verification.php';

const BOOKING_SELECT = "SELECT b.id, b.schedule_id, b.customer_id, b.total, b.seat_count, b.created_at, b.expires_at, b.paid_at,
        CASE WHEN b.status = 'pending' AND b.expires_at <= NOW() THEN 'expired' ELSE b.status END AS status,
        r.origin, r.destination, s.departure_at, s.arrival_at, p.full_name AS provider_name, c.full_name AS customer_name
    FROM bookings b
    JOIN schedules s ON s.id = b.schedule_id
    JOIN routes r ON r.id = s.route_id
    JOIN users p ON p.id = s.provider_id
    JOIN users c ON c.id = b.customer_id";

function customer_booking(int $bookingId, int $customerId): array
{
    $booking = query(BOOKING_SELECT . ' WHERE b.id = ? AND b.customer_id = ?', [$bookingId, $customerId])->fetch();
    if (!$booking) {
        fail_page(404, 'Pesanan tidak ditemukan.');
    }
    return $booking;
}

function create_booking(int $scheduleId, int $providerId, int $customerId, array $passengers): int
{
    return transaction(static function () use ($scheduleId, $providerId, $customerId, $passengers): int {
        // Use the same provider-then-schedule lock order as publication and document changes.
        $provider = verification_provider($providerId, true);
        if (!$provider || $provider['verification_status'] !== 'approved' || !company_files_complete($provider)) {
            throw new ValidationError('Jadwal sudah tidak dapat dipesan.');
        }
        $schedule = query(
            'SELECT vehicle_id, price, departure_at, departure_at > NOW() AS bookable
             FROM schedules WHERE id = ? AND provider_id = ? AND is_published = 1 FOR UPDATE',
            [$scheduleId, $providerId]
        )->fetch();
        if (!$schedule || !$schedule['bookable']) {
            throw new ValidationError('Jadwal sudah tidak dapat dipesan.');
        }
        $vehicle = query(
            'SELECT seat_capacity, vehicle_photo, stnk_document, kir_document FROM vehicles WHERE id = ? AND provider_id = ?',
            [$schedule['vehicle_id'], $providerId]
        )->fetch();
        if (!$vehicle || !vehicle_files_complete($vehicle)) {
            throw new ValidationError('Jadwal sudah tidak dapat dipesan.');
        }
        $capacity = (int) $vehicle['seat_capacity'];
        $seats = array_keys($passengers);
        if (max($seats) > $capacity) {
            throw new ValidationError('Nomor kursi sudah tidak tersedia.');
        }

        query("UPDATE bookings SET status = 'expired' WHERE schedule_id = ? AND status = 'pending' AND expires_at <= NOW()", [$scheduleId]);
        query(
            "DELETE bs FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id
             WHERE bs.schedule_id = ? AND b.status = 'expired'",
            [$scheduleId]
        );
        $taken = query('SELECT seat_number FROM booking_seats WHERE schedule_id = ?', [$scheduleId])->fetchAll(PDO::FETCH_COLUMN);
        if (array_intersect($seats, $taken)) {
            throw new ValidationError('Salah satu kursi baru saja dipesan. Silakan pilih kursi lain.');
        }

        $total = (int) $schedule['price'] * count($seats);
        query(
            'INSERT INTO bookings (schedule_id, customer_id, total, seat_count, expires_at)
             VALUES (?, ?, ?, ?, LEAST(DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?))',
            [$scheduleId, $customerId, $total, count($seats), $schedule['departure_at']]
        );
        $bookingId = (int) db()->lastInsertId();
        foreach ($passengers as $seat => $name) {
            query(
                'INSERT INTO booking_seats (schedule_id, seat_number, booking_id, passenger_name) VALUES (?, ?, ?, ?)',
                [$scheduleId, $seat, $bookingId, $name]
            );
        }
        return $bookingId;
    });
}

function simulate_payment(int $bookingId, int $customerId, int $scheduleId): void
{
    transaction(static function () use ($bookingId, $customerId, $scheduleId): void {
        query('SELECT id FROM schedules WHERE id = ? FOR UPDATE', [$scheduleId]);
        // The browser supplies neither a payment status nor an amount.
        query(
            "UPDATE bookings SET status = 'paid', paid_at = NOW()
             WHERE id = ? AND customer_id = ? AND schedule_id = ? AND status = 'pending' AND expires_at > NOW()",
            [$bookingId, $customerId, $scheduleId]
        );
    });
}

function booking_passengers(int $bookingId): array
{
    return query('SELECT seat_number, passenger_name FROM booking_seats WHERE booking_id = ? ORDER BY seat_number', [$bookingId])->fetchAll();
}

function booking_code(int $id): string
{
    return 'TG-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

function status_badge(string $status): void
{
    $label = match ($status) {
        'paid' => 'Lunas',
        'pending' => 'Menunggu pembayaran',
        'expired' => 'Kedaluwarsa',
    };
    ?><span class="badge status-<?= e($status) ?>"><?= e($label) ?></span><?php
}

function booking_table(array $bookings, string $role): void
{
    ?>
    <div class="table-scroll"><table>
        <thead><tr>
            <th scope="col">Pesanan</th><th scope="col">Rute</th><th scope="col">Berangkat (WIB)</th>
            <?php if ($role !== 'provider'): ?><th scope="col">Penyedia</th><?php endif; ?>
            <?php if ($role !== 'customer'): ?><th scope="col">Pelanggan</th><?php endif; ?>
            <th scope="col">Total</th><th scope="col">Status</th>
            <?php if ($role === 'customer'): ?><th scope="col">Tindakan</th><?php endif; ?>
        </tr></thead>
        <tbody>
            <?php if (!$bookings) table_empty($role === 'provider' ? 6 : 7, 'Belum ada pesanan.'); ?>
            <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?= e(booking_code((int) $booking['id'])) ?></td>
                    <td><?= e($booking['origin'] . ' → ' . $booking['destination']) ?></td>
                    <td><?= e(tanggal($booking['departure_at']) . ' · ' . waktu($booking['departure_at'])) ?></td>
                    <?php if ($role !== 'provider'): ?><td><?= e($booking['provider_name']) ?></td><?php endif; ?>
                    <?php if ($role !== 'customer'): ?><td><?= e($booking['customer_name']) ?></td><?php endif; ?>
                    <td><?= e(rupiah((int) $booking['total'])) ?></td>
                    <td><?php status_badge($booking['status']); ?></td>
                    <?php if ($role === 'customer'): ?>
                        <td>
                            <?php if ($booking['status'] === 'paid'): ?>
                                <a href="<?= e('/customer/ticket.php?id=' . $booking['id']) ?>">E-tiket</a>
                            <?php else: ?>
                                <a href="<?= e('/customer/payment.php?id=' . $booking['id']) ?>">Detail</a>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php
}

function passenger_list(array $passengers): void
{
    if (!$passengers) {
        return;
    }
    ?>
    <h3>Penumpang</h3>
    <ul class="passenger-list">
        <?php foreach ($passengers as $passenger): ?>
            <li><span><?= e($passenger['passenger_name']) ?></span><span class="badge">Kursi <?= e($passenger['seat_number']) ?></span></li>
        <?php endforeach; ?>
    </ul>
    <?php
}
