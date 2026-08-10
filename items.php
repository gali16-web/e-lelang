<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $itemStatement = db()->prepare('SELECT * FROM items WHERE id=?');
    $itemStatement->execute([$itemId]);
    $item = $itemStatement->fetch();

    if (!$item) {
        flash('danger', 'Data barang tidak ditemukan.');
    } elseif (in_array($action, ['approve', 'reject'], true) && is_admin()) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $statement = db()->prepare('UPDATE items SET status=?, verification_note=?, verified_by=?, verified_at=NOW() WHERE id=?');
        $statement->execute([$status, trim((string) ($_POST['note'] ?? '')) ?: null, $user['id'], $itemId]);
        audit_log($action . '_item', 'item', $itemId);
        flash('success', 'Status barang berhasil diperbarui.');
    } elseif ($action === 'delete' && ((int) $item['owner_id'] === (int) $user['id'] || is_admin())) {
        if (!in_array($item['status'], ['pending', 'rejected'], true)) {
            flash('warning', 'Barang yang sudah disetujui atau dilelang tidak dapat dihapus.');
        } else {
            $statement = db()->prepare('DELETE FROM items WHERE id=?');
            $statement->execute([$itemId]);
            audit_log('delete_item', 'item', $itemId);
            flash('success', 'Barang berhasil dihapus.');
        }
    }
    redirect('items.php');
}

if (is_admin()) {
    $items = db()->query(
        'SELECT i.*, u.full_name AS owner_name, u.role AS owner_role FROM items i JOIN users u ON u.id=i.owner_id ORDER BY FIELD(i.status,"pending","approved","auctioned","sold","rejected"), i.created_at DESC'
    )->fetchAll();
} else {
    $statement = db()->prepare('SELECT i.*, u.full_name AS owner_name, u.role AS owner_role FROM items i JOIN users u ON u.id=i.owner_id WHERE i.owner_id=? ORDER BY i.created_at DESC');
    $statement->execute([$user['id']]);
    $items = $statement->fetchAll();
}

$pageTitle = is_admin() ? 'Verifikasi dan Data Barang' : 'Barang Saya';
$activePage = 'items';
require __DIR__ . '/app/header.php';
?>
<div class="section-head mt-0">
    <div><h2><?= is_admin() ? 'Seluruh barang' : 'Daftar barang yang diajukan' ?></h2><p>Barang harus memenuhi ketentuan sekolah sebelum dijadwalkan dalam lelang.</p></div>
    <a class="btn btn-primary" href="<?= e(url('item_form.php')) ?>">+ Ajukan barang</a>
</div>
<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Barang</th><?php if (is_admin()): ?><th>Pemilik</th><?php endif; ?><th>Harga awal</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (!$items): ?><tr><td colspan="6" class="empty-state">Belum ada data barang.</td></tr><?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><div style="display:flex;gap:10px;align-items:center"><img class="table-image" src="<?= e($item['image'] ? url('uploads/items/' . $item['image']) : url('assets/img/logo-sman12.png')) ?>" alt=""><div><strong><?= e($item['name']) ?></strong><br><small class="muted"><?= e($item['category']) ?> · <?= e($item['brand'] ?: '-') ?></small></div></div></td>
                    <?php if (is_admin()): ?><td><?= e($item['owner_name']) ?><br><small class="muted"><?= e(role_label($item['owner_role'])) ?></small></td><?php endif; ?>
                    <td class="nowrap"><?= e(rupiah($item['starting_price'])) ?></td>
                    <td><span class="badge <?= $item['status'] === 'approved' ? 'badge-success' : ($item['status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>"><?= e(status_label($item['status'])) ?></span><?php if ($item['verification_note']): ?><br><small class="muted"><?= e($item['verification_note']) ?></small><?php endif; ?></td>
                    <td><?= e(indo_datetime($item['created_at'])) ?></td>
                    <td><div class="actions">
                        <?php if ((int) $item['owner_id'] === (int) $user['id'] && in_array($item['status'], ['pending', 'rejected'], true)): ?><a class="btn btn-outline btn-sm" href="<?= e(url('item_form.php?id=' . $item['id'])) ?>">Edit</a><?php endif; ?>
                        <?php if (is_admin() && $item['status'] === 'pending'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= e($item['id']) ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm" type="submit">Setujui</button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= e($item['id']) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="note" value="Perlu perbaikan data barang"><button class="btn btn-danger btn-sm" type="submit">Tolak</button></form>
                        <?php endif; ?>
                        <?php if (((int) $item['owner_id'] === (int) $user['id'] || is_admin()) && in_array($item['status'], ['pending', 'rejected'], true)): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= e($item['id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline btn-sm" data-confirm="Hapus barang ini?" type="submit">Hapus</button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
