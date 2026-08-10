<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_login();
$user = current_user();
$itemId = (int) ($_GET['id'] ?? 0);
$item = [
    'name' => '', 'brand' => '', 'category' => '', 'item_condition' => 'good',
    'description' => '', 'starting_price' => '', 'image' => null, 'status' => 'pending',
];

if ($itemId) {
    $statement = db()->prepare('SELECT * FROM items WHERE id=?');
    $statement->execute([$itemId]);
    $found = $statement->fetch();
    if (!$found || ((int) $found['owner_id'] !== (int) $user['id'] && !is_admin())) {
        http_response_code(404);
        exit('Barang tidak ditemukan.');
    }
    $item = $found;
    if (!in_array($item['status'], ['pending', 'rejected'], true) && !is_admin()) {
        flash('warning', 'Barang yang sudah disetujui tidak dapat diubah.');
        redirect('items.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $brand = trim((string) ($_POST['brand'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $condition = (string) ($_POST['item_condition'] ?? 'good');
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = filter_var($_POST['starting_price'] ?? null, FILTER_VALIDATE_INT);
    $errors = [];
    if (mb_strlen($name) < 3 || mb_strlen($description) < 3 || $category === '') $errors[] = 'Nama, kategori, dan deskripsi barang wajib dilengkapi (nama dan deskripsi minimal 3 karakter).';
    if (!$price || $price < 1000) $errors[] = 'Harga awal minimal Rp1.000 dan harus berupa angka.';
    if (!in_array($condition, ['new', 'like_new', 'good', 'fair'], true)) $errors[] = 'Kondisi barang tidak valid.';

    $image = $item['image'];
    if (!empty($_FILES['image']['name'])) {
        try { $image = store_image($_FILES['image'], 'items'); } catch (RuntimeException $exception) { $errors[] = $exception->getMessage(); }
    }
    if (!$image) $errors[] = 'Foto barang wajib diunggah.';

    if (!$errors) {
        if ($itemId) {
            $statement = db()->prepare('UPDATE items SET name=?, brand=?, category=?, item_condition=?, description=?, starting_price=?, image=?, status="pending", verification_note=NULL, verified_by=NULL, verified_at=NULL WHERE id=?');
            $statement->execute([$name, $brand ?: null, $category, $condition, $description, $price, $image, $itemId]);
            audit_log('update_item', 'item', $itemId);
            flash('success', 'Barang diperbarui dan dikirim kembali untuk diverifikasi.');
        } else {
            $statement = db()->prepare('INSERT INTO items (owner_id, name, brand, category, item_condition, description, image, starting_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")');
            $statement->execute([$user['id'], $name, $brand ?: null, $category, $condition, $description, $image, $price]);
            $newId = (int) db()->lastInsertId();
            audit_log('create_item', 'item', $newId);
            flash('success', 'Barang berhasil diajukan dan menunggu verifikasi administrator.');
        }
        redirect('items.php');
    }
    foreach ($errors as $error) flash('danger', $error);
    $item = array_merge($item, compact('name', 'brand', 'category', 'condition', 'description', 'price', 'image'));
    $item['item_condition'] = $condition;
    $item['starting_price'] = $price ?: '';
}

$pageTitle = $itemId ? 'Edit Barang' : 'Ajukan Barang';
$activePage = 'items';
require __DIR__ . '/app/header.php';
?>
<section class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <div class="field"><label>Nama barang <small class="muted">(minimal 3 karakter)</small></label><input name="name" value="<?= e($item['name']) ?>" minlength="3" required></div>
        <div class="field"><label>Merek</label><input name="brand" value="<?= e($item['brand']) ?>"></div>
        <div class="field"><label>Kategori</label><input name="category" value="<?= e($item['category']) ?>" placeholder="Buku, Elektronik, Perlengkapan sekolah" required></div>
        <div class="field"><label>Kondisi</label><select name="item_condition"><option value="new" <?= $item['item_condition'] === 'new' ? 'selected' : '' ?>>Baru</option><option value="like_new" <?= $item['item_condition'] === 'like_new' ? 'selected' : '' ?>>Seperti baru</option><option value="good" <?= $item['item_condition'] === 'good' ? 'selected' : '' ?>>Baik</option><option value="fair" <?= $item['item_condition'] === 'fair' ? 'selected' : '' ?>>Cukup</option></select></div>
        <div class="field"><label>Harga awal (rupiah)</label><input type="number" min="1000" step="1000" name="starting_price" value="<?= e($item['starting_price']) ?>" required></div>
        <div class="field"><label>Foto barang</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-input="#item-preview" <?= $item['image'] ? '' : 'required' ?>><small>Maksimal 2 MB.</small></div>
        <div class="field full"><img id="item-preview" src="<?= e($item['image'] ? url('uploads/items/' . $item['image']) : '') ?>" alt="Pratinjau" style="max-width:260px;border-radius:12px" <?= $item['image'] ? '' : 'hidden' ?>></div>
        <div class="field full"><label>Deskripsi dan kelengkapan barang <small class="muted">(minimal 3 karakter)</small></label><textarea name="description" minlength="3" required><?= e($item['description']) ?></textarea></div>
        <div class="field full"><div class="actions"><button class="btn btn-primary" type="submit">Simpan dan ajukan</button><a class="btn btn-outline" href="<?= e(url('items.php')) ?>">Batal</a></div></div>
    </form>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
