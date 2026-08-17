<?php
// ajax/messages.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// Check if authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$submission_id = intval($_GET['submission_id'] ?? 0);

if ($submission_id <= 0) {
    echo json_encode([]);
    exit();
}

// Authorization check: College users can only access their own college's submissions
if ($_SESSION['user_role'] === 'college_user') {
    $check = $pdo->prepare("SELECT college_id FROM submissions WHERE id = ?");
    $check->execute([$submission_id]);
    $sub_college_id = $check->fetchColumn();
    if ($sub_college_id != $_SESSION['college_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - You do not have permission to view these messages.']);
        exit();
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.submission_id = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$submission_id]);
    $messages = $stmt->fetchAll();
    echo json_encode($messages);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
