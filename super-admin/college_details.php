<?php
// super-admin/college_details.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'College Workspace';
require_once __DIR__ . '/../includes/header.php';

$college_id = intval($_GET['id'] ?? 0);

if ($college_id === 0) {
    redirect('/super-admin/colleges.php', 'Invalid College ID.', 'danger');
}

// Handle Add Department directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dept') {
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO departments (college_id, name, code, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$college_id, $name, $code]);
            $_SESSION['flash_msg'] = 'Department added to college!';
            $_SESSION['flash_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['flash_msg'] = 'Error adding department: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }
    }
    header("Location: college_details.php?id=" . $college_id);
    exit();
}

// Fetch College Profile
$c_stmt = $pdo->prepare("SELECT * FROM colleges WHERE id = ?");
$c_stmt->execute([$college_id]);
$college = $c_stmt->fetch();

if (!$college) {
    redirect('/super-admin/colleges.php', 'College not found.', 'danger');
}

// Stats
$pending_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE college_id = $college_id AND status = 'pending'")->fetchColumn();
$processing_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE college_id = $college_id AND status = 'processing'")->fetchColumn();
$completed_count = $pdo->query("SELECT COUNT(*) FROM submissions WHERE college_id = $college_id AND status = 'completed'")->fetchColumn();

// Fetch Departments
$d_stmt = $pdo->prepare("SELECT * FROM departments WHERE college_id = ? ORDER BY name ASC");
$d_stmt->execute([$college_id]);
$departments = $d_stmt->fetchAll();

// Fetch Users
$u_stmt = $pdo->prepare("SELECT * FROM users WHERE college_id = ? ORDER BY id DESC");
$u_stmt->execute([$college_id]);
$users = $u_stmt->fetchAll();

