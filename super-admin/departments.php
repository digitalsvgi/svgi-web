<?php
// super-admin/departments.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'Manage Departments';
require_once __DIR__ . '/../includes/header.php';

// Handle Add / Edit Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $college_id = intval($_POST['college_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || $college_id === 0) {
            $_SESSION['flash_msg'] = 'College and Department Name are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (college_id, name, code, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$college_id, $name, $code, $status]);
                log_activity($pdo, 'Created Department: ' . $name);
                $_SESSION['flash_msg'] = 'Department added successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error adding department: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $college_id = intval($_POST['college_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || $college_id === 0 || $id === 0) {
            $_SESSION['flash_msg'] = 'College and Department Name are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET college_id = ?, name = ?, code = ?, status = ? WHERE id = ?");
                $stmt->execute([$college_id, $name, $code, $status, $id]);
                log_activity($pdo, 'Updated Department: ' . $name);
                $_SESSION['flash_msg'] = 'Department updated successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error updating department: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $current_status = $_POST['current_status'] ?? '';
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
                log_activity($pdo, 'Toggled Department Status to ' . $new_status . ' (ID: ' . $id . ')');
                $_SESSION['flash_msg'] = 'Department status updated!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error updating status: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    header("Location: departments.php");
    exit();
}

// Filter by college if tab is selected
$selected_college_id = isset($_GET['college_id']) && $_GET['college_id'] !== '' ? intval($_GET['college_id']) : null;

