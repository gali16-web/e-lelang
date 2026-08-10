<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    verify_csrf();
    $auctionId = (int) ($_POST['auction_id'] ?? 0);
    $statement = db()->prepare('SELECT a.*, i.id AS item_id FROM auctions a JOIN items i ON i.id=a.item_id WHERE a.id=?');
    $statement->execute([$auctionId]);
    $auction = $statement->fetch();
    if (!$auction || $auction['status'] === 'closed') {
        flash('warning', 'Lelang tidak ditemukan atau sudah ditutup.');
    } else {
        db()->beginTransaction();
        db()->prepare('UPDATE auctions SET status="cancelled" WHERE id=?')->execute([$auctionId]);
        db()->prepare('UPDATE items SET status="approved" WHERE id=?')->execute([$auction['item_id']]);
        db()->commit();
        audit_log('cancel_auction', 'auction', $auctionId);
        flash('success', 'Lelang dibatalkan dan barang dapat dijadwalkan kembali.');
    }
    redirect('auctions.php');
}

$filter = (string) ($_GET['status'] ?? 'all');
$where = match ($filter) {
    'open' => "a.status='open' AND NOW() BETWEEN a.start_at AND a.end_at",
    'scheduled' => "a.status='open' AND NOW()<a.start_at",
    'ended' => "a.status='open' AND NOW()>a.end_at",
    'closed' => "a.status='closed'",
    default => '1=1',
};
$statement = db()->query(
    "SELECT a.*, i.name, i.image, i.starting_price, i.owner_id, u.full_name AS owner_name,
    w.full_name AS winner_name, (SELECT MAX(amount) FROM bids WHERE auction_id=a.id) AS highest_bid,
    (SELECT COUNT(*) FROM bids WHERE auction_id=a.id) AS bid_count
    FROM auctions a JOIN items i ON i.id=a.item_id JOIN users u ON u.id=i.owner_id
    LEFT JOIN users w ON w.id=a.winner_id WHERE $where ORDER BY a.created_at DESC"
);
$auctions = $statement->fetchAll();

$pageTitle = 'Lelang';
$activePage = 'auctions';
require __DIR__ . '/app/header.php';
?>
<div class="section-head mt-0">
    <div><h2>Jadwal dan proses lelang</h2><p>Selection Sort mengurutkan seluruh penawaran untuk menentukan nilai tertinggi.</p></div>
    <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(url('auction_form.php')) ?>">+ Buat jadwal</a><?php endif; ?>
</div>
<div class="actions no-print" style="margin-bottom:18px">
    <?php foreach (['all' => 'Semua', 'open' => 'Berlangsung', 'scheduled' => 'Akan datang', 'ended' => 'Perlu ditutup', 'closed' => 'Selesai'] as $key => $label): ?>
        <a class="btn <?= $filter === $key ? 'btn-primary' : 'btn-outline' ?> btn-sm" href="<?= e(url('auctions.php?status=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<div class="grid grid-2">
    <?php if (!$auctions): ?><div class="card empty-state">Tidak ada lelang pada kategori ini.</div><?php endif; ?>
    <?php foreach ($auctions as $auction): ?>
        <?php $status = effective_auction_status($auction); ?>
        <article class="card auction-card">
            <img src="<?= e($auction['image'] ? url('uploads/items/' . $auction['image']) : url('assets/img/logo-sman12.png')) ?>" alt="<?= e($auction['name']) ?>">
            <div class="auction-card-content">
                <span class="badge <?= $status === 'open' ? 'badge-success' : ($status === 'closed' ? 'badge-info' : 'badge-warning') ?>"><?= e(status_label($status)) ?></span>
                <h3><?= e($auction['name']) ?></h3>
                <div class="price"><?= e(rupiah($auction['highest_bid'] ?: $auction['starting_price'])) ?></div>
                <div class="auction-meta"><span><?= e($auction['bid_count']) ?> penawaran</span><span>Pemilik: <?= e($auction['owner_name']) ?></span><span>Berakhir: <?= e(indo_datetime($auction['end_at'])) ?></span></div>
                <?php if ($status === 'open'): ?><p class="countdown" data-countdown="<?= e(date(DATE_ATOM, strtotime($auction['end_at']))) ?>"></p><?php endif; ?>
                <?php if ($auction['winner_name']): ?><p><strong>Pemenang:</strong> <?= e($auction['winner_name']) ?></p><?php endif; ?>
                <div class="actions"><a class="btn btn-primary btn-sm" href="<?= e(url('auction_detail.php?id=' . $auction['id'])) ?>">Detail</a>
                    <?php if (is_admin() && in_array($status, ['ended', 'open'], true)): ?><form method="post" action="<?= e(url('close_auction.php')) ?>"><?= csrf_field() ?><input type="hidden" name="auction_id" value="<?= e($auction['id']) ?>"><button class="btn btn-warning btn-sm" data-confirm="Tutup lelang dan tetapkan pemenang?" type="submit">Tutup & urutkan</button></form><?php endif; ?>
                    <?php if (is_admin() && !in_array($status, ['closed', 'cancelled'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="auction_id" value="<?= e($auction['id']) ?>"><button class="btn btn-outline btn-sm" data-confirm="Batalkan lelang ini?" type="submit">Batalkan</button></form><?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
