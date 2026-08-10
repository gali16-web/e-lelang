<?php
declare(strict_types=1);

return [
    'app_name' => 'E-Lelang SMAN 12 Medan',
    'school_name' => 'SMAN 12 Medan',
    'timezone' => 'Asia/Jakarta',
    'base_url' => '',
    'database' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'elelang_sman12',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'max_size' => 2 * 1024 * 1024,
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
