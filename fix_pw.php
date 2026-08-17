<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
$h = password_hash('admin123', PASSWORD_BCRYPT);
$pdo->query("UPDATE users SET password = '$h' WHERE role IN ('admin', 'super_admin')");
echo "Passwords updated successfully.\n";
