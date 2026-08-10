<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$values = [
    'username' => '', 'email' => '', 'full_name' => '', 'identity_number' => '',
    'role' => 'student', 'gender' => '', 'phone' => '', 'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
    $errors = [];

    if (!preg_match('/^[A-Za-z0-9_.]{4,40}$/', $values['username'])) {
        $errors[] = 'Username minimal 4 karakter dan hanya boleh berisi huruf, angka, titik, atau garis bawah.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Alamat email tidak valid.';
    }
    if (mb_strlen($values['full_name']) < 3) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (!in_array($values['role'], ['student', 'teacher', 'staff'], true)) {
        $errors[] = 'Jenis pengguna tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'Konfirmasi kata sandi tidak sama.';
    }

    $check = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
    $check->execute([$values['username'], $values['email']]);
    if ((int) $check->fetchColumn() > 0) {
        $errors[] = 'Username atau email sudah digunakan.';
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO users (username, email, password, full_name, identity_number, gender, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $statement->execute([
            $values['username'], $values['email'], password_hash($password, PASSWORD_DEFAULT),
            $values['full_name'], $values['identity_number'] ?: null, $values['gender'] ?: null,
            $values['phone'] ?: null, $values['address'] ?: null, $values['role'],
        ]);
        flash('success', 'Pendaftaran berhasil. Akun dapat digunakan setelah disetujui administrator.');
        redirect('index.php');
    }
    foreach ($errors as $error) {
        flash('danger', $error);
    }
}

$pageTitle = 'Registrasi';
require __DIR__ . '/app/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <div class="hero-brand">
            <img src="<?= e(url('assets/img/logo-sman12.png')) ?>" alt="Logo SMAN 12 Medan">
            <div><strong>E-LELANG SMAN 12 MEDAN</strong><br><span class="muted">Akun internal warga sekolah</span></div>
        </div>
        <h1>Mulai pengalaman kewirausahaan digital.</h1>
        <p>Registrasi tersedia bagi siswa, guru, dan staf SMAN 12 Medan. Administrator akan memeriksa data sebelum akun diaktifkan.</p>
    </div>
    <div class="login-panel">
        <div class="card login-card" style="width:min(620px,100%)">
            <h2>Formulir registrasi</h2>
            <p>Gunakan identitas yang benar dan dapat diverifikasi sekolah.</p>
            <form method="post" class="form-grid">
                <?= csrf_field() ?>
                <div class="field"><label>Nama lengkap</label><input name="full_name" value="<?= e($values['full_name']) ?>" required></div>
                <div class="field"><label>Jenis pengguna</label><select name="role" required><option value="student" <?= $values['role'] === 'student' ? 'selected' : '' ?>>Siswa</option><option value="teacher" <?= $values['role'] === 'teacher' ? 'selected' : '' ?>>Guru</option><option value="staff" <?= $values['role'] === 'staff' ? 'selected' : '' ?>>Staf sekolah</option></select></div>
                <div class="field"><label>Username</label><input name="username" value="<?= e($values['username']) ?>" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($values['email']) ?>" required></div>
                <div class="field"><label>NIS/NIP/Nomor identitas</label><input name="identity_number" value="<?= e($values['identity_number']) ?>"></div>
                <div class="field"><label>Nomor telepon</label><input name="phone" value="<?= e($values['phone']) ?>"></div>
                <div class="field"><label>Jenis kelamin</label><select name="gender"><option value="">Pilih</option><option value="male" <?= $values['gender'] === 'male' ? 'selected' : '' ?>>Laki-laki</option><option value="female" <?= $values['gender'] === 'female' ? 'selected' : '' ?>>Perempuan</option></select></div>
                <div class="field"><label>Alamat</label><input name="address" value="<?= e($values['address']) ?>"></div>
                <div class="field"><label>Kata sandi</label><input type="password" name="password" minlength="8" required></div>
                <div class="field"><label>Ulangi kata sandi</label><input type="password" name="password_confirmation" minlength="8" required></div>
                <div class="field full"><button class="btn btn-primary" type="submit">Kirim pendaftaran</button></div>
            </form>
            <div class="auth-links"><a href="<?= e(url('index.php')) ?>">← Kembali ke halaman masuk</a></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
