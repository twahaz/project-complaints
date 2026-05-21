<?php
// login_process.php
session_start();

require_once 'includes/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = "Please fill in all fields.";
    header("Location: index.php");
    exit();
}

// Auto-detect: if username contains '@' treat as staff (email), else student (registration number)
if (strpos($username, '@') !== false) {
    // Staff login by email – also fetch department_id
    $sql = "SELECT id, reg_number, email, password_hash, full_name, role, is_active, department_id 
            FROM users 
            WHERE email = ? AND role != 'student'";
    $user_type = 'staff';
} else {
    // Student login by registration number – students may also have department_id
    $sql = "SELECT id, reg_number, email, password_hash, full_name, role, is_active, department_id 
            FROM users 
            WHERE reg_number = ? AND role = 'student'";
    $user_type = 'student';
}

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $_SESSION['login_error'] = "System error. Try again.";
    header("Location: index.php");
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['login_error'] = "Invalid username or password.";
    header("Location: index.php");
    exit();
}

if ($user['is_active'] != 1) {
    $_SESSION['login_error'] = "Account deactivated. Contact admin.";
    header("Location: index.php");
    exit();
}

if (!password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = "Invalid username or password.";
    header("Location: index.php");
    exit();
}

// Update last login
$update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['reg_number'] = $user['reg_number'];
$_SESSION['email'] = $user['email'];
$_SESSION['department_id'] = $user['department_id'];  // store department_id for staff/students

session_regenerate_id(true);
mysqli_close($conn);

// Redirect based on role
if ($user['role'] === 'student') {
    header("Location: students/student_dashboard.php");
} elseif ($user['role'] === 'hod') {
    header("Location: hod/hod_dashboard.php");
} elseif ($user['role'] === 'dean') {
    header("Location: staff/dean_dashboard.php");
} 
elseif ($user['role'] === 'examination_officer') {
    header("Location: staff/examination_dashboard.php");
}
elseif ($user['role'] === 'accountant') {
    header("Location: staff/accountant_dashboard.php");
}
elseif ($user['role'] === 'president') {
    header("Location: staff/president_dashboard.php");
}
elseif ($user['role'] === 'deputy_rector') {
    header("Location: staff/deputy_rector_dashboard.php");
} elseif ($user['role'] === 'rector') {
    header("Location: staff/rector_dashboard.php");
}
else {
    header("Location: staff/staff_dashboard.php");
}


exit();
?>