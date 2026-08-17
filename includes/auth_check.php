<?php
// includes/auth_check.php
require_once __DIR__ . '/../config/config.php';

function check_auth($allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        redirect('/login.php', 'Please log in to access this page.', 'warning');
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['user_role'], $allowed_roles)) {
        redirect('/login.php', 'Access Denied: You do not have permission to view this page.', 'danger');
    }
}
