<?php
// super-admin/reports.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']);

$page_title = 'System Reports';
require_once __DIR__ . '/../includes/header.php';

// Helper for date stats
function getDateStats($pdo, $startDate, $endDate = null) {
    $sql = "SELECT 
               COUNT(*) as total,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM submissions 
            WHERE 1=1";
    $params = [];
    if ($startDate) {
        $sql .= " AND created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $sql .= " AND created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Fetch stats
$college_reports = $pdo->query("
    SELECT c.name as college_name,
           COUNT(s.id) as total,
           SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending,
           SUM(CASE WHEN s.status = 'processing' THEN 1 ELSE 0 END) as processing,
           SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM colleges c
    LEFT JOIN submissions s ON s.college_id = c.id
    GROUP BY c.id
    ORDER BY total DESC
")->fetchAll();

$dept_reports = $pdo->query("
    SELECT d.name as dept_name,
           COUNT(s.id) as total,
           SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending,
           SUM(CASE WHEN s.status = 'processing' THEN 1 ELSE 0 END) as processing,
           SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM departments d
    LEFT JOIN submissions s ON s.department_id = d.id
    GROUP BY d.id
    ORDER BY total DESC
")->fetchAll();

$today_stats = getDateStats($pdo, date('Y-m-d'), date('Y-m-d'));
$week_stats = getDateStats($pdo, date('Y-m-d', strtotime('monday this week')), date('Y-m-d'));
$month_stats = getDateStats($pdo, date('Y-m-01'), date('Y-m-d'));

$custom_from = $_GET['custom_from'] ?? '';
$custom_to = $_GET['custom_to'] ?? '';
$custom_stats = null;
if (!empty($custom_from) && !empty($custom_to)) {
    $custom_stats = getDateStats($pdo, $custom_from, $custom_to);
}
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h3 class="fw-bold text-dark">Analytical Performance Reports</h3>
        <p class="text-muted">Track work items progress, submission velocity, and colleges participation weights.</p>
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-download me-1"></i> Export Data
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item small disabled text-muted" href="#"><i class="bi bi-file-pdf me-1"></i> PDF (Coming Soon)</a></li>
            <li><a class="dropdown-item small disabled text-muted" href="#"><i class="bi bi-file-earmark-excel me-1"></i> Excel (Coming Soon)</a></li>
            <li><a class="dropdown-item small disabled text-muted" href="#"><i class="bi bi-filetype-csv me-1"></i> CSV (Coming Soon)</a></li>
        </ul>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold" id="college-tab" data-bs-toggle="tab" data-bs-target="#college-pane" type="button" role="tab" aria-controls="college-pane" aria-selected="true">
            <i class="bi bi-bank me-1"></i> College-wise
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="dept-tab" data-bs-toggle="tab" data-bs-target="#dept-pane" type="button" role="tab" aria-controls="dept-pane" aria-selected="false">
            <i class="bi bi-building me-1"></i> Department-wise
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="date-tab" data-bs-toggle="tab" data-bs-target="#date-pane" type="button" role="tab" aria-controls="date-pane" aria-selected="false">
            <i class="bi bi-calendar-event me-1"></i> Date-wise
        </button>
    </li>
</ul>

<!-- Tabs Contents -->
<div class="tab-content" id="reportTabsContent">
    
    <!-- College-wise Pane -->
    <div class="tab-pane fade show active" id="college-pane" role="tabpanel" aria-labelledby="college-tab">
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table align-middle table-hover borderless-table">
                        <thead>
                            <tr class="text-muted small">
                                <th>College Name</th>
                                <th class="text-center">Total</th>
                                <th class="text-center text-warning">Pending</th>
                                <th class="text-center text-primary">Processing</th>
                                <th class="text-center text-success">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($college_reports as $rep): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rep['college_name']); ?></strong></td>
                                <td class="text-center"><span class="badge bg-secondary font-monospace"><?php echo $rep['total']; ?></span></td>
                                <td class="text-center font-monospace fw-bold text-warning"><?php echo intval($rep['pending']); ?></td>
                                <td class="text-center font-monospace fw-bold text-primary"><?php echo intval($rep['processing']); ?></td>
                                <td class="text-center font-monospace fw-bold text-success"><?php echo intval($rep['completed']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Department-wise Pane -->
    <div class="tab-pane fade" id="dept-pane" role="tabpanel" aria-labelledby="dept-tab">
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table align-middle table-hover borderless-table">
                        <thead>
                            <tr class="text-muted small">
                                <th>Department Name</th>
                                <th class="text-center">Total</th>
                                <th class="text-center text-warning">Pending</th>
                                <th class="text-center text-primary">Processing</th>
                                <th class="text-center text-success">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_reports as $rep): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rep['dept_name']); ?></strong></td>
                                <td class="text-center"><span class="badge bg-secondary font-monospace"><?php echo $rep['total']; ?></span></td>
                                <td class="text-center font-monospace fw-bold text-warning"><?php echo intval($rep['pending']); ?></td>
                                <td class="text-center font-monospace fw-bold text-primary"><?php echo intval($rep['processing']); ?></td>
                                <td class="text-center font-monospace fw-bold text-success"><?php echo intval($rep['completed']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Date-wise Pane -->
    <div class="tab-pane fade" id="date-pane" role="tabpanel" aria-labelledby="date-tab">
        <div class="row g-4">
            
            <!-- Predefined ranges summary cards -->
            <div class="col-12 col-md-4">
                <div class="card glass-card border-0 shadow-sm p-4 mb-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-1 text-primary"></i> Predefined Ranges</h5>
                    
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="fw-bold text-dark mb-1">Today</div>
                        <div class="small text-muted mb-2">Total Submissions: <strong class="text-dark"><?php echo $today_stats['total']; ?></strong></div>
                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.8rem;">
                            <span>Pending: <strong><?php echo intval($today_stats['pending']); ?></strong></span>
                            <span>Processing: <strong><?php echo intval($today_stats['processing']); ?></strong></span>
                            <span>Completed: <strong><?php echo intval($today_stats['completed']); ?></strong></span>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded mb-3">
                        <div class="fw-bold text-dark mb-1">This Week</div>
                        <div class="small text-muted mb-2">Total Submissions: <strong class="text-dark"><?php echo $week_stats['total']; ?></strong></div>
                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.8rem;">
                            <span>Pending: <strong><?php echo intval($week_stats['pending']); ?></strong></span>
                            <span>Processing: <strong><?php echo intval($week_stats['processing']); ?></strong></span>
                            <span>Completed: <strong><?php echo intval($week_stats['completed']); ?></strong></span>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded mb-0">
                        <div class="fw-bold text-dark mb-1">This Month</div>
                        <div class="small text-muted mb-2">Total Submissions: <strong class="text-dark"><?php echo $month_stats['total']; ?></strong></div>
                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.8rem;">
                            <span>Pending: <strong><?php echo intval($month_stats['pending']); ?></strong></span>
                            <span>Processing: <strong><?php echo intval($month_stats['processing']); ?></strong></span>
                            <span>Completed: <strong><?php echo intval($month_stats['completed']); ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom date filter pane -->
            <div class="col-12 col-md-8">
                <div class="card glass-card border-0 shadow-sm p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-funnel-fill me-1 text-success"></i> Custom Date Filter</h5>
                    
                    <form method="GET" action="reports.php" class="row g-3 align-items-end mb-4">
                        <!-- Keep date tab active on reload -->
                        <script>
                            document.addEventListener("DOMContentLoaded", () => {
                                if (window.location.search.indexOf('custom_from') > -1) {
                                    const tab = new bootstrap.Tab(document.getElementById('date-tab'));
                                    tab.show();
                                }
                            });
                        </script>

                        <div class="col-12 col-md-5">
                            <label for="custom_from" class="form-label small fw-bold text-muted mb-1">From Date</label>
                            <input type="date" name="custom_from" id="custom_from" class="form-control" value="<?php echo htmlspecialchars($custom_from); ?>" required>
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="custom_to" class="form-label small fw-bold text-muted mb-1">To Date</label>
                            <input type="date" name="custom_to" id="custom_to" class="form-control" value="<?php echo htmlspecialchars($custom_to); ?>" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm w-100">Apply</button>
                        </div>
                    </form>

                    <?php if ($custom_stats): ?>
                        <div class="p-4 border rounded bg-white">
                            <h6 class="fw-bold text-dark mb-3">Custom Range Results: <span class="text-primary font-monospace"><?php echo htmlspecialchars($custom_from); ?></span> to <span class="text-primary font-monospace"><?php echo htmlspecialchars($custom_to); ?></span></h6>
                            <div class="row text-center g-3">
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-light rounded">
                                        <div class="display-6 fw-bold text-dark"><?php echo $custom_stats['total']; ?></div>
                                        <div class="small text-muted">Total</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-warning bg-opacity-10 rounded">
                                        <div class="display-6 fw-bold text-warning"><?php echo intval($custom_stats['pending']); ?></div>
                                        <div class="small text-warning">Pending</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-primary bg-opacity-10 rounded">
                                        <div class="display-6 fw-bold text-primary"><?php echo intval($custom_stats['processing']); ?></div>
                                        <div class="small text-primary">Processing</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-success bg-opacity-10 rounded">
                                        <div class="display-6 fw-bold text-success"><?php echo intval($custom_stats['completed']); ?></div>
                                        <div class="small text-success">Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5 bg-light rounded border border-dashed">
                            <i class="bi bi-calendar-range display-5 mb-2"></i>
                            <p class="mb-0">Choose a custom from and to date range and click Apply to inspect data-wise distribution.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
