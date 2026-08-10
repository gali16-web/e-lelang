<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ELELANG_SMAN12');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/SelectionSorter.php';
require_once __DIR__ . '/functions.php';

try {
    $db = $config['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $exception) {
    http_response_code(500);
    $safeMessage = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    exit('<!doctype html><html lang="id"><meta charset="utf-8"><title>Koneksi gagal</title><style>body{font:16px Arial;background:#f5f7fb;padding:40px}.box{max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:16px;box-shadow:0 10px 30px #0001}code{background:#eef2f7;padding:3px 7px;border-radius:5px}</style><div class="box"><h1>Koneksi database belum siap</h1><p>Impor <code>database/elelang_sman12.sql</code>, lalu periksa pengaturan pada <code>config.php</code>.</p><details><summary>Detail teknis</summary><p>' . $safeMessage . '</p></details></div></html>');
}
