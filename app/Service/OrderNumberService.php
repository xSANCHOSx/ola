<?php

declare(strict_types=1);

class OrderNumberService
{
    /**
     * Намагається отримати номер з БД, при невдачі — з файлу.
     * Повертає null лише якщо обидва способи впали.
     */
    public function getNext(?PDO $pdo, string $counterFile): ?int
    {
        if ($pdo instanceof PDO) {
            try {
                return $this->nextFromDb($pdo, $counterFile);
            } catch (Throwable $e) {
                dev_log_runtime('Order number from DB failed: ' . $e->getMessage());
            }
        }

        try {
            return $this->nextFallback($counterFile);
        } catch (Throwable $e) {
            dev_log_runtime('Order number fallback failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Атомарний SELECT ... FOR UPDATE + UPDATE в транзакції.
     */
    private function nextFromDb(PDO $pdo, string $counterFile): int
    {
        $pdo->beginTransaction();

        try {
            $row = $pdo->query(
                'SELECT current_value FROM order_sequence WHERE id = 1 FOR UPDATE'
            )->fetch();

            if (!$row) {
                $seed = $this->readFileCounter($counterFile);
                $stmt = $pdo->prepare(
                    'INSERT INTO order_sequence (id, current_value) VALUES (1, :seed)'
                );
                $stmt->execute(['seed' => $seed]);
                $current = $seed;
            } else {
                $current = (int) $row['current_value'];
            }

            $next = $current + 1;
            $stmt = $pdo->prepare(
                'UPDATE order_sequence SET current_value = :next WHERE id = 1'
            );
            $stmt->execute(['next' => $next]);
            $pdo->commit();

            return $next;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Файловий лічильник із flock — захист від race condition.
     */
    private function nextFallback(string $counterFile): int
    {
        $fp = fopen($counterFile, 'c+');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open counter file: $counterFile");
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new \RuntimeException("Cannot acquire lock on counter file: $counterFile");
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
        if (!file_exists($counterFile)) {
            return 0;
        }
        $raw = trim((string) @file_get_contents($counterFile));

        return ctype_digit($raw) ? (int) $raw : 0;
    }
}
