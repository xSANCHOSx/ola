<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pdo = dev_db_connection();
if (!$pdo) {
    exit('DB connection failed. Create config/local.php from config/local.example.php');
}

$schema = file_get_contents(__DIR__ . '/database/schema.sql');
if ($schema === false) {
    exit('schema.sql not found');
}
$pdo->exec($schema);

$counterRaw = trim((string)@file_get_contents(__DIR__ . '/counter.txt'));
$counter = ctype_digit($counterRaw) ? (int)$counterRaw : 0;
$stmt = $pdo->prepare('INSERT INTO order_sequence (id, current_value) VALUES (1, :value) ON DUPLICATE KEY UPDATE current_value = GREATEST(current_value, VALUES(current_value))');
$stmt->execute(['value' => $counter]);

$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($adminCount === 0) {
    $pass = bin2hex(random_bytes(6));
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
    $stmt->execute(['u' => 'admin', 'p' => $hash]);
    echo 'Setup complete. Admin: admin / ' . $pass;
} else {
    echo 'Setup complete.';
}
