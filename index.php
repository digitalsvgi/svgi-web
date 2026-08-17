<?php
// index.php
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'super_admin') {
        redirect('/super-admin/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('/admin/dashboard.php');
    } elseif ($role === 'college_user') {
        redirect('/college/dashboard.php');
    }
}

redirect('/login.php');
