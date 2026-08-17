<?php
// super-admin/backup.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'System Backup';
require_once __DIR__ . '/../includes/header.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    try {
        // Fetch all tables
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "-- College Work Management System Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql .= "\n\n" . $row[1] . ";\n\n";

            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rowCount = $stmt->rowCount();

            if ($rowCount > 0) {
                $sql .= "INSERT INTO `$table` VALUES \n";
                $rowsData = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $vals = [];
                    foreach ($row as $val) {
                        if (is_null($val)) {
                            $vals[] = "NULL";
                        } else {
                            $val = addslashes($val);
                            $val = str_replace("\n", "\\n", $val);
                            $vals[] = "'$val'";
                        }
                    }
                    $rowsData[] = "(" . implode(", ", $vals) . ")";
                }
                $sql .= implode(",\n", $rowsData) . ";\n";
            }
        }
        
        $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }

        $filename = 'backup_' . date('Y_m_d_H_i_s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;

        file_put_contents($filepath, $sql);
        
        log_activity($pdo, 'Created Database Backup', null, null, $filename);

        $success_msg = 'Database backup created successfully! You can download it below.';
    } catch (Exception $e) {
        $error_msg = 'Backup failed: ' . $e->getMessage();
    }
}

if (isset($_GET['download']) && !empty($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = __DIR__ . '/../backups/' . $file;
    if (file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($filepath);
        exit;
    } else {
        $error_msg = 'File not found.';
    }
}
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark">System Backup</h3>
    <p class="text-muted">Generate and download a full SQL backup of the database.</p>
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
    <div class="col-12 col-md-6">
        <div class="card glass-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hdd-network text-primary me-2"></i>Create New Backup</h5>
            </div>
            <div class="card-body p-4 text-center py-5">
                <i class="bi bi-database-down display-1 text-muted mb-4 d-block"></i>
                <p class="text-muted mb-4">Click the button below to generate a complete snapshot of the database, including all colleges, users, submissions, and messages.</p>
                <form action="backup.php" method="POST">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm px-4 py-2"><i class="bi bi-play-circle me-2"></i>Generate SQL Backup</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6">
        <div class="card glass-card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-archive text-primary me-2"></i>Available Backups</h5>
            </div>
            <div class="card-body p-4">
                <div class="list-group list-group-flush">
                    <?php
                    $backup_dir = __DIR__ . '/../backups';
                    if (is_dir($backup_dir)) {
                        $files = scandir($backup_dir);
                        $backups = [];
                        foreach ($files as $f) {
                            if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
                                $backups[] = $f;
                            }
                        }
                        rsort($backups); // Newest first

                        if (empty($backups)) {
                            echo '<div class="text-center text-muted py-4">No backups found.</div>';
                        } else {
                            foreach ($backups as $b) {
                                $size = round(filesize($backup_dir . '/' . $b) / 1024, 2) . ' KB';
                                echo '
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 bg-transparent border-bottom">
                                    <div>
                                        <i class="bi bi-filetype-sql text-primary me-2"></i>
                                        <span class="fw-medium">' . htmlspecialchars($b) . '</span>
                                        <div class="small text-muted mt-1">' . $size . '</div>
                                    </div>
                                    <a href="backup.php?download=' . urlencode($b) . '" class="btn btn-sm btn-light-primary rounded-pill fw-medium"><i class="bi bi-download"></i></a>
                                </div>';
                            }
                        }
                    } else {
                        echo '<div class="text-center text-muted py-4">No backups generated yet.</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
