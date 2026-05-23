<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$migrationFile = $argv[1] ?? 'migration_coupons_archive.sql';
$filePath = __DIR__ . '/' . $migrationFile;

if (!file_exists($filePath)) {
    echo "❌ Файл міграції не знайдено: $filePath\n";
    exit(1);
}

try {
    $pdo = dev_db_connection();
    $sql = file_get_contents($filePath);

    // Розділяємо SQL на окремі запити (розділювач ;)
    $queries = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($q) => !empty($q) && !str_starts_with($q, '--')
    );

    echo "📋 Застосування міграції: $migrationFile\n";
    echo "📊 Знайдено " . count($queries) . " запитів\n\n";

    foreach ($queries as $i => $query) {
        try {
            $pdo->exec($query);
            echo "✅ Запит " . ($i + 1) . " виконано успішно\n";
        } catch (PDOException $e) {
            echo "⚠️  Запит " . ($i + 1) . " помилка: " . $e->getMessage() . "\n";
            // Продовжуємо, так як деякі запити можуть бути ідемпотентні (IF NOT EXISTS)
        }
    }

    echo "\n✨ Міграція завершена!\n";
    exit(0);

} catch (Throwable $e) {
    echo "❌ Помилка: " . $e->getMessage() . "\n";
    exit(1);
}
