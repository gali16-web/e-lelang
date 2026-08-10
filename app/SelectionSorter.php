<?php
declare(strict_types=1);

final class SelectionSorter
{
    /**
     * Mengurutkan penawaran dari nominal terbesar ke terkecil.
     * Jika nominal sama, penawaran yang masuk lebih dahulu didahulukan.
     *
     * @param array<int, array<string, mixed>> $bids
     * @return array<int, array<string, mixed>>
     */
    public static function descending(array $bids): array
    {
        return self::withTrace($bids)['sorted'];
    }

    /**
     * Menghasilkan data terurut beserta jejak setiap iterasi untuk kebutuhan
     * edukasi dan pengujian algoritma pada aplikasi.
     *
     * @param array<int, array<string, mixed>> $bids
     * @return array{sorted: array<int, array<string, mixed>>, trace: array<int, array<string, mixed>>}
     */
    public static function withTrace(array $bids): array
    {
        $data = array_values($bids);
        $trace = [];
        $count = count($data);

        for ($i = 0; $i < $count - 1; $i++) {
            $maxIndex = $i;

            for ($j = $i + 1; $j < $count; $j++) {
                if (self::shouldComeFirst($data[$j], $data[$maxIndex])) {
                    $maxIndex = $j;
                }
            }

            $swapped = $maxIndex !== $i;
            $snapshotBefore = array_values($data);
            if ($swapped) {
                [$data[$i], $data[$maxIndex]] = [$data[$maxIndex], $data[$i]];
            }

            $trace[] = [
                'iteration'      => $i + 1,
                'selected_index' => $maxIndex,
                'swapped'        => $swapped,
                'swapped_from'   => $maxIndex,
                'swapped_to'     => $i,
                'snapshot_before' => $snapshotBefore,
                'snapshot'       => array_values($data),
            ];
        }

        return ['sorted' => $data, 'trace' => $trace];
    }

    /** @param array<string, mixed> $candidate @param array<string, mixed> $current */
    private static function shouldComeFirst(array $candidate, array $current): bool
    {
        $candidateAmount = (int) ($candidate['amount'] ?? 0);
        $currentAmount = (int) ($current['amount'] ?? 0);

        if ($candidateAmount !== $currentAmount) {
            return $candidateAmount > $currentAmount;
        }

        $candidateTime = (string) ($candidate['created_at'] ?? '9999-12-31 23:59:59');
        $currentTime = (string) ($current['created_at'] ?? '9999-12-31 23:59:59');
        if ($candidateTime !== $currentTime) {
            return $candidateTime < $currentTime;
        }

        return (int) ($candidate['id'] ?? PHP_INT_MAX) < (int) ($current['id'] ?? PHP_INT_MAX);
    }
}
