<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (is_logged_in() && current_user() && current_user()['status'] === 'active') {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $statement->execute([$login, $login]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        flash('danger', 'Username, email, atau kata sandi tidak sesuai.');
        redirect('index.php');
    }
    if ($user['status'] === 'pending') {
        flash('warning', 'Akun masih menunggu persetujuan administrator.');
        redirect('index.php');
    }
    if ($user['status'] !== 'active') {
        flash('danger', 'Akun tidak dapat digunakan. Silakan hubungi administrator.');
        redirect('index.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    audit_log('login', 'user', (int) $user['id']);
    redirect('dashboard.php');
}

$pageTitle = 'Masuk';
require __DIR__ . '/app/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <div class="hero-brand">
            <img src="<?= e(url('assets/img/logo-sman12.png')) ?>" alt="Logo SMAN 12 Medan">
            <div><strong>SMAN 12 MEDAN</strong><br><span class="muted">Media Edukasi Kewirausahaan Digital</span></div>
        </div>
        <h1>Belajar berwirausaha melalui lelang digital.</h1>
        <p>Kelola barang, pahami penentuan harga, lakukan penawaran secara etis, dan pelajari cara Selection Sort menentukan penawar tertinggi secara transparan.</p>
        <div class="feature-pills">
            <span>Lelang internal sekolah</span>
            <span>Selection Sort</span>
            <span>Transaksi transparan</span>
            <span>Literasi digital</span>
        </div>
    </div>
    <div class="login-panel">
        <div class="card login-card">
            <h2>Selamat datang</h2>
            <p>Masuk menggunakan akun yang telah disetujui administrator.</p>
            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="login">Username atau email</label>
                    <input id="login" name="login" autocomplete="username" required autofocus>
                </div>
                <div class="field mt-2">
                    <label for="password">Kata sandi</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary" type="submit">Masuk ke aplikasi</button>
            </form>
            <div class="auth-links"><span>Belum memiliki akun?</span><a href="<?= e(url('register.php')) ?>">Daftar sekarang</a></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
