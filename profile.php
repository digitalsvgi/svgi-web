<?php
// profile.php
require_once __DIR__ . '/includes/auth_check.php';
check_auth(); // Allow any authenticated user

$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Fetch user data
$stmt = $pdo->prepare("SELECT u.*, c.name as college_name FROM users u LEFT JOIN colleges c ON u.college_id = c.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        if (!empty($name)) {
            try {
                $upd = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $upd->execute([$name, $user_id]);
                $_SESSION['user_name'] = $name;
                $user['name'] = $name;
                
                log_activity($pdo, 'Updated Profile', null, null, $name);
                
                $success_msg = 'Profile updated successfully.';
            } catch (PDOException $e) {
                $error_msg = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error_msg = 'Name cannot be empty.';
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_msg = 'Please fill all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error_msg = 'New passwords do not match.';
        } else {
            if (password_verify($current_password, $user['password'])) {
                try {
                    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->execute([$hashed, $user_id]);
                    
                    log_activity($pdo, 'Changed Password');
                    
                    $success_msg = 'Password changed successfully.';
                } catch (PDOException $e) {
                    $error_msg = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error_msg = 'Current password is incorrect.';
            }
        }
    }
}
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark">My Profile</h3>
    <p class="text-muted">Manage your personal account settings.</p>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile Information -->
    <div class="col-12 col-md-6">
        <div class="card glass-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-badge text-primary me-2"></i>Account Details</h5>
            </div>
            <div class="card-body p-4">
                <form action="profile.php" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Email Address</label>
                        <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled>
                        <div class="form-text">Email address cannot be changed. Contact administrator if needed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Role</label>
                        <input type="text" class="form-control bg-light text-capitalize" value="<?php echo htmlspecialchars(str_replace('_', ' ', $user['role'])); ?>" readonly disabled>
                    </div>
                    
                    <?php if ($user['role'] === 'college_user'): ?>
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">Institution</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['college_name']); ?>" readonly disabled>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm"><i class="bi bi-save me-2"></i>Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="col-12 col-md-6">
        <div class="card glass-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock text-primary me-2"></i>Change Password</h5>
            </div>
            <div class="card-body p-4">
                <form action="profile.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control" required autocomplete="new-password">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm"><i class="bi bi-key me-2"></i>Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
