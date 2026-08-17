<?php
// config/db.php

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'cwm_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // Dynamic Schema Helper - Auto-inject missing columns
     $pdo->exec("ALTER TABLE `colleges` ADD COLUMN IF NOT EXISTS `email` VARCHAR(191) NULL");
     $pdo->exec("ALTER TABLE `colleges` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(50) NULL");
     $pdo->exec("ALTER TABLE `colleges` ADD COLUMN IF NOT EXISTS `address` TEXT NULL");
     $pdo->exec("ALTER TABLE `colleges` ADD COLUMN IF NOT EXISTS `logo` VARCHAR(255) NULL");
     
     $pdo->exec("ALTER TABLE `departments` ADD COLUMN IF NOT EXISTS `code` VARCHAR(50) NULL");
     
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `update_url` VARCHAR(255) NULL");
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `processing_url` VARCHAR(255) NULL");
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `completed_url` VARCHAR(255) NULL");
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal'");
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `processing_notes` TEXT NULL");
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `completion_notes` TEXT NULL");
     $pdo->exec("ALTER TABLE `submissions` ADD COLUMN IF NOT EXISTS `edit_count` INT DEFAULT 0");
     
     $pdo->exec("
         CREATE TABLE IF NOT EXISTS `submission_edit_history` (
             `id` INT AUTO_INCREMENT PRIMARY KEY,
             `submission_id` INT NOT NULL,
             `title` VARCHAR(255) NOT NULL,
             `description` TEXT NULL,
             `priority` VARCHAR(50) NOT NULL,
             `update_url` VARCHAR(255) NULL,
             `edited_by` INT NOT NULL,
             `edited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE,
             FOREIGN KEY (`edited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
         ) ENGINE=InnoDB;
     ");
     
     $pdo->exec("ALTER TABLE `submission_attachments` ADD COLUMN IF NOT EXISTS `google_drive_url` TEXT NULL");
     
     $pdo->exec("
         CREATE TABLE IF NOT EXISTS `activity_logs` (
             `id` INT AUTO_INCREMENT PRIMARY KEY,
             `user_id` INT NULL,
             `user_name` VARCHAR(255) NOT NULL,
             `action` VARCHAR(255) NOT NULL,
             `task_id` VARCHAR(50) NULL,
             `old_value` VARCHAR(255) NULL,
             `new_value` VARCHAR(255) NULL,
             `ip_address` VARCHAR(45) NULL,
             `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
         ) ENGINE=InnoDB;
     ");

     $pdo->exec("
         CREATE TABLE IF NOT EXISTS `submission_files` (
             `id` INT AUTO_INCREMENT PRIMARY KEY,
             `submission_id` INT NOT NULL,
             `file_name` VARCHAR(255) NOT NULL,
             `file_type` VARCHAR(100) NULL,
             `google_drive_file_id` VARCHAR(255) NULL,
             `google_drive_url` TEXT NULL,
             `file_size` INT NULL,
             `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
         ) ENGINE=InnoDB;
     ");

     $pdo->exec("
         CREATE TABLE IF NOT EXISTS `submission_images` (
             `id` INT AUTO_INCREMENT PRIMARY KEY,
             `submission_id` INT NOT NULL,
             `file_name` VARCHAR(255) NOT NULL,
             `file_type` VARCHAR(100) NULL,
             `google_drive_file_id` VARCHAR(255) NULL,
             `google_drive_url` TEXT NULL,
             `file_size` INT NULL,
             `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
         ) ENGINE=InnoDB;
     ");

     $pdo->exec("
         CREATE TABLE IF NOT EXISTS `submission_status_history` (
             `id` INT AUTO_INCREMENT PRIMARY KEY,
             `submission_id` INT NOT NULL,
             `status` VARCHAR(50) NOT NULL,
             `notes` TEXT NULL,
             `changed_by` INT NOT NULL,
             `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE,
             FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
         ) ENGINE=InnoDB;
     ");
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
