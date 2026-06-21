<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/connection.php';

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($complaint_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

$sql = "SELECT c.*, u.full_name as student_name, u.reg_number, cat.name as category_name 
        FROM complaints c 
        JOIN users u ON c.student_id = u.id 
        JOIN categories cat ON c.category_id = cat.id 
        WHERE c.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $complaint_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$complaint = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($complaint) {
    echo json_encode(['success' => true, 'complaint' => $complaint]);
} else {
    echo json_encode(['success' => false, 'message' => 'Complaint not found']);
}
?>