<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
require_once '../includes/connection.php';

$student_id = $_SESSION['user_id'];
$complaint_id = intval($_POST['complaint_id'] ?? 0);
$message = trim($_POST['followup_comment'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
    exit();
}

$check_sql = "SELECT id FROM complaints WHERE id = ? AND student_id = ? AND status NOT IN ('closed', 'resolved')";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $complaint_id, $student_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
if (!mysqli_fetch_assoc($check_result)) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint or already closed/resolved.']);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit();
}
mysqli_stmt_close($check_stmt);

$insert_sql = "INSERT INTO responses (complaint_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($insert_stmt, "iis", $complaint_id, $student_id, $message);
if (mysqli_stmt_execute($insert_stmt)) {
    echo json_encode(['success' => true, 'message' => 'Follow‑up added.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>