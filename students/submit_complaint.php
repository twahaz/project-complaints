<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/connection.php';

$student_id = $_SESSION['user_id'];
$category = $_POST['category'] ?? '';
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$location = trim($_POST['location'] ?? '');
$incident_date = $_POST['incident_date'] ?? null;
$priority = $_POST['priority'] ?? 'medium';

if (empty($category) || empty($title) || empty($description) || !$incident_date) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit();
}

// Map category name to category_id (or insert new)
$cat_sql = "SELECT id FROM categories WHERE name = ?";
$cat_stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($cat_stmt, "s", $category);
mysqli_stmt_execute($cat_stmt);
$cat_result = mysqli_stmt_get_result($cat_stmt);
$cat_row = mysqli_fetch_assoc($cat_result);
if ($cat_row) {
    $category_id = $cat_row['id'];
} else {
    // Insert new category if not exists
    $insert_cat = "INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)";
    $ins_stmt = mysqli_prepare($conn, $insert_cat);
    $slug = strtolower(str_replace(' ', '-', $category));
    $desc = "Category for " . $category;
    mysqli_stmt_bind_param($ins_stmt, "sss", $category, $slug, $desc);
    mysqli_stmt_execute($ins_stmt);
    $category_id = mysqli_insert_id($conn);
    mysqli_stmt_close($ins_stmt);
}
mysqli_stmt_close($cat_stmt);

// Generate unique complaint number
$year = date('Y');
$prefix = 'CMP';
$last_sql = "SELECT complaint_number FROM complaints WHERE complaint_number LIKE '{$prefix}-{$year}-%' ORDER BY id DESC LIMIT 1";
$last_result = mysqli_query($conn, $last_sql);
$last_row = mysqli_fetch_assoc($last_result);
$new_num = $last_row ? (int)substr($last_row['complaint_number'], -4) + 1 : 1;
$complaint_number = sprintf("%s-%d-%04d", $prefix, $year, $new_num);

// Handle file upload (optional)
$attachment_path = null;
if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['evidence'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'video/mp4', 'audio/mpeg'];
    $max_size = 10 * 1024 * 1024; // 10MB
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, PDF, MP4, MP3']);
        exit();
    }
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB.']);
        exit();
    }
    $upload_dir = '../uploads/complaints/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'complaint_' . $student_id . '_' . time() . '.' . $ext;
    $destination = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $attachment_path = 'uploads/complaints/' . $filename;
    } else {
        echo json_encode(['success' => false, 'message' => 'File upload failed.']);
        exit();
    }
}

// Insert complaint (initially assigned_to = NULL)
$status = 'pending';
// IMPORTANT: Set updated_at equal to created_at (NOW()) for SLA tracking
$sql = "INSERT INTO complaints (complaint_number, student_id, category_id, title, description, location, incident_date, priority, attachment_path, status, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "siisssssss", $complaint_number, $student_id, $category_id, $title, $description, $location, $incident_date, $priority, $attachment_path, $status);
if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}
$complaint_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// ========== AUTO-ASSIGN LOGIC ==========
// Get category name
$cat_name_sql = "SELECT name FROM categories WHERE id = ?";
$cat_name_stmt = mysqli_prepare($conn, $cat_name_sql);
mysqli_stmt_bind_param($cat_name_stmt, "i", $category_id);
mysqli_stmt_execute($cat_name_stmt);
$cat_name_result = mysqli_stmt_get_result($cat_name_stmt);
$cat_name_row = mysqli_fetch_assoc($cat_name_result);
$category_name = $cat_name_row['name'];
mysqli_stmt_close($cat_name_stmt);

$assigned_to = null;

