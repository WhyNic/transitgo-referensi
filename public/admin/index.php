<?php

require __DIR__ . '/../../includes/bootstrap.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/provider.php';
require APP_ROOT . '/includes/bookings.php';

require_user('admin');
$view = field($_GET, 'view');
$views = [
    'users' => ['Pengguna', 'SELECT COUNT(*) FROM users'],
    'providers' => ['Penyedia', "SELECT COUNT(*) FROM users WHERE role = 'provider'"],
    'routes' => ['Rute', 'SELECT COUNT(*) FROM routes'],
    'schedules' => ['Jadwal', 'SELECT COUNT(*) FROM schedules'],
    'bookings' => ['Pesanan', 'SELECT COUNT(*) FROM bookings'],
];
if (isset($_GET['view']) && !isset($views[$view])) {
    fail_page(404, 'Halaman tidak ditemukan.');
}
$rows = match ($view) {
    'users' => query('SELECT full_name, email, role, created_at FROM users ORDER BY id DESC')->fetchAll(),
    'providers' => query("SELECT full_name, email, role, created_at FROM users WHERE role = 'provider' ORDER BY id DESC")->fetchAll(),
    'routes' => query('SELECT r.*, u.full_name AS provider_name FROM routes r JOIN users u ON u.id = r.provider_id ORDER BY r.id DESC')->fetchAll(),
    'schedules' => query(
        'SELECT s.*, r.origin, r.destination, u.full_name AS provider_name, v.vehicle_name
         FROM schedules s JOIN routes r ON r.id = s.route_id JOIN vehicles v ON v.id = s.vehicle_id
         JOIN users u ON u.id = s.provider_id ORDER BY s.departure_at DESC'
    )->fetchAll(),
    'bookings' => query(BOOKING_SELECT . ' ORDER BY b.created_at DESC, b.id DESC')->fetchAll(),
    default => [],
};
$counts = [];
if ($view === '') {
    $pendingCount = (int) query("SELECT COUNT(*) FROM users WHERE role = 'provider' AND verification_status = 'pending'")->fetchColumn();
    foreach ($views as $key => [$label, $sql]) {
        $counts[$key] = (int) query($sql)->fetchColumn();
    }
}
$roleLabels = ['customer' => 'Pelanggan', 'provider' => 'Penyedia', 'admin' => 'Admin'];
page_start($views[$view][0] ?? 'Dasbor admin');
?>
<section class="container page-section">
    <div class="page-heading">
        <?php if ($view !== ''): ?><a class="back-link" href="/admin/">← Dasbor admin</a><?php endif; ?>
        <h1><?= e($views[$view][0] ?? 'Dasbor admin') ?></h1>
    </div>
    <?php if ($view === ''): ?>
        <div class="stats-grid">
            <?php foreach ($views as $key => [$title]): ?>
                <a class="panel stat-card" href="<?= e('/admin/?view=' . $key) ?>"><span><?= e($title) ?></span><strong><?= e($counts[$key]) ?></strong></a>
            <?php endforeach; ?>
            <a class="panel stat-card" href="/admin/verification.php"><span>Verifikasi penyedia</span><strong><?= e($pendingCount) ?></strong></a>
        </div>
    <?php elseif ($view === 'bookings'): ?>
        <div class="panel table-panel"><?php booking_table($rows, 'admin'); ?></div>
    <?php else: ?>
        <?php
        $accountView = $view === 'users' || $view === 'providers';
        $columns = $accountView ? ['Nama', 'Email', 'Peran', 'Terdaftar'] : ['Penyedia', ...provider_spec($view)['columns']];
        ?>
        <div class="panel table-panel">
            <div class="table-scroll"><table>
                <thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= e($column) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php if (!$rows) table_empty(count($columns)); ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $cells = $accountView
                            ? [$row['full_name'], $row['email'], $roleLabels[$row['role']], tanggal($row['created_at'])]
                            : [$row['provider_name'], ...provider_cells($view, $row)];
                        ?>
                        <tr><?php foreach ($cells as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    <?php endif; ?>
</section>
<?php page_end(); ?>
