<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/SelectionSorter.php';

$data = [
    ['id' => 1, 'amount' => 1100000, 'created_at' => '2026-08-01 09:00:00'],
    ['id' => 2, 'amount' => 1750000, 'created_at' => '2026-08-01 09:05:00'],
    ['id' => 3, 'amount' => 1250000, 'created_at' => '2026-08-01 09:10:00'],
    ['id' => 4, 'amount' => 1500000, 'created_at' => '2026-08-01 09:15:00'],
];

$result = SelectionSorter::withTrace($data);
$actual = array_column($result['sorted'], 'amount');
$expected = [1750000, 1500000, 1250000, 1100000];

if ($actual !== $expected) {
    fwrite(STDERR, 'GAGAL: ' . json_encode($actual) . PHP_EOL);
    exit(1);
}

echo 'BERHASIL: Selection Sort menghasilkan urutan descending yang benar.' . PHP_EOL;
echo 'Hasil: ' . implode(', ', $actual) . PHP_EOL;
echo 'Jumlah iterasi: ' . count($result['trace']) . PHP_EOL;
