<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'profile');

    if ($action === 'profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        if (mb_strlen($fullName) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Nama atau email belum valid.');
        } else {
            $statement = db()->prepare('UPDATE users SET full_name=?, email=?, identity_number=?, phone=?, gender=?, birth_date=?, address=? WHERE id=?');
            $statement->execute([
                $fullName, $email, trim((string) $_POST['identity_number']) ?: null,
                trim((string) $_POST['phone']) ?: null, $_POST['gender'] ?: null,
                $_POST['birth_date'] ?: null, trim((string) $_POST['address']) ?: null, $user['id'],
            ]);
            audit_log('update_profile', 'user', (int) $user['id']);
            flash('success', 'Profil berhasil diperbarui.');
        }
    }

    if ($action === 'password') {
        if (!password_verify((string) $_POST['current_password'], $user['password'])) {
            flash('danger', 'Kata sandi saat ini tidak sesuai.');
        } elseif (strlen((string) $_POST['new_password']) < 8 || $_POST['new_password'] !== $_POST['password_confirmation']) {
            flash('danger', 'Kata sandi baru minimal 8 karakter dan konfirmasi harus sama.');
        } else {
            $statement = db()->prepare('UPDATE users SET password=? WHERE id=?');
            $statement->execute([password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
            audit_log('change_password', 'user', (int) $user['id']);
            flash('success', 'Kata sandi berhasil diperbarui.');
        }
    }

    if ($action === 'bank') {
        $existing = db()->prepare('SELECT id FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC LIMIT 1');
        $existing->execute([$user['id']]);
        $bankId = $existing->fetchColumn();
        if ($bankId) {
            $statement = db()->prepare('UPDATE bank_accounts SET bank_name=?, account_number=?, account_holder=?, is_primary=1 WHERE id=? AND user_id=?');
            $statement->execute([trim((string) $_POST['bank_name']), trim((string) $_POST['account_number']), trim((string) $_POST['account_holder']), $bankId, $user['id']]);
        } else {
            $statement = db()->prepare('INSERT INTO bank_accounts (user_id, bank_name, account_number, account_holder, is_primary) VALUES (?, ?, ?, ?, 1)');
            $statement->execute([$user['id'], trim((string) $_POST['bank_name']), trim((string) $_POST['account_number']), trim((string) $_POST['account_holder'])]);
        }
        flash('success', 'Data rekening berhasil disimpan.');
    }
    redirect('profile.php');
}

$bankStatement = db()->prepare('SELECT * FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC LIMIT 1');
$bankStatement->execute([$user['id']]);
$bank = $bankStatement->fetch() ?: [];

$pageTitle = 'Profil dan Rekening';
require __DIR__ . '/app/header.php';
?>
<div class="grid grid-2">
    <section class="card">
        <h2>Data profil</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="field"><label>Nama lengkap</label><input name="full_name" value="<?= e($user['full_name']) ?>" required></div>
            <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($user['email']) ?>" required></div>
            <div class="field"><label>Username</label><input value="<?= e($user['username']) ?>" disabled></div>
            <div class="field"><label>Peran</label><input value="<?= e(role_label($user['role'])) ?>" disabled></div>
            <div class="field"><label>NIS/NIP/Identitas</label><input name="identity_number" value="<?= e($user['identity_number']) ?>"></div>
            <div class="field"><label>Nomor telepon</label><input name="phone" value="<?= e($user['phone']) ?>"></div>
            <div class="field"><label>Jenis kelamin</label><select name="gender"><option value="">Pilih</option><option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Laki-laki</option><option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Perempuan</option></select></div>
            <div class="field"><label>Tanggal lahir</label><input type="date" name="birth_date" value="<?= e($user['birth_date']) ?>"></div>
            <div class="field full"><label>Alamat</label><textarea name="address"><?= e($user['address']) ?></textarea></div>
            <div class="field full"><button class="btn btn-primary" type="submit">Simpan profil</button></div>
        </form>
    </section>
    <div class="grid">
        <section class="card">
            <h2>Rekening penerima</h2>
            <p class="muted">Digunakan untuk pembayaran barang yang berhasil Anda jual.</p>
            <form method="post" class="form-grid">
                <?= csrf_field() ?><input type="hidden" name="action" value="bank">
                <div class="field"><label>Nama bank</label><input name="bank_name" value="<?= e($bank['bank_name'] ?? '') ?>" required></div>
                <div class="field"><label>Nomor rekening</label><input name="account_number" value="<?= e($bank['account_number'] ?? '') ?>" required></div>
                <div class="field full"><label>Nama pemilik rekening</label><input name="account_holder" value="<?= e($bank['account_holder'] ?? $user['full_name']) ?>" required></div>
                <div class="field full"><button class="btn btn-primary" type="submit">Simpan rekening</button></div>
            </form>
        </section>
        <section class="card">
            <h2>Ubah kata sandi</h2>
            <form method="post" class="form-grid">
                <?= csrf_field() ?><input type="hidden" name="action" value="password">
                <div class="field full"><label>Kata sandi saat ini</label><input type="password" name="current_password" required></div>
                <div class="field"><label>Kata sandi baru</label><input type="password" name="new_password" minlength="8" required></div>
                <div class="field"><label>Ulangi kata sandi</label><input type="password" name="password_confirmation" minlength="8" required></div>
                <div class="field full"><button class="btn btn-outline" type="submit">Perbarui kata sandi</button></div>
            </form>
        </section>
    </div>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
