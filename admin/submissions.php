<?php
// admin/submissions.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['admin']);

$page_title = 'Submissions Queue';
require_once __DIR__ . '/../includes/header.php';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $_SESSION['flash_msg'] = 'Admins are not authorized to update task status.';
        $_SESSION['flash_type'] = 'danger';
        header("Location: submissions.php");
        exit();
    } elseif ($action === 'send_message') {
        $submission_id = intval($_POST['submission_id'] ?? 0);
        $message_text = trim($_POST['message_text'] ?? '');
        $sender_id = $_SESSION['user_id'];

        if ($submission_id > 0 && !empty($message_text)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (submission_id, sender_id, message_text) VALUES (?, ?, ?)");
                $stmt->execute([$submission_id, $sender_id, $message_text]);

                // Send notification
                $sub = $pdo->prepare("SELECT created_by, title FROM submissions WHERE id = ?");
                $sub->execute([$submission_id]);
                $sData = $sub->fetch();
                if ($sData && $sData['created_by'] !== $sender_id) {
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $notif->execute([$sData['created_by'], 'New Message received', "Admin sent a message regarding '{$sData['title']}'.\n$message_text"]);
                }

                $_SESSION['flash_msg'] = 'Message sent!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error sending message: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    header("Location: submissions.php");
    exit();
}

// Filters
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
    <h3 class="fw-bold text-dark">Submissions Processing Desk</h3>
    <p class="text-muted">Process institutional submissions, change workflow statuses, and communicate with colleges.</p>
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
                <label for="search_query" class="form-label small fw-bold text-muted mb-1">Search (Task ID / Title / Description)</label>
                <input type="text" name="search" id="search_query" class="form-control form-control-sm" placeholder="Search keywords..." value="<?php echo htmlspecialchars($search_query); ?>">
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
            All Tasks (<?php echo count($submissions); ?>)
        </a>
        <a href="submissions.php?status=pending" class="ref-tab-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
            Pending Review
        </a>
        <a href="submissions.php?status=processing" class="ref-tab-link <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
            Under Processing
        </a>
        <a href="submissions.php?status=completed" class="ref-tab-link <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
            Completed
        </a>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, count($submissions)); ?> of <?php echo count($submissions); ?> records
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="ref-btn-bulk dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Bulk Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item small" href="#"><i class="bi bi-check2-all me-2"></i>Select All</a></li>
                    <li><a class="dropdown-item small text-warning" href="#"><i class="bi bi-pause-circle me-2"></i>Mark Processing</a></li>
                    <li><a class="dropdown-item small text-success" href="#"><i class="bi bi-check-circle me-2"></i>Mark Completed</a></li>
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
                    <tr><td colspan="7" class="text-center text-muted py-5">No submissions match the filters.</td></tr>
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
                        <td>
                            <span class="small text-dark fw-medium"><?php echo htmlspecialchars($sub['user_name']); ?></span>
                            <div class="text-muted small" style="font-size: 0.72rem;"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></div>
                        </td>
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
                                <button type="button" class="ref-btn-more text-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#messageModal"
                                        data-id="<?php echo $sub['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($sub['title']); ?>"
                                        title="Direct Message">
                                    <i class="bi bi-chat-dots"></i>
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

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="submissions.php" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="status_sub_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="statusModalLabel">Update Workflow Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="status_select" class="form-label small fw-bold text-muted">Workflow Step</label>
                        <select class="form-select" name="status" id="status_select" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <!-- Processing Status Fields -->
                    <div id="processing_fields_wrapper">
                        <div class="mb-3">
                            <label for="status_pnotes" class="form-label small fw-bold text-muted">Processing Note</label>
                            <textarea class="form-control" name="processing_notes" id="status_pnotes" rows="3" placeholder="Enter processing details..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status_prurl" class="form-label small fw-bold text-muted">Processing URL</label>
                            <input type="url" class="form-control" name="processing_url" id="status_prurl" placeholder="https://example.com/progress">
                        </div>
                    </div>

                    <!-- Completed Status Fields -->
                    <div id="completed_fields_wrapper">
                        <div class="mb-3">
                            <label for="status_compurl" class="form-label small fw-bold text-muted">Completed URL</label>
                            <input type="url" class="form-control" name="completed_url" id="status_compurl" placeholder="https://example.com/result">
                        </div>
                        <div class="mb-3">
                            <label for="status_cnotes" class="form-label small fw-bold text-muted">Completion Note</label>
                            <textarea class="form-control" name="completion_notes" id="status_cnotes" rows="3" placeholder="Enter completion details..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="status_submit_btn" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chat / Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="messageModalLabel">Submission Discussion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="chat_sub_title" class="mb-3 fw-bold text-primary"></div>
                <!-- Chat message history viewport -->
                <div id="chat_history" class="p-3 border rounded mb-3 bg-light" style="height: 300px; overflow-y: auto;">
                    <div class="text-center text-muted">Loading messages...</div>
                </div>
                <!-- Send new message form -->
                <form action="submissions.php" method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="submission_id" id="chat_sub_id">
                    <div class="input-group">
                        <textarea class="form-control" name="message_text" placeholder="Type clarification message..." rows="2" required></textarea>
                        <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm d-flex align-items-center px-4"><i class="bi bi-send me-1"></i> Send</button>
                    </div>
                </form>
            </div>
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

            </div>
            
            <div class="modal-footer border-0 pt-0 bg-white">
                <button type="button" class="btn btn-light border px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Close</button>
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
        });
    }
    // Status Modal Setup
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        const statusSelect = document.getElementById('status_select');
        const procWrapper = document.getElementById('processing_fields_wrapper');
        const compWrapper = document.getElementById('completed_fields_wrapper');
        const submitBtn = document.getElementById('status_submit_btn');

        function toggleStatusFields(status) {
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
            toggleStatusFields(e.target.value);
        });

        statusModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const status = button.getAttribute('data-status');
            const pnotes = button.getAttribute('data-pnotes');
            const prurl = button.getAttribute('data-prurl');
            const cnotes = button.getAttribute('data-cnotes');
            const compurl = button.getAttribute('data-compurl');

            document.getElementById('status_sub_id').value = id;
            statusSelect.value = status;
            document.getElementById('status_pnotes').value = pnotes || '';
            document.getElementById('status_prurl').value = prurl || '';
            document.getElementById('status_cnotes').value = cnotes || '';
            document.getElementById('status_compurl').value = compurl || '';

            toggleStatusFields(status);
        });
    }

    // Chat Message Modal Setup
    const messageModal = document.getElementById('messageModal');
    if (messageModal) {
        messageModal.addEventListener('show.bs.modal', async (event) => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const title = button.getAttribute('data-title');

            document.getElementById('chat_sub_id').value = id;
            document.getElementById('chat_sub_title').innerText = "Topic: " + title;
            
            const chatHistory = document.getElementById('chat_history');
            chatHistory.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Loading conversation...</div>';

            try {
                const response = await fetch(`../ajax/messages.php?submission_id=${id}`);
                const messages = await response.json();
                
                if (messages.length === 0) {
                    chatHistory.innerHTML = '<div class="text-center text-muted py-5">No messages yet. Send a message to start communicating.</div>';
                } else {
                    chatHistory.innerHTML = '';
                    messages.forEach(msg => {
                        const isMe = (parseInt(msg.sender_id) === <?php echo $_SESSION['user_id']; ?>);
                        const alignClass = isMe ? 'text-end' : '';
                        const cardBg = isMe ? 'bg-primary text-white' : 'bg-white border';
                        const wrapperClass = isMe ? 'd-flex flex-column align-items-end mb-3' : 'd-flex flex-column align-items-start mb-3';
                        
                        chatHistory.innerHTML += `
                            <div class="${wrapperClass}">
                                <div class="small text-muted mb-1">${escapeHtml(msg.sender_name)} (${escapeHtml(msg.sender_role)})</div>
                                <div class="p-2.5 rounded shadow-sm px-3 ${cardBg}" style="max-width: 75%; text-align: left; display: inline-block;">
                                    ${escapeHtml(msg.message_text)}
                                </div>
                                <div class="small text-muted mt-1" style="font-size: 0.75rem;">${escapeHtml(msg.created_at)}</div>
                            </div>
                        `;
                    });
                    chatHistory.scrollTop = chatHistory.scrollHeight;
                }
            } catch (err) {
                chatHistory.innerHTML = '<div class="text-center text-danger py-5">Failed to fetch messages.</div>';
            }
        });
    }
});

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
