<?php
// super-admin/colleges.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'Manage Colleges';
require_once __DIR__ . '/../includes/header.php';

// Handle Add / Edit Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // College Fields
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        // College Login User Fields
        $login_name = trim($_POST['login_name'] ?? '');
        $login_email = trim($_POST['login_email'] ?? '');
        $login_password = trim($_POST['login_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // Logo Upload
        $logo_name = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_dir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($logo_dir)) {
                mkdir($logo_dir, 0777, true);
            }
            $logo_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logo_name = uniqid('logo_', true) . '.' . $logo_ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $logo_dir . $logo_name);
        }

        if (empty($name) || empty($code) || empty($email) || empty($login_name) || empty($login_email) || empty($login_password)) {
            $_SESSION['flash_msg'] = 'All starred (*) fields are required.';
            $_SESSION['flash_type'] = 'danger';
        } elseif ($login_password !== $confirm_password) {
            $_SESSION['flash_msg'] = 'Passwords do not match.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert College
                $stmt = $pdo->prepare("
                    INSERT INTO colleges (name, code, email, phone, address, logo, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $code, $email, $phone, $address, $logo_name, $status]);
                $college_id = $pdo->lastInsertId();

                // 2. Insert Initial User Login
                $pwd_hash = password_hash($login_password, PASSWORD_BCRYPT);
                $user_stmt = $pdo->prepare("
                    INSERT INTO users (college_id, name, email, password, role, status) 
                    VALUES (?, ?, ?, ?, 'college_user', 'active')
                ");
                $user_stmt->execute([$college_id, $login_name, $login_email, $pwd_hash]);

                $pdo->commit();
                log_activity($pdo, 'Created College: ' . $name);
                $_SESSION['flash_msg'] = 'College and Login credentials created successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash_msg'] = 'Error: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        // Logo update check
        $logo_name = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_dir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($logo_dir)) {
                mkdir($logo_dir, 0777, true);
            }
            $logo_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logo_name = uniqid('logo_', true) . '.' . $logo_ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $logo_dir . $logo_name);
        }

        if (empty($name) || empty($code) || empty($email) || $id === 0) {
            $_SESSION['flash_msg'] = 'All starred (*) fields are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                if ($logo_name) {
                    $stmt = $pdo->prepare("
                        UPDATE colleges 
                        SET name = ?, code = ?, email = ?, phone = ?, address = ?, logo = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $code, $email, $phone, $address, $logo_name, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE colleges 
                        SET name = ?, code = ?, email = ?, phone = ?, address = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $code, $email, $phone, $address, $status, $id]);
                }
                log_activity($pdo, 'Updated College: ' . $name);
                $_SESSION['flash_msg'] = 'College updated successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error updating college: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $current_status = $_POST['current_status'] ?? '';
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE colleges SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
                log_activity($pdo, 'Toggled College Status to ' . $new_status . ' (ID: ' . $id . ')');
                $_SESSION['flash_msg'] = 'College status updated!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    header("Location: colleges.php");
    exit();
}

// Fetch all colleges with stats count (Departments, Users, Pending, Processing, Completed)
$colleges = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM departments WHERE college_id = c.id) as dept_count,
           (SELECT COUNT(*) FROM users WHERE college_id = c.id) as user_count,
           (SELECT COUNT(*) FROM submissions WHERE college_id = c.id AND status = 'pending') as pending_count,
           (SELECT COUNT(*) FROM submissions WHERE college_id = c.id AND status = 'processing') as processing_count,
           (SELECT COUNT(*) FROM submissions WHERE college_id = c.id AND status = 'completed') as completed_count
    FROM colleges c
    ORDER BY c.id DESC
")->fetchAll();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.03em;">Institutional Colleges</h2>
        <p class="text-muted small mb-0">Manage registered educational institutions, track workloads, and administer logins.</p>
    </div>
    <button type="button" class="btn btn-primary px-4 py-2.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollegeModal">
        <i class="bi bi-plus-lg"></i> Add New College
    </button>
</div>

