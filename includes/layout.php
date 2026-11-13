<?php

function page_start(string $title, string $section = ''): void
{
    $user = $section === 'error' ? null : current_user();
    $notice = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#102b25">
        <title><?= e($title) ?> · TransitGo</title>
        <link rel="stylesheet" href="/assets/app.css">
    </head>
    <body>
    <a class="skip" href="#main">Langsung ke konten</a>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="<?= e($user ? role_home($user['role']) : '/') ?>"><span class="brand-icon" aria-hidden="true"></span>Transit<span>Go</span></a>
            <nav aria-label="Navigasi utama">
                <?php if (!$user): ?>
                    <a class="nav-link" href="/#cari">Cari tiket</a>
                    <a class="button button-outline button-small" href="/login.php">Masuk</a>
                <?php elseif ($user['role'] === 'customer'): ?>
                    <a class="nav-link" href="/">Cari tiket</a>
                    <a class="nav-link" href="/customer/bookings.php">Pesanan</a>
                <?php else: ?>
                    <a class="nav-link" href="<?= e(role_home($user['role'])) ?>">Dasbor</a>
                    <?php if ($user['role'] === 'provider'): ?>
                        <a class="nav-link" href="/provider/manage.php?type=routes">Rute</a>
                        <a class="nav-link" href="/provider/manage.php?type=schedules">Jadwal</a>
                        <a class="nav-link" href="/provider/manage.php?type=vehicles">Kendaraan</a>
                        <a class="nav-link" href="/provider/bookings.php">Pesanan</a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($user): ?>
                    <a class="nav-link" href="/profile.php">Profil</a>
                    <form method="post" action="/logout.php" class="inline-form">
                        <?= csrf_field() ?>
                        <button class="button button-outline button-small">Keluar</button>
                    </form>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main id="main">
        <?php if ($notice): ?>
            <div class="container flash-wrap">
                <p class="alert <?= e($notice[1]) ?>" role="status"><?= e($notice[0]) ?></p>
            </div>
        <?php endif; ?>
    <?php
}

function page_end(): void
{
    ?>
    </main>
    <footer class="site-footer">
        <div class="container footer-inner"><span>TransitGo</span><small>Pemesanan bus dan travel antarkota</small></div>
    </footer>
    </body>
    </html>
    <?php
}

function form_error(string $error): void
{
    if ($error !== '') {
        ?><p class="alert danger" role="alert"><?= e($error) ?></p><?php
    }
}

function table_empty(int $columns, string $message = 'Belum ada data.'): void
{
    ?><tr><td colspan="<?= e($columns) ?>" class="muted"><?= e($message) ?></td></tr><?php
}
