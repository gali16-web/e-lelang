<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();
$auctionId = (int) ($_GET['id'] ?? 0);

function load_auction(int $auctionId): array|false
{
    $statement = db()->prepare(
        'SELECT a.*, i.name, i.brand, i.category, i.item_condition, i.description, i.image, i.starting_price, i.owner_id,
        u.full_name AS owner_name, w.full_name AS winner_name FROM auctions a
        JOIN items i ON i.id=a.item_id JOIN users u ON u.id=i.owner_id LEFT JOIN users w ON w.id=a.winner_id WHERE a.id=?'
    );
    $statement->execute([$auctionId]);
    return $statement->fetch();
}

$auction = load_auction($auctionId);
if (!$auction) {
    http_response_code(404);
    exit('Lelang tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_INT);
    try {
        db()->beginTransaction();
        $lock = db()->prepare('SELECT a.*, i.owner_id, i.starting_price FROM auctions a JOIN items i ON i.id=a.item_id WHERE a.id=? FOR UPDATE');
        $lock->execute([$auctionId]);
        $lockedAuction = $lock->fetch();
        if (!$lockedAuction || effective_auction_status($lockedAuction) !== 'open') {
            throw new RuntimeException('Lelang tidak sedang berlangsung.');
        }
        if ((int) $lockedAuction['owner_id'] === (int) current_user()['id']) {
            throw new RuntimeException('Pemilik barang tidak dapat menawar barangnya sendiri.');
        }
        $minimum = (int) $lockedAuction['starting_price'];
        if (!$amount || $amount < $minimum) {
            throw new RuntimeException('Penawaran minimal adalah ' . rupiah($minimum) . '.');
        }
        $checkBid = db()->prepare('SELECT id FROM bids WHERE auction_id=? AND user_id=?');
        $checkBid->execute([$auctionId, current_user()['id']]);
        $existingBid = $checkBid->fetch();

        if ($existingBid) {
            $updateBid = db()->prepare('UPDATE bids SET amount=?, created_at=NOW() WHERE id=?');
            $updateBid->execute([$amount, $existingBid['id']]);
            $bidId = (int) $existingBid['id'];
            $msg = 'Penawaran berhasil diperbarui.';
        } else {
            $statement = db()->prepare('INSERT INTO bids (auction_id, user_id, amount) VALUES (?, ?, ?)');
            $statement->execute([$auctionId, current_user()['id'], $amount]);
            $bidId = (int) db()->lastInsertId();
            $msg = 'Penawaran berhasil disimpan.';
        }
        db()->commit();
        audit_log('place_bid', 'bid', $bidId, rupiah($amount));
        flash('success', $msg);
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        flash('danger', $exception->getMessage());
    }
    redirect('auction_detail.php?id=' . $auctionId);
}

$auction = load_auction($auctionId);
$rawStatement = db()->prepare('SELECT b.*, u.full_name, u.username FROM bids b JOIN users u ON u.id=b.user_id WHERE b.auction_id=? ORDER BY b.created_at ASC, b.id ASC');
$rawStatement->execute([$auctionId]);
$rawBids = $rawStatement->fetchAll();
$sorting = SelectionSorter::withTrace($rawBids);
$bids = $sorting['sorted'];
$status = effective_auction_status($auction);
$minimum = (int) $auction['starting_price'];
$myBidAmount = null;
if (is_logged_in()) {
    $myBidStmt = db()->prepare('SELECT amount FROM bids WHERE auction_id=? AND user_id=?');
    $myBidStmt->execute([$auctionId, current_user()['id']]);
    $myBid = $myBidStmt->fetch();
    if ($myBid) {
        $myBidAmount = (int) $myBid['amount'];
    }
}

$pageTitle = 'Detail Lelang';
$activePage = 'auctions';
require __DIR__ . '/app/header.php';
?>
<section class="card item-hero">
    <img src="<?= e($auction['image'] ? url('uploads/items/' . $auction['image']) : url('assets/img/logo-sman12.png')) ?>" alt="<?= e($auction['name']) ?>">
    <div>
        <span class="badge <?= $status === 'open' ? 'badge-success' : 'badge-warning' ?>"><?= e(status_label($status)) ?></span>
        <h2><?= e($auction['name']) ?></h2>
        <p class="muted"><?= e($auction['brand'] ?: 'Tanpa merek') ?> · <?= e($auction['category']) ?> · Kondisi <?= e(str_replace('_', ' ', $auction['item_condition'])) ?></p>
        <p><?= nl2br(e($auction['description'])) ?></p>
        <div class="grid grid-2">
            <div><small class="muted">Harga awal</small><div class="price"><?= e(rupiah($auction['starting_price'])) ?></div></div>
            <?php if ($status !== 'open'): ?>
            <div><small class="muted">Penawaran tertinggi</small><div class="price"><?= e($bids ? rupiah((int)$bids[0]['amount']) : '-') ?></div></div>
            <?php else: ?>
            <div><small class="muted">Total penawaran masuk</small><div class="price"><?= count($bids) ?> peserta</div></div>
            <?php endif; ?>
        </div>
        <p class="muted">Pemilik: <?= e($auction['owner_name']) ?><br>Periode: <?= e(indo_datetime($auction['start_at'])) ?> – <?= e(indo_datetime($auction['end_at'])) ?></p>
        <?php if ($status === 'open'): ?><p class="countdown" data-countdown="<?= e(date(DATE_ATOM, strtotime($auction['end_at']))) ?>"></p><?php endif; ?>
        <?php if ($status === 'closed'): ?><div class="callout"><strong>Pemenang:</strong> <?= e($auction['winner_name'] ?: 'Tidak ada penawaran') ?><?php if ($auction['winning_bid']): ?> dengan nilai <?= e(rupiah($auction['winning_bid'])) ?><?php endif; ?></div><?php endif; ?>

        <?php if ($status === 'open' && !is_admin() && (int) $auction['owner_id'] !== (int) current_user()['id']): ?>
            <form method="post" class="form-grid mt-2">
                <?= csrf_field() ?>
                <div class="field"><label>Nominal penawaran</label><input type="number" name="amount" min="<?= e($minimum) ?>" step="1000" placeholder="Minimal <?= e(rupiah($minimum)) ?>" value="<?= $myBidAmount ? e($myBidAmount) : '' ?>" required></div>
                <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit"><?= $myBidAmount ? 'Perbarui penawaran' : 'Kirim penawaran' ?></button></div>
            </form>
        <?php elseif ($status === 'open' && (int) $auction['owner_id'] === (int) current_user()['id']): ?><p class="alert alert-info">Anda adalah pemilik barang sehingga tidak dapat memberikan penawaran.</p><?php endif; ?>

        <?php if (is_admin() && in_array($status, ['open', 'ended'], true)): ?><form class="mt-2" method="post" action="<?= e(url('close_auction.php')) ?>"><?= csrf_field() ?><input type="hidden" name="auction_id" value="<?= e($auctionId) ?>"><button class="btn btn-warning" data-confirm="Tutup lelang dan tetapkan hasil Selection Sort?" type="submit">Tutup dan tentukan pemenang</button></form><?php endif; ?>
    </div>
</section>

<?php if ($status !== 'open'): ?>
<div class="section-head"><div><h2>Urutan penawaran</h2><p>Diurutkan dari terbesar ke terkecil menggunakan Selection Sort.</p></div><span class="badge badge-info"><?= count($bids) ?> data</span></div>
<section class="card">
    <div class="table-wrap"><table><thead><tr><th>Peringkat</th><th>Penawar</th><th>Nominal</th><th>Waktu</th></tr></thead><tbody>
        <?php if (!$bids): ?><tr><td colspan="4" class="empty-state">Belum ada penawaran.</td></tr><?php endif; ?>
        <?php foreach ($bids as $index => $bid): ?><tr><td><span class="bid-rank <?= $index === 0 ? 'first' : '' ?>"><?= $index + 1 ?></span></td><td><?= e($bid['full_name']) ?><br><small class="muted">@<?= e($bid['username']) ?></small></td><td><strong><?= e(rupiah($bid['amount'])) ?></strong></td><td><?= e(indo_datetime($bid['created_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>

<?php if ($rawBids): ?>
<div class="section-head"><div><h2>Transparansi proses Selection Sort</h2><p>Jejak pengurutan dapat digunakan untuk pembelajaran dan pengujian algoritma.</p></div></div>
<section class="card">
    <div class="table-wrap"><table><thead><tr><th>Iterasi</th><th>Hasil sementara</th><th>Pertukaran</th></tr></thead><tbody>
        <?php foreach ($sorting['trace'] as $step): ?><tr>
            <td>Iterasi <?= e($step['iteration']) ?></td>
            <td>
                <?php foreach ($step['snapshot_before'] as $pos => $bid): ?>
                    <span class="badge <?= ($step['swapped'] && ($pos === $step['swapped_from'] || $pos === $step['swapped_to'])) ? 'badge-warning' : '' ?>"><?= e(rupiah($bid['amount'])) ?></span>
                <?php endforeach; ?>
                <?php if ($step['swapped']): ?>
                    &nbsp;→&nbsp;
                    <?php foreach ($step['snapshot'] as $pos => $bid): ?>
                        <span class="badge <?= $pos === $step['swapped_to'] ? 'badge-success' : '' ?>"><?= e(rupiah($bid['amount'])) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
            <td><?= $step['swapped'] ? 'Tukar posisi ' . ($step['swapped_from'] + 1) . ' ↔ ' . ($step['swapped_to'] + 1) : 'Posisi sudah tepat' ?></td>
        </tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
<?php else: ?>
<div class="section-head"><div><h2>Status Lelang: Berlangsung (Sealed Bid)</h2><p>Nominal dan peringkat disembunyikan sampai lelang ditutup oleh Administrator. Seluruh penawaran akan diurutkan menggunakan Selection Sort di akhir periode.</p></div><span class="badge badge-info"><?= count($bids) ?> penawaran masuk</span></div>
<?php endif; ?>
<?php require __DIR__ . '/app/footer.php'; ?>
