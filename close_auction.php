<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('auctions.php');
verify_csrf();
$auctionId = (int) ($_POST['auction_id'] ?? 0);

try {
    db()->beginTransaction();
    $statement = db()->prepare('SELECT a.*, i.owner_id, i.id AS item_id FROM auctions a JOIN items i ON i.id=a.item_id WHERE a.id=? FOR UPDATE');
    $statement->execute([$auctionId]);
    $auction = $statement->fetch();
    if (!$auction || $auction['status'] !== 'open') throw new RuntimeException('Lelang tidak dapat ditutup.');

    $bidStatement = db()->prepare('SELECT b.*, u.full_name, u.username FROM bids b JOIN users u ON u.id=b.user_id WHERE b.auction_id=?');
    $bidStatement->execute([$auctionId]);
    $result = SelectionSorter::withTrace($bidStatement->fetchAll());
    $winner = $result['sorted'][0] ?? null;
    $snapshot = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

    $update = db()->prepare('UPDATE auctions SET status="closed", winner_id=?, winning_bid=?, selection_sort_snapshot=?, closed_by=?, closed_at=NOW() WHERE id=?');
    $update->execute([$winner['user_id'] ?? null, $winner['amount'] ?? null, $snapshot, current_user()['id'], $auctionId]);

    if ($winner) {
        db()->prepare('UPDATE items SET status="sold" WHERE id=?')->execute([$auction['item_id']]);
        $bankStatement = db()->prepare('SELECT id FROM bank_accounts WHERE user_id=? ORDER BY is_primary DESC, id ASC LIMIT 1');
        $bankStatement->execute([$auction['owner_id']]);
        $bankId = $bankStatement->fetchColumn() ?: null;
        $payment = db()->prepare('INSERT INTO payments (auction_id, payer_id, payee_id, bank_account_id, amount, status) VALUES (?, ?, ?, ?, ?, "pending")');
        $payment->execute([$auctionId, $winner['user_id'], $auction['owner_id'], $bankId, $winner['amount']]);
        $distribution = db()->prepare('INSERT INTO distributions (auction_id, seller_id, buyer_id, status) VALUES (?, ?, ?, "pending")');
        $distribution->execute([$auctionId, $auction['owner_id'], $winner['user_id']]);
    } else {
        db()->prepare('UPDATE items SET status="approved" WHERE id=?')->execute([$auction['item_id']]);
    }
    db()->commit();
    audit_log('close_auction_selection_sort', 'auction', $auctionId, $winner ? 'Pemenang: ' . $winner['username'] . ', ' . rupiah($winner['amount']) : 'Tanpa penawaran');
    flash('success', $winner ? 'Lelang ditutup. Selection Sort menetapkan ' . $winner['full_name'] . ' sebagai pemenang.' : 'Lelang ditutup tanpa pemenang karena tidak ada penawaran.');
} catch (Throwable $exception) {
    if (db()->inTransaction()) db()->rollBack();
    flash('danger', $exception->getMessage());
}
redirect('auction_detail.php?id=' . $auctionId);
