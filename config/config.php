<?php
// config/config.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

// Error reporting - change to 0 in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
require_once __DIR__ . '/EnvHelper.php';
EnvHelper::load(__DIR__ . '/../.env');

// Define Base URL path helper with dynamic local/production detection
$envBaseUrl = getenv('BASE_URL');
if ($envBaseUrl !== false) {
    define('BASE_URL', $envBaseUrl);
} else {
    $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
    $serverAddr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    $isLocal = ($serverName === 'localhost' || $serverName === '127.0.0.1' || $serverAddr === '127.0.0.1' || $serverAddr === '::1');
    define('BASE_URL', $isLocal ? '/trackingSystem_CWM' : '');
}

// Google Drive Config placeholders (reads from env)
define('GD_CLIENT_ID', getenv('GD_CLIENT_ID') ?: 'YOUR_GOOGLE_DRIVE_CLIENT_ID');
define('GD_CLIENT_SECRET', getenv('GD_CLIENT_SECRET') ?: 'YOUR_GOOGLE_DRIVE_CLIENT_SECRET');
define('GD_REDIRECT_URI', getenv('GD_REDIRECT_URI') ?: 'YOUR_GOOGLE_DRIVE_REDIRECT_URI');
define('GD_REFRESH_TOKEN', getenv('GD_REFRESH_TOKEN') ?: 'YOUR_GOOGLE_DRIVE_REFRESH_TOKEN');
define('GD_PARENT_FOLDER_ID', getenv('GD_PARENT_FOLDER_ID') ?: 'YOUR_GOOGLE_DRIVE_FOLDER_ID');

// Helper: Redirect with message
function redirect($path, $msg = '', $type = 'success') {
    if (!empty($msg)) {
        $_SESSION['flash_msg'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: " . BASE_URL . $path);
    exit();
}

// Helper: Redirect user according to role
function redirect_by_role($role) {
    switch ($role) {
        case 'super_admin':
            header("Location: " . BASE_URL . "/super-admin/dashboard.php");
            break;
        case 'admin':
            header("Location: " . BASE_URL . "/admin/dashboard.php");
            break;
        case 'college_user':
            header("Location: " . BASE_URL . "/college/dashboard.php");
            break;
        default:
            header("Location: " . BASE_URL . "/login.php");
            break;
    }
    exit();
}

// Helper: Display Alert
function display_alert() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_msg']);
        unset($_SESSION['flash_type']);
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">' .
             $msg .
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
             '</div>';
    }
}

// Helper: Format Task ID consistently
function get_task_id($id, $created_at) {
    $year = date('Y', strtotime($created_at));
    return "TASK-" . $year . "-" . str_pad($id, 6, '0', STR_PAD_LEFT);
}

// Helper: Log audit action to activity_logs
function log_activity($pdo, $action, $taskId = null, $oldValue = null, $newValue = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['name'] ?? 'System';
    if (isset($_SESSION['college_name'])) {
        $userName = $_SESSION['college_name'];
    }
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, user_name, action, task_id, old_value, new_value, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $userName, $action, $taskId, $oldValue, $newValue, $ipAddress]);
}
