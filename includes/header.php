<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' &bull; CWM Portal' : 'College Work Management & Tracking System'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom 2026 CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
<?php 
// Show sidebar if user is logged in
if (isset($_SESSION['user_id'])) {
    include __DIR__ . '/sidebar.php';
}
?>
<div class="<?php echo isset($_SESSION['user_id']) ? 'main-content' : 'w-100 p-4'; ?>">
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Top Modern Glass Navbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm btn-light d-lg-none" type="button" aria-label="Toggle Navigation">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <h1 class="top-navbar-title"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard Overview'; ?></h1>
            </div>
        </div>
        
        <div class="top-navbar-actions">
            <div class="d-none d-md-flex align-items-center me-2 text-muted small">
                <i class="bi bi-calendar3 me-1.5 text-primary"></i>
                <span class="fw-semibold"><?php echo date('D, M d, Y'); ?></span>
            </div>
            
            <a href="<?php echo BASE_URL; ?>/profile.php" class="btn btn-sm btn-light-primary text-decoration-none px-3" title="View Profile">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            </a>

            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm btn-act-danger text-decoration-none px-3" title="Sign Out">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-sm-inline">Logout</span>
            </a>
        </div>
    </header>
    <?php endif; ?>

    <div class="container-fluid p-0">
        <?php display_alert(); ?>