// 1. Academic -> assign to HOD of student's department
if ($category_name === 'Academic') {
    // Get student's department
    $dept_sql = "SELECT department_id FROM users WHERE id = ?";
    $dept_stmt = mysqli_prepare($conn, $dept_sql);
    mysqli_stmt_bind_param($dept_stmt, "i", $student_id);
    mysqli_stmt_execute($dept_stmt);
    $dept_result = mysqli_stmt_get_result($dept_stmt);
    $dept_row = mysqli_fetch_assoc($dept_result);
    $student_dept = $dept_row['department_id'] ?? null;
    mysqli_stmt_close($dept_stmt);
    
    if ($student_dept) {
        // Find HOD of that department
        $hod_sql = "SELECT hod_id FROM departments WHERE id = ?";
        $hod_stmt = mysqli_prepare($conn, $hod_sql);
        mysqli_stmt_bind_param($hod_stmt, "i", $student_dept);
        mysqli_stmt_execute($hod_stmt);
        $hod_result = mysqli_stmt_get_result($hod_stmt);
        $hod_row = mysqli_fetch_assoc($hod_result);
        $assigned_to = $hod_row['hod_id'] ?? null;
        mysqli_stmt_close($hod_stmt);
    }
}
// 2. Examination case -> assign to Examination Officer
elseif ($category_name === 'Examination case') {
    $exam_sql = "SELECT id FROM users WHERE role = 'examination_officer' LIMIT 1";
    $exam_stmt = mysqli_prepare($conn, $exam_sql);
    mysqli_stmt_execute($exam_stmt);
    $exam_result = mysqli_stmt_get_result($exam_stmt);
    $exam_row = mysqli_fetch_assoc($exam_result);
    $assigned_to = $exam_row['id'] ?? null;
    mysqli_stmt_close($exam_stmt);
}
// 3. Fees -> assign to Accountant
elseif ($category_name === 'Fees') {
    $acc_sql = "SELECT id FROM users WHERE role = 'accountant' LIMIT 1";
    $acc_stmt = mysqli_prepare($conn, $acc_sql);
    mysqli_stmt_execute($acc_stmt);
    $acc_result = mysqli_stmt_get_result($acc_stmt);
    $acc_row = mysqli_fetch_assoc($acc_result);
    $assigned_to = $acc_row['id'] ?? null;
    mysqli_stmt_close($acc_stmt);
}
// 4. Hostel, Infrastructure, Service, Gender issue -> assign to Dean
elseif (in_array($category_name, ['Hostel', 'Infrastructure', 'Service', 'Gender issue'])) {
    $dean_sql = "SELECT id FROM users WHERE role = 'dean' LIMIT 1";
    $dean_stmt = mysqli_prepare($conn, $dean_sql);
    mysqli_stmt_execute($dean_stmt);
    $dean_result = mysqli_stmt_get_result($dean_stmt);
    $dean_row = mysqli_fetch_assoc($dean_result);
    $assigned_to = $dean_row['id'] ?? null;
    mysqli_stmt_close($dean_stmt);
}
// 5. Students Government -> assign to President (IAASO)
elseif ($category_name === 'Students Government') {
    $president_sql = "SELECT id FROM users WHERE role = 'president' LIMIT 1";
    $president_stmt = mysqli_prepare($conn, $president_sql);
    mysqli_stmt_execute($president_stmt);
    $president_result = mysqli_stmt_get_result($president_stmt);
    $president_row = mysqli_fetch_assoc($president_result);
    $assigned_to = $president_row['id'] ?? null;
    mysqli_stmt_close($president_stmt);
}

// If an assignee was found, update the complaint
if ($assigned_to) {
    $assign_sql = "UPDATE complaints SET assigned_to = ? WHERE id = ?";
    $assign_stmt = mysqli_prepare($conn, $assign_sql);
    mysqli_stmt_bind_param($assign_stmt, "ii", $assigned_to, $complaint_id);
    mysqli_stmt_execute($assign_stmt);
    mysqli_stmt_close($assign_stmt);
}

mysqli_close($conn);
echo json_encode(['success' => true, 'message' => 'Complaint submitted', 'complaint_id' => $complaint_id]);
?>