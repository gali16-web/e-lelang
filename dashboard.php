<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();

$user = current_user();
if (is_admin()) {
    $stats = [
        'Pengguna aktif' => (int) db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
        'Menunggu persetujuan' => (int) db()->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn(),
        'Barang menunggu' => (int) db()->query("SELECT COUNT(*) FROM items WHERE status='pending'")->fetchColumn(),
        'Lelang aktif' => (int) db()->query("SELECT COUNT(*) FROM auctions WHERE status='open' AND NOW() BETWEEN start_at AND end_at")->fetchColumn(),
    ];
} else {
    $statements = [];
    $queries = [
        'Barang saya' => "SELECT COUNT(*) FROM items WHERE owner_id=?",
        'Lelang diikuti' => "SELECT COUNT(DISTINCT auction_id) FROM bids WHERE user_id=?",
        'Lelang dimenangkan' => "SELECT COUNT(*) FROM auctions WHERE winner_id=?",
        'Skor edukasi terbaik' => "SELECT COALESCE(MAX(score),0) FROM learning_results WHERE user_id=?",
    ];
    $stats = [];
    foreach ($queries as $label => $query) {
        $statement = db()->prepare($query);
        $statement->execute([$user['id']]);
        $stats[$label] = (int) $statement->fetchColumn();
    }
}

$statement = db()->query(
    "SELECT a.*, i.name, i.image, i.starting_price, u.full_name AS owner_name,
    (SELECT MAX(amount) FROM bids WHERE auction_id=a.id) AS highest_bid,
    (SELECT COUNT(*) FROM bids WHERE auction_id=a.id) AS bid_count
    FROM auctions a JOIN items i ON i.id=a.item_id JOIN users u ON u.id=i.owner_id
    WHERE a.status='open' ORDER BY a.start_at DESC LIMIT 6"
);
$auctions = $statement->fetchAll();

$pageTitle = 'Beranda';
$activePage = 'dashboard';
require __DIR__ . '/app/header.php';
?>
<div class="callout">
    <strong>Halo, <?= e($user['full_name']) ?>.</strong>
    <?= is_admin() ? 'Pantau aktivitas sistem dan selesaikan verifikasi yang masih menunggu.' : 'Gunakan aplikasi secara jujur, bertanggung jawab, dan sesuai etika transaksi digital.' ?>
</div>

<div class="grid grid-4 mt-2">
    <?php foreach ($stats as $label => $value): ?>
        <div class="card stat-card"><div><small><?= e($label) ?></small><strong><?= e($value) ?><?= str_contains($label, 'Skor') ? '/100' : '' ?></strong></div></div>
    <?php endforeach; ?>
</div>

<div class="section-head"><div><h2>Lelang terbaru</h2><p>Barang yang sedang atau akan dilelang.</p></div><a class="btn btn-outline" href="<?= e(url('auctions.php')) ?>">Lihat semua</a></div>
<div class="grid grid-2">
    <?php if (!$auctions): ?><div class="card empty-state">Belum ada jadwal lelang aktif.</div><?php endif; ?>
    <?php foreach ($auctions as $auction): ?>
        <?php $status = effective_auction_status($auction); ?>
        <article class="card auction-card">
            <img src="<?= e($auction['image'] ? url('uploads/items/' . $auction['image']) : url('assets/img/logo-sman12.png')) ?>" alt="<?= e($auction['name']) ?>">
            <div class="auction-card-content">
                <span class="badge <?= $status === 'open' ? 'badge-success' : 'badge-info' ?>"><?= e(status_label($status)) ?></span>
                <h3><?= e($auction['name']) ?></h3>
                <div class="price"><?= e(rupiah($auction['highest_bid'] ?: $auction['starting_price'])) ?></div>
                <div class="auction-meta"><span><?= e($auction['bid_count']) ?> penawaran</span><span>Pemilik: <?= e($auction['owner_name']) ?></span></div>
                <div class="actions mt-2"><a class="btn btn-primary btn-sm" href="<?= e(url('auction_detail.php?id=' . $auction['id'])) ?>">Lihat lelang</a></div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<div class="section-head"><div><h2>Belajar sebelum bertransaksi</h2><p>Pahami dasar harga, etika, dan keamanan transaksi digital.</p></div></div>
<div class="grid grid-3">
    <div class="card learning-module"><span class="number">1</span><h3>Menilai produk</h3><p class="muted">Kenali kondisi barang, manfaat, biaya, dan harga pasar sebelum menentukan harga awal.</p></div>
    <div class="card learning-module"><span class="number">2</span><h3>Menawar secara etis</h3><p class="muted">Gunakan kemampuan finansial sendiri dan hindari menaikkan harga tanpa niat membeli.</p></div>
    <div class="card learning-module"><span class="number">3</span><h3>Selection Sort</h3><p class="muted">Pelajari bagaimana sistem membandingkan nominal untuk menyusun penawaran tertinggi.</p><a class="btn btn-secondary btn-sm" href="<?= e(url('education.php')) ?>">Mulai belajar</a></div>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
