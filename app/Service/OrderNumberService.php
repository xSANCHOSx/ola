<?php

declare(strict_types=1);

class OrderNumberService
{
    public function getNext(?PDO $pdo, string $counterFile): ?int
    {
        if ($pdo instanceof PDO) {
            try {
                $n = $this->nextFromDb($pdo, $counterFile);
                OlaLogger::info('ORDER_NUM_FROM_DB', ['number' => $n]);
                return $n;
            } catch (Throwable $e) {
                OlaLogger::error('ORDER_NUM_DB_FAIL', ['msg' => $e->getMessage()]);
                dev_log_runtime('Order number from DB failed: ' . $e->getMessage());
            }
        }

        OlaLogger::warn('ORDER_NUM_FALLBACK', ['counter_file' => $counterFile]);
        try {
            $n = $this->nextFallback($counterFile);
            OlaLogger::info('ORDER_NUM_FROM_FILE', ['number' => $n]);
            return $n;
        } catch (Throwable $e) {
            OlaLogger::error('ORDER_NUM_FILE_FAIL', ['msg' => $e->getMessage(), 'file' => $counterFile]);
            dev_log_runtime('Order number fallback failed: ' . $e->getMessage());
        }

        return null;
    }

    private function nextFromDb(PDO $pdo, string $counterFile): int
    {
        $pdo->beginTransaction();
        try {
            $row = $pdo->query('SELECT current_value FROM order_sequence WHERE id = 1 FOR UPDATE')->fetch();

            if (!$row) {
                $seed = $this->readFileCounter($counterFile);
                OlaLogger::warn('ORDER_SEQ_MISSING', ['seed_from_file' => $seed]);
                $stmt = $pdo->prepare('INSERT INTO order_sequence (id, current_value) VALUES (1, :seed)');
                $stmt->execute(['seed' => $seed]);
                $current = $seed;
            } else {
                $current = (int) $row['current_value'];
            }

            $next = $current + 1;
            $pdo->prepare('UPDATE order_sequence SET current_value = :next WHERE id = 1')
                ->execute(['next' => $next]);
            $pdo->commit();

            return $next;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function nextFallback(string $counterFile): int
    {
        $fp = fopen($counterFile, 'c+');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open counter file: $counterFile");
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException("Cannot acquire lock on: $counterFile");
        }
        try {
            $raw     = trim((string) fread($fp, 20));
            $counter = ctype_digit($raw) && $raw !== '' ? (int) $raw : 0;
            $counter++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) $counter);
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $counter;
    }

    private function readFileCounter(string $counterFile): int
    {
        if (!file_exists($counterFile)) return 0;
        $raw = trim((string) @file_get_contents($counterFile));
        return ctype_digit($raw) ? (int) $raw : 0;
    }
}
