<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? '';
?>
<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Dark Luxury Sidebar -->
<aside id="sidebar" class="sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div class="brand-icon-box">
            <i class="bi bi-shield-check"></i>
        </div>
        <div>
            <div class="brand-text">CWM PORTAL</div>
            <div class="brand-subtext">Work &bull; Track &bull; Verify</div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="nav-label">Core Navigation</div>
        
        <!-- Dashboard Link -->
        <?php
        $folder_name = ($role === 'college_user') ? 'college' : str_replace('_', '-', $role);
        ?>
        <a href="<?php echo BASE_URL; ?>/<?php echo $folder_name; ?>/dashboard.php" 
           class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'super_admin'): ?>
            <div class="nav-label mt-2">Institution Control</div>
            <a href="<?php echo BASE_URL; ?>/super-admin/colleges.php" 
               class="nav-link <?php echo $current_page === 'colleges.php' || $current_page === 'college_details.php' ? 'active' : ''; ?>">
                <i class="bi bi-buildings"></i>
                <span>Colleges</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/super-admin/departments.php" 
               class="nav-link <?php echo $current_page === 'departments.php' ? 'active' : ''; ?>">
                <i class="bi bi-diagram-3-fill"></i>
                <span>Departments</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/super-admin/users.php" 
               class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <i class="bi bi-people-fill"></i>
                <span>Users & Roles</span>
            </a>

            <div class="nav-label mt-2">Work & Analytics</div>
            <a href="<?php echo BASE_URL; ?>/super-admin/submissions.php" 
               class="nav-link <?php echo $current_page === 'submissions.php' ? 'active' : ''; ?>">
                <i class="bi bi-folder-check"></i>
                <span>Submissions</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/super-admin/reports.php" 
               class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-steps"></i>
                <span>Reports & Stats</span>
            </a>

            <div class="nav-label mt-2">System Management</div>
            <a href="<?php echo BASE_URL; ?>/super-admin/settings.php" 
               class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-sliders2-vertical"></i>
                <span>Settings & API</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/super-admin/activity_logs.php" 
               class="nav-link <?php echo $current_page === 'activity_logs.php' ? 'active' : ''; ?>">
                <i class="bi bi-shield-shaded"></i>
                <span>Audit Logs</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/super-admin/backup.php" 
               class="nav-link <?php echo $current_page === 'backup.php' ? 'active' : ''; ?>">
                <i class="bi bi-database-fill-down"></i>
                <span>DB Backups</span>
            </a>

        <?php elseif ($role === 'admin'): ?>
            <div class="nav-label mt-2">Work Operations</div>
            <a href="<?php echo BASE_URL; ?>/admin/submissions.php" 
               class="nav-link <?php echo $current_page === 'submissions.php' ? 'active' : ''; ?>">
                <i class="bi bi-folder2-open"></i>
                <span>All Submissions</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/messages.php" 
               class="nav-link <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
                <i class="bi bi-chat-left-text-fill"></i>
                <span>Live Messages</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/reports.php" 
               class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-steps"></i>
                <span>Reports & Analytics</span>
            </a>

        <?php elseif ($role === 'college_user'): ?>
            <div class="nav-label mt-2">My Requests</div>
            <a href="<?php echo BASE_URL; ?>/college/submissions.php" 
               class="nav-link <?php echo $current_page === 'submissions.php' ? 'active' : ''; ?>">
                <i class="bi bi-cloud-arrow-up-fill"></i>
                <span>Work Submissions</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/college/messages.php" 
               class="nav-link <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
                <i class="bi bi-chat-left-text-fill"></i>
                <span>Direct Chat</span>
            </a>
        <?php endif; ?>
    </nav>
    
    <!-- User Profile Footer Card -->
    <div class="sidebar-footer">
        <div class="user-profile-widget">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-info-text">
                <div class="user-info-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                <div class="user-info-role"><?php echo str_replace('_', ' ', $role); ?></div>
            </div>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-danger ms-auto" title="Sign Out">
                <i class="bi bi-power fs-5"></i>
            </a>
        </div>
    </div>
</aside>
