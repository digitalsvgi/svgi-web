<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['admin']);

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Fetch stats
$total = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'")->fetchColumn();
$processing = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'processing'")->fetchColumn();
$completed = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'completed'")->fetchColumn();

// Fetch recent 10 submissions with college and department details
$recent_submissions = $pdo->query("
    SELECT s.*, c.name as college_name, d.name as dept_name, u.name as user_name 
    FROM submissions s
    JOIN colleges c ON s.college_id = c.id
    JOIN departments d ON s.department_id = d.id
    JOIN users u ON s.created_by = u.id
    ORDER BY s.id DESC LIMIT 10
")->fetchAll();
?>

<!-- Top Trading / SaaS Metric Cards -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Submissions</div>
                <div class="stat-value"><?php echo $total; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-dark">
                <i class="bi bi-folder2-open"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Pending Review</div>
                <div class="stat-value"><?php echo $pending; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">In Processing</div>
                <div class="stat-value"><?php echo $processing; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-info">
                <i class="bi bi-gear-wide-connected"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Completed Work</div>
                <div class="stat-value"><?php echo $completed; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>
</div>

<div class="card glass-card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">Recent Submissions Queue</h5>
        <a href="submissions.php" class="btn btn-primary btn-sm">Process Submissions</a>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead>
                    <tr class="text-muted small">
                        <th>College</th>
                        <th>Department</th>
                        <th>Work Title</th>
                        <th>Submitted By</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_submissions)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No submissions received yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_submissions as $sub): ?>
                        <tr style="cursor: pointer;" onclick="location.href='submissions.php?id=<?php echo $sub['id']; ?>'">
                            <td><strong><?php echo htmlspecialchars($sub['college_name']); ?></strong></td>
                            <td><span class="small text-muted"><?php echo htmlspecialchars($sub['dept_name']); ?></span></td>
                            <td><span class="fw-medium text-dark"><?php echo htmlspecialchars($sub['title']); ?></span></td>
                            <td><span class="small text-muted"><?php echo htmlspecialchars($sub['user_name']); ?></span></td>
                            <td><span class="small text-muted"><?php echo date('M d, Y h:i A', strtotime($sub['created_at'])); ?></span></td>
                            <td>
                                <span class="badge badge-<?php echo $sub['status']; ?> px-2.5 py-1.5 rounded text-capitalize">
                                    <?php echo $sub['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
