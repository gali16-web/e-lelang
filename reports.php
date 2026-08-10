<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_admin();

$report = (string) ($_GET['type'] ?? 'auctions');
$definitions = [
    'users' => [
        'title' => 'Laporan Pengguna',
        'columns' => ['full_name' => 'Nama', 'username' => 'Username', 'role' => 'Peran', 'status' => 'Status', 'created_at' => 'Tanggal Daftar'],
        'sql' => 'SELECT full_name, username, role, status, created_at FROM users ORDER BY created_at DESC',
    ],
    'items' => [
        'title' => 'Laporan Barang',
        'columns' => ['name' => 'Barang', 'owner_name' => 'Pemilik', 'category' => 'Kategori', 'starting_price' => 'Harga Awal', 'status' => 'Status'],
        'sql' => 'SELECT i.name, u.full_name AS owner_name, i.category, i.starting_price, i.status FROM items i JOIN users u ON u.id=i.owner_id ORDER BY i.created_at DESC',
    ],
    'auctions' => [
        'title' => 'Laporan Hasil Lelang',
        'columns' => ['item_name' => 'Barang', 'start_at' => 'Mulai', 'end_at' => 'Selesai', 'winner_name' => 'Pemenang', 'winning_bid' => 'Harga Akhir', 'status' => 'Status'],
        'sql' => 'SELECT i.name AS item_name, a.start_at, a.end_at, u.full_name AS winner_name, a.winning_bid, a.status FROM auctions a JOIN items i ON i.id=a.item_id LEFT JOIN users u ON u.id=a.winner_id ORDER BY a.created_at DESC',
    ],
    'bids' => [
        'title' => 'Laporan Penawaran',
        'columns' => ['item_name' => 'Barang', 'bidder_name' => 'Penawar', 'amount' => 'Nominal', 'created_at' => 'Waktu'],
        'sql' => 'SELECT i.name AS item_name, u.full_name AS bidder_name, b.amount, b.created_at FROM bids b JOIN auctions a ON a.id=b.auction_id JOIN items i ON i.id=a.item_id JOIN users u ON u.id=b.user_id ORDER BY b.created_at DESC',
    ],
    'payments' => [
        'title' => 'Laporan Pembayaran dan Rekening',
        'columns' => ['item_name' => 'Barang', 'payer_name' => 'Pembayar', 'payee_name' => 'Penerima', 'amount' => 'Nominal', 'bank_name' => 'Bank', 'account_number' => 'Rekening', 'status' => 'Status'],
        'sql' => 'SELECT i.name AS item_name, buyer.full_name AS payer_name, seller.full_name AS payee_name, p.amount, ba.bank_name, ba.account_number, p.status FROM payments p JOIN auctions a ON a.id=p.auction_id JOIN items i ON i.id=a.item_id JOIN users buyer ON buyer.id=p.payer_id JOIN users seller ON seller.id=p.payee_id LEFT JOIN bank_accounts ba ON ba.id=p.bank_account_id ORDER BY p.created_at DESC',
    ],
    'distributions' => [
        'title' => 'Laporan Distribusi',
        'columns' => ['item_name' => 'Barang', 'seller_name' => 'Penjual', 'buyer_name' => 'Pembeli', 'meeting_location' => 'Lokasi', 'scheduled_at' => 'Jadwal', 'status' => 'Status'],
        'sql' => 'SELECT i.name AS item_name, seller.full_name AS seller_name, buyer.full_name AS buyer_name, d.meeting_location, d.scheduled_at, d.status FROM distributions d JOIN auctions a ON a.id=d.auction_id JOIN items i ON i.id=a.item_id JOIN users seller ON seller.id=d.seller_id JOIN users buyer ON buyer.id=d.buyer_id ORDER BY d.created_at DESC',
    ],
    'learning' => [
        'title' => 'Laporan Edukasi Kewirausahaan Digital',
        'columns' => ['full_name' => 'Pengguna', 'role' => 'Peran', 'score' => 'Skor', 'created_at' => 'Tanggal'],
        'sql' => 'SELECT u.full_name, u.role, l.score, l.created_at FROM learning_results l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC',
    ],
];

if (!isset($definitions[$report])) $report = 'auctions';
$definition = $definitions[$report];
$rows = db()->query($definition['sql'])->fetchAll();

function report_value(string $key, mixed $value): string
{
    if ($value === null || $value === '') return '-';
    if (in_array($key, ['starting_price', 'winning_bid', 'amount'], true)) return rupiah($value);
    if (in_array($key, ['created_at', 'start_at', 'end_at', 'scheduled_at'], true)) return indo_datetime((string) $value);
    if ($key === 'role') return role_label((string) $value);
    if ($key === 'status') return status_label((string) $value);
    return (string) $value;
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $report . '-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array_values($definition['columns']), ';', '"', '\\');
    foreach ($rows as $row) {
        $line = [];
        foreach ($definition['columns'] as $key => $label) $line[] = report_value($key, $row[$key] ?? null);
        fputcsv($output, $line, ';', '"', '\\');
    }
    fclose($output);
    exit;
}

$summary = [
    'Pengguna aktif' => (int) db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
    'Barang terdaftar' => (int) db()->query('SELECT COUNT(*) FROM items')->fetchColumn(),
    'Lelang selesai' => (int) db()->query("SELECT COUNT(*) FROM auctions WHERE status='closed'")->fetchColumn(),
    'Nilai transaksi' => (float) db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'")->fetchColumn(),
];

$pageTitle = $definition['title'];
$activePage = 'reports';
require __DIR__ . '/app/header.php';
?>
<div class="grid grid-4 no-print">
    <?php foreach ($summary as $label => $value): ?><div class="card stat-card"><div><small><?= e($label) ?></small><strong><?= str_contains($label, 'Nilai') ? e(rupiah($value)) : e($value) ?></strong></div></div><?php endforeach; ?>
</div>
<div class="section-head"><div><h2><?= e($definition['title']) ?></h2><p>Dicetak pada <?= e(indo_datetime(date('Y-m-d H:i:s'))) ?></p></div><div class="actions no-print"><button class="btn btn-outline" onclick="window.print()">Cetak</button><a class="btn btn-primary" href="<?= e(url('reports.php?type=' . $report . '&export=csv')) ?>">Unduh CSV</a></div></div>
<div class="actions no-print" style="margin-bottom:18px">
    <?php foreach ($definitions as $key => $item): ?><a class="btn <?= $report === $key ? 'btn-primary' : 'btn-outline' ?> btn-sm" href="<?= e(url('reports.php?type=' . $key)) ?>"><?= e(str_replace('Laporan ', '', $item['title'])) ?></a><?php endforeach; ?>
</div>
<section class="card">
    <div class="table-wrap"><table><thead><tr><th>No.</th><?php foreach ($definition['columns'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="<?= count($definition['columns']) + 1 ?>" class="empty-state">Belum ada data pada laporan ini.</td></tr><?php endif; ?>
        <?php foreach ($rows as $index => $row): ?><tr><td><?= $index + 1 ?></td><?php foreach ($definition['columns'] as $key => $label): ?><td><?= e(report_value($key, $row[$key] ?? null)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