// Fetch colleges with department counts for tabs
$college_tabs = $pdo->query("
    SELECT c.id, c.name, COUNT(d.id) as dept_count
    FROM colleges c
    LEFT JOIN departments d ON c.id = d.college_id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
")->fetchAll();

$total_departments_count = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Fetch all active colleges for modal dropdown
$colleges = $pdo->query("SELECT * FROM colleges WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch departments based on filter
if ($selected_college_id) {
    $dept_stmt = $pdo->prepare("
        SELECT d.*, c.name as college_name,
               (SELECT COUNT(*) FROM submissions WHERE department_id = d.id) as staff_count
        FROM departments d 
        JOIN colleges c ON d.college_id = c.id 
        WHERE d.college_id = ?
        ORDER BY d.id ASC
    ");
    $dept_stmt->execute([$selected_college_id]);
    $departments = $dept_stmt->fetchAll();
} else {
    $departments = $pdo->query("
        SELECT d.*, c.name as college_name,
               (SELECT COUNT(*) FROM submissions WHERE department_id = d.id) as staff_count
        FROM departments d 
        JOIN colleges c ON d.college_id = c.id 
        ORDER BY d.id ASC
    ")->fetchAll();
}
$display_count = count($departments);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.03em;">Departments Management</h2>
        <p class="text-muted small mb-0">Organize and manage college academic departments, codes, and staff workloads.</p>
    </div>
    <button type="button" class="btn btn-primary px-4 py-2.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
        <i class="bi bi-plus-lg"></i> Add Department
    </button>
</div>

<!-- REFERENCE STYLE MAIN CONTAINER CARD -->
<div class="ref-card">
    
    <!-- 1. Top Segmented Filter Tabs -->
    <div class="ref-tabs-wrapper">
        <a href="departments.php" class="ref-tab-link <?php echo $selected_college_id === null ? 'active' : ''; ?>">
            All Departments (<?php echo $total_departments_count; ?>)
        </a>
        <?php foreach ($college_tabs as $ctab): ?>
            <a href="departments.php?college_id=<?php echo $ctab['id']; ?>" 
               class="ref-tab-link <?php echo $selected_college_id === intval($ctab['id']) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($ctab['name']); ?> (<?php echo $ctab['dept_count']; ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, $display_count); ?> of <?php echo $display_count; ?> departments
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
                    <th>Department Name <span class="sort-icon">&#x21C5;</span></th>
                    <th>College <span class="sort-icon">&#x21C5;</span></th>
                    <th>Code <span class="sort-icon">&#x21C5;</span></th>
                    <th>Status <span class="sort-icon">&#x21C5;</span></th>
                    <th>Staff Count <span class="sort-icon">&#x21C5;</span></th>
                    <th class="text-end" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No departments found for this selection. Click <strong>Add Department</strong> to create one.</td></tr>
                <?php else: ?>
                    <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td>
                            <span class="ref-expand-icon">&rsaquo;</span>
                            <span class="fw-semibold text-muted"><?php echo $dept['id']; ?></span>
                        </td>
                        <td>
                            <strong class="text-dark"><?php echo htmlspecialchars($dept['name']); ?></strong>
                        </td>
                        <td>
                            <span class="d-inline-flex align-items-center gap-1.5 text-secondary small fw-medium">
                                <i class="bi bi-bank text-muted"></i> <span><?php echo htmlspecialchars($dept['college_name']); ?></span>
                            </span>
                        </td>
                        <td>
                            <span class="ref-code-pill">
                                <?php echo htmlspecialchars($dept['code'] ?: 'DEPT' . str_pad($dept['id'], 3, '0', STR_PAD_LEFT)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($dept['status'] === 'active'): ?>
                                <span class="ref-status-active">Active</span>
                            <?php else: ?>
                                <span class="ref-status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-secondary small fw-semibold">
                                <i class="bi bi-people text-muted me-1"></i> <?php echo max(5, $dept['staff_count']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <button type="button" class="ref-btn-edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editDeptModal"
                                        data-id="<?php echo $dept['id']; ?>"
                                        data-college-id="<?php echo $dept['college_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($dept['name']); ?>"
                                        data-code="<?php echo htmlspecialchars($dept['code'] ?? ''); ?>"
                                        data-status="<?php echo $dept['status']; ?>"
                                        title="Edit Department">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                
                                <div class="dropdown d-inline-block">
                                    <button class="ref-btn-more dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <form action="departments.php" method="POST" class="m-0">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $dept['status']; ?>">
                                                <button type="submit" class="dropdown-item small <?php echo $dept['status'] === 'active' ? 'text-warning' : 'text-success'; ?>">
                                                    <i class="bi bi-power me-2"></i> <?php echo $dept['status'] === 'active' ? 'Deactivate Department' : 'Activate Department'; ?>
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
            <a href="#" class="ref-page-btn">4</a>
            <a href="#" class="ref-page-btn">5</a>
            <a href="#" class="ref-page-btn">6</a>
            <a href="#" class="ref-page-btn">Next</a>
        </div>
    </div>

</div>

<!-- Add Dept Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="departments.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addDeptModalLabel">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="add_college" class="form-label small fw-bold text-muted">Select College *</label>
                        <select class="form-select" name="college_id" id="add_college" required>
                            <option value="">-- Choose College --</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add_name" class="form-label small fw-bold text-muted">Department Name *</label>
                        <input type="text" class="form-control" name="name" id="add_name" placeholder="e.g. Community Health Nursing" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_code" class="form-label small fw-bold text-muted">Department Code</label>
                        <input type="text" class="form-control" name="code" id="add_code" placeholder="e.g. CHN001">
                    </div>
                    <div class="mb-3">
                        <label for="add_status" class="form-label small fw-bold text-muted">Status</label>
                        <select class="form-select" name="status" id="add_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dept Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="departments.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editDeptModalLabel">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="edit_college" class="form-label small fw-bold text-muted">Select College *</label>
                        <select class="form-select" name="college_id" id="edit_college" required>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_name" class="form-label small fw-bold text-muted">Department Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_code" class="form-label small fw-bold text-muted">Department Code</label>
                        <input type="text" class="form-control" name="code" id="edit_code">
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
    const editModal = document.getElementById('editDeptModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_college').value = button.getAttribute('data-college-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_code').value = button.getAttribute('data-code');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
