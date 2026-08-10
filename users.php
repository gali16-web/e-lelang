<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($userId === (int) $admin['id']) {
        flash('warning', 'Status akun administrator yang sedang digunakan tidak dapat diubah.');
    } elseif (!in_array($status, ['active', 'rejected', 'suspended'], true)) {
        flash('danger', 'Status akun tidak valid.');
    } else {
        $statement = db()->prepare('UPDATE users SET status=?, approved_by=?, approved_at=IF(?="active", NOW(), approved_at) WHERE id=? AND role<>"admin"');
        $statement->execute([$status, $admin['id'], $status, $userId]);
        audit_log('update_user_status', 'user', $userId, $status);
        flash('success', 'Status pengguna berhasil diperbarui.');
    }
    redirect('users.php');
}

$users = db()->query('SELECT u.*, a.full_name AS approver_name FROM users u LEFT JOIN users a ON a.id=u.approved_by ORDER BY FIELD(u.status,"pending","active","suspended","rejected"), u.created_at DESC')->fetchAll();
$pageTitle = 'Pengguna';
$activePage = 'users';
require __DIR__ . '/app/header.php';
?>
<div class="section-head mt-0"><div><h2>Persetujuan dan pengelolaan akun</h2><p>Pastikan akun hanya diberikan kepada warga SMAN 12 Medan.</p></div></div>
<section class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Pengguna</th><th>Peran</th><th>Identitas</th><th>Status</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><strong><?= e($row['full_name']) ?></strong><br><small class="muted">@<?= e($row['username']) ?> · <?= e($row['email']) ?></small></td>
                <td><?= e(role_label($row['role'])) ?></td>
                <td><?= e($row['identity_number'] ?: '-') ?></td>
                <td><span class="badge <?= $row['status'] === 'active' ? 'badge-success' : ($row['status'] === 'pending' ? 'badge-warning' : 'badge-danger') ?>"><?= e(status_label($row['status'])) ?></span></td>
                <td><?= e(indo_datetime($row['created_at'])) ?></td>
                <td><div class="actions">
                    <?php if ($row['role'] !== 'admin'): ?>
                        <?php foreach (['active' => 'Aktifkan', 'rejected' => 'Tolak', 'suspended' => 'Tangguhkan'] as $status => $label): ?>
                            <?php if ($row['status'] !== $status): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($row['id']) ?>"><input type="hidden" name="status" value="<?= e($status) ?>"><button class="btn <?= $status === 'active' ? 'btn-primary' : 'btn-outline' ?> btn-sm" type="submit"><?= e($label) ?></button></form><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
