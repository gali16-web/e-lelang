<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $distributionId = (int) ($_POST['distribution_id'] ?? 0);
    $statement = db()->prepare('SELECT * FROM distributions WHERE id=?');
    $statement->execute([$distributionId]);
    $distribution = $statement->fetch();
    if (!$distribution || (!is_admin() && (int) $distribution['seller_id'] !== (int) $user['id'])) {
        flash('danger', 'Anda tidak memiliki akses untuk memperbarui distribusi ini.');
    } else {
        $status = (string) ($_POST['status'] ?? 'pending');
        $method = (string) ($_POST['method'] ?? 'school_pickup');
        if (!in_array($status, ['pending', 'ready', 'completed', 'cancelled'], true) || !in_array($method, ['school_pickup', 'direct_meet'], true)) {
            flash('danger', 'Data distribusi tidak valid.');
        } else {
            $update = db()->prepare('UPDATE distributions SET method=?, meeting_location=?, scheduled_at=?, status=?, notes=?, completed_at=IF(?="completed", NOW(), completed_at), updated_by=? WHERE id=?');
            $update->execute([
                $method, trim((string) ($_POST['meeting_location'] ?? '')) ?: null,
                !empty($_POST['scheduled_at']) ? str_replace('T', ' ', (string) $_POST['scheduled_at']) : null,
                $status, trim((string) ($_POST['notes'] ?? '')) ?: null, $status, $user['id'], $distributionId,
            ]);
            audit_log('update_distribution', 'distribution', $distributionId, $status);
            flash('success', 'Data distribusi berhasil diperbarui.');
        }
    }
    redirect('distribution.php');
}

$where = is_admin() ? '1=1' : '(d.seller_id=:uid1 OR d.buyer_id=:uid2)';
$statement = db()->prepare(
    "SELECT d.*, i.name AS item_name, seller.full_name AS seller_name, buyer.full_name AS buyer_name,
    p.status AS payment_status FROM distributions d JOIN auctions a ON a.id=d.auction_id
    JOIN items i ON i.id=a.item_id JOIN users seller ON seller.id=d.seller_id JOIN users buyer ON buyer.id=d.buyer_id
    JOIN payments p ON p.auction_id=d.auction_id WHERE $where ORDER BY d.created_at DESC"
);
$statement->execute(is_admin() ? [] : ['uid1' => $user['id'], 'uid2' => $user['id']]);
$distributions = $statement->fetchAll();

$pageTitle = 'Distribusi Barang';
$activePage = 'distribution';
require __DIR__ . '/app/header.php';
?>
<div class="section-head mt-0"><div><h2>Penyerahan barang</h2><p>Penyerahan dilakukan di lingkungan sekolah atau melalui pertemuan langsung yang disepakati.</p></div></div>
<div class="grid">
<?php if (!$distributions): ?><div class="card empty-state">Belum ada data distribusi barang.</div><?php endif; ?>
<?php foreach ($distributions as $row): ?>
    <section class="card">
        <div class="section-head mt-0"><div><span class="badge <?= $row['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>"><?= e(status_label($row['status'])) ?></span><h2><?= e($row['item_name']) ?></h2></div><span class="badge <?= $row['payment_status'] === 'verified' ? 'badge-success' : 'badge-warning' ?>">Pembayaran: <?= e(status_label($row['payment_status'])) ?></span></div>
        <div class="grid grid-3"><div><small class="muted">Penjual</small><p><strong><?= e($row['seller_name']) ?></strong></p></div><div><small class="muted">Pembeli</small><p><strong><?= e($row['buyer_name']) ?></strong></p></div><div><small class="muted">Jadwal</small><p><strong><?= e(indo_datetime($row['scheduled_at'])) ?></strong></p></div></div>
        <p class="muted">Lokasi: <?= e($row['meeting_location'] ?: 'Belum ditentukan') ?><?= $row['notes'] ? ' · ' . e($row['notes']) : '' ?></p>
        <?php if (is_admin() || (int) $row['seller_id'] === (int) $user['id']): ?>
            <form method="post" class="form-grid">
                <?= csrf_field() ?><input type="hidden" name="distribution_id" value="<?= e($row['id']) ?>">
                <div class="field"><label>Metode</label><select name="method"><option value="school_pickup" <?= $row['method'] === 'school_pickup' ? 'selected' : '' ?>>Pengambilan di sekolah</option><option value="direct_meet" <?= $row['method'] === 'direct_meet' ? 'selected' : '' ?>>Pertemuan langsung</option></select></div>
                <div class="field"><label>Status</label><select name="status"><option value="pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Menunggu</option><option value="ready" <?= $row['status'] === 'ready' ? 'selected' : '' ?>>Siap diserahkan</option><option value="completed" <?= $row['status'] === 'completed' ? 'selected' : '' ?>>Selesai</option><option value="cancelled" <?= $row['status'] === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option></select></div>
                <div class="field"><label>Lokasi</label><input name="meeting_location" value="<?= e($row['meeting_location']) ?>" placeholder="Contoh: Ruang koperasi sekolah"></div>
                <div class="field"><label>Jadwal penyerahan</label><input type="datetime-local" name="scheduled_at" value="<?= e($row['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($row['scheduled_at'])) : '') ?>"></div>
                <div class="field full"><label>Catatan</label><input name="notes" value="<?= e($row['notes']) ?>"></div>
                <div class="field full"><button class="btn btn-primary" type="submit">Simpan distribusi</button></div>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
