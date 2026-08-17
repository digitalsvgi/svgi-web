<?php
// college/submissions.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/GoogleDriveHelper.php';
check_auth(['college_user']);

$page_title = 'My Submissions';
require_once __DIR__ . '/../includes/header.php';

$college_id = $_SESSION['college_id'];
$user_id = $_SESSION['user_id'];

// Populate college_name if missing
if (empty($_SESSION['college_name']) && !empty($college_id)) {
    $collStmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
    $collStmt->execute([$college_id]);
    $_SESSION['college_name'] = $collStmt->fetchColumn();
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_submission') {
        $department_id = intval($_POST['department_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $update_url = trim($_POST['update_url'] ?? '');
        $completed_url = trim($_POST['completed_url'] ?? '');

        if ($department_id === 0 || empty($title) || empty($description)) {
            $_SESSION['flash_msg'] = 'Department, Title, and Description are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            // Validation of attachments
            $errors = [];
            
            // Validate Images (Max 5, allowed JPG, JPEG, PNG, WEBP)
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $image_count = 0;
            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $img_names = array_filter($_FILES['images']['name']);
                $image_count = count($img_names);
                if ($image_count > 5) {
                    $errors[] = 'You can upload a maximum of 5 images.';
                } else {
                    foreach ($_FILES['images']['name'] as $idx => $name) {
                        if (!empty($name) && $_FILES['images']['error'][$idx] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            if (!in_array($ext, $allowed_img_exts)) {
                                $errors[] = "Image extension '.{$ext}' is not allowed. (Allowed: JPG, JPEG, PNG, WEBP)";
                            }
                        }
                    }
                }
            }

            // Validate Documents (Allowed: PDF, DOC, DOCX, TXT, XLSX, PPTX, ZIP)
            $allowed_doc_exts = ['pdf', 'doc', 'docx', 'txt', 'xlsx', 'pptx', 'zip'];
            if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
                foreach ($_FILES['documents']['name'] as $idx => $name) {
                    if (!empty($name) && $_FILES['documents']['error'][$idx] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_doc_exts)) {
                            $errors[] = "Document extension '.{$ext}' is not allowed. (Allowed: PDF, DOC, DOCX, TXT, XLSX, PPTX, ZIP)";
                        }
                    }
                }
            }

            if (!empty($errors)) {
                $_SESSION['flash_msg'] = implode('<br>', $errors);
                $_SESSION['flash_type'] = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // 1. Create Submission
                    $stmt = $pdo->prepare("
                        INSERT INTO submissions (college_id, department_id, title, description, update_url, completed_url, priority, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                    ");
                    $stmt->execute([$college_id, $department_id, $title, $description, $update_url, $completed_url, $priority, $user_id]);
                    $submission_id = $pdo->lastInsertId();

                    // Retrieve names for folder path
                    $cRow = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
                    $cRow->execute([$college_id]);
                    $collegeName = $cRow->fetchColumn();

                    $dRow = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                    $dRow->execute([$department_id]);
                    $deptName = $dRow->fetchColumn();

                    $taskId = get_task_id($submission_id, date('Y-m-d H:i:s'));

                    // Resolve Google Drive Folder Structure
                    $taskFolderId = GoogleDriveHelper::resolveTaskFolder($collegeName, $deptName);

                    $upFolder = $pdo->prepare("UPDATE submissions SET google_drive_folder_id = ? WHERE id = ?");
                    $upFolder->execute([$taskFolderId, $submission_id]);

                    // Upload Helper function
                    $processUploads = function($filesArr, $subId, $folderId, $tableTarget) use ($pdo) {
                        if (!isset($filesArr) || !is_array($filesArr['name'])) return;
                        
                        foreach ($filesArr['name'] as $i => $file_name) {
                            if (!empty($file_name) && $filesArr['error'][$i] === UPLOAD_ERR_OK) {
                                $file_type = $filesArr['type'][$i];
                                $file_size = $filesArr['size'][$i];
                                $tmp_name  = $filesArr['tmp_name'][$i];
                                
                                $upload_dir = __DIR__ . '/../uploads/';
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0777, true);
                                }
                                
                                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                                $new_file_name = uniqid('cwm_tmp_', true) . '.' . $file_ext;
                                $temp_path = $upload_dir . $new_file_name;
                                
                                if (move_uploaded_file($tmp_name, $temp_path)) {
                                    $driveResult = GoogleDriveHelper::uploadFile($temp_path, $file_name, $file_type, $folderId);
                                    
                                    // Insert into dynamic table (submission_files or submission_images)
                                    $target_stmt = $pdo->prepare("
                                        INSERT INTO `{$tableTarget}` (submission_id, file_name, file_type, google_drive_file_id, google_drive_url, file_size) 
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $target_stmt->execute([
                                        $subId, 
                                        $file_name, 
                                        $file_type, 
                                        $driveResult['id'], 
                                        $driveResult['webViewLink'], 
                                        $file_size
                                    ]);

                                    // Also insert into submission_attachments for backwards compatibility
                                    $att_stmt = $pdo->prepare("
                                        INSERT INTO submission_attachments (submission_id, file_name, file_type, google_drive_file_id, google_drive_url, file_size) 
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $att_stmt->execute([
                                        $subId, 
                                        $file_name, 
                                        $file_type, 
                                        $driveResult['id'], 
                                        $driveResult['webViewLink'], 
                                        $file_size
                                    ]);
                                    
                                    if (file_exists($temp_path) && strpos($driveResult['id'], 'mock_gd') === false) {
                                        unlink($temp_path);
                                    }
                                }
                            }
                        }
                    };

                    // Process upload groups
                    $processUploads($_FILES['documents'], $submission_id, $taskFolderId, 'submission_files');
                    $processUploads($_FILES['images'], $submission_id, $taskFolderId, 'submission_images');

                    // Record initial status history entry
                    $hist_stmt = $pdo->prepare("
                        INSERT INTO submission_status_history (submission_id, status, notes, changed_by) 
                        VALUES (?, 'pending', 'Initial submission created', ?)
                    ");
                    $hist_stmt->execute([$submission_id, $user_id]);

                    // 3. Notify Admin users
                    $taskId = get_task_id($submission_id, date('Y-m-d H:i:s'));
                    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
                    foreach ($admins as $adm) {
                        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                        $notif_stmt->execute([
                            $adm['id'], 
                            'New Submission Received', 
                            "New submission received.\n" . $taskId
                        ]);
                    }

                    // Log activity
                    log_activity($pdo, "Created " . $taskId, $taskId, null, "pending");

                    $pdo->commit();
                    $_SESSION['flash_msg'] = 'Submission created successfully!';
                    $_SESSION['flash_type'] = 'success';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['flash_msg'] = 'Database error: ' . $e->getMessage();
                    $_SESSION['flash_type'] = 'danger';
                }
            }
        }
        header("Location: submissions.php");
        exit();
    } elseif ($action === 'edit_submission') {
        $submission_id = intval($_POST['submission_id'] ?? 0);
        $department_id = intval($_POST['department_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $update_url = trim($_POST['update_url'] ?? '');
        $completed_url = trim($_POST['completed_url'] ?? '');
        $delete_files = $_POST['delete_files'] ?? []; // Array of attachment IDs to delete

        if ($submission_id === 0 || $department_id === 0 || empty($title) || empty($description)) {
            $_SESSION['flash_msg'] = 'ID, Department, Title, and Description are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            // Authorization Check: Ensure target submission belongs to this college!
            $check = $pdo->prepare("SELECT college_id, status, google_drive_folder_id FROM submissions WHERE id = ?");
            $check->execute([$submission_id]);
            $subRow = $check->fetch();

            if (!$subRow || $subRow['college_id'] != $college_id) {
                $_SESSION['flash_msg'] = 'Unauthorized access.';
                $_SESSION['flash_type'] = 'danger';
                header("Location: submissions.php");
                exit();
            }

            // Verify status: only pending submissions can be edited by college users
            if (strtolower($subRow['status']) !== 'pending') {
                $_SESSION['flash_msg'] = 'Only pending submissions can be modified.';
                $_SESSION['flash_type'] = 'warning';
                header("Location: submissions.php");
                exit();
            }

            $taskFolderId = $subRow['google_drive_folder_id'];

            // Validation of attachments
            $errors = [];
            
            // Validate Images (Max 5, allowed JPG, JPEG, PNG, WEBP)
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $image_count = 0;
            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $img_names = array_filter($_FILES['images']['name']);
                $image_count = count($img_names);
                if ($image_count > 5) {
                    $errors[] = 'You can upload a maximum of 5 images.';
                } else {
                    foreach ($_FILES['images']['name'] as $idx => $name) {
                        if (!empty($name) && $_FILES['images']['error'][$idx] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            if (!in_array($ext, $allowed_img_exts)) {
                                $errors[] = "Image extension '.{$ext}' is not allowed. (Allowed: JPG, JPEG, PNG, WEBP)";
                            }
                        }
                    }
                }
            }

            // Validate Documents (Allowed: PDF, DOC, DOCX, TXT, XLSX, PPTX, ZIP)
            $allowed_doc_exts = ['pdf', 'doc', 'docx', 'txt', 'xlsx', 'pptx', 'zip'];
            if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
                foreach ($_FILES['documents']['name'] as $idx => $name) {
                    if (!empty($name) && $_FILES['documents']['error'][$idx] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_doc_exts)) {
                            $errors[] = "Document extension '.{$ext}' is not allowed. (Allowed: PDF, DOC, DOCX, TXT, XLSX, PPTX, ZIP)";
                        }
                    }
                }
            }

            if (!empty($errors)) {
                $_SESSION['flash_msg'] = implode('<br>', $errors);
                $_SESSION['flash_type'] = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // 1. Fetch current details to log as old history
                    $oldDetailsQuery = $pdo->prepare("SELECT title, description, priority, update_url FROM submissions WHERE id = ?");
                    $oldDetailsQuery->execute([$submission_id]);
                    $oldDetails = $oldDetailsQuery->fetch();

                    // 2. Insert into submission_edit_history
                    $histInsert = $pdo->prepare("
                        INSERT INTO submission_edit_history (submission_id, title, description, priority, update_url, edited_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $histInsert->execute([
                        $submission_id,
                        $oldDetails['title'],
                        $oldDetails['description'],
                        $oldDetails['priority'],
                        $oldDetails['update_url'],
                        $_SESSION['user_id']
                    ]);

                    // 3. Update Submission and increment edit_count
                    $upStmt = $pdo->prepare("
                        UPDATE submissions 
                        SET department_id = ?, title = ?, description = ?, update_url = ?, completed_url = ?, priority = ?, edit_count = edit_count + 1 
                        WHERE id = ?
                    ");
                    $upStmt->execute([$department_id, $title, $description, $update_url, $completed_url, $priority, $submission_id]);

                    // 2. Handle File Deletions
                    if (!empty($delete_files)) {
                        foreach ($delete_files as $file_id) {
                            // Fetch file info to delete from Drive
                            $fInfo = $pdo->prepare("SELECT google_drive_file_id FROM submission_attachments WHERE id = ? AND submission_id = ?");
                            $fInfo->execute([$file_id, $submission_id]);
                            $gdId = $fInfo->fetchColumn();

                            if ($gdId) {
                                GoogleDriveHelper::deleteFile($gdId);
                            }

                            // Delete from DB tables
                            $del1 = $pdo->prepare("DELETE FROM submission_attachments WHERE id = ? AND submission_id = ?");
                            $del1->execute([$file_id, $submission_id]);

                            $del2 = $pdo->prepare("DELETE FROM submission_files WHERE google_drive_file_id = ? AND submission_id = ?");
                            $del2->execute([$gdId, $submission_id]);

                            $del3 = $pdo->prepare("DELETE FROM submission_images WHERE google_drive_file_id = ? AND submission_id = ?");
                            $del3->execute([$gdId, $submission_id]);
                        }
                    }

                    // 3. Resolve target upload folder dynamically by current date
                    $cRow = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
                    $cRow->execute([$college_id]);
                    $collegeName = $cRow->fetchColumn();

                    $dRow = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                    $dRow->execute([$department_id]);
                    $deptName = $dRow->fetchColumn();

                    $taskFolderId = GoogleDriveHelper::resolveTaskFolder($collegeName, $deptName);

                    $upFolder = $pdo->prepare("UPDATE submissions SET google_drive_folder_id = ? WHERE id = ?");
                    $upFolder->execute([$taskFolderId, $submission_id]);

                    $processUploads = function($filesArr, $subId, $folderId, $tableTarget) use ($pdo) {
                        if (!isset($filesArr) || !is_array($filesArr['name'])) return;
                        
                        foreach ($filesArr['name'] as $i => $file_name) {
                            if (!empty($file_name) && $filesArr['error'][$i] === UPLOAD_ERR_OK) {
                                $file_type = $filesArr['type'][$i];
                                $file_size = $filesArr['size'][$i];
                                $tmp_name  = $filesArr['tmp_name'][$i];
                                
                                $upload_dir = __DIR__ . '/../uploads/';
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0777, true);
                                }
                                
                                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                                $new_file_name = uniqid('cwm_tmp_', true) . '.' . $file_ext;
                                $temp_path = $upload_dir . $new_file_name;
                                
                                if (move_uploaded_file($tmp_name, $temp_path)) {
                                    $driveResult = GoogleDriveHelper::uploadFile($temp_path, $file_name, $file_type, $folderId);
                                    
                                    // Insert into dynamic table (submission_files or submission_images)
                                    $target_stmt = $pdo->prepare("
                                        INSERT INTO `{$tableTarget}` (submission_id, file_name, file_type, google_drive_file_id, google_drive_url, file_size) 
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $target_stmt->execute([
                                        $subId, 
                                        $file_name, 
                                        $file_type, 
                                        $driveResult['id'], 
                                        $driveResult['webViewLink'], 
                                        $file_size
                                    ]);

                                    // Also insert into submission_attachments for backwards compatibility
                                    $att_stmt = $pdo->prepare("
                                        INSERT INTO submission_attachments (submission_id, file_name, file_type, google_drive_file_id, google_drive_url, file_size) 
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $att_stmt->execute([
                                        $subId, 
                                        $file_name, 
                                        $file_type, 
                                        $driveResult['id'], 
                                        $driveResult['webViewLink'], 
                                        $file_size
                                    ]);
                                    
                                    if (file_exists($temp_path) && strpos($driveResult['id'], 'mock_gd') === false) {
                                        unlink($temp_path);
                                    }
                                }
                            }
                        }
                    };

                    $processUploads($_FILES['documents'], $submission_id, $taskFolderId, 'submission_files');
                    $processUploads($_FILES['images'], $submission_id, $taskFolderId, 'submission_images');

                    // Record update status history entry
                    $hist_stmt = $pdo->prepare("
                        INSERT INTO submission_status_history (submission_id, status, notes, changed_by) 
                        VALUES (?, (SELECT status FROM submissions WHERE id = ?), 'Submission details updated by College User', ?)
                    ");
                    $hist_stmt->execute([$submission_id, $submission_id, $user_id]);

                    $taskId = get_task_id($submission_id, date('Y-m-d H:i:s'));
                    log_activity($pdo, "Updated details of " . $taskId, $taskId, null, "updated");

                    $pdo->commit();
                    $_SESSION['flash_msg'] = 'Submission updated successfully!';
                    $_SESSION['flash_type'] = 'success';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['flash_msg'] = 'Database error: ' . $e->getMessage();
                    $_SESSION['flash_type'] = 'danger';
                }
            }
        }
        header("Location: submissions.php");
        exit();
    } elseif ($action === 'send_message') {
        $submission_id = intval($_POST['submission_id'] ?? 0);
        $message_text = trim($_POST['message_text'] ?? '');

        if ($submission_id > 0 && !empty($message_text)) {
            try {
                // Authorization Check: Ensure target submission belongs to this college!
                $check = $pdo->prepare("SELECT college_id FROM submissions WHERE id = ?");
                $check->execute([$submission_id]);
                $ownerCollege = $check->fetchColumn();

                if ($ownerCollege != $college_id) {
                    $_SESSION['flash_msg'] = 'Unauthorized access.';
                    $_SESSION['flash_type'] = 'danger';
                    header("Location: submissions.php");
                    exit();
                }

                $stmt = $pdo->prepare("INSERT INTO messages (submission_id, sender_id, message_text) VALUES (?, ?, ?)");
                $stmt->execute([$submission_id, $user_id, $message_text]);

                // Notify admin users
                $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
                foreach ($admins as $adm) {
                    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $notif_stmt->execute([
                        $adm['id'], 
                        'New message from College User', 
                        "Message regarding submission #{$submission_id}: '{$message_text}'"
                    ]);
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

// Fetch all active departments for this college
$depts = $pdo->prepare("SELECT * FROM departments WHERE college_id = ? AND status = 'active' ORDER BY name ASC");
$depts->execute([$college_id]);
$departments = $depts->fetchAll();

// Fetch submissions for this college
$sub_stmt = $pdo->prepare("
    SELECT s.*, d.name as dept_name, u.name as user_name
    FROM submissions s
    JOIN departments d ON s.department_id = d.id
    JOIN users u ON s.created_by = u.id
    WHERE s.college_id = ?
    ORDER BY s.id DESC
");
$sub_stmt->execute([$college_id]);
$submissions = $sub_stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark tracking-tight">Submissions Desk</h3>
    <button type="button" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newSubModal">
        <i class="bi bi-file-earmark-plus me-2"></i> Submit Work Request
    </button>
</div>

<!-- REFERENCE STYLE MAIN CONTAINER CARD -->
<div class="ref-card">
    
    <!-- 1. Top Segmented Filter Tabs -->
    <div class="ref-tabs-wrapper">
        <a href="submissions.php" class="ref-tab-link active">
            All Submissions (<?php echo count($submissions); ?>)
        </a>
    </div>

    <!-- 2. Control Toolbar -->
    <div class="ref-toolbar">
        <div class="ref-showing-text">
            Showing 1 to <?php echo min(10, count($submissions)); ?> of <?php echo count($submissions); ?> work records
        </div>
        <div class="d-flex align-items-center gap-2">
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
                    <th style="min-width: 200px;">Department <span class="sort-icon">&#x21C5;</span></th>
                    <th style="min-width: 400px;">Submission Title <span class="sort-icon">&#x21C5;</span></th>
                    <th style="width: 150px;">File Attachment <span class="sort-icon">&#x21C5;</span></th>
                    <th style="width: 180px;">Submitted By <span class="sort-icon">&#x21C5;</span></th>
                    <th style="width: 120px;">Status <span class="sort-icon">&#x21C5;</span></th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No submissions from your college yet. Click <strong>Submit Work Request</strong> above.</td></tr>
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
                        <td><strong class="text-dark"><?php echo htmlspecialchars($sub['dept_name']); ?></strong></td>
                        <td>
                            <strong class="text-dark d-block"><?php echo htmlspecialchars($sub['title']); ?></strong>
                            <div class="text-muted small" style="font-size: 0.78rem;"><?php echo htmlspecialchars(substr($sub['description'] ?? '', 0, 45)) . (strlen($sub['description'] ?? '') > 45 ? '...' : ''); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($attachments)): ?>
                                <?php foreach ($attachments as $att): ?>
                                    <div class="mb-1 text-truncate" style="max-width: 140px;">
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
                                        data-id="<?php echo $sub['id']; ?>"
                                        data-dept-id="<?php echo $sub['department_id']; ?>"
                                        data-task-id="<?php echo get_task_id($sub['id'], $sub['created_at']); ?>"
                                        data-college="<?php echo htmlspecialchars($_SESSION['college_name'] ?? 'My Institution'); ?>"
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
                                <button type="button" class="ref-btn-more" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#messageModal"
                                        data-id="<?php echo $sub['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($sub['title']); ?>"
                                        title="Direct Chat with Admin">
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

<!-- Submit Request Modal -->
<div class="modal fade" id="newSubModal" tabindex="-1" aria-labelledby="newSubModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="submissions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_submission">
                <div class="modal-header popup-header-gradient d-flex align-items-center justify-content-between">
                    <div class="popup-title-container">
                        <div class="popup-title-icon">
                            <i class="bi bi-file-earmark-plus"></i>
                        </div>
                        <h5 class="modal-title font-monospace fw-bold m-0" id="newSubModalLabel">New Work Submission</h5>
                    </div>
                    <button type="button" class="popup-close-btn-circle" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body py-4">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="department_id" class="modern-form-label">Department</label>
                            <select class="form-select modern-form-input" name="department_id" id="department_id" required>
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="priority" class="modern-form-label">Priority</label>
                            <select class="form-select modern-form-input" name="priority" id="priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="title" class="modern-form-label">Work Request Title</label>
                        <input type="text" class="form-control modern-form-input" name="title" id="title" placeholder="e.g. Annual Budget Report Submission" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="modern-form-label">Description *</label>
                        <textarea class="form-control modern-form-input" name="description" id="description" rows="4" placeholder="Describe the submission details..." required></textarea>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="update_url" class="modern-form-label">Update URL</label>
                            <input type="url" class="form-control modern-form-input" name="update_url" id="update_url" placeholder="https://example.com/status">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="completed_url" class="modern-form-label">Completed URL</label>
                            <input type="url" class="form-control modern-form-input" name="completed_url" id="completed_url" placeholder="https://example.com/result">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="doc_uploads" class="modern-form-label">Upload Documents</label>
                            <input class="form-control modern-form-input" type="file" name="documents[]" id="doc_uploads" accept=".pdf,.doc,.docx,.txt,.xlsx,.pptx,.zip" multiple>
                            <div class="form-text small text-muted">Accepts PDF, DOC, DOCX, TXT, XLSX, PPTX, ZIP.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="img_uploads" class="modern-form-label">Upload Images</label>
                            <input class="form-control modern-form-input" type="file" name="images[]" id="img_uploads" accept="image/*" multiple>
                            <div class="form-text small text-muted">Accepts JPG, JPEG, PNG, WEBP. Max 5 images.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 bg-white">
                    <button type="button" class="btn btn-light border px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm"><i class="bi bi-check-lg me-1"></i> Submit Request</button>
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
                <h5 class="modal-title fw-bold" id="messageModalLabel">Clarification chat with Administrator</h5>
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
                        <textarea class="form-control" name="message_text" placeholder="Type message to admin..." rows="2" required></textarea>
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
                                 <button type="button" class="popup-upload-btn btn-sm" id="details_add_doc_btn" onclick="openEditModalAndUpload('documents')"><i class="bi bi-plus-lg"></i> Add Document</button>
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
                                <button type="button" class="popup-upload-btn btn-sm" id="details_add_img_btn" onclick="openEditModalAndUpload('images')"><i class="bi bi-plus-lg"></i> Add Image</button>
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
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="details_edit_btn" data-bs-toggle="modal" data-bs-target="#editTaskModal" data-bs-dismiss="modal"><i class="bi bi-pencil me-1"></i> Edit Task</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="submissions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_submission">
                <input type="hidden" name="submission_id" id="edit_submission_id">
                
                <div class="modal-header popup-header-gradient d-flex align-items-center justify-content-between">
                    <div class="popup-title-container">
                        <div class="popup-title-icon">
                            <i class="bi bi-pencil"></i>
                        </div>
                        <h5 class="modal-title font-monospace fw-bold m-0" id="editTaskModalLabel">
                            Edit Work Submission
                        </h5>
                    </div>
                    <button type="button" class="popup-close-btn-circle" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                
                <div class="modal-body py-4">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="edit_department_id" class="form-label small fw-bold text-muted">Department</label>
                            <select class="form-select" name="department_id" id="edit_department_id" required>
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_priority" class="form-label small fw-bold text-muted">Priority</label>
                            <select class="form-select" name="priority" id="edit_priority">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label small fw-bold text-muted">Work Request Title</label>
                        <input type="text" class="form-control" name="title" id="edit_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label small fw-bold text-muted">Description *</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="edit_update_url" class="form-label small fw-bold text-muted">Update URL</label>
                            <input type="url" class="form-control" name="update_url" id="edit_update_url" placeholder="https://example.com/status">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_completed_url" class="form-label small fw-bold text-muted">Completed URL</label>
                            <input type="url" class="form-control" name="completed_url" id="edit_completed_url" placeholder="https://example.com/result">
                        </div>
                    </div>

                    <!-- Existing Attachments & Deletion Selection -->
                    <div class="mb-4">
                        <span class="popup-section-label">Existing Attachments (Select to Delete)</span>
                        <div id="edit_existing_attachments_container" class="border rounded p-3 bg-light d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted small">No existing attachments.</span>
                        </div>
                    </div>

                    <!-- New Upload Groups -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="edit_doc_uploads" class="form-label small fw-bold text-muted">Upload New Documents</label>
                            <input class="form-control" type="file" name="documents[]" id="edit_doc_uploads" accept=".pdf,.doc,.docx,.txt,.xlsx,.pptx,.zip" multiple>
                            <div class="form-text small text-muted">Accepts PDF, DOC, DOCX, TXT, XLSX, PPTX, ZIP.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edit_img_uploads" class="form-label small fw-bold text-muted">Upload New Images</label>
                            <input class="form-control" type="file" name="images[]" id="edit_img_uploads" accept="image/*" multiple>
                            <div class="form-text small text-muted">Accepts JPG, JPEG, PNG, WEBP. Max 5 images.</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 pt-0 bg-white">
                    <button type="button" class="btn btn-light border px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                </div>
            </form>
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
            const id = button.getAttribute('data-id');
            const deptId = button.getAttribute('data-dept-id');
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

            // Set data attributes on the Edit Task button and control visibility based on status
            const editBtn = document.getElementById('details_edit_btn');
            const addDocBtn = document.getElementById('details_add_doc_btn');
            const addImgBtn = document.getElementById('details_add_img_btn');
            const isPending = (status === 'PENDING');

            if (editBtn) {
                if (isPending) {
                    editBtn.style.display = 'inline-block';
                    editBtn.setAttribute('data-id', id);
                    editBtn.setAttribute('data-dept-id', deptId);
                    editBtn.setAttribute('data-priority', priority);
                    editBtn.setAttribute('data-title', title);
                    editBtn.setAttribute('data-desc', desc);
                    editBtn.setAttribute('data-upurl', upurl);
                    editBtn.setAttribute('data-compurl', compurl);
                    editBtn.setAttribute('data-files', JSON.stringify(filesData));
                } else {
                    editBtn.style.display = 'none';
                }
            }

            if (addDocBtn) addDocBtn.style.display = isPending ? 'inline-block' : 'none';
            if (addImgBtn) addImgBtn.style.display = isPending ? 'inline-block' : 'none';
        });
    }

    // Edit Task Modal Setup
    const editTaskModal = document.getElementById('editTaskModal');
    if (editTaskModal) {
        editTaskModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const deptId = button.getAttribute('data-dept-id');
            const priority = button.getAttribute('data-priority');
            const title = button.getAttribute('data-title');
            const desc = button.getAttribute('data-desc');
            const upurl = button.getAttribute('data-upurl');
            const compurl = button.getAttribute('data-compurl');
            const filesData = JSON.parse(button.getAttribute('data-files') || '[]');

            document.getElementById('edit_submission_id').value = id;
            document.getElementById('edit_department_id').value = deptId;
            document.getElementById('edit_priority').value = priority;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = desc;
            document.getElementById('edit_update_url').value = upurl;
            document.getElementById('edit_completed_url').value = compurl;

            const existingContainer = document.getElementById('edit_existing_attachments_container');
            existingContainer.innerHTML = '';
            if (filesData.length === 0) {
                existingContainer.innerHTML = '<span class="text-muted small">No existing attachments.</span>';
            } else {
                filesData.forEach((file) => {
                    existingContainer.innerHTML += `
                        <div class="d-flex align-items-center justify-content-between p-2 bg-white border rounded small">
                            <span class="text-truncate fw-medium me-3" style="max-width: 260px;" title="${escapeHtml(file.file_name)}">
                                <i class="bi bi-paperclip text-muted me-1"></i>${escapeHtml(file.file_name)}
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <a href="${file.google_drive_url}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 small text-decoration-none">View</a>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" name="delete_files[]" value="${file.id}" id="del_file_${file.id}">
                                    <label class="form-check-label text-danger small fw-semibold" for="del_file_${file.id}">Delete</label>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
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
                    chatHistory.innerHTML = '<div class="text-center text-muted py-5">No messages yet. Send a message to get in touch with admins.</div>';
                } else {
                    chatHistory.innerHTML = '';
                    messages.forEach(msg => {
                        const isMe = (parseInt(msg.sender_id) === <?php echo $_SESSION['user_id']; ?>);
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

function openEditModalAndUpload(type) {
    const detailsModalEl = document.getElementById('detailsModal');
    const detailsModal = bootstrap.Modal.getInstance(detailsModalEl);
    if (detailsModal) {
        detailsModal.hide();
    }
    
    const editModalEl = document.getElementById('editTaskModal');
    
    const editBtn = document.getElementById('details_edit_btn');
    if (editBtn) {
        editBtn.click();
        
        editModalEl.addEventListener('shown.bs.modal', function handler() {
            if (type === 'documents') {
                document.getElementById('edit_doc_uploads').click();
            } else if (type === 'images') {
                document.getElementById('edit_img_uploads').click();
            }
            editModalEl.removeEventListener('shown.bs.modal', handler);
        });
    }
}

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
