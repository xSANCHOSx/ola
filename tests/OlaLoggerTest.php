<?php

use PHPUnit\Framework\TestCase;

class OlaLoggerTest extends TestCase
{
    private string $testLogDir;
    private string $originalDocRoot;

    protected function setUp(): void
    {
        // Створюємо тимчасову директорію для логів
        $this->testLogDir = sys_get_temp_dir() . '/ola_test_logs_' . uniqid();
        mkdir($this->testLogDir, 0755, true);

        // Зберігаємо оригінальний DOCUMENT_ROOT
        $this->originalDocRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $_SERVER['DOCUMENT_ROOT'] = $this->testLogDir;

        // Скидаємо статичний стан OlaLogger через рефлексію
        $reflection = new ReflectionClass(OlaLogger::class);
        $property = $reflection->getProperty('logDir');
        $property->setAccessible(true);
        $property->setValue(null, '');
    }

    protected function tearDown(): void
    {
        // Відновлюємо оригінальний DOCUMENT_ROOT
        $_SERVER['DOCUMENT_ROOT'] = $this->originalDocRoot;

        // Видаляємо тестову директорію
        if (is_dir($this->testLogDir)) {
            $this->removeDirectory($this->testLogDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testDebugWritesToLogFile(): void
    {
        OlaLogger::debug('TEST_STEP', ['key' => 'value']);

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('TEST_STEP', $content);
        $this->assertStringContainsString('"key":"value"', $content);
    }

    public function testInfoWritesToLogFile(): void
    {
        OlaLogger::info('INFO_STEP', ['status' => 'ok']);

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('INFO_STEP', $content);
        $this->assertStringContainsString('"status":"ok"', $content);
    }

    public function testWarnWritesToLogFile(): void
    {
        OlaLogger::warn('WARN_STEP', ['issue' => 'minor']);

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('WARN', $content);
        $this->assertStringContainsString('WARN_STEP', $content);
        $this->assertStringContainsString('"issue":"minor"', $content);
    }

    public function testErrorWritesToLogFile(): void
    {
        OlaLogger::error('ERROR_STEP', ['error' => 'critical']);

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('ERROR_STEP', $content);
        $this->assertStringContainsString('"error":"critical"', $content);
    }

    public function testLogDirectoryCreatedAutomatically(): void
    {
        $logDir = $this->testLogDir . '/log';
        $this->assertDirectoryDoesNotExist($logDir);

        OlaLogger::info('CREATE_DIR_TEST');

        $this->assertDirectoryExists($logDir);
    }

    public function testMultipleLogsAppendToSameFile(): void
    {
        OlaLogger::info('FIRST_LOG', ['num' => 1]);
        OlaLogger::info('SECOND_LOG', ['num' => 2]);
        OlaLogger::info('THIRD_LOG', ['num' => 3]);

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('FIRST_LOG', $content);
        $this->assertStringContainsString('SECOND_LOG', $content);
        $this->assertStringContainsString('THIRD_LOG', $content);
        $this->assertStringContainsString('"num":1', $content);
        $this->assertStringContainsString('"num":2', $content);
        $this->assertStringContainsString('"num":3', $content);
    }

    public function testLogWithEmptyContext(): void
    {
        OlaLogger::info('NO_CONTEXT');

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('NO_CONTEXT', $content);
        // Не повинно бути порожнього JSON об'єкта
        $lines = explode("\n", trim($content));
        $lastLine = end($lines);
        $this->assertStringNotContainsString('{}', $lastLine);
        $this->assertStringNotContainsString('[]', $lastLine);
    }

    public function testLogFormatContainsTimestamp(): void
    {
        OlaLogger::info('TIMESTAMP_TEST');

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        // Перевіряємо формат дати: YYYY-MM-DD HH:MM:SS
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            $content
        );
    }

    public function testLogLevelPadding(): void
    {
        OlaLogger::debug('PAD_TEST');
        OlaLogger::info('PAD_TEST');
        OlaLogger::warn('PAD_TEST');
        OlaLogger::error('PAD_TEST');

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        // Всі рівні повинні бути вирівняні до 5 символів
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('INFO ', $content);
        $this->assertStringContainsString('WARN ', $content);
        $this->assertStringContainsString('ERROR', $content);
    }

    public function testUnicodeCharactersInContext(): void
    {
        OlaLogger::info('UNICODE_TEST', [
            'name' => 'Тестовий користувач',
            'emoji' => '🎉',
            'special' => 'Спеціальні символи: №"\'',
        ]);

        $logFile = $this->testLogDir . '/log/order_debug_' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('Тестовий користувач', $content);
        $this->assertStringContainsString('🎉', $content);
        $this->assertStringContainsString('Спеціальні символи', $content);
    }
}
