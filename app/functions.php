<?php
declare(strict_types=1);

function db(): PDO
{
    global $pdo;
    return $pdo;
}

/** @return array<string, mixed> */
function config(): array
{
    global $config;
    return $config;
}

function base_url(): string
{
    $configured = trim((string) (config()['base_url'] ?? ''));
    if ($configured !== '') {
        return '/' . trim($configured, '/');
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $directory = str_replace('\\', '/', dirname($script));
    $directory = rtrim($directory, '/.');
    return $directory === '' ? '' : $directory;
}

function url(string $path = ''): string
{
    return base_url() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(int|float|string|null $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function indo_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }
    return date('d-m-Y H:i', strtotime($value)) . ' WIB';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type: string, message: string}> */
function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan kembali dan coba lagi.');
    }
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** @return array<string, mixed>|null */
function current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;

    if (!is_logged_in()) {
        return null;
    }
    $statement = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $statement->execute([(int) $_SESSION['user_id']]);
    $user = $statement->fetch() ?: null;
    return $user;
}

function require_login(): void
{
    if (!is_logged_in() || current_user() === null) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect('index.php');
    }
    if (current_user()['status'] !== 'active') {
        unset($_SESSION['user_id']);
        flash('warning', 'Akun Anda belum aktif. Hubungi administrator.');
        redirect('index.php');
    }
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Akses hanya tersedia untuk administrator.');
    }
}

function role_label(string $role): string
{
    return match ($role) {
        'student' => 'Siswa',
        'teacher' => 'Guru',
        'staff' => 'Staf',
        'admin' => 'Administrator',
        default => ucfirst($role),
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Menunggu',
        'active' => 'Aktif',
        'approved' => 'Disetujui',
        'open' => 'Berlangsung',
        'verified' => 'Terverifikasi',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
        'suspended' => 'Ditangguhkan',
        'draft' => 'Draf',
        'closed' => 'Ditutup',
        'cancelled' => 'Dibatalkan',
        'scheduled' => 'Akan Datang',
        'ended' => 'Waktu Berakhir',
        'auctioned' => 'Dijadwalkan',
        'sold' => 'Terjual',
        'ready' => 'Siap Diserahkan',
        default => ucfirst($status),
    };
}

/** @param array<string, mixed> $auction */
function effective_auction_status(array $auction): string
{
    if (in_array($auction['status'], ['closed', 'cancelled'], true)) {
        return $auction['status'];
    }
    $now = time();
    if ($now < strtotime((string) $auction['start_at'])) {
        return 'scheduled';
    }
    if ($now > strtotime((string) $auction['end_at'])) {
        return 'ended';
    }
    return 'open';
}

/** @return array<int, array<string, mixed>> */
function auction_bids(int $auctionId): array
{
    $statement = db()->prepare(
        'SELECT b.*, u.full_name, u.username FROM bids b JOIN users u ON u.id = b.user_id WHERE b.auction_id = ?'
    );
    $statement->execute([$auctionId]);
    return SelectionSorter::descending($statement->fetchAll());
}

/** @param array<string, mixed> $file */
function store_image(array $file, string $folder): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Berkas gambar belum dipilih atau gagal diunggah.');
    }

    $uploads = config()['uploads'];
    if ((int) $file['size'] > (int) $uploads['max_size']) {
        throw new RuntimeException('Ukuran gambar maksimal 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $uploads['allowed_mime'], true)) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => throw new RuntimeException('Format gambar tidak didukung.'),
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetDir = dirname(__DIR__) . '/uploads/' . trim($folder, '/');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Folder unggahan tidak dapat dibuat.');
    }
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $filename)) {
        throw new RuntimeException('Gambar gagal disimpan.');
    }
    return $filename;
}

function audit_log(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    $statement = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([
        current_user()['id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
