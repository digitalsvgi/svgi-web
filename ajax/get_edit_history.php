<?php
// ajax/get_edit_history.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['super_admin']); // Only Super Admin can access!

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$submission_id = intval($_GET['submission_id'] ?? 0);

if ($submission_id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

try {
    // Fetch edit count
    $subQuery = $pdo->prepare("SELECT edit_count FROM submissions WHERE id = ?");
    $subQuery->execute([$submission_id]);
    $editCount = intval($subQuery->fetchColumn() ?: 0);

    // Fetch edit history with editor user name
    $historyQuery = $pdo->prepare("
        SELECT h.*, u.name as editor_name 
        FROM submission_edit_history h
        JOIN users u ON h.edited_by = u.id
        WHERE h.submission_id = ?
        ORDER BY h.edited_at DESC
    ");
    $historyQuery->execute([$submission_id]);
    $history = $historyQuery->fetchAll();

    echo json_encode([
        'edit_count' => $editCount,
        'history' => $history
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
