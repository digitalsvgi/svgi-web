<?php
// super-admin/submissions.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'Super Admin Submissions Queue';
require_once __DIR__ . '/../includes/header.php';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $processing_notes = trim($_POST['processing_notes'] ?? '');
        $processing_url = trim($_POST['processing_url'] ?? '');
        $completion_notes = trim($_POST['completion_notes'] ?? '');
        $completed_url = trim($_POST['completed_url'] ?? '');

        if ($id > 0 && in_array($status, ['pending', 'processing', 'completed'])) {
            try {
                // Fetch old status for activity logging
                $oldSub = $pdo->prepare("SELECT status, created_at FROM submissions WHERE id = ?");
                $oldSub->execute([$id]);
                $oldData = $oldSub->fetch();
                $oldStatus = $oldData ? $oldData['status'] : '';
                $createdAt = $oldData ? $oldData['created_at'] : date('Y-m-d H:i:s');
                $taskId = get_task_id($id, $createdAt);

                $stmt = $pdo->prepare("
                    UPDATE submissions 
                    SET status = ?, processing_notes = ?, processing_url = ?, completion_notes = ?, completed_url = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $processing_notes, $processing_url, $completion_notes, $completed_url, $id]);

                // Log activity
                if ($oldStatus !== $status) {
                    $actionText = "Changed " . ucfirst($oldStatus) . " → " . ucfirst($status);
                    log_activity($pdo, $actionText, $taskId, $oldStatus, $status);
                } else {
                    log_activity($pdo, "Updated submission settings", $taskId, $status, $status);
                }

                // Insert into submission_status_history
                $history_notes = ($status === 'processing') ? $processing_notes : (($status === 'completed') ? $completion_notes : 'Status updated');
                $hist_stmt = $pdo->prepare("
                    INSERT INTO submission_status_history (submission_id, status, notes, changed_by) 
                    VALUES (?, ?, ?, ?)
                ");
                $hist_stmt->execute([$id, $status, $history_notes, $_SESSION['user_id']]);

                // Create alert notification for submission creator
                $sub = $pdo->prepare("SELECT created_by, title FROM submissions WHERE id = ?");
                $sub->execute([$id]);
                $sData = $sub->fetch();
                if ($sData) {
                    $title = "Submission Status Updated";
                    $msg = "Your submission status has been updated to " . ucfirst($status) . ".";
                    if ($status === 'processing') {
                        $msg = "Your submission is now Processing.";
                    } elseif ($status === 'completed') {
                        $msg = "Your submission has been Completed.";
                    }
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $notif->execute([$sData['created_by'], $title, $msg]);
                }

                $_SESSION['flash_msg'] = 'Submission status updated by Super Admin!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    header("Location: submissions.php");
    exit();
}

// Filters & Search
$college_filter = intval($_GET['college_id'] ?? 0);
$dept_filter = intval($_GET['department_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$query = "
    SELECT s.*, c.name as college_name, d.name as dept_name, u.name as user_name
    FROM submissions s
    JOIN colleges c ON s.college_id = c.id
    JOIN departments d ON s.department_id = d.id
    JOIN users u ON s.created_by = u.id
    WHERE 1=1
";
$params = [];

if ($college_filter > 0) {
    $query .= " AND s.college_id = ?";
    $params[] = $college_filter;
}
if ($dept_filter > 0) {
    $query .= " AND s.department_id = ?";
    $params[] = $dept_filter;
}
if (!empty($status_filter)) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
}
if (!empty($priority_filter)) {
    $query .= " AND s.priority = ?";
    $params[] = $priority_filter;
}
if (!empty($from_date)) {
    $query .= " AND DATE(s.created_at) >= ?";
    $params[] = $from_date;
}
if (!empty($to_date)) {
    $query .= " AND DATE(s.created_at) <= ?";
    $params[] = $to_date;
}

if (!empty($search_query)) {
    if (preg_match('/TASK-(\d{4})-(\d+)/i', $search_query, $matches)) {
        $parsed_id = intval($matches[2]);
        $query .= " AND s.id = ?";
        $params[] = $parsed_id;
    } else if (preg_match('/^(\d+)$/', $search_query, $matches)) {
        $query .= " AND (s.id = ? OR s.title LIKE ? OR s.description LIKE ?)";
        $params[] = intval($matches[1]);
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    } else {
        $query .= " AND (s.title LIKE ? OR s.description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
}

$query .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

// Fetch dropdown data
$colleges = $pdo->query("SELECT * FROM colleges WHERE status='active' ORDER BY name ASC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name ASC")->fetchAll();
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark">All Submissions (Super Admin Desk)</h3>
    <p class="text-muted">Supervise all institutional submissions, process requests, view details, and change status workflow.</p>
</div>

<!-- Filters Bar -->
<div class="card glass-card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" action="submissions.php" class="row g-3">
            <!-- Row 1 -->
            <div class="col-12 col-md-3">
                <label for="filter_college" class="form-label small fw-bold text-muted mb-1">College</label>
                <select name="college_id" id="filter_college" class="form-select form-select-sm">
                    <option value="">-- All Colleges --</option>
                    <?php foreach ($colleges as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $college_filter === $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label for="filter_dept" class="form-label small fw-bold text-muted mb-1">Department</label>
                <select name="department_id" id="filter_dept" class="form-select form-select-sm">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $dept_filter === $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="filter_status" class="form-label small fw-bold text-muted mb-1">Status</label>
                <select name="status" id="filter_status" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="filter_priority" class="form-label small fw-bold text-muted mb-1">Priority</label>
                <select name="priority" id="filter_priority" class="form-select form-select-sm">
                    <option value="">-- All Priorities --</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="normal" <?php echo $priority_filter === 'normal' ? 'selected' : ''; ?>>Normal</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                </select>
            </div>

            <!-- Row 2 -->
            <div class="col-12 col-md-3">
                <label for="filter_from" class="form-label small fw-bold text-muted mb-1">From Date</label>
                <input type="date" name="from_date" id="filter_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>

            <div class="col-12 col-md-3">
                <label for="filter_to" class="form-label small fw-bold text-muted mb-1">To Date</label>
                <input type="date" name="to_date" id="filter_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>

            <div class="col-12 col-md-4">
                <label for="search_input" class="form-label small fw-bold text-muted mb-1">Search (Task ID / Title / Description)</label>
                <input type="text" name="search" id="search_input" class="form-control form-control-sm" placeholder="Search keywords..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            
            <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm btn-sm w-50">Filter</button>
                <a href="submissions.php" class="btn btn-outline-secondary btn-sm w-50">Reset</a>
            </div>
        </form>
    </div>
</div>
<!-- REFERENCE STYLE MAIN CONTAINER CARD -->
<div class="ref-card">
    
    <!-- 1. Top Segmented Filter Tabs -->
    <div class="ref-tabs-wrapper">
        <a href="submissions.php" class="ref-tab-link <?php echo empty($status_filter) && empty($college_filter) ? 'active' : ''; ?>">
            All Submissions (<?php echo count($submissions); ?>)
        </a>
        <a href="submissions.php?status=pending" class="ref-tab-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
            Pending Work
        </a>
        <a href="submissions.php?status=processing" class="ref-tab-link <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
            Processing Tasks
        </a>
        <a href="submissions.php?status=completed" class="ref-tab-link <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
            Completed
        </a>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, count($submissions)); ?> of <?php echo count($submissions); ?> submissions
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="ref-btn-bulk dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Bulk Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item small" href="#"><i class="bi bi-check2-all me-2"></i>Select All</a></li>
                    <li><a class="dropdown-item small text-warning" href="#"><i class="bi bi-pause-circle me-2"></i>Set Processing</a></li>
                    <li><a class="dropdown-item small text-success" href="#"><i class="bi bi-check-circle me-2"></i>Set Completed</a></li>
                </ul>
            </div>
            
            <div class="ref-view-toggle">
                <button type="button" class="ref-view-btn" title="Grid View"><i class="bi bi-grid"></i></button>
                <button type="button" class="ref-view-btn active" title="List View"><i class="bi bi-list-ul"></i></button>
            </div>
        </div>
    </div>

    <!-- 3. Reference Table -->
    <div class="ref-table-responsive">
        <table class="ref-table">
            <thead>
                <tr>
                    <th style="width: 140px;">Task ID <span class="sort-icon">&#x21C5;</span></th>
                    <th style="min-width: 400px;">Submission Title <span class="sort-icon">&#x21C5;</span></th>
                    <th style="min-width: 280px;">College & Dept <span class="sort-icon">&#x21C5;</span></th>
                    <th style="width: 120px;">Attachment <span class="sort-icon">&#x21C5;</span></th>
                    <th style="width: 180px;">Submitted By <span class="sort-icon">&#x21C5;</span></th>
                    <th style="width: 120px;">Status <span class="sort-icon">&#x21C5;</span></th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No submissions match parameters.</td></tr>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): 
                        $att_stmt = $pdo->prepare("SELECT * FROM submission_attachments WHERE submission_id = ?");
                        $att_stmt->execute([$sub['id']]);
                        $attachments = $att_stmt->fetchAll();
                    ?>
                    <tr>
                        <td>
                            <span class="ref-code-pill">
                                <?php echo get_task_id($sub['id'], $sub['created_at']); ?>
                            </span>
                        </td>
                        <td>
                            <strong class="text-dark d-block"><?php echo htmlspecialchars($sub['title']); ?></strong>
                            <div class="text-muted small" style="font-size: 0.78rem;"><?php echo htmlspecialchars(substr($sub['description'] ?? '', 0, 45)) . (strlen($sub['description'] ?? '') > 45 ? '...' : ''); ?></div>
                        </td>
                        <td>
                            <span class="d-inline-flex align-items-center gap-1.5 text-secondary small fw-medium">
                                <i class="bi bi-bank text-muted"></i> <span><?php echo htmlspecialchars($sub['college_name']); ?></span>
                            </span>
                            <div class="text-muted small" style="font-size: 0.75rem;"><?php echo htmlspecialchars($sub['dept_name']); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($attachments)): ?>
                                <?php foreach ($attachments as $att): ?>
                                    <div class="mb-1 text-truncate" style="max-width: 130px;">
                                        <a href="<?php echo htmlspecialchars($att['google_drive_url']); ?>" target="_blank" class="text-decoration-none small ref-attachment-link">
                                            <i class="bi bi-paperclip me-1"></i><?php echo htmlspecialchars($att['file_name']); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted small">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="small text-dark fw-medium"><?php echo htmlspecialchars($sub['user_name']); ?></span></td>
                        <td>
                            <?php if ($sub['status'] === 'completed'): ?>
                                <span class="ref-status-active">Completed</span>
                            <?php elseif ($sub['status'] === 'processing'): ?>
                                <span class="ref-status-processing">Processing</span>
                            <?php else: ?>
                                <span class="ref-status-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <button type="button" class="ref-btn-edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailsModal"
                                        data-id="<?php echo $sub['id']; ?>"
                                        data-task-id="<?php echo get_task_id($sub['id'], $sub['created_at']); ?>"
                                        data-college="<?php echo htmlspecialchars($sub['college_name']); ?>"
                                        data-dept="<?php echo htmlspecialchars($sub['dept_name']); ?>"
                                        data-created="<?php echo date('d M Y', strtotime($sub['created_at'])); ?>"
                                        data-created-by="<?php echo htmlspecialchars($sub['user_name']); ?>"
                                        data-status="<?php echo strtoupper($sub['status']); ?>"
                                        data-priority="<?php echo htmlspecialchars($sub['priority'] ?? 'normal'); ?>"
                                        data-title="<?php echo htmlspecialchars($sub['title']); ?>"
                                        data-desc="<?php echo htmlspecialchars($sub['description'] ?? 'No description.'); ?>"
                                        data-upurl="<?php echo htmlspecialchars($sub['update_url'] ?? ''); ?>"
                                        data-prurl="<?php echo htmlspecialchars($sub['processing_url'] ?? ''); ?>"
                                        data-compurl="<?php echo htmlspecialchars($sub['completed_url'] ?? ''); ?>"
                                        data-pnotes="<?php echo htmlspecialchars($sub['processing_notes'] ?? 'None.'); ?>"
                                        data-cnotes="<?php echo htmlspecialchars($sub['completion_notes'] ?? 'None.'); ?>"
                                        data-files='<?php echo json_encode($attachments); ?>'
                                        title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="ref-btn-more" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStatusModal"
                                        data-id="<?php echo $sub['id']; ?>"
                                        data-status="<?php echo $sub['status']; ?>"
                                        data-pnotes="<?php echo htmlspecialchars($sub['processing_notes'] ?? ''); ?>"
                                        data-prurl="<?php echo htmlspecialchars($sub['processing_url'] ?? ''); ?>"
                                        data-cnotes="<?php echo htmlspecialchars($sub['completion_notes'] ?? ''); ?>"
                                        data-compurl="<?php echo htmlspecialchars($sub['completed_url'] ?? ''); ?>"
                                        title="Update Status">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </div>
                        </td>
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

<!-- Process Workflow Modal -->
<div class="modal fade" id="editStatusModal" tabindex="-1" aria-labelledby="editStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="submissions.php" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="workflow_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editStatusModalLabel">Workflow Status & Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="workflow_status" class="form-label small fw-bold text-muted">Workflow Step</label>
                        <select class="form-select" name="status" id="workflow_status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <!-- Processing Status Fields -->
                    <div id="workflow_proc_wrapper">
                        <div class="mb-3">
                            <label for="workflow_pnotes" class="form-label small fw-bold text-muted">Processing Note</label>
                            <textarea class="form-control" name="processing_notes" id="workflow_pnotes" rows="3" placeholder="Enter processing details..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="workflow_prurl" class="form-label small fw-bold text-muted">Processing URL</label>
                            <input type="url" class="form-control" name="processing_url" id="workflow_prurl" placeholder="https://example.com/progress">
                        </div>
                    </div>

                    <!-- Completed Status Fields -->
                    <div id="workflow_comp_wrapper">
                        <div class="mb-3">
                            <label for="workflow_compurl" class="form-label small fw-bold text-muted">Completed URL</label>
                            <input type="url" class="form-control" name="completed_url" id="workflow_compurl" placeholder="https://example.com/result">
                        </div>
                        <div class="mb-3">
                            <label for="workflow_cnotes" class="form-label small fw-bold text-muted">Completion Note</label>
                            <textarea class="form-control" name="completion_notes" id="workflow_cnotes" rows="3" placeholder="Enter completion details..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="workflow_submit_btn" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Submission Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <!-- Modal Header with gradient matching Screenshot 3 -->
            <div class="modal-header popup-header-gradient d-flex align-items-center justify-content-between">
                <div class="popup-title-container">
                    <div class="popup-title-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h5 class="modal-title font-monospace fw-bold m-0" id="detailsModalLabel">
                        <span id="det_task_id"></span> Details
                    </h5>
                </div>
                <button type="button" class="popup-close-btn-circle" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="modal-body py-4">
                
                <!-- 1. Top 4 Cards Grid (College, Department, Created, Created By) -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="popup-widget-card">
                            <div class="popup-widget-icon-box popup-widget-icon-green">
                                <i class="bi bi-bank"></i>
                            </div>
                            <div>
                                <span class="popup-widget-label">College</span>
                                <div id="det_college" class="popup-widget-value"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="popup-widget-card">
                            <div class="popup-widget-icon-box popup-widget-icon-blue">
                                <i class="bi bi-activity"></i>
                            </div>
                            <div>
                                <span class="popup-widget-label">Department</span>
                                <div id="det_dept" class="popup-widget-value"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="popup-widget-card">
                            <div class="popup-widget-icon-box popup-widget-icon-green">
                                <i class="bi bi-calendar3"></i>
                            </div>
                            <div>
                                <span class="popup-widget-label">Created On</span>
                                <div id="det_created" class="popup-widget-value"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="popup-widget-card">
                            <div class="popup-widget-icon-box popup-widget-icon-blue">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <span class="popup-widget-label">Created By</span>
                                <div id="det_created_by" class="popup-widget-value"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Title & Description Box -->
                <div class="mb-4">
                    <span class="popup-section-label">Title</span>
                    <div id="det_title" class="popup-section-title"></div>
                </div>

                <div class="mb-4">
                    <span class="popup-section-label">Description</span>
                    <p id="det_desc" class="mb-0"></p>
                </div>

                <!-- 3. Status & Priority Row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="popup-split-card">
                            <div class="popup-split-left">
                                <div class="popup-widget-icon-box popup-widget-icon-green">
                                    <i class="bi bi-circle-fill" style="font-size: 0.6rem; color: #4EB849;"></i>
                                </div>
                                <span class="popup-section-label m-0">Operational Status</span>
                            </div>
                            <span id="det_status" class="popup-badge-pending"></span>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <div class="popup-split-card">
                            <div class="popup-split-left">
                                <div class="popup-widget-icon-box popup-widget-icon-blue">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <span class="popup-section-label m-0">Priority</span>
                            </div>
                            <span id="det_priority" class="popup-badge-priority"></span>
                        </div>
                    </div>
                </div>

                <!-- 4. Documents & Images Row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="popup-upload-card">
                            <div class="popup-upload-header d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="popup-widget-icon-box popup-widget-icon-green m-0">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </div>
                                    <span class="popup-upload-title">Documents</span>
                                </div>
                            </div>
                            <div id="det_docs" class="w-100">
                                <div class="popup-upload-placeholder">No documents.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <div class="popup-upload-card">
                            <div class="popup-upload-header d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="popup-widget-icon-box popup-widget-icon-blue m-0">
                                        <i class="bi bi-image"></i>
                                    </div>
                                    <span class="popup-upload-title">Images</span>
                                </div>
                            </div>
                            <div id="det_imgs" class="w-100 d-flex flex-wrap gap-2">
                                <div class="popup-upload-placeholder">No images.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. URL Row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="popup-split-card">
                            <div class="popup-split-left">
                                <div class="popup-widget-icon-box popup-widget-icon-green">
                                    <i class="bi bi-link-45deg"></i>
                                </div>
                                <span class="popup-section-label m-0">Update URL</span>
                            </div>
                            <div id="det_upurl_wrapper">
                                <a id="det_upurl" href="#" target="_blank" class="small text-decoration-none">Link</a>
                            </div>
                            <span id="det_upurl_none" class="small text-muted">—</span>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-4">
                        <div class="popup-split-card">
                            <div class="popup-split-left">
                                <div class="popup-widget-icon-box popup-widget-icon-blue">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <span class="popup-section-label m-0">Processing URL</span>
                            </div>
                            <div id="det_prurl_wrapper">
                                <a id="det_prurl" href="#" target="_blank" class="small text-decoration-none">Link</a>
                            </div>
                            <span id="det_prurl_none" class="small text-muted">—</span>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-4">
                        <div class="popup-split-card">
                            <div class="popup-split-left">
                                <div class="popup-widget-icon-box popup-widget-icon-green">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <span class="popup-section-label m-0">Completed URL</span>
                            </div>
                            <div id="det_compurl_wrapper">
                                <a id="det_compurl" href="#" target="_blank" class="small text-decoration-none">Link</a>
                            </div>
                            <span id="det_compurl_none" class="small text-muted">—</span>
                        </div>
                    </div>
                </div>

                <!-- 6. Processing & Completion Notes Row -->
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <span class="popup-section-label"><i class="bi bi-chat-left-dots text-success me-1"></i> Processing Notes (Admin)</span>
                        <div id="det_pnotes" class="small">None.</div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <span class="popup-section-label"><i class="bi bi-chat-left-dots text-primary me-1"></i> Completion Notes (Admin)</span>
                        <div id="det_cnotes" class="small">None.</div>
                    </div>
                </div>
                
                <!-- 7. College User Edit History (Super Admin Only) -->
                <hr class="my-4">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="popup-section-label m-0">
                                <i class="bi bi-clock-history text-danger me-1"></i> College Edit History 
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill ms-1" id="det_edit_count">0 Edits</span>
                            </span>
                        </div>
                        <div id="det_edit_history_container" style="max-height: 250px; overflow-y: auto;">
                            <div class="text-muted small py-2">No edit history found.</div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-footer border-0 pt-0 bg-white">
                <button type="button" class="btn btn-light border px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#statusModal" data-bs-dismiss="modal"><i class="bi bi-pencil me-1"></i> Edit Task</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Details Modal Setup
    const detailsModal = document.getElementById('detailsModal');
    if (detailsModal) {
        detailsModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const taskId = button.getAttribute('data-task-id');
            const college = button.getAttribute('data-college');
            const dept = button.getAttribute('data-dept');
            const created = button.getAttribute('data-created');
            const createdBy = button.getAttribute('data-created-by');
            const status = button.getAttribute('data-status');
            const title = button.getAttribute('data-title');
            const desc = button.getAttribute('data-desc');
            const upurl = button.getAttribute('data-upurl');
            const prurl = button.getAttribute('data-prurl');
            const compurl = button.getAttribute('data-compurl');
            const pnotes = button.getAttribute('data-pnotes');
            const cnotes = button.getAttribute('data-cnotes');
            const priority = button.getAttribute('data-priority') || 'normal';

            document.getElementById('det_task_id').innerText = taskId;
            document.getElementById('det_college').innerText = college;
            document.getElementById('det_dept').innerText = dept;
            document.getElementById('det_created').innerText = created;
            document.getElementById('det_created_by').innerText = createdBy;
            document.getElementById('det_title').innerText = title;
            document.getElementById('det_desc').innerText = desc;
            document.getElementById('det_pnotes').innerText = pnotes || 'None.';
            document.getElementById('det_cnotes').innerText = cnotes || 'None.';

            // Status Badge
            const statusBadge = document.getElementById('det_status');
            statusBadge.innerText = status;
            statusBadge.className = 'popup-badge-pending';
            if (status === 'PROCESSING') statusBadge.className = 'popup-badge-processing';
            if (status === 'COMPLETED') statusBadge.className = 'popup-badge-completed';

            // Priority Badge
            const prioBadge = document.getElementById('det_priority');
            prioBadge.innerText = priority;
            prioBadge.className = 'popup-badge-priority-normal';
            if (priority === 'high') prioBadge.className = 'popup-badge-priority-high';
            if (priority === 'urgent') prioBadge.className = 'popup-badge-priority-urgent';

            const upurlLink = document.getElementById('det_upurl');
            const upurlWrapper = document.getElementById('det_upurl_wrapper');
            const upurlNone = document.getElementById('det_upurl_none');
            if (upurl) {
                upurlLink.href = upurl;
                upurlLink.innerText = upurl;
                upurlWrapper.style.display = 'block';
                upurlNone.style.display = 'none';
            } else {
                upurlWrapper.style.display = 'none';
                upurlNone.style.display = 'block';
            }

            const prurlLink = document.getElementById('det_prurl');
            const prurlWrapper = document.getElementById('det_prurl_wrapper');
            const prurlNone = document.getElementById('det_prurl_none');
            if (prurl) {
                prurlLink.href = prurl;
                prurlLink.innerText = prurl;
                prurlWrapper.style.display = 'block';
                prurlNone.style.display = 'none';
            } else {
                prurlWrapper.style.display = 'none';
                prurlNone.style.display = 'block';
            }

            const compurlLink = document.getElementById('det_compurl');
            const compurlWrapper = document.getElementById('det_compurl_wrapper');
            const compurlNone = document.getElementById('det_compurl_none');
            if (compurl) {
                compurlLink.href = compurl;
                compurlLink.innerText = compurl;
                compurlWrapper.style.display = 'block';
                compurlNone.style.display = 'none';
            } else {
                compurlWrapper.style.display = 'none';
                compurlNone.style.display = 'block';
            }

            // Files Classification parsing
            const filesData = JSON.parse(button.getAttribute('data-files') || '[]');
            const docContainer = document.getElementById('det_docs');
            const imgContainer = document.getElementById('det_imgs');
            docContainer.innerHTML = '';
            imgContainer.innerHTML = '';

            if (filesData.length === 0) {
                docContainer.innerHTML = '<span class="text-muted small">No documents.</span>';
                imgContainer.innerHTML = '<span class="text-muted small">No images.</span>';
            } else {
                let docCount = 0;
                let imgCount = 0;
                filesData.forEach((file) => {
                    const ext = file.file_name.split('.').pop().toLowerCase();
                    const isImg = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);

                    if (isImg) {
                        imgCount++;
                        imgContainer.innerHTML += `
                            <a href="${file.google_drive_url}" target="_blank" class="d-inline-block me-2 mb-2 p-1 border rounded bg-white text-decoration-none" title="${file.file_name}">
                                <div class="small text-center text-muted fw-bold mb-1" style="font-size: 0.65rem;">Image ${imgCount}</div>
                                <i class="bi bi-image fs-3 d-block text-center text-secondary"></i>
                            </a>
                        `;
                    } else {
                        docCount++;
                        docContainer.innerHTML += `
                            <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-light border rounded small">
                                <span class="text-truncate fw-medium me-3" style="max-width: 140px;" title="${file.file_name}">
                                    <i class="bi bi-file-earmark-text-fill text-secondary me-1"></i>${file.file_name}
                                </span>
                                <div>
                                    <a href="${file.google_drive_url}" target="_blank" class="btn btn-xs btn-light-primary rounded-pill fw-medium py-0 px-2 me-1 small">View</a>
                                    <a href="${file.google_drive_url}" download="${file.file_name}" target="_blank" class="btn btn-xs btn-primary py-0 px-2 small">Download</a>
                                </div>
                            </div>
                        `;
                    }
                });
                if (docCount === 0) docContainer.innerHTML = '<span class="text-muted small">No documents.</span>';
                if (imgCount === 0) imgContainer.innerHTML = '<span class="text-muted small">No images.</span>';
            }

            // Fetch edit history from AJAX (Super Admin Only)
            const editCountBadge = document.getElementById('det_edit_count');
            const historyContainer = document.getElementById('det_edit_history_container');
            
            if (editCountBadge && historyContainer) {
                editCountBadge.innerText = 'Loading...';
                historyContainer.innerHTML = '<div class="text-muted small py-2">Loading edit history...</div>';
                
                fetch(`../ajax/get_edit_history.php?submission_id=${id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            editCountBadge.innerText = '0 Edits';
                            historyContainer.innerHTML = `<div class="text-danger small py-2">Error: ${data.error}</div>`;
                            return;
                        }
                        
                        editCountBadge.innerText = `${data.edit_count} Edits`;
                        editCountBadge.className = data.edit_count > 0 ? 'badge bg-danger text-white rounded-pill ms-1' : 'badge bg-danger bg-opacity-10 text-danger rounded-pill ms-1';
                        
                        if (!data.history || data.history.length === 0) {
                            historyContainer.innerHTML = '<div class="text-muted small py-2">No edit history found.</div>';
                        } else {
                            let html = '';
                            data.history.forEach((h, index) => {
                                const editNum = data.history.length - index;
                                html += `
                                    <div class="p-3 mb-2 bg-light border rounded position-relative">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge bg-secondary py-1 px-2 text-white small">Edit #${editNum}</span>
                                            <small class="text-muted"><i class="bi bi-clock me-1"></i>${h.edited_at} by <strong>${h.editor_name}</strong></small>
                                        </div>
                                        <div class="mb-1"><strong>Title:</strong> <span class="text-dark">${h.title}</span></div>
                                        <div class="mb-1"><strong>Description:</strong> <span class="text-secondary small d-block">${h.description || '—'}</span></div>
                                        <div class="d-flex gap-3 text-muted small mt-2">
                                            <div><strong class="text-dark">Priority:</strong> <span class="text-uppercase font-monospace">${h.priority}</span></div>
                                            <div><strong class="text-dark">Update URL:</strong> ${h.update_url ? `<a href="${h.update_url}" target="_blank">${h.update_url}</a>` : '—'}</div>
                                        </div>
                                    </div>
                                `;
                            });
                            historyContainer.innerHTML = html;
                        }
                    })
                    .catch(err => {
                        editCountBadge.innerText = '0 Edits';
                        historyContainer.innerHTML = `<div class="text-danger small py-2">Error loading history: ${err.message}</div>`;
                    });
            }
        });
    }
    const editModal = document.getElementById('editStatusModal');
    if (editModal) {
        const statusSelect = document.getElementById('workflow_status');
        const procWrapper = document.getElementById('workflow_proc_wrapper');
        const compWrapper = document.getElementById('workflow_comp_wrapper');
        const submitBtn = document.getElementById('workflow_submit_btn');

        function toggleWorkflowFields(status) {
            if (status === 'processing') {
                procWrapper.style.display = 'block';
                compWrapper.style.display = 'none';
                submitBtn.innerText = 'Save Update';
            } else if (status === 'completed') {
                procWrapper.style.display = 'none';
                compWrapper.style.display = 'block';
                submitBtn.innerText = 'Mark Completed';
            } else {
                procWrapper.style.display = 'none';
                compWrapper.style.display = 'none';
                submitBtn.innerText = 'Save Status';
            }
        }

        statusSelect.addEventListener('change', (e) => {
            toggleWorkflowFields(e.target.value);
        });

        editModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const status = button.getAttribute('data-status');
            const pnotes = button.getAttribute('data-pnotes');
            const prurl = button.getAttribute('data-prurl');
            const cnotes = button.getAttribute('data-cnotes');
            const compurl = button.getAttribute('data-compurl');

            document.getElementById('workflow_id').value = id;
            statusSelect.value = status;
            document.getElementById('workflow_pnotes').value = pnotes || '';
            document.getElementById('workflow_prurl').value = prurl || '';
            document.getElementById('workflow_cnotes').value = cnotes || '';
            document.getElementById('workflow_compurl').value = compurl || '';

            toggleWorkflowFields(status);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
