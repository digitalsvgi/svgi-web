<?php
// scratch/clear_test_data.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

echo "Clearing test data..." . PHP_EOL;

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    $pdo->exec("TRUNCATE TABLE `submissions`;");
    $pdo->exec("TRUNCATE TABLE `submission_attachments`;");
    $pdo->exec("TRUNCATE TABLE `submission_files`;");
    $pdo->exec("TRUNCATE TABLE `submission_images`;");
    $pdo->exec("TRUNCATE TABLE `submission_status_history`;");
    $pdo->exec("TRUNCATE TABLE `submission_edit_history`;");
    $pdo->exec("TRUNCATE TABLE `messages`;");
    $pdo->exec("TRUNCATE TABLE `notifications`;");
    $pdo->exec("TRUNCATE TABLE `activity_logs`;");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "[SUCCESS] All test submissions, attachments, files, messages, notifications, and activity logs have been successfully cleared!" . PHP_EOL;
} catch (Exception $e) {
    echo "[ERROR] Failed to clear test data: " . $e->getMessage() . PHP_EOL;
}
