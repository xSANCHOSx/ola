<?php

declare(strict_types=1);

/**
 * Простий файловий логер.
 *
 * Лог: {DOCUMENT_ROOT}/log/order_debug.log
 * Ротація: новий файл щодня (order_debug_2026-05-11.log)
 * Рівні: DEBUG | INFO | WARN | ERROR
 */
class OlaLogger
{
    private static string $logDir = '';

    // ── Public API ────────────────────────────────────────────────────────────

    public static function debug(string $step, array $ctx = []): void
    {
        self::write('DEBUG', $step, $ctx);
    }

    public static function info(string $step, array $ctx = []): void
    {
        self::write('INFO', $step, $ctx);
    }

    public static function warn(string $step, array $ctx = []): void
    {
        self::write('WARN', $step, $ctx);
    }

    public static function error(string $step, array $ctx = []): void
    {
        self::write('ERROR', $step, $ctx);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function write(string $level, string $step, array $ctx): void
    {
        $dir  = self::getLogDir();
        $file = $dir . '/order_debug_' . date('Y-m-d') . '.log';

        $line = implode(' | ', [
            date('Y-m-d H:i:s'),
            str_pad($level, 5),
            $step,
            empty($ctx) ? '' : json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]) . PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function getLogDir(): string
    {
        if (self::$logDir !== '') {
            return self::$logDir;
        }

        $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
        $dir  = $root . '/log';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        self::$logDir = $dir;
        return $dir;
    }
}
