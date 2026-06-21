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

// Get category ID
$cat_sql = "SELECT id FROM categories WHERE name = ?";
$cat_stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($cat_stmt, "s", $category);
mysqli_stmt_execute($cat_stmt);
$cat_result = mysqli_stmt_get_result($cat_stmt);
$cat_row = mysqli_fetch_assoc($cat_result);

if (!$cat_row) {
    echo json_encode(['success' => false, 'message' => 'Category not found: ' . $category]);
    exit();
}
$category_id = $cat_row['id'];
mysqli_stmt_close($cat_stmt);

// ========== AUTO-ASSIGN LOGIC ==========
$assigned_to = null;

// Accountant categories
if ($category == 'Accountant' || $category == 'Fees') {
    $assigned_to = 4;  // Accountant ID
}
// IT Support category - assign to IT Officer
elseif ($category == 'IT Support') {
    $it_sql = "SELECT id FROM users WHERE role = 'it_officer' LIMIT 1";
    $it_stmt = mysqli_prepare($conn, $it_sql);
    mysqli_stmt_execute($it_stmt);
    $it_result = mysqli_stmt_get_result($it_stmt);
    $it_row = mysqli_fetch_assoc($it_result);
    $assigned_to = $it_row['id'] ?? null;
    mysqli_stmt_close($it_stmt);
}
// Academic - assign to HOD
elseif ($category == 'Academic') {
    $hod_query = "SELECT d.hod_id FROM users u JOIN departments d ON u.department_id = d.id WHERE u.id = $student_id";
    $hod_result = mysqli_query($conn, $hod_query);
    $hod_row = mysqli_fetch_assoc($hod_result);
    $assigned_to = $hod_row['hod_id'] ?? null;
}
// Dean categories
elseif (in_array($category, ['Hostel', 'Infrastructure', 'Service', 'Gender issue', 'Accommodation', 'Cafeteria', 'Security', 'Library', 'Sports', 'Other'])) {
    $assigned_to = 3;  // Dean ID
}
// Examination case
elseif ($category == 'Examination case') {
    $exam_query = "SELECT id FROM users WHERE role = 'examination_officer' LIMIT 1";
    $exam_result = mysqli_query($conn, $exam_query);
    $exam_row = mysqli_fetch_assoc($exam_result);
    $assigned_to = $exam_row['id'] ?? null;
}
// Students Government
elseif ($category == 'Students Government') {
    $pres_query = "SELECT id FROM users WHERE role = 'president' LIMIT 1";
    $pres_result = mysqli_query($conn, $pres_query);
    $pres_row = mysqli_fetch_assoc($pres_result);
    $assigned_to = $pres_row['id'] ?? null;
}

// Generate complaint number
$year = date('Y');
$prefix = 'CMP';
$last_sql = "SELECT complaint_number FROM complaints WHERE complaint_number LIKE '$prefix-$year-%' ORDER BY id DESC LIMIT 1";
$last_result = mysqli_query($conn, $last_sql);
$last_row = mysqli_fetch_assoc($last_result);
$new_num = $last_row ? (int)substr($last_row['complaint_number'], -4) + 1 : 1;
$complaint_number = sprintf("%s-%d-%04d", $prefix, $year, $new_num);

// Insert complaint with assigned_to
$sql = "INSERT INTO complaints (
    complaint_number, 
    student_id, 
    category_id, 
    title, 
    description, 
    location, 
    incident_date, 
    priority, 
    assigned_to, 
    status, 
    created_at, 
    updated_at
) VALUES (
    '$complaint_number', 
    $student_id, 
    $category_id, 
    '" . mysqli_real_escape_string($conn, $title) . "', 
    '" . mysqli_real_escape_string($conn, $description) . "', 
    '" . mysqli_real_escape_string($conn, $location) . "', 
    '$incident_date', 
    '$priority', 
    " . ($assigned_to ? $assigned_to : "NULL") . ", 
    'pending', 
    NOW(), 
    NOW()
)";

if (!mysqli_query($conn, $sql)) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}
$complaint_id = mysqli_insert_id($conn);

// Handle file upload
if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['evidence'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'video/mp4', 'audio/mpeg'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (in_array($file['type'], $allowed) && $file['size'] <= $max_size) {
        $upload_dir = '../uploads/complaints/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'complaint_' . $student_id . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $path = 'uploads/complaints/' . $filename;
            mysqli_query($conn, "UPDATE complaints SET attachment_path = '$path' WHERE id = $complaint_id");
        }
    }
}

mysqli_close($conn);

echo json_encode([
    'success' => true, 
    'message' => 'Complaint submitted successfully',
    'complaint_id' => $complaint_id,
    'assigned_to' => $assigned_to,
    'category' => $category
]);
?>