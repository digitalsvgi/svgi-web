<?php
// super-admin/dashboard.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'Super Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

// Count metrics
$college_count = $pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn();
$dept_count = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$submission_count = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();

// Status metrics
$pending_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'")->fetchColumn();
$processing_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'processing'")->fetchColumn();
$completed_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'completed'")->fetchColumn();

// Interval metrics
$today_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$week_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$month_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Recent Submissions
$recent_submissions = $pdo->query("
    SELECT s.*, c.name as college_name, d.name as dept_name 
    FROM submissions s
    JOIN colleges c ON s.college_id = c.id
    JOIN departments d ON s.department_id = d.id
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Recent Messages
$recent_messages = $pdo->query("
    SELECT m.*, u.name as sender_name, u.role as sender_role, s.title as sub_title
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN submissions s ON m.submission_id = s.id
    ORDER BY m.id DESC LIMIT 5
")->fetchAll();

// Urgent Items (Pending status and older than 2 days)
$urgent_items = $pdo->query("
    SELECT s.*, c.name as college_name, d.name as dept_name 
    FROM submissions s
    JOIN colleges c ON s.college_id = c.id
    JOIN departments d ON s.department_id = d.id
    WHERE s.status = 'pending' AND s.created_at <= DATE_SUB(NOW(), INTERVAL 2 DAY)
    ORDER BY s.id ASC LIMIT 5
")->fetchAll();
?>

<!-- Top Trading / SaaS Metric Cards -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Registered Colleges</div>
                <div class="stat-value"><?php echo $college_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-primary">
                <i class="bi bi-buildings"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Departments</div>
                <div class="stat-value"><?php echo $dept_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-info">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">System Users</div>
                <div class="stat-value"><?php echo $user_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-success">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Work Submissions</div>
                <div class="stat-value"><?php echo $submission_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-warning">
                <i class="bi bi-folder-check"></i>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Status & Time Intervals -->
<div class="row g-3 mb-4">
    <!-- Statuses -->
    <div class="col-12 col-md-6">
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="fw-bold text-dark mb-0">Workflow Stages</h6></div>
            <div class="card-body py-3 d-flex justify-content-between text-center">
                <div class="w-100 border-end">
                    <h4 class="fw-bold text-warning mb-0"><?php echo $pending_count; ?></h4>
                    <span class="small text-muted">Pending</span>
                </div>
                <div class="w-100 border-end">
                    <h4 class="fw-bold text-primary mb-0"><?php echo $processing_count; ?></h4>
                    <span class="small text-muted">Processing</span>
                </div>
                <div class="w-100">
                    <h4 class="fw-bold text-success mb-0"><?php echo $completed_count; ?></h4>
                    <span class="small text-muted">Completed</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Time Intervals -->
    <div class="col-12 col-md-6">
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="fw-bold text-dark mb-0">Submission Timeline</h6></div>
            <div class="card-body py-3 d-flex justify-content-between text-center">
                <div class="w-100 border-end">
                    <h4 class="fw-bold text-dark mb-0"><?php echo $today_count; ?></h4>
                    <span class="small text-muted">Today</span>
                </div>
                <div class="w-100 border-end">
                    <h4 class="fw-bold text-dark mb-0"><?php echo $week_count; ?></h4>
                    <span class="small text-muted">This Week</span>
                </div>
                <div class="w-100">
                    <h4 class="fw-bold text-dark mb-0"><?php echo $month_count; ?></h4>
                    <span class="small text-muted">This Month</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Urgent Items -->
    <div class="col-12 col-lg-6">
        <div class="card glass-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center">
                <h5 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Urgent Items (Pending > 2 Days)</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="table-responsive">
                    <table class="table align-middle table-sm small">
                        <thead>
                            <tr class="text-muted">
                                <th>College</th>
                                <th>Department</th>
                                <th>Title</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($urgent_items)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No urgent items. Great job!</td></tr>
                            <?php else: ?>
                                <?php foreach ($urgent_items as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['college_name']); ?></strong></td>
                                    <td><span class="text-muted"><?php echo htmlspecialchars($item['dept_name']); ?></span></td>
                                    <td><span class="fw-medium text-dark"><?php echo htmlspecialchars($item['title']); ?></span></td>
                                    <td><span class="text-danger small fw-bold"><?php echo date('M d', strtotime($item['created_at'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Submissions -->
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0">Recent Submissions</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="table-responsive">
                    <table class="table align-middle table-sm small">
                        <thead>
                            <tr class="text-muted">
                                <th>College</th>
                                <th>Title</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_submissions as $sub): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sub['college_name']); ?></strong></td>
                                <td><span class="fw-medium text-dark"><?php echo htmlspecialchars($sub['title']); ?></span></td>
                                <td>
                                    <span class="badge badge-<?php echo $sub['status']; ?> rounded text-capitalize" style="font-size: 0.75rem;">
                                        <?php echo $sub['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Messages and Activity -->
    <div class="col-12 col-lg-6">
        <!-- Recent Messages -->
        <div class="card glass-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0">Recent Discussion Activities</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($recent_messages)): ?>
                    <div class="text-center text-muted py-4">No recent messages.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($recent_messages as $msg): ?>
                            <div class="list-group-item border-0 p-2.5 bg-light rounded mb-2">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($msg['sender_name']); ?> (<?php echo ucfirst($msg['sender_role']); ?>)</span>
                                    <small class="text-muted"><?php echo date('M d h:i A', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <div class="text-muted mb-1" style="font-size: 0.8rem;">Re: <strong><?php echo htmlspecialchars($msg['sub_title']); ?></strong></div>
                                <p class="mb-0 text-muted italic">"<?php echo htmlspecialchars($msg['message_text']); ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent System Activity -->
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0">System Activity Trace</h5>
                <a href="activity_logs.php" class="btn btn-sm btn-link text-primary text-decoration-none">View All</a>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="list-group list-group-flush small">
                    <div class="list-group-item border-0 p-2.5 bg-light rounded mb-2">
                        <div class="d-flex w-100 justify-content-between">
                            <span class="fw-bold text-dark">Super Admin</span>
                            <small class="text-muted">Today 10:25 AM</small>
                        </div>
                        <p class="mb-0 text-muted">Created college user credentials.</p>
                    </div>
                    <div class="list-group-item border-0 p-2.5 bg-light rounded mb-2">
                        <div class="d-flex w-100 justify-content-between">
                            <span class="fw-bold text-dark">System Admin</span>
                            <small class="text-muted">Today 09:12 AM</small>
                        </div>
                        <p class="mb-0 text-muted">Updated budget proposal submission to Completed.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
