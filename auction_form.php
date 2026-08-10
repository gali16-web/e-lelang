<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_admin();

$items = db()->query(
    "SELECT i.*, u.full_name AS owner_name FROM items i JOIN users u ON u.id=i.owner_id
    WHERE i.status='approved' AND NOT EXISTS (SELECT 1 FROM auctions a WHERE a.item_id=i.id AND a.status IN ('open','draft'))
    ORDER BY i.created_at DESC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $startAt = str_replace('T', ' ', (string) ($_POST['start_at'] ?? ''));
    $endAt = str_replace('T', ' ', (string) ($_POST['end_at'] ?? ''));
    $increment = filter_var($_POST['increment_amount'] ?? null, FILTER_VALIDATE_INT);
    $validItemIds = array_map(static fn(array $item): int => (int) $item['id'], $items);
    $errors = [];
    if (!in_array($itemId, $validItemIds, true)) $errors[] = 'Barang belum disetujui atau sudah memiliki jadwal aktif.';
    if (!$startAt || !$endAt || strtotime($endAt) <= strtotime($startAt)) $errors[] = 'Waktu selesai harus setelah waktu mulai.';
    if (!$increment || $increment < 1000) $errors[] = 'Kenaikan minimal penawaran sekurang-kurangnya Rp1.000.';

    if (!$errors) {
        db()->beginTransaction();
        $statement = db()->prepare('INSERT INTO auctions (item_id, start_at, end_at, increment_amount, status, created_by) VALUES (?, ?, ?, ?, "open", ?)');
        $statement->execute([$itemId, $startAt, $endAt, $increment, current_user()['id']]);
        $auctionId = (int) db()->lastInsertId();
        db()->prepare('UPDATE items SET status="auctioned" WHERE id=?')->execute([$itemId]);
        db()->commit();
        audit_log('create_auction', 'auction', $auctionId);
        flash('success', 'Jadwal lelang berhasil dibuat.');
        redirect('auction_detail.php?id=' . $auctionId);
    }
    foreach ($errors as $error) flash('danger', $error);
}

$pageTitle = 'Buat Jadwal Lelang';
$activePage = 'auctions';
require __DIR__ . '/app/header.php';
?>
<section class="card">
    <?php if (!$items): ?>
        <div class="empty-state"><h3>Belum ada barang yang siap dijadwalkan</h3><p>Setujui barang pada menu Barang terlebih dahulu.</p><a class="btn btn-primary" href="<?= e(url('items.php')) ?>">Buka data barang</a></div>
    <?php else: ?>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <div class="field full"><label>Barang</label><select name="item_id" required><option value="">Pilih barang</option><?php foreach ($items as $item): ?><option value="<?= e($item['id']) ?>"><?= e($item['name']) ?> — <?= e($item['owner_name']) ?> — <?= e(rupiah($item['starting_price'])) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Waktu mulai</label><input type="datetime-local" name="start_at" required></div>
            <div class="field"><label>Waktu selesai</label><input type="datetime-local" name="end_at" required></div>
            <div class="field"><label>Kenaikan minimal</label><input type="number" name="increment_amount" min="1000" step="1000" value="10000" required></div>
            <div class="field full"><div class="callout"><strong>Aturan:</strong> setiap penawaran harus lebih tinggi dari penawaran tertinggi sebelumnya sekurang-kurangnya sebesar kenaikan minimal.</div></div>
            <div class="field full"><div class="actions"><button class="btn btn-primary" type="submit">Buat jadwal</button><a class="btn btn-outline" href="<?= e(url('auctions.php')) ?>">Batal</a></div></div>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
