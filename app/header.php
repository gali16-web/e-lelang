<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? config()['app_name'];
$activePage = $activePage ?? '';
$user = current_user();
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e(config()['app_name']) ?></title>
    <link rel="icon" href="<?= e(url('assets/img/logo-sman12.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="<?= e(url('dashboard.php')) ?>">
                <img src="<?= e(url('assets/img/logo-sman12.png')) ?>" alt="Logo SMAN 12 Medan">
                <span><strong>E-Lelang</strong><small>SMAN 12 Medan</small></span>
            </a>
            <nav class="nav-list" aria-label="Navigasi utama">
                <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard.php')) ?>">Beranda</a>
                <a class="<?= $activePage === 'auctions' ? 'active' : '' ?>" href="<?= e(url('auctions.php')) ?>">Lelang</a>
                <a class="<?= $activePage === 'items' ? 'active' : '' ?>" href="<?= e(url('items.php')) ?>">Barang</a>
                <a class="<?= $activePage === 'education' ? 'active' : '' ?>" href="<?= e(url('education.php')) ?>">Edukasi Digital</a>

                <?php if (is_admin()): ?>
                    <div class="nav-label">Administrator</div>
                    <a class="<?= $activePage === 'users' ? 'active' : '' ?>" href="<?= e(url('users.php')) ?>">Pengguna</a>
                    <a class="<?= $activePage === 'reports' ? 'active' : '' ?>" href="<?= e(url('reports.php')) ?>">Laporan</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="avatar"><?= e(strtoupper(substr((string) $user['full_name'], 0, 1))) ?></div>
                <div><strong><?= e($user['full_name']) ?></strong><small><?= e(role_label($user['role'])) ?></small></div>
                <a class="logout-link" href="<?= e(url('logout.php')) ?>" aria-label="Keluar">Keluar</a>
            </div>
        </aside>
    <?php endif; ?>

    <main class="<?= $user ? 'main-content' : 'guest-content' ?>">
        <?php if ($user): ?>
            <header class="topbar">
                <button class="menu-button" type="button" data-sidebar-toggle aria-label="Buka menu">☰</button>
                <div><h1><?= e($pageTitle) ?></h1><p><?= e(date('l, d F Y')) ?></p></div>
                <a class="profile-chip" href="<?= e(url('profile.php')) ?>"><?= e($user['username']) ?></a>
            </header>
        <?php endif; ?>

        <div class="page-container">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
