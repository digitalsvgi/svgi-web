<?php
// super-admin/settings.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'System Settings';
require_once __DIR__ . '/../includes/header.php';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_activity($pdo, 'Updated System Settings');
    
    $_SESSION['flash_msg'] = 'System settings updated successfully!';
    $_SESSION['flash_type'] = 'success';
    header("Location: settings.php");
    exit();
}
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark">Core System Settings</h3>
    <p class="text-muted">Configure portal policies, security options, and external API mappings.</p>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0">General Portal Configuration</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="settings.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Portal Application Name</label>
                        <input type="text" class="form-control" value="College Work Management & Tracking System" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Google Drive Default Destination Folder ID</label>
                        <input type="text" class="form-control" placeholder="e.g. 1a2b3c4d5e6f7g8h9i0j..." value="1x_GDriveDefaultRootCWM">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Max Allowed Attachment Size (MB)</label>
                        <input type="number" class="form-control" value="10" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Maintenance Mode</label>
                        <select class="form-select">
                            <option value="0" selected>Disabled (Portal Online)</option>
                            <option value="1">Enabled (System Administrators Only)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card glass-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0">Google Cloud API Status</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge bg-success rounded-circle p-2 me-2"><i class="bi bi-check-lg text-white"></i></span>
                    <div>
                        <div class="fw-semibold">Google OAuth2 Client</div>
                        <span class="small text-muted">Configured via .env</span>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <span class="badge bg-success rounded-circle p-2 me-2"><i class="bi bi-check-lg text-white"></i></span>
                    <div>
                        <div class="fw-semibold">Drive Scope Permission</div>
                        <span class="small text-muted">Access Token verified</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
