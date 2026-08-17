<?php
// super-admin/users.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';

// Handle Add / Edit Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'college_user';
        $college_id = ($role === 'college_user') ? intval($_POST['college_id'] ?? 0) : null;
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['flash_msg'] = 'Name, Email and Password are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (college_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$college_id, $name, $email, $password_hash, $role, $status]);
                log_activity($pdo, 'Created User: ' . $name);
                $_SESSION['flash_msg'] = 'User created successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error creating user: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'college_user';
        $college_id = ($role === 'college_user') ? intval($_POST['college_id'] ?? 0) : null;
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || $id === 0) {
            $_SESSION['flash_msg'] = 'Name and Email are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET college_id = ?, name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->execute([$college_id, $name, $email, $password_hash, $role, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET college_id = ?, name = ?, email = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->execute([$college_id, $name, $email, $role, $status, $id]);
                }
                log_activity($pdo, 'Updated User: ' . $name);
                $_SESSION['flash_msg'] = 'User details updated successfully!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error updating user: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $current_status = $_POST['current_status'] ?? '';
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';

        // Prevent self-deactivation
        if ($id === $_SESSION['user_id']) {
            $_SESSION['flash_msg'] = 'You cannot deactivate your own account!';
            $_SESSION['flash_type'] = 'danger';
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
                log_activity($pdo, 'Toggled User Status to ' . $new_status . ' (ID: ' . $id . ')');
                $_SESSION['flash_msg'] = 'User status updated!';
                $_SESSION['flash_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['flash_msg'] = 'Error updating user status: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    header("Location: users.php");
    exit();
}

// Fetch active colleges for association
$colleges = $pdo->query("SELECT * FROM colleges WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Filter by role if selected
$selected_role = $_GET['role'] ?? '';

// User counts for tabs
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$super_admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
$admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$college_user_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'college_user'")->fetchColumn();

// Fetch users with college info
if (!empty($selected_role)) {
    $u_stmt = $pdo->prepare("
        SELECT u.*, c.name as college_name 
        FROM users u 
        LEFT JOIN colleges c ON u.college_id = c.id 
        WHERE u.role = ?
        ORDER BY u.id DESC
    ");
    $u_stmt->execute([$selected_role]);
    $users = $u_stmt->fetchAll();
} else {
    $users = $pdo->query("
        SELECT u.*, c.name as college_name 
        FROM users u 
        LEFT JOIN colleges c ON u.college_id = c.id 
        ORDER BY u.id DESC
    ")->fetchAll();
}
$display_user_count = count($users);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.03em;">User Directory & Roles</h2>
        <p class="text-muted small mb-0">Manage system administrators, staff members, and institutional accounts.</p>
    </div>
    <button type="button" class="btn btn-primary px-4 py-2.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill"></i> Add System User
    </button>
</div>

<!-- REFERENCE STYLE MAIN CONTAINER CARD -->
<div class="ref-card">
    
    <!-- 1. Top Segmented Filter Tabs -->
    <div class="ref-tabs-wrapper">
        <a href="users.php" class="ref-tab-link <?php echo empty($selected_role) ? 'active' : ''; ?>">
            All Users (<?php echo $total_users; ?>)
        </a>
        <a href="users.php?role=super_admin" class="ref-tab-link <?php echo $selected_role === 'super_admin' ? 'active' : ''; ?>">
            Super Admins (<?php echo $super_admin_count; ?>)
        </a>
        <a href="users.php?role=admin" class="ref-tab-link <?php echo $selected_role === 'admin' ? 'active' : ''; ?>">
            System Admins (<?php echo $admin_count; ?>)
        </a>
        <a href="users.php?role=college_user" class="ref-tab-link <?php echo $selected_role === 'college_user' ? 'active' : ''; ?>">
            College Staff (<?php echo $college_user_count; ?>)
        </a>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, $display_user_count); ?> of <?php echo $display_user_count; ?> users
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
                    <th>User Profile <span class="sort-icon">&#x21C5;</span></th>
                    <th>Email Address <span class="sort-icon">&#x21C5;</span></th>
                    <th>System Role <span class="sort-icon">&#x21C5;</span></th>
                    <th>Institutional Scope <span class="sort-icon">&#x21C5;</span></th>
                    <th>Account Status <span class="sort-icon">&#x21C5;</span></th>
                    <th class="text-end" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No users found for this filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $usr): ?>
                    <tr>
                        <td>
                            <span class="ref-expand-icon">&rsaquo;</span>
                            <span class="fw-semibold text-muted"><?php echo $usr['id']; ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);">
                                    <?php echo strtoupper(substr($usr['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong class="text-dark d-block"><?php echo htmlspecialchars($usr['name']); ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><span class="text-dark small fw-medium"><?php echo htmlspecialchars($usr['email']); ?></span></td>
                        <td>
                            <?php 
                            $role_badge = ($usr['role'] === 'super_admin') ? 'primary' : (($usr['role'] === 'admin') ? 'info' : 'secondary');
                            ?>
                            <span class="badge bg-<?php echo $role_badge; ?> bg-opacity-10 text-<?php echo $role_badge; ?> px-2.5 py-1 rounded font-monospace text-uppercase" style="font-size: 0.72rem;">
                                <?php echo str_replace('_', ' ', $usr['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($usr['college_name']): ?>
                                <span class="d-inline-flex align-items-center gap-1.5 text-secondary small fw-medium">
                                    <i class="bi bi-bank text-muted"></i> <span><?php echo htmlspecialchars($usr['college_name']); ?></span>
                                </span>
                            <?php else: ?>
                                <span class="ref-code-pill" style="font-size: 0.7rem;">Central HQ</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($usr['status'] === 'active'): ?>
                                <span class="ref-status-active">Active</span>
                            <?php else: ?>
                                <span class="ref-status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <button type="button" class="ref-btn-edit" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal"
                                        data-id="<?php echo $usr['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($usr['name']); ?>"
                                        data-email="<?php echo htmlspecialchars($usr['email']); ?>"
                                        data-role="<?php echo $usr['role']; ?>"
                                        data-college-id="<?php echo $usr['college_id'] ?? ''; ?>"
                                        data-status="<?php echo $usr['status']; ?>"
                                        title="Edit User">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <div class="dropdown d-inline-block">
                                    <button class="ref-btn-more dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <form action="users.php" method="POST" class="m-0">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $usr['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $usr['status']; ?>">
                                                <button type="submit" class="dropdown-item small <?php echo $usr['status'] === 'active' ? 'text-warning' : 'text-success'; ?>" <?php echo $usr['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                    <i class="bi bi-power me-2"></i> <?php echo $usr['status'] === 'active' ? 'Deactivate User' : 'Activate User'; ?>
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
            <a href="#" class="ref-page-btn">Next</a>
        </div>
    </div>

</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="users.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="add_name" class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" class="form-control" name="name" id="add_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_email" class="form-label small fw-bold text-muted">Email Address</label>
                        <input type="email" class="form-control" name="email" id="add_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label small fw-bold text-muted">Password</label>
                        <input type="password" class="form-control" name="password" id="add_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_role" class="form-label small fw-bold text-muted">Role</label>
                        <select class="form-select" name="role" id="add_role" onchange="toggleCollegeSelect('add')" required>
                            <option value="college_user">College User (Submissions & Tracking)</option>
                            <option value="admin">System Admin (Process Work & Messaging)</option>
                            <option value="super_admin">Super Admin (Full Management)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="add_college_wrapper">
                        <label for="add_college_id" class="form-label small fw-bold text-muted">Associate College</label>
                        <select class="form-select" name="college_id" id="add_college_id">
                            <option value="">-- Select College --</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="users.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editUserModalLabel">Edit User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label small fw-bold text-muted">Email Address</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label small fw-bold text-muted">New Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password" id="edit_password">
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label small fw-bold text-muted">Role</label>
                        <select class="form-select" name="role" id="edit_role" onchange="toggleCollegeSelect('edit')" required>
                            <option value="college_user">College User (Submissions & Tracking)</option>
                            <option value="admin">System Admin (Process Work & Messaging)</option>
                            <option value="super_admin">Super Admin (Full Management)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="edit_college_wrapper">
                        <label for="edit_college_id" class="form-label small fw-bold text-muted">Associate College</label>
                        <select class="form-select" name="college_id" id="edit_college_id">
                            <option value="">-- Select College --</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
function toggleCollegeSelect(prefix) {
    const roleSelect = document.getElementById(`${prefix}_role`);
    const wrapper = document.getElementById(`${prefix}_college_wrapper`);
    const collegeId = document.getElementById(`${prefix}_college_id`);
    
    if (roleSelect.value === 'college_user') {
        wrapper.style.display = 'block';
        collegeId.setAttribute('required', 'required');
    } else {
        wrapper.style.display = 'none';
        collegeId.removeAttribute('required');
        collegeId.value = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Initial run for Modal fields
    toggleCollegeSelect('add');
    
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const email = button.getAttribute('data-email');
            const role = button.getAttribute('data-role');
            const collegeId = button.getAttribute('data-college-id');
            const status = button.getAttribute('data-status');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_status').value = status;
            
            toggleCollegeSelect('edit');
            document.getElementById('edit_college_id').value = collegeId;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
