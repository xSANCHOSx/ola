<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
admin_session_clear();
header('Location: /admin/login.php');
exit;