<!-- REFERENCE STYLE MAIN CONTAINER CARD -->
<div class="ref-card">
    
    <!-- 1. Top Segmented Filter Tabs -->
    <div class="ref-tabs-wrapper">
        <a href="colleges.php" class="ref-tab-link active">
            All Institutions (<?php echo count($colleges); ?>)
        </a>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, count($colleges)); ?> of <?php echo count($colleges); ?> institutions
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="ref-btn-bulk dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Bulk Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li><a class="dropdown-item small" href="#"><i class="bi bi-check2-all me-2"></i>Select All</a></li>
                    <li><a class="dropdown-item small text-warning" href="#"><i class="bi bi-pause-circle me-2"></i>Set Inactive</a></li>
                    <li><a class="dropdown-item small text-success" href="#"><i class="bi bi-play-circle me-2"></i>Set Active</a></li>
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
                    <th style="width: 70px;">ID <span class="sort-icon">&#x21C5;</span></th>
                    <th>Institution Name <span class="sort-icon">&#x21C5;</span></th>
                    <th>Code <span class="sort-icon">&#x21C5;</span></th>
                    <th>Depts <span class="sort-icon">&#x21C5;</span></th>
                    <th>Users <span class="sort-icon">&#x21C5;</span></th>
                    <th>Workload <span class="sort-icon">&#x21C5;</span></th>
                    <th>Status <span class="sort-icon">&#x21C5;</span></th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($colleges)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No institutions registered yet. Click <strong>Add New College</strong> to create one.</td></tr>
                <?php else: ?>
                    <?php foreach ($colleges as $college): ?>
                    <tr>
                        <td>
                            <span class="ref-expand-icon">&rsaquo;</span>
                            <span class="fw-semibold text-muted"><?php echo $college['id']; ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2.5">
                                <?php if ($college['logo']): ?>
                                    <img src="<?php echo BASE_URL; ?>/uploads/logos/<?php echo htmlspecialchars($college['logo']); ?>" alt="logo" class="rounded-circle border" style="width: 32px; height: 32px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.75rem; background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);">
                                        <?php echo strtoupper(substr($college['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <a href="college_details.php?id=<?php echo $college['id']; ?>" class="text-decoration-none fw-bold text-dark d-block">
                                        <?php echo htmlspecialchars($college['name']); ?>
                                    </a>
                                    <span class="text-muted small" style="font-size: 0.75rem;"><?php echo htmlspecialchars($college['email'] ?? 'No email'); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="ref-code-pill">
                                <?php echo htmlspecialchars($college['code']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-secondary small fw-semibold">
                                <i class="bi bi-diagram-3 text-muted me-1"></i> <?php echo $college['dept_count']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-secondary small fw-semibold">
                                <i class="bi bi-people text-muted me-1"></i> <?php echo $college['user_count']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-inline-flex gap-1.5">
                                <span class="count-badge count-badge-warning" title="Pending"><?php echo $college['pending_count']; ?></span>
                                <span class="count-badge count-badge-info" title="Processing"><?php echo $college['processing_count']; ?></span>
                                <span class="count-badge count-badge-success" title="Completed"><?php echo $college['completed_count']; ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($college['status'] === 'active'): ?>
                                <span class="ref-status-active">Active</span>
                            <?php else: ?>
                                <span class="ref-status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <a href="college_details.php?id=<?php echo $college['id']; ?>" class="ref-btn-more" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="ref-btn-edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editCollegeModal"
                                        data-id="<?php echo $college['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($college['name']); ?>"
                                        data-code="<?php echo htmlspecialchars($college['code']); ?>"
                                        data-email="<?php echo htmlspecialchars($college['email'] ?? ''); ?>"
                                        data-phone="<?php echo htmlspecialchars($college['phone'] ?? ''); ?>"
                                        data-address="<?php echo htmlspecialchars($college['address'] ?? ''); ?>"
                                        data-status="<?php echo $college['status']; ?>"
                                        title="Edit Institution">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <div class="dropdown d-inline-block">
                                    <button class="ref-btn-more dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <form action="colleges.php" method="POST" class="m-0">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $college['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $college['status']; ?>">
                                                <button type="submit" class="dropdown-item small <?php echo $college['status'] === 'active' ? 'text-warning' : 'text-success'; ?>">
                                                    <i class="bi bi-power me-2"></i> <?php echo $college['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
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
            <a href="#" class="ref-page-btn">3</a>
            <a href="#" class="ref-page-btn">Next</a>
        </div>
    </div>

</div>

<!-- Add College Modal -->
<div class="modal fade" id="addCollegeModal" tabindex="-1" aria-labelledby="addCollegeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="colleges.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addCollegeModalLabel">Add College & User Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="row g-3">
                        <!-- College Details -->
                        <div class="col-12 col-md-6 border-end pe-md-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-bank me-2"></i>College Details</h6>
                            <div class="mb-3">
                                <label for="add_name" class="form-label small fw-bold text-muted">College Name *</label>
                                <input type="text" class="form-control" name="name" id="add_name" placeholder="Stanford College" required>
                            </div>
                            <div class="mb-3">
                                <label for="add_code" class="form-label small fw-bold text-muted">College Code *</label>
                                <input type="text" class="form-control" name="code" id="add_code" placeholder="STANFORD" required>
                            </div>
                            <div class="mb-3">
                                <label for="add_email" class="form-label small fw-bold text-muted">Official Email Address *</label>
                                <input type="email" class="form-control" name="email" id="add_email" placeholder="contact@college.edu" required>
                            </div>
                            <div class="mb-3">
                                <label for="add_phone" class="form-label small fw-bold text-muted">Phone Number</label>
                                <input type="text" class="form-control" name="phone" id="add_phone">
                            </div>
                            <div class="mb-3">
                                <label for="add_address" class="form-label small fw-bold text-muted">Address</label>
                                <textarea class="form-control" name="address" id="add_address" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="add_logo" class="form-label small fw-bold text-muted">College Logo</label>
                                <input class="form-control" type="file" name="logo" id="add_logo">
                            </div>
                            <div class="mb-3">
                                <label for="add_status" class="form-label small fw-bold text-muted">Status</label>
                                <select class="form-select" name="status" id="add_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Login Credentials -->
                        <div class="col-12 col-md-6 ps-md-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-shield-lock me-2"></i>Create College Login</h6>
                            <div class="mb-3">
                                <label for="login_name" class="form-label small fw-bold text-muted">Account Owner Name *</label>
                                <input type="text" class="form-control" name="login_name" id="login_name" placeholder="John Doe" required>
                            </div>
                            <div class="mb-3">
                                <label for="login_email" class="form-label small fw-bold text-muted">Login User Email *</label>
                                <input type="email" class="form-control" name="login_email" id="login_email" placeholder="owner@college.edu" required>
                            </div>
                            <div class="mb-3">
                                <label for="login_password" class="form-label small fw-bold text-muted">Password *</label>
                                <input type="password" class="form-control" name="login_password" id="login_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label small fw-bold text-muted">Confirm Password *</label>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save College & Login</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit College Modal -->
<div class="modal fade" id="editCollegeModal" tabindex="-1" aria-labelledby="editCollegeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="colleges.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editCollegeModalLabel">Edit College Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label small fw-bold text-muted">College Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_code" class="form-label small fw-bold text-muted">College Code *</label>
                        <input type="text" class="form-control" name="code" id="edit_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label small fw-bold text-muted">Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label small fw-bold text-muted">Phone</label>
                        <input type="text" class="form-control" name="phone" id="edit_phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label small fw-bold text-muted">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_logo" class="form-label small fw-bold text-muted">Logo File</label>
                        <input class="form-control" type="file" name="logo" id="edit_logo">
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label small fw-bold text-muted">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editCollegeModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_code').value = button.getAttribute('data-code');
            document.getElementById('edit_email').value = button.getAttribute('data-email');
            document.getElementById('edit_phone').value = button.getAttribute('data-phone');
            document.getElementById('edit_address').value = button.getAttribute('data-address');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
