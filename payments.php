<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $statement = db()->prepare('SELECT * FROM payments WHERE id=?');
    $statement->execute([$paymentId]);
    $payment = $statement->fetch();
    $action = (string) ($_POST['action'] ?? '');

    if (!$payment) {
        flash('danger', 'Data pembayaran tidak ditemukan.');
    } elseif ($action === 'upload' && (int) $payment['payer_id'] === (int) $user['id']) {
        try {
            $image = store_image($_FILES['proof_image'] ?? [], 'payments');
            $update = db()->prepare('UPDATE payments SET proof_image=?, status="pending", paid_at=NOW(), verification_note=NULL WHERE id=?');
            $update->execute([$image, $paymentId]);
            audit_log('upload_payment_proof', 'payment', $paymentId);
            flash('success', 'Bukti pembayaran berhasil dikirim untuk diverifikasi.');
        } catch (RuntimeException $exception) {
            flash('danger', $exception->getMessage());
        }
    } elseif (in_array($action, ['verify', 'reject'], true) && is_admin()) {
        if (!$payment['proof_image']) {
            flash('warning', 'Bukti pembayaran belum tersedia.');
        } else {
            $status = $action === 'verify' ? 'verified' : 'rejected';
            db()->beginTransaction();
            $update = db()->prepare('UPDATE payments SET status=?, verification_note=?, verified_by=?, verified_at=NOW() WHERE id=?');
            $update->execute([$status, trim((string) ($_POST['note'] ?? '')) ?: null, $user['id'], $paymentId]);
            if ($status === 'verified') {
                db()->prepare('UPDATE distributions SET status="ready" WHERE auction_id=? AND status="pending"')->execute([$payment['auction_id']]);
            }
            db()->commit();
            audit_log($action . '_payment', 'payment', $paymentId);
            flash('success', 'Status pembayaran berhasil diperbarui.');
        }
    }
    redirect('payments.php');
}

$where = is_admin() ? '1=1' : '(p.payer_id=:uid1 OR p.payee_id=:uid2)';
$statement = db()->prepare(
    "SELECT p.*, a.winning_bid, i.name AS item_name, buyer.full_name AS buyer_name, seller.full_name AS seller_name,
    ba.bank_name, ba.account_number, ba.account_holder FROM payments p
    JOIN auctions a ON a.id=p.auction_id JOIN items i ON i.id=a.item_id
    JOIN users buyer ON buyer.id=p.payer_id JOIN users seller ON seller.id=p.payee_id
    LEFT JOIN bank_accounts ba ON ba.id=p.bank_account_id WHERE $where ORDER BY p.created_at DESC"
);
$statement->execute(is_admin() ? [] : ['uid1' => $user['id'], 'uid2' => $user['id']]);
$payments = $statement->fetchAll();

$pageTitle = 'Pembayaran dan Rekening';
$activePage = 'payments';
require __DIR__ . '/app/header.php';
?>
<div class="section-head mt-0"><div><h2>Transaksi hasil lelang</h2><p>Pemenang mengunggah bukti pembayaran ke rekening pemilik barang.</p></div></div>
<div class="grid">
<?php if (!$payments): ?><div class="card empty-state">Belum ada transaksi pembayaran.</div><?php endif; ?>
<?php foreach ($payments as $payment): ?>
    <section class="card">
        <div class="section-head mt-0"><div><span class="badge <?= $payment['status'] === 'verified' ? 'badge-success' : ($payment['status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>"><?= e(status_label($payment['status'])) ?></span><h2><?= e($payment['item_name']) ?></h2></div><div class="price"><?= e(rupiah($payment['amount'])) ?></div></div>
        <div class="grid grid-3">
            <div><small class="muted">Pemenang/Pembayar</small><p><strong><?= e($payment['buyer_name']) ?></strong></p></div>
            <div><small class="muted">Penjual/Penerima</small><p><strong><?= e($payment['seller_name']) ?></strong></p></div>
            <div><small class="muted">Rekening tujuan</small><p><strong><?= e($payment['bank_name'] ?: 'Belum tersedia') ?></strong><br><?= e($payment['account_number'] ?: '-') ?><br><?= e($payment['account_holder'] ?: '-') ?></p></div>
        </div>
        <?php if ($payment['verification_note']): ?><p class="alert alert-warning"><?= e($payment['verification_note']) ?></p><?php endif; ?>
        <div class="actions">
            <?php if ($payment['proof_image']): ?><a class="btn btn-outline btn-sm" target="_blank" href="<?= e(url('uploads/payments/' . $payment['proof_image'])) ?>">Lihat bukti pembayaran</a><?php endif; ?>
            <?php if ((int) $payment['payer_id'] === (int) $user['id'] && $payment['status'] !== 'verified'): ?>
                <form method="post" enctype="multipart/form-data" class="actions"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= e($payment['id']) ?>"><input type="hidden" name="action" value="upload"><input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-primary btn-sm" type="submit">Unggah bukti</button></form>
            <?php endif; ?>
            <?php if (is_admin() && $payment['proof_image'] && $payment['status'] !== 'verified'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= e($payment['id']) ?>"><input type="hidden" name="action" value="verify"><button class="btn btn-primary btn-sm" type="submit">Verifikasi</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= e($payment['id']) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="note" value="Bukti pembayaran perlu diperbaiki"><button class="btn btn-danger btn-sm" type="submit">Tolak</button></form>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
