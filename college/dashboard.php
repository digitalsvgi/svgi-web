<?php
// college/dashboard.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['college_user']);

$page_title = 'College Dashboard';
require_once __DIR__ . '/../includes/header.php';

$college_id = $_SESSION['college_id'];
$user_id = $_SESSION['user_id'];

// Populate college_name if missing
if (empty($_SESSION['college_name']) && !empty($college_id)) {
    $collStmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
    $collStmt->execute([$college_id]);
    $_SESSION['college_name'] = $collStmt->fetchColumn();
}

// Handle Notification Dismissal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'read_notification') {
    $notif_id = intval($_POST['notif_id'] ?? 0);
    if ($notif_id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([notif_id, $user_id]);
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch stats for this college
$total = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE college_id = ?");
$total->execute([$college_id]);
$total_count = $total->fetchColumn();

$pending = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE college_id = ? AND status = 'pending'");
$pending->execute([$college_id]);
$pending_count = $pending->fetchColumn();

$processing = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE college_id = ? AND status = 'processing'");
$processing->execute([$college_id]);
$processing_count = $processing->fetchColumn();

$completed = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE college_id = ? AND status = 'completed'");
$completed->execute([$college_id]);
$completed_count = $completed->fetchColumn();

// Fetch unread notifications
$notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 5");
$notifications->execute([$user_id]);
$notif_list = $notifications->fetchAll();

// Fetch recent submissions
$recent_submissions = $pdo->prepare("
    SELECT s.*, d.name as dept_name 
    FROM submissions s
    JOIN departments d ON s.department_id = d.id
    WHERE s.college_id = ?
    ORDER BY s.id DESC LIMIT 5
");
$recent_submissions->execute([$college_id]);
$sub_list = $recent_submissions->fetchAll();

// Fetch Pending Items
$pending_stmt = $pdo->prepare("
    SELECT s.*, d.name as dept_name 
    FROM submissions s
    JOIN departments d ON s.department_id = d.id
    WHERE s.college_id = ? AND s.status = 'pending'
    ORDER BY s.id DESC LIMIT 5
");
$pending_stmt->execute([$college_id]);
$pending_items = $pending_stmt->fetchAll();

// Fetch Processing Items
$processing_stmt = $pdo->prepare("
    SELECT s.*, d.name as dept_name 
    FROM submissions s
    JOIN departments d ON s.department_id = d.id
    WHERE s.college_id = ? AND s.status = 'processing'
    ORDER BY s.id DESC LIMIT 5
");
$processing_stmt->execute([$college_id]);
$processing_items = $processing_stmt->fetchAll();

// Fetch Recently Completed Items
$completed_stmt = $pdo->prepare("
    SELECT s.*, d.name as dept_name 
    FROM submissions s
    JOIN departments d ON s.department_id = d.id
    WHERE s.college_id = ? AND s.status = 'completed'
    ORDER BY s.id DESC LIMIT 5
");
$completed_stmt->execute([$college_id]);
$completed_items = $completed_stmt->fetchAll();

// Fetch Recent Messages regarding this college's submissions
$messages_stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name, u.role as sender_role, s.title as sub_title
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN submissions s ON m.submission_id = s.id
    WHERE s.college_id = ?
    ORDER BY m.id DESC LIMIT 5
");
$messages_stmt->execute([$college_id]);
$message_list = $messages_stmt->fetchAll();
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['college_name']); ?></h3>
    <p class="text-muted">Manage your departments, upload work requests, and view processing statuses.</p>
</div>

<!-- Top Trading / SaaS Metric Cards -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Work Items</div>
                <div class="stat-value"><?php echo $total_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-dark">
                <i class="bi bi-folder2-open"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Awaiting Review</div>
                <div class="stat-value"><?php echo $pending_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">In Progress</div>
                <div class="stat-value"><?php echo $processing_count; ?></div>
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
                <div class="stat-value"><?php echo $completed_count; ?></div>
            </div>
            <div class="stat-icon-wrapper stat-icon-success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Status Lists -->
    <div class="col-12 col-lg-8">
        <!-- Tabbed Status Views -->
        <div class="card glass-card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 p-0 border-bottom">
                <ul class="nav nav-tabs card-header-tabs m-0 px-3" id="statusTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-3 fw-bold" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent-pane" type="button" role="tab">Recent Submissions</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 fw-bold text-warning" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab">Pending (<?php echo count($pending_items); ?>)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 fw-bold text-primary" id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing-pane" type="button" role="tab">Processing (<?php echo count($processing_items); ?>)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 fw-bold text-success" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-pane" type="button" role="tab">Recently Completed</button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-4">
                <div class="tab-content" id="statusTabContent">
                    <!-- Recent Submissions Pane -->
                    <div class="tab-pane fade show active" id="recent-pane" role="tabpanel" aria-labelledby="recent-tab">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm small">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Department</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sub_list)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No submissions.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($sub_list as $sub): ?>
                                        <tr>
                                            <td><span class="text-muted fw-medium"><?php echo htmlspecialchars($sub['dept_name']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                                            <td><span class="badge badge-<?php echo $sub['status']; ?>"><?php echo $sub['status']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pending Items Pane -->
                    <div class="tab-pane fade" id="pending-pane" role="tabpanel" aria-labelledby="pending-tab">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm small">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Department</th>
                                        <th>Title</th>
                                        <th>Submitted At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pending_items)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No pending items.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_items as $sub): ?>
                                        <tr>
                                            <td><span class="text-muted fw-medium"><?php echo htmlspecialchars($sub['dept_name']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                                            <td><span class="text-muted"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Processing Items Pane -->
                    <div class="tab-pane fade" id="processing-pane" role="tabpanel" aria-labelledby="processing-tab">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm small">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Department</th>
                                        <th>Title</th>
                                        <th>Progress Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($processing_items)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No processing items.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($processing_items as $sub): ?>
                                        <tr>
                                            <td><span class="text-muted fw-medium"><?php echo htmlspecialchars($sub['dept_name']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                                            <td><span class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?php echo htmlspecialchars($sub['processing_notes'] ?? 'Processing...'); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recently Completed Pane -->
                    <div class="tab-pane fade" id="completed-pane" role="tabpanel" aria-labelledby="completed-tab">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm small">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Department</th>
                                        <th>Title</th>
                                        <th>Completed URL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($completed_items)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No completed items yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($completed_items as $sub): ?>
                                        <tr>
                                            <td><span class="text-muted fw-medium"><?php echo htmlspecialchars($sub['dept_name']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                                            <td>
                                                <?php if($sub['completed_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($sub['completed_url']); ?>" target="_blank" class="text-success small fw-medium"><i class="bi bi-link-45deg"></i> Link</a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Messages -->
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0">Recent Discussion Activities</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($message_list)): ?>
                    <div class="text-center text-muted py-4">No recent messages.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($message_list as $msg): ?>
                            <div class="list-group-item border-0 p-2.5 bg-light rounded mb-2">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($msg['sender_name']); ?> (<?php echo ucfirst($msg['sender_role']); ?>)</span>
                                    <small class="text-muted"><?php echo date('M d h:i A', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <div class="text-muted mb-1" style="font-size: 0.8rem;">Re: <strong><?php echo htmlspecialchars($msg['sub_title']); ?></strong></div>
                                <p class="mb-0 text-muted">"<?php echo htmlspecialchars($msg['message_text']); ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Notifications -->
    <div class="col-12 col-lg-4">
        <div class="card glass-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0 text-dark">Notifications</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($notif_list)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bell-slash fs-2 mb-2 d-block"></i>
                        No new notifications.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($notif_list as $notif): ?>
                            <li class="list-group-item border-0 p-3 bg-light rounded mb-2">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <h6 class="fw-bold mb-1 text-dark" style="font-size:0.85rem;"><?php echo htmlspecialchars($notif['title']); ?></h6>
                                    <form action="dashboard.php" method="POST">
                                        <input type="hidden" name="action" value="read_notification">
                                        <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" class="btn btn-sm text-secondary p-0" title="Dismiss">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                </div>
                                <p class="text-muted small mb-1" style="font-size:0.75rem;"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
