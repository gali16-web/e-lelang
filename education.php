<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();

$questions = [
    1 => ['question' => 'Apa dasar yang tepat untuk menentukan harga awal barang?', 'options' => ['a' => 'Keinginan penjual saja', 'b' => 'Kondisi, manfaat, dan harga pasar', 'c' => 'Harga tertinggi tanpa perbandingan'], 'answer' => 'b'],
    2 => ['question' => 'Apa yang harus dilakukan sebelum memberikan penawaran?', 'options' => ['a' => 'Memahami kondisi barang dan kemampuan membayar', 'b' => 'Menawar semua barang', 'c' => 'Mengikuti nominal teman'], 'answer' => 'a'],
    3 => ['question' => 'Bagaimana Selection Sort menentukan urutan tertinggi?', 'options' => ['a' => 'Memilih data secara acak', 'b' => 'Membandingkan nilai dan menempatkan nilai terbesar di depan', 'c' => 'Menghapus nilai terendah'], 'answer' => 'b'],
    4 => ['question' => 'Perilaku yang sesuai etika lelang adalah ...', 'options' => ['a' => 'Menawar tanpa niat membeli', 'b' => 'Menggunakan akun orang lain', 'c' => 'Menawar sesuai kemampuan dan bertanggung jawab'], 'answer' => 'c'],
    5 => ['question' => 'Mengapa bukti pembayaran harus dijaga?', 'options' => ['a' => 'Karena memuat informasi transaksi pribadi', 'b' => 'Agar dapat disebarkan', 'c' => 'Karena tidak mempunyai fungsi'], 'answer' => 'a'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $answers = $_POST['answers'] ?? [];
    $correct = 0;
    foreach ($questions as $number => $question) {
        if (($answers[$number] ?? '') === $question['answer']) $correct++;
    }
    $score = $correct * 20;
    $statement = db()->prepare('INSERT INTO learning_results (user_id, score, answers) VALUES (?, ?, ?)');
    $statement->execute([current_user()['id'], $score, json_encode($answers, JSON_UNESCAPED_UNICODE)]);
    audit_log('complete_learning_quiz', 'learning_result', (int) db()->lastInsertId(), 'Skor ' . $score);
    flash($score >= 60 ? 'success' : 'warning', 'Kuis selesai. Skor Anda ' . $score . '/100.');
    redirect('education.php');
}

$resultStatement = db()->prepare('SELECT * FROM learning_results WHERE user_id=? ORDER BY created_at DESC LIMIT 5');
$resultStatement->execute([current_user()['id']]);
$results = $resultStatement->fetchAll();

$pageTitle = 'Edukasi Kewirausahaan Digital';
$activePage = 'education';
require __DIR__ . '/app/header.php';
?>
<div class="callout"><strong>Tujuan pembelajaran:</strong> pengguna memahami penilaian produk, mekanisme lelang, etika transaksi, keamanan digital, dan logika Selection Sort.</div>
<div class="grid grid-2 mt-2">
    <section class="card learning-module"><span class="number">1</span><h2>Produk dan penentuan harga</h2><p>Harga awal mempertimbangkan kondisi, usia pemakaian, kelengkapan, manfaat, dan harga barang sejenis. Deskripsi harus jujur agar pembeli dapat mengambil keputusan secara rasional.</p></section>
    <section class="card learning-module"><span class="number">2</span><h2>Etika lelang digital</h2><p>Penawar wajib memiliki niat membeli, tidak menggunakan identitas orang lain, tidak memanipulasi harga, dan memenuhi pembayaran apabila menjadi pemenang.</p></section>
    <section class="card learning-module"><span class="number">3</span><h2>Keamanan transaksi</h2><p>Jaga kata sandi, periksa identitas barang dan penjual, jangan menyebarkan nomor rekening atau bukti pembayaran, serta selesaikan distribusi di tempat yang disepakati.</p></section>
    <section class="card learning-module"><span class="number">4</span><h2>Cara kerja Selection Sort</h2><p>Sistem mencari nominal terbesar dari kumpulan penawaran, menukarnya ke posisi pertama, lalu mengulangi proses pada bagian data yang tersisa. Hasil akhirnya adalah urutan descending dan data pertama menjadi pemenang.</p><div class="callout"><strong>Contoh:</strong> 110.000, 175.000, 125.000, 150.000 → 175.000, 150.000, 125.000, 110.000.</div></section>
</div>

<div class="section-head"><div><h2>Kuis pemahaman</h2><p>Jawab seluruh pertanyaan untuk mengukur pemahaman awal.</p></div></div>
<section class="card">
    <form method="post">
        <?= csrf_field() ?>
        <?php foreach ($questions as $number => $question): ?>
            <fieldset style="border:0;padding:0;margin:0 0 24px"><legend><strong><?= $number ?>. <?= e($question['question']) ?></strong></legend><div class="grid" style="gap:8px;margin-top:10px"><?php foreach ($question['options'] as $key => $option): ?><label class="quiz-option"><input type="radio" name="answers[<?= $number ?>]" value="<?= e($key) ?>" required><span><?= e($option) ?></span></label><?php endforeach; ?></div></fieldset>
        <?php endforeach; ?>
        <button class="btn btn-primary" type="submit">Kirim jawaban</button>
    </form>
</section>

<?php if ($results): ?><div class="section-head"><div><h2>Riwayat hasil belajar</h2></div></div><section class="card"><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Skor</th><th>Kategori</th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><?= e(indo_datetime($result['created_at'])) ?></td><td><strong><?= e($result['score']) ?>/100</strong></td><td><?= $result['score'] >= 80 ? 'Sangat Baik' : ($result['score'] >= 60 ? 'Baik' : 'Perlu Belajar Kembali') ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<?php require __DIR__ . '/app/footer.php'; ?>