// Fetch Submissions
$s_stmt = $pdo->prepare("
    SELECT s.*, d.name as dept_name, u.name as user_name 
    FROM submissions s
    JOIN departments d ON s.department_id = d.id
    JOIN users u ON s.created_by = u.id
    WHERE s.college_id = ?
    ORDER BY s.id DESC
");
$s_stmt->execute([$college_id]);
$submissions = $s_stmt->fetchAll();

// Fetch Messages regarding this college's submissions
$m_stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name, u.role as sender_role, s.title as sub_title
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN submissions s ON m.submission_id = s.id
    WHERE s.college_id = ?
    ORDER BY m.id DESC LIMIT 10
");
$m_stmt->execute([$college_id]);
$messages = $m_stmt->fetchAll();
?>

<!-- Title & Stats Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 g-3">
    <div class="d-flex align-items-center">
        <?php if ($college['logo']): ?>
            <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo htmlspecialchars($college['logo']); ?>" alt="logo" class="rounded border me-3" style="width: 60px; height: 60px; object-fit: cover;">
        <?php else: ?>
            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted fw-bold me-3 fs-3" style="width: 60px; height: 60px;">
                <?php echo substr($college['name'], 0, 2); ?>
            </div>
        <?php endif; ?>
        <div>
            <h3 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($college['name']); ?> Workspace</h3>
            <span class="badge bg-secondary">Code: <?php echo htmlspecialchars($college['code']); ?></span>
        </div>
    </div>
    
    <!-- Stats Badge group -->
    <div class="d-flex gap-2">
        <div class="border rounded p-2.5 px-3 bg-white text-center shadow-sm">
            <span class="small text-muted d-block" style="font-size: 0.75rem;">Pending</span>
            <span class="fw-bold text-warning fs-5"><?php echo $pending_count; ?></span>
        </div>
        <div class="border rounded p-2.5 px-3 bg-white text-center shadow-sm">
            <span class="small text-muted d-block" style="font-size: 0.75rem;">Processing</span>
            <span class="fw-bold text-primary fs-5"><?php echo $processing_count; ?></span>
        </div>
        <div class="border rounded p-2.5 px-3 bg-white text-center shadow-sm">
            <span class="small text-muted d-block" style="font-size: 0.75rem;">Completed</span>
            <span class="fw-bold text-success fs-5"><?php echo $completed_count; ?></span>
        </div>
    </div>
</div>

<div class="card glass-card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 p-0 border-bottom">
        <ul class="nav nav-tabs card-header-tabs m-0 px-3" id="collegeTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 fw-bold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-pane" type="button" role="tab">Basic Info</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 fw-bold" id="depts-tab" data-bs-toggle="tab" data-bs-target="#depts-pane" type="button" role="tab">Departments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 fw-bold" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab">Users</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 fw-bold" id="subs-tab" data-bs-toggle="tab" data-bs-target="#subs-pane" type="button" role="tab">Submissions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 fw-bold" id="msg-tab" data-bs-toggle="tab" data-bs-target="#msg-pane" type="button" role="tab">Messages & Activity</button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4">
        <div class="tab-content" id="collegeTabContent">
            <!-- Basic Information Pane -->
            <div class="tab-pane fade show active" id="info-pane" role="tabpanel" aria-labelledby="info-tab">
                <h5 class="fw-bold mb-3">Institutional Profile</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small fw-bold">Official Email</label>
                        <p class="border-bottom pb-2 text-dark"><?php echo htmlspecialchars($college['email'] ?? 'Not set'); ?></p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small fw-bold">Phone Number</label>
                        <p class="border-bottom pb-2 text-dark"><?php echo htmlspecialchars($college['phone'] ?? 'Not set'); ?></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-muted small fw-bold">Campus Address</label>
                        <p class="border-bottom pb-2 text-dark"><?php echo nl2br(htmlspecialchars($college['address'] ?? 'Not set')); ?></p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small fw-bold">Operational Status</label>
                        <div>
                            <span class="badge bg-<?php echo $college['status'] === 'active' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $college['status'] === 'active' ? 'success' : 'danger'; ?> px-3 py-1.5 rounded-pill fw-semibold">
                                <?php echo ucfirst($college['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Departments Pane -->
            <div class="tab-pane fade" id="depts-pane" role="tabpanel" aria-labelledby="depts-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Registered Departments</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#directAddDeptModal">
                        <i class="bi bi-plus"></i> Add Department
                    </button>
                </div>
                <ul class="list-group">
                    <?php if (empty($departments)): ?>
                        <li class="list-group-item text-center text-muted">No departments registered for this college.</li>
                    <?php else: ?>
                        <?php foreach ($departments as $index => $dept): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo ($index + 1) . ". " . htmlspecialchars($dept['name']); ?> <?php if(!empty($dept['code'])): ?><code>[<?php echo htmlspecialchars($dept['code']); ?>]</code><?php endif; ?></span>
                                <span class="badge bg-<?php echo $dept['status'] === 'active' ? 'success' : 'secondary'; ?> bg-opacity-10 text-<?php echo $dept['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($dept['status']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Users Pane -->
            <div class="tab-pane fade" id="users-pane" role="tabpanel" aria-labelledby="users-tab">
                <h5 class="fw-bold mb-3">Associated Personnel</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Name</th>
                                <th>Email Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="3" class="text-center text-muted">No users registered for this college.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($u['email']); ?></code></td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $u['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($u['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Submissions Pane -->
            <div class="tab-pane fade" id="subs-pane" role="tabpanel" aria-labelledby="subs-tab">
                <h5 class="fw-bold mb-3">Institutional Work Requests</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Department</th>
                                <th>Title</th>
                                <th>Submitted By</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($submissions)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No submissions found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td><span class="small text-muted fw-semibold"><?php echo htmlspecialchars($sub['dept_name']); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                                    <td><span class="small text-muted"><?php echo htmlspecialchars($sub['user_name']); ?></span></td>
                                    <td><span class="small text-muted"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $sub['status']; ?> rounded text-capitalize">
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
            
            <!-- Messages & Activity Pane -->
            <div class="tab-pane fade" id="msg-pane" role="tabpanel" aria-labelledby="msg-tab">
                <div class="row">
                    <div class="col-12 col-lg-6 border-end pe-lg-4">
                        <h5 class="fw-bold mb-3">Discussion Feed</h5>
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-4">No message threads found.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush small">
                                <?php foreach ($messages as $msg): ?>
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
                    
                    <div class="col-12 col-lg-6 ps-lg-4">
                        <h5 class="fw-bold mb-3">Mock Log Activity</h5>
                        <div class="list-group list-group-flush small">
                            <div class="list-group-item border-0 p-2.5 bg-light rounded mb-2">
                                <div class="d-flex w-100 justify-content-between">
                                    <span class="fw-bold text-dark">Staff Login</span>
                                    <small class="text-muted">Today 08:30 AM</small>
                                </div>
                                <p class="mb-0 text-muted">College owner logged in successfully.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Dept Modal -->
<div class="modal fade" id="directAddDeptModal" tabindex="-1" aria-labelledby="directAddDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="college_details.php?id=<?php echo $college_id; ?>" method="POST">
                <input type="hidden" name="action" value="add_dept">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="directAddDeptModalLabel">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="dept_name" class="form-label small fw-bold text-muted">Department Name *</label>
                        <input type="text" class="form-control" name="name" id="dept_name" placeholder="e.g. Computer Science" required>
                    </div>
                    <div class="mb-3">
                        <label for="dept_code" class="form-label small fw-bold text-muted">Department Code</label>
                        <input type="text" class="form-control" name="code" id="dept_code" placeholder="e.g. CSE">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
