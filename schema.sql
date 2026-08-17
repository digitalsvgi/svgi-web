-- College Work Management & Tracking System Database Schema

-- CREATE DATABASE IF NOT EXISTS `cwm_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `cwm_db`;

-- 1. Colleges Table
CREATE TABLE IF NOT EXISTS `colleges` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(191) NULL,
  `phone` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `logo` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `college_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`college_id`) REFERENCES `colleges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `college_id` INT NULL, -- NULL for Super Admin & Admin
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'college_user') NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`college_id`) REFERENCES `colleges`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Submissions Table
CREATE TABLE IF NOT EXISTS `submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `college_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('pending', 'processing', 'completed') DEFAULT 'pending',
  `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  `google_drive_folder_id` VARCHAR(255) NULL,
  `update_url` VARCHAR(255) NULL,
  `processing_url` VARCHAR(255) NULL,
  `completed_url` VARCHAR(255) NULL,
  `processing_notes` TEXT NULL,
  `completion_notes` TEXT NULL,
  `edit_count` INT DEFAULT 0,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`college_id`) REFERENCES `colleges`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Submission Attachments Table
CREATE TABLE IF NOT EXISTS `submission_attachments` (
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

-- 6. Messages Table (Submission-based chat)
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed Default Data (Passwords: admin123)
-- Admin Password hash: $2y$10$QO9H8B1L4b2/mUq3wX8s4OenJvV1QyP113K5x1Fw.6x8oKkGfeZqa (admin123)
INSERT INTO `users` (`id`, `college_id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, NULL, 'Super Admin User', 'superadmin@example.com', '$2y$10$QO9H8B1L4b2/mUq3wX8s4OenJvV1QyP113K5x1Fw.6x8oKkGfeZqa', 'super_admin', 'active'),
(2, NULL, 'Admin User', 'admin@example.com', '$2y$10$QO9H8B1L4b2/mUq3wX8s4OenJvV1QyP113K5x1Fw.6x8oKkGfeZqa', 'admin', 'active');

-- 8. Activity Logs Table
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

-- 9. Submission Files Table
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

-- 10. Submission Images Table
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

-- 11. Submission Status History Table
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

-- 12. Submission Edit History Table (Tracks college user edits)
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
