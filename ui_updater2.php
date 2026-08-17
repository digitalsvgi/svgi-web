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

    // Make all primary and secondary buttons into modern pills
    $content = str_replace('btn btn-primary', 'btn btn-primary rounded-pill fw-medium px-4 shadow-sm', $content);
    $content = str_replace('btn btn-success', 'btn btn-success rounded-pill fw-medium px-4 shadow-sm', $content);
    $content = str_replace('btn btn-danger', 'btn btn-danger rounded-pill fw-medium px-4 shadow-sm', $content);
    $content = str_replace('btn btn-warning', 'btn btn-warning rounded-pill fw-medium px-4 shadow-sm', $content);
    
    file_put_contents($file, $content);
    echo "Updated buttons in $file\n";
}
?>
