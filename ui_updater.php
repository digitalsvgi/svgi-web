<?php
$files = [
    'super-admin/colleges.php',
    'super-admin/departments.php',
    'super-admin/users.php',
    'super-admin/submissions.php',
    'super-admin/reports.php',
    'super-admin/settings.php',
    'super-admin/activity_logs.php',
    'super-admin/backup.php',
    'super-admin/college_details.php',
    'admin/submissions.php',
    'admin/reports.php',
    'admin/messages.php',
    'college/submissions.php',
    'college/messages.php',
    'profile.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Replace old buttons with modern sleek buttons
    $content = str_replace('btn-outline-primary', 'btn-light-primary rounded-pill fw-medium', $content);
    $content = str_replace('btn-outline-info', 'btn-light-info rounded-pill fw-medium', $content);
    $content = str_replace('btn-outline-danger', 'btn-light-danger rounded-pill fw-medium', $content);
    $content = str_replace('btn-outline-warning', 'btn-light-warning rounded-pill fw-medium', $content);
    
    // Add borderless class to all table-hover instances to look modern
    $content = preg_replace('/<table class="table align-middle table-hover">/', '<table class="table align-middle table-hover borderless-table">', $content);

    // Make header texts bolder
    $content = preg_replace('/<h3 class="fw-bold mb-0 text-dark">/', '<h3 class="fw-bold mb-0 text-dark tracking-tight">', $content);

    // Make modal headers borderless
    $content = preg_replace('/<div class="modal-header">/', '<div class="modal-header border-0 pb-0">', $content);
    $content = preg_replace('/<div class="modal-footer">/', '<div class="modal-footer border-0 pt-0">', $content);

    // Add padding to card-body if it only has p-3
    $content = preg_replace('/<div class="card-body p-3">/', '<div class="card-body p-4">', $content);

    // Ensure all badges are rounded-pill instead of just rounded
    $content = str_replace(' px-2.5 py-1.5 rounded"', ' px-3 py-1.5 rounded-pill fw-semibold"', $content);
    $content = str_replace(' px-2.5 py-1.5 rounded">', ' px-3 py-1.5 rounded-pill fw-semibold">', $content);
    
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
