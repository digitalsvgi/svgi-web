<?php
// super-admin/activity_logs.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'Activity Logs';
require_once __DIR__ . '/../includes/header.php';

// Fetch dynamic logs from DB
$logs = $pdo->query("
    SELECT al.*, u.role 
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.id DESC
")->fetchAll();
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark">System Audit & Activity Logs</h3>
    <p class="text-muted">Trace all administrative updates, database operations, status changes, and institutional submissions.</p>
</div>

<!-- REFERENCE STYLE MAIN CONTAINER CARD -->
<div class="ref-card">
    
    <!-- 1. Top Segmented Filter Tabs -->
    <div class="ref-tabs-wrapper">
        <a href="activity_logs.php" class="ref-tab-link active">
            Audit Activity Records (<?php echo count($logs); ?>)
        </a>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, count($logs)); ?> of <?php echo count($logs); ?> audit logs
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="ref-view-toggle">
                <button type="button" class="ref-view-btn active" title="List View"><i class="bi bi-list-ul"></i></button>
            </div>
        </div>
    </div>

    <!-- 3. Reference Table -->
    <div class="ref-table-responsive">
        <table class="ref-table">
            <thead>
                <tr>
                    <th style="width: 140px;">Timestamp <span class="sort-icon">&#x21C5;</span></th>
                    <th>User / Actor <span class="sort-icon">&#x21C5;</span></th>
                    <th>Action Taken <span class="sort-icon">&#x21C5;</span></th>
                    <th>Related Task <span class="sort-icon">&#x21C5;</span></th>
                    <th>Previous State <span class="sort-icon">&#x21C5;</span></th>
                    <th>New State <span class="sort-icon">&#x21C5;</span></th>
                    <th>IP Address <span class="sort-icon">&#x21C5;</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No activity logs recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><span class="small text-muted fw-semibold"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></span></td>
                        <td>
                            <strong class="text-dark d-block"><?php echo htmlspecialchars($log['user_name'] ?? 'System Process'); ?></strong>
                            <?php if ($log['role']): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-dark font-monospace text-uppercase" style="font-size: 0.65rem;"><?php echo htmlspecialchars($log['role']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="ref-code-pill text-primary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                        <td><span class="font-monospace text-dark fw-bold small"><?php echo htmlspecialchars($log['task_id'] ?? 'N/A'); ?></span></td>
                        <td><span class="small text-muted font-monospace"><?php echo htmlspecialchars($log['old_value'] ?? '-'); ?></span></td>
                        <td><span class="small text-success font-monospace fw-bold"><?php echo htmlspecialchars($log['new_value'] ?? '-'); ?></span></td>
                        <td><span class="ref-code-pill" style="font-size: 0.7rem;"><?php echo htmlspecialchars($log['ip_address']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 4. Reference Footer Pagination -->
    <div class="ref-pagination-bar">
        <div class="d-flex align-items-center gap-2 text-muted small">
            <span>Show</span>
            <select class="form-select form-select-sm" style="width: 70px; border-radius: 8px;">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <span>entries</span>
        </div>

        <div class="ref-page-nav">
            <a href="#" class="ref-page-btn text-muted">Previous</a>
            <a href="#" class="ref-page-btn active">1</a>
            <a href="#" class="ref-page-btn">2</a>
            <a href="#" class="ref-page-btn">Next</a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
