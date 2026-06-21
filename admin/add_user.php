<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/connection.php';

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$admin_email = $_SESSION['email'];

$active_page = 'add_user';

// Get profile data
$prof_sql = "SELECT phone_number, profile_picture FROM users WHERE id = ?";
$prof_stmt = mysqli_prepare($conn, $prof_sql);
mysqli_stmt_bind_param($prof_stmt, "i", $admin_id);
mysqli_stmt_execute($prof_stmt);
$prof_result = mysqli_stmt_get_result($prof_stmt);
$prof_data = mysqli_fetch_assoc($prof_result);
$phone = $prof_data['phone_number'] ?? '';
$profile_pic = $prof_data['profile_picture'] ?? '';
if (!isset($_SESSION['profile_picture']) && $profile_pic) $_SESSION['profile_picture'] = $profile_pic;
mysqli_stmt_close($prof_stmt);

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $reg_number = trim($_POST['reg_number']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $course_id = intval($_POST['course_id']);
    $academic_year_id = intval($_POST['academic_year_id']);
    $year_of_study = intval($_POST['year_of_study']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validation
    if (empty($reg_number)) $errors[] = "Registration number is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($course_id)) $errors[] = "Course is required.";
    if (empty($academic_year_id)) $errors[] = "Academic year is required.";
    if (empty($year_of_study)) $errors[] = "Year of study is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    
    // Phone number validation
    if (!empty($phone) && strlen($phone) > 10) {
        $errors[] = "Phone number must not exceed 10 digits.";
    }
    if (!empty($phone) && !preg_match('/^[0-9]+$/', $phone)) {
        $errors[] = "Phone number must contain only digits.";
    }
    
    // Registration number format validation - Allow 3+ characters for prefix
    if (!empty($reg_number) && !preg_match('/^[A-Z]{3,}-\d{2}-\d{4}-\d{4}$/', $reg_number)) {
        $errors[] = "Registration number format must be: XXX-XX-XXXX-XXXX (e.g., BCS-00-0000-0000 or BIRM-01-0001-2024)";
    }
    
    // Email validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Get course details
    $course_sql = "SELECT department_id, duration_years, level FROM courses WHERE id = ? AND is_active = 1";
    $course_stmt = mysqli_prepare($conn, $course_sql);
    mysqli_stmt_bind_param($course_stmt, "i", $course_id);
    mysqli_stmt_execute($course_stmt);
    $course_result = mysqli_stmt_get_result($course_stmt);
    $course_data = mysqli_fetch_assoc($course_result);
    
    if (!$course_data) {
        $errors[] = "Invalid course selected!";
    } else {
        $department_id = $course_data['department_id'];
        $duration_years = $course_data['duration_years'];
        
        if ($year_of_study > $duration_years) {
            $errors[] = "Year of study cannot exceed course duration ($duration_years years)";
        }
    }
    mysqli_stmt_close($course_stmt);
    
    // Check if reg_number exists
    $check_sql = "SELECT id FROM users WHERE reg_number = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $reg_number);
    mysqli_stmt_execute($check_stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))) {
        $errors[] = "Registration number already exists!";
    }
    mysqli_stmt_close($check_stmt);
    
    // Check if email exists
    $check_email_sql = "SELECT id FROM users WHERE email = ?";
    $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
    mysqli_stmt_bind_param($check_email_stmt, "s", $email);
    mysqli_stmt_execute($check_email_stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check_email_stmt))) {
        $errors[] = "Email already exists!";
    }
    mysqli_stmt_close($check_email_stmt);
    
    // Handle profile picture
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 2 * 1024 * 1024;
        
        if (!in_array($file['type'], $allowed)) {
            $errors[] = "Only JPG, PNG, GIF images are allowed for profile picture.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Profile picture must be less than 2MB.";
        } else {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $profile_picture = 'uploads/profiles/' . $filename;
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }
    }
    
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $insert_sql = "INSERT INTO users (
            reg_number, email, password_hash, full_name, role, phone_number, 
            department_id, current_course_id, current_academic_year_id, 
            current_year_of_study, profile_picture, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        // Variables: reg_number, email, password_hash, full_name, phone, department_id, course_id, academic_year_id, year_of_study, profile_picture = 10
        // Types: sssss (5 strings: reg_number, email, password_hash, full_name, phone)
        //        iiii (4 integers: department_id, course_id, academic_year_id, year_of_study)
        //        s (1 string: profile_picture)
        // Total: 10 variables, types: "sssssiiiis"
        mysqli_stmt_bind_param($insert_stmt, "sssssiiiis", 
            $reg_number, $email, $hashed, $full_name, $phone, 
            $department_id, $course_id, $academic_year_id, $year_of_study, $profile_picture
        );
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $student_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert_stmt);
            
            // Insert into student_academic_records
            $record_sql = "INSERT INTO student_academic_records (
                student_id, academic_year_id, course_id, year_of_study, registration_date, status
            ) VALUES (?, ?, ?, ?, NOW(), 'active')";
            
            $record_stmt = mysqli_prepare($conn, $record_sql);
            mysqli_stmt_bind_param($record_stmt, "iiii", $student_id, $academic_year_id, $course_id, $year_of_study);
            
            if (mysqli_stmt_execute($record_stmt)) {
                // Log the action
                $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'create', ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_sql);
                $description = "Added new student: $full_name (Reg: $reg_number)";
                $ip = $_SERVER['REMOTE_ADDR'];
                mysqli_stmt_bind_param($log_stmt, "iss", $admin_id, $description, $ip);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
                
                $_SESSION['toast_message'] = "✅ Student registered successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Student added but academic record failed.";
                $_SESSION['toast_type'] = "warning";
            }
            mysqli_stmt_close($record_stmt);
        } else {
            $_SESSION['toast_message'] = "Database error: " . mysqli_error($conn);
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_message'] = implode("<br>", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: add_user.php");
    exit();
}

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validation
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($role)) $errors[] = "Role is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    
    // Phone number validation
    if (!empty($phone) && strlen($phone) > 10) {
        $errors[] = "Phone number must not exceed 10 digits.";
    }
    if (!empty($phone) && !preg_match('/^[0-9]+$/', $phone)) {
        $errors[] = "Phone number must contain only digits.";
    }
    
    // Email validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Check if email exists
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))) {
        $errors[] = "Email already exists!";
    }
    mysqli_stmt_close($check_stmt);
    
    // Handle profile picture
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 2 * 1024 * 1024;
        
        if (!in_array($file['type'], $allowed)) {
            $errors[] = "Only JPG, PNG, GIF images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Image must be less than 2MB.";
        } else {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $role . '_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $profile_picture = 'uploads/profiles/' . $filename;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }
    
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $insert_sql = "INSERT INTO users (
            email, password_hash, full_name, role, phone_number, department_id, 
            profile_picture, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        // Variables: email, password_hash, full_name, role, phone, department_id, profile_picture = 7
        // Types: sssss (5 strings: email, password_hash, full_name, role, phone)
        //        i (1 integer: department_id)
        //        s (1 string: profile_picture)
        // Total: 7 variables, types: "sssssis"
        mysqli_stmt_bind_param($insert_stmt, "sssssis", 
            $email, $hashed, $full_name, $role, $phone, $department_id, $profile_picture
        );
        
        if (mysqli_stmt_execute($insert_stmt)) {
            // Log the action
            $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'create', ?, ?)";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            $description = "Added new staff: $full_name (Role: $role)";
            $ip = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($log_stmt, "iss", $admin_id, $description, $ip);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
            
            $_SESSION['toast_message'] = "✅ Staff added successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Database error: " . mysqli_error($conn);
            $_SESSION['toast_type'] = "error";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        $_SESSION['toast_message'] = implode("<br>", $errors);
        $_SESSION['toast_type'] = "error";
    }
    header("Location: add_user.php");
    exit();
}

// Get departments for dropdown
$departments = [];
$dept_sql = "SELECT id, name FROM departments ORDER BY name";
$dept_result = mysqli_query($conn, $dept_sql);
while ($dept = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $dept;
}

// Get academic years - default to 2025/2026 if exists
$academic_years = [];
$ay_sql = "SELECT * FROM academic_years WHERE is_active = 1 ORDER BY year_name DESC";
$ay_result = mysqli_query($conn, $ay_sql);
while ($ay = mysqli_fetch_assoc($ay_result)) {
    $academic_years[] = $ay;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Add User - Admin Panel</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           COMPLETE STYLES - Consistent with Dashboard
           ============================================ */
        
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #ebf4fe;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== TOAST NOTIFICATIONS ========== */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 380px;
            width: 100%;
            pointer-events: none;
        }

        .toast {
            padding: 16px 20px;
            border-radius: 14px;
            font-weight: 500;
            font-size: 0.9rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastSlideIn 0.4s ease-out;
            pointer-events: auto;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .toast.hide { animation: toastSlideOut 0.4s ease-in forwards; }
        .toast-success { border-left: 4px solid #10b981; }
        .toast-success i { color: #10b981; }
        .toast-error { border-left: 4px solid #dc2626; }
        .toast-error i { color: #dc2626; }
        .toast-info { border-left: 4px solid #1a56db; }
        .toast-info i { color: #1a56db; }

        .toast i { font-size: 1.3rem; flex-shrink: 0; }
        .toast .toast-message { flex: 1; color: #1f2c40; }
        .toast .toast-close {
            background: none; border: none; color: #8ba0bc;
            cursor: pointer; font-size: 1rem; padding: 4px;
            transition: color 0.2s; flex-shrink: 0;
        }
        .toast .toast-close:hover { color: #dc2626; }

        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes toastSlideOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(40px); }
        }

        .toast .toast-progress {
            position: absolute; bottom: 0; left: 0; height: 3px;
            border-radius: 0 0 14px 14px;
            background: rgba(0, 0, 0, 0.1);
            animation: toastProgress 3.5s linear forwards;
        }
        .toast-success .toast-progress { background: #10b981; }
        .toast-error .toast-progress { background: #dc2626; }
        .toast-info .toast-progress { background: #1a56db; }

        @keyframes toastProgress {
            from { width: 100%; }
            to { width: 0%; }
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 280px;
            background: #ebf4fe;
            color: #0a2a5e;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0,0,0,0.04);
            z-index: 100;
            border-right: 1px solid rgba(10,42,94,0.06);
        }
        .sidebar.collapsed { width: 80px; }

        .sidebar-header {
            padding: 28px 20px 20px 20px;
            border-bottom: 1px solid rgba(10,42,94,0.08);
            margin-bottom: 8px;
        }
        .row-cfms { display: flex; justify-content: space-between; align-items: center; }
        .brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0a2a5e;
            letter-spacing: -0.5px;
        }
        .brand span { color: #2563eb; }

        .toggle-inline, .toggle-standalone {
            background: rgba(10,42,94,0.08);
            border: none;
            color: #0a2a5e;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            font-size: 1.1rem;
        }
        .toggle-inline:hover, .toggle-standalone:hover { background: rgba(10,42,94,0.15); }

        .row-tagline { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .tagline {
            font-size: 0.7rem;
            color: rgba(10,42,94,0.5);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .sidebar:not(.collapsed) .toggle-inline { display: none; }
        .sidebar.collapsed .row-tagline { display: none; }
        .sidebar.collapsed .row-cfms { flex-direction: column; justify-content: center; gap: 12px; }
        .sidebar.collapsed .brand { font-size: 1rem; }
        .sidebar.collapsed .toggle-inline { display: flex; }

        /* ========== SIDEBAR MENU ========== */
        .sidebar-menu { 
            flex: 1; 
            padding: 8px 14px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            margin: 8px 0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            color: #1a1a2e;
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
            font-size: 0.95rem;
            background: transparent;
        }
        .menu-item i { 
            width: 24px; 
            font-size: 1.2rem; 
            text-align: center;
            color: #1a1a2e;
        }
        .menu-item span { transition: opacity 0.2s; }
        .sidebar.collapsed .menu-item span { opacity: 0; visibility: hidden; width: 0; }
        .sidebar.collapsed .menu-item { justify-content: center; padding: 14px 0; }

        .menu-item:hover { 
            background: #1a56db;
            color: #ffffff;
        }
        .menu-item:hover i {
            color: #ffffff;
        }

        .menu-item.active {
            background: #1a56db;
            color: #ffffff;
            font-weight: 600;
            box-shadow: inset 3px 0 0 #60a5fa;
        }
        .menu-item.active i {
            color: #ffffff;
        }

        /* ========== LOGOUT ========== */
        .logout-item { 
            margin-top: auto; 
            margin-bottom: 24px; 
            border-top: 1px solid rgba(10,42,94,0.08); 
            padding-top: 16px; 
        }
        .logout-item .menu-item { 
            color: #1a1a2e;
        }
        .logout-item .menu-item i {
            color: #1a1a2e;
        }
        .logout-item .menu-item:hover { 
            background: #dc2626;
            color: #ffffff;
        }
        .logout-item .menu-item:hover i {
            color: #ffffff;
        }

        /* ---------- MAIN CONTENT ---------- */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: #ebf4fe;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 80px; }

        /* ---------- TOP BAR ---------- */
        .top-bar {
            background: rgba(255,255,255,0.92);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(10,42,94,0.06);
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
            flex-wrap: wrap;
            gap: 12px;
        }

        .profile-info { 
            display: flex; 
            align-items: center; 
            gap: 12px;
            flex-shrink: 0;
        }
        .profile-pic {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0a2a5e, #003d7a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            overflow: hidden;
            border: 2px solid rgba(10,42,94,0.08);
            flex-shrink: 0;
        }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-details { text-align: right; }
        .profile-details .name { 
            font-weight: 700; 
            color: #0a2a5e; 
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .profile-details .reg { 
            font-size: 0.7rem; 
            color: rgba(10,42,94,0.5);
            white-space: nowrap;
        }

        /* ---------- DASHBOARD BODY ---------- */
        .dashboard-body { 
            padding: 28px 36px;
            background: #ebf4fe;
            flex: 1;
        }

        /* ========== CONTENT AREA ========== */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 350px;
        }
        .content-area h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ========== TAB BUTTONS ========== */
        .tab-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e5edf5;
            padding-bottom: 4px;
        }
        .tab-btn {
            padding: 10px 24px;
            background: none;
            border: none;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
            color: #6b85a0;
            border-radius: 30px 30px 0 0;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            background: #f0f4f9;
            color: #0a2a5e;
        }
        .tab-btn.active {
            color: #1a56db;
            border-bottom: 3px solid #1a56db;
            background: transparent;
        }

        /* ========== FORMS ========== */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #0a2a5e;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-group label .required {
            color: #dc2626;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e5edf5;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: border 0.2s;
            background: #fafcff;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.08);
        }
        .form-group input.error, .form-group select.error {
            border-color: #dc2626;
        }
        .form-group .helper-text {
            font-size: 0.78rem;
            color: #8ba0bc;
            margin-top: 4px;
        }
        .form-group .error-message {
            color: #dc2626;
            font-size: 0.75rem;
            margin-top: 4px;
            display: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            background: #1a56db;
            color: white;
            border: none;
            padding: 14px 36px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-submit:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 86, 219, 0.3);
        }

        /* ========== COURSE DETAILS ========== */
        .course-details {
            background: #f0f4ff;
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid #dbeafe;
            display: none;
        }
        .course-details h5 {
            color: #0a2a5e;
            font-size: 0.85rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .course-details .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .course-details .detail-item .label {
            font-size: 0.65rem;
            text-transform: uppercase;
            color: #6b85a0;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .course-details .detail-item .value {
            font-weight: 600;
            color: #0a2a5e;
            font-size: 0.9rem;
        }

        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-blue { background: #dbeafe; color: #1e40af; }

        /* ---------- MODAL ---------- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0.2s, opacity 0.2s;
        }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-container {
            background: white;
            border-radius: 24px;
            max-width: 400px;
            width: 90%;
            padding: 32px 28px;
            text-align: center;
        }
        .modal-container i { font-size: 2.5rem; color: #0a2a5e; margin-bottom: 12px; }
        .modal-container h3 { color: #0a2a5e; font-size: 1.2rem; }
        .modal-container p { color: #6b85a0; margin-top: 8px; }
        .modal-buttons { 
            display: flex; 
            gap: 12px; 
            justify-content: center; 
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .modal-btn {
            padding: 10px 28px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 100px;
        }
        .modal-btn.confirm { background: #1a56db; color: white; }
        .modal-btn.confirm:hover { background: #0d3b8a; transform: translateY(-2px); }
        .modal-btn.cancel { background: #f0f4f9; color: #4a5a7a; }
        .modal-btn.cancel:hover { background: #e5edf5; }

        /* ========== LOADING OVERLAY ========== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1100;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0.3s, opacity 0.3s;
        }
        .loading-overlay.active { 
            visibility: visible; 
            opacity: 1; 
        }
        .loading-content {
            text-align: center;
            padding: 40px 50px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.15);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { 
            margin-top: 16px; 
            color: #ffffff; 
            font-weight: 500;
            font-size: 1rem;
            letter-spacing: 0.3px;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */

        @media (max-width: 1024px) {
            .dashboard-body { padding: 20px 24px; }
            .top-bar { padding: 14px 24px; }
            .form-row { grid-template-columns: 1fr; }
            .course-details .detail-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px !important;
                overflow: hidden;
                transition: width 0.3s ease;
            }
            .sidebar:not(.collapsed) { width: 70px !important; }
            .sidebar.collapsed { width: 70px !important; }
            
            .brand {
                font-size: 0.8rem !important;
                text-align: center;
            }
            .brand span { display: none !important; }
            .row-tagline { display: none !important; }
            .row-cfms {
                flex-direction: column !important;
                gap: 4px !important;
                justify-content: center !important;
                align-items: center !important;
            }
            .toggle-inline {
                display: flex !important;
                font-size: 0.9rem;
                width: 28px;
                height: 28px;
            }
            .toggle-standalone { display: none !important; }
            
            .menu-item {
                justify-content: center !important;
                padding: 12px 0 !important;
                margin: 12px 0 !important;
                gap: 0 !important;
                border-radius: 10px !important;
            }
            .menu-item span { display: none !important; }
            .menu-item i {
                font-size: 1.3rem !important;
                width: 100% !important;
                text-align: center !important;
                margin: 0 !important;
            }
            .menu-item:hover { transform: none !important; }
            
            .logout-item .menu-item span { display: none !important; }
            .logout-item .menu-item i { font-size: 1.3rem !important; }
            .logout-item {
                margin-bottom: 20px;
                padding-top: 16px;
            }

            .main-content {
                margin-left: 70px !important;
                transition: margin-left 0.3s ease;
            }
            .sidebar.collapsed ~ .main-content {
                margin-left: 70px !important;
            }

            .dashboard-body { padding: 12px; }
            .top-bar { padding: 10px 12px; gap: 8px; }

            .profile-details .name { font-size: 0.7rem; }
            .profile-details .reg { font-size: 0.5rem; }
            .profile-pic { width: 32px; height: 32px; }

            .content-area {
                padding: 16px;
                border-radius: 12px;
            }
            .content-area h4 {
                font-size: 0.85rem;
            }

            .tab-btn {
                padding: 8px 14px;
                font-size: 0.8rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .btn-submit {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
                font-size: 0.85rem;
            }

            .modal-container {
                padding: 20px 16px;
            }
            .modal-container h3 {
                font-size: 1rem;
            }
            .modal-btn {
                padding: 8px 16px;
                font-size: 0.75rem;
                min-width: 70px;
            }

            .loading-content {
                padding: 24px 24px;
            }
            .spinner {
                width: 32px;
                height: 32px;
            }
            .loading-text {
                font-size: 0.8rem;
            }

            .toast-container {
                top: 8px;
                right: 8px;
                max-width: calc(100% - 16px);
            }
            .toast {
                font-size: 0.75rem;
                padding: 10px 14px;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 60px !important;
            }
            .sidebar:not(.collapsed) { width: 60px !important; }
            .sidebar.collapsed { width: 60px !important; }
            
            .brand {
                font-size: 0.7rem !important;
            }
            .menu-item {
                padding: 10px 0 !important;
                margin: 10px 0 !important;
            }
            .menu-item i {
                font-size: 1.1rem !important;
            }
            .logout-item .menu-item i {
                font-size: 1.1rem !important;
            }
            .logout-item {
                margin-bottom: 16px;
                padding-top: 12px;
            }

            .main-content {
                margin-left: 60px !important;
            }
            .sidebar.collapsed ~ .main-content {
                margin-left: 60px !important;
            }

            .dashboard-body { padding: 8px; }
            .top-bar { padding: 8px 10px; gap: 6px; }
            .profile-pic { width: 28px; height: 28px; }
            .profile-details .name { font-size: 0.6rem; }
            .profile-details .reg { font-size: 0.45rem; }

            .content-area {
                padding: 12px;
                border-radius: 10px;
            }
            .content-area h4 {
                font-size: 0.75rem;
            }

            .tab-btn {
                padding: 6px 10px;
                font-size: 0.7rem;
            }

            .form-group label {
                font-size: 0.8rem;
            }
            .form-group input, .form-group select {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            .btn-submit {
                padding: 10px 16px;
                font-size: 0.8rem;
            }

            .modal-container {
                padding: 16px 14px;
            }
            .modal-container h3 {
                font-size: 0.9rem;
            }
            .modal-btn {
                padding: 6px 14px;
                font-size: 0.7rem;
                min-width: 60px;
            }

            .loading-content {
                padding: 20px 20px;
            }
            .spinner {
                width: 28px;
                height: 28px;
            }
            .loading-text {
                font-size: 0.75rem;
            }

            .toast-container {
                top: 6px;
                right: 6px;
                max-width: calc(100% - 12px);
            }
            .toast {
                font-size: 0.7rem;
                padding: 8px 12px;
            }
            .toast i {
                font-size: 1rem;
            }
        }

        @media (max-width: 380px) {
            .sidebar {
                width: 55px !important;
            }
            .sidebar:not(.collapsed) { width: 55px !important; }
            .sidebar.collapsed { width: 55px !important; }
            .main-content {
                margin-left: 55px !important;
            }
            .sidebar.collapsed ~ .main-content {
                margin-left: 55px !important;
            }
            .menu-item {
                padding: 8px 0 !important;
                margin: 8px 0 !important;
            }
            .menu-item i {
                font-size: 1rem !important;
            }
            .logout-item .menu-item i {
                font-size: 1rem !important;
            }
        }
    </style>
</head>
<body>

<!-- ========== TOAST CONTAINER ========== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ========== SIDEBAR ========== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="row-cfms">
            <span class="brand">CFMS <span>| Admin</span></span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">Admin Portal</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>

    <div class="sidebar-menu">
        <a href="admin_dashboard.php" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="manage_users.php" class="menu-item">
            <i class="fas fa-users"></i><span>Manage Users</span>
        </a>
        <a href="add_user.php" class="menu-item active">
            <i class="fas fa-user-plus"></i><span>Add User</span>
        </a>
        <a href="all_complaints.php" class="menu-item">
            <i class="fas fa-file-alt"></i><span>All Complaints</span>
        </a>
        <a href="announcements.php" class="menu-item">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        <a href="system_logs.php" class="menu-item">
            <i class="fas fa-history"></i><span>System Logs</span>
        </a>
        <a href="profile.php" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="change_password.php" class="menu-item">
            <i class="fas fa-key"></i><span>Change Password</span>
        </a>
    </div>
    
    <div class="logout-item">
        <div class="menu-item" id="logoutBtn">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">
    <!-- TOP BAR -->
    <div class="top-bar">
        <div>
            <div style="font-size: 0.9rem; font-weight: 600; color: #0a2a5e;">
                <i class="fas fa-university" style="color: #1a56db;"></i> Welcome, <?php echo htmlspecialchars($admin_name); ?>
            </div>
            <div style="font-size: 0.75rem; color: #6b85a0;">System Administrator</div>
        </div>
        <div class="profile-info">
            <div class="profile-pic">
                <?php if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="reg">Admin</div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD BODY -->
    <div class="dashboard-body">
        <?php if (isset($_SESSION['toast_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type']; ?>');
                });
            </script>
            <?php unset($_SESSION['toast_message']); unset($_SESSION['toast_type']); ?>
        <?php endif; ?>
        
        <div class="content-area">
            <h4><i class="fas fa-user-plus" style="color:#1a56db;"></i> Add New User</h4>
            
            <div class="tab-buttons">
                <button type="button" class="tab-btn active" id="btnStudent" onclick="showTab('student')"><i class="fas fa-user-graduate"></i> Add Student</button>
                <button type="button" class="tab-btn" id="btnStaff" onclick="showTab('staff')"><i class="fas fa-user-tie"></i> Add Staff</button>
            </div>
            
            <!-- Student Form -->
            <div id="studentForm">
                <form method="POST" enctype="multipart/form-data" id="studentRegistrationForm">
                    <input type="hidden" name="add_student" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Registration Number <span class="required">*</span></label>
                            <input type="text" name="reg_number" id="reg_number" required placeholder="BCS-00-0000-0000">
                            <div class="helper-text">Format: XXX-XX-XXXX-XXXX (e.g., BCS-00-0000-0000 or BIRM-01-0001-2024)</div>
                            <div class="error-message" id="reg_number_error"></div>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" id="email" required placeholder="student@students.iaa.ac.tz">
                            <div class="error-message" id="email_error"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" id="full_name" required placeholder="Enter full name">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" id="phone" maxlength="10" placeholder="0712345678">
                            <div class="helper-text">Maximum 10 digits (e.g., 0712345678)</div>
                            <div class="error-message" id="phone_error"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course / Programme <span class="required">*</span></label>
                            <select name="course_id" id="course_id" required onchange="updateCourseDetails()">
                                <option value="">-- Select Course --</option>
                                <?php
                                $levels = ['certificate', 'diploma', 'bachelor', 'master'];
                                $level_labels = [
                                    'certificate' => '📘 CERTIFICATE PROGRAMMES',
                                    'diploma' => '📗 DIPLOMA PROGRAMMES',
                                    'bachelor' => '📕 BACHELOR DEGREES',
                                    'master' => '📙 MASTER DEGREES'
                                ];
                                
                                foreach ($levels as $level):
                                    $courses_sql = "SELECT c.*, d.name as dept_name FROM courses c 
                                                   JOIN departments d ON c.department_id = d.id 
                                                   WHERE c.is_active = 1 AND c.level = '$level'
                                                   ORDER BY c.name";
                                    $courses_result = mysqli_query($conn, $courses_sql);
                                    if (mysqli_num_rows($courses_result) > 0):
                                ?>
                                    <option disabled style="background:#f0f4ff; font-weight:bold; color:#1a56db;">— <?php echo $level_labels[$level]; ?> —</option>
                                    <?php while ($course = mysqli_fetch_assoc($courses_result)): ?>
                                        <option value="<?php echo $course['id']; ?>" 
                                                data-level="<?php echo $course['level']; ?>"
                                                data-duration="<?php echo $course['duration_years']; ?>"
                                                data-dept="<?php echo htmlspecialchars($course['dept_name']); ?>">
                                            <?php echo htmlspecialchars($course['name']); ?> (<?php echo $course['duration_years']; ?> yrs)
                                        </option>
                                    <?php endwhile; ?>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Academic Year <span class="required">*</span></label>
                            <select name="academic_year_id" id="academic_year_id" required>
                                <option value="">-- Select Academic Year --</option>
                                <?php foreach ($academic_years as $ay): ?>
                                    <option value="<?php echo $ay['id']; ?>" <?php echo ($ay['year_name'] == '2025/2026') ? 'selected' : ''; ?>>
                                        <?php echo $ay['year_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year of Study <span class="required">*</span></label>
                            <select name="year_of_study" id="year_of_study" required>
                                <option value="">-- Select Course First --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" id="department_display" readonly style="background:#f8fafc; cursor:default;">
                            <div class="helper-text">Auto-filled from course selection</div>
                        </div>
                    </div>
                    
                    <div id="courseDetails" class="course-details">
                        <h5><i class="fas fa-info-circle" style="color:#1a56db;"></i> Course Details</h5>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="label">📊 Level</div>
                                <div class="value" id="course_level"><span class="badge badge-blue">Select a course</span></div>
                            </div>
                            <div class="detail-item">
                                <div class="label">⏱️ Duration</div>
                                <div class="value" id="course_duration">--</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="text" name="password" id="password" required placeholder="Enter password">
                            <div class="helper-text">Minimum 6 characters</div>
                            <div class="error-message" id="password_error"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <input type="text" name="confirm_password" id="confirm_password" required placeholder="Confirm password">
                            <div class="error-message" id="confirm_password_error"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Profile Picture (optional)</label>
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <div class="helper-text">Allowed: JPG, PNG, GIF (Max 2MB)</div>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-save"></i> Register Student</button>
                </form>
            </div>
            
            <!-- Staff Form -->
            <div id="staffForm" style="display: none;">
                <form method="POST" enctype="multipart/form-data" id="staffRegistrationForm">
                    <input type="hidden" name="add_staff" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" id="staff_email" required placeholder="staff@iaa.ac.tz">
                            <div class="error-message" id="staff_email_error"></div>
                        </div>
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" id="staff_full_name" required placeholder="Enter full name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" id="staff_phone" maxlength="10" placeholder="0712345678">
                            <div class="helper-text">Maximum 10 digits (e.g., 0712345678)</div>
                            <div class="error-message" id="staff_phone_error"></div>
                        </div>
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" id="staff_role" required>
                                <option value="">-- Select Role --</option>
                                <option value="hod">HOD (Head of Department)</option>
                                <option value="dean">Dean of Students</option>
                                <option value="accountant">Accountant</option>
                                <option value="examination_officer">Examination Officer</option>
                                <option value="president">Student President (IAASO)</option>
                                <option value="deputy_rector">Deputy Rector</option>
                                <option value="rector">Rector</option>
                                <option value="it_officer">IT Officer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Department (if applicable)</label>
                        <select name="department_id" id="staff_department">
                            <option value="">No Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="text" name="password" id="staff_password" required placeholder="Enter password">
                            <div class="helper-text">Minimum 6 characters</div>
                            <div class="error-message" id="staff_password_error"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <input type="text" name="confirm_password" id="staff_confirm_password" required placeholder="Confirm password">
                            <div class="error-message" id="staff_confirm_password_error"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Profile Picture (optional)</label>
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <div class="helper-text">Allowed: JPG, PNG, GIF (Max 2MB)</div>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="staffSubmitBtn"><i class="fas fa-save"></i> Add Staff</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========== LOGOUT MODAL ========== -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-container">
        <i class="fas fa-sign-out-alt"></i>
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>
        <div class="modal-buttons">
            <button class="modal-btn confirm" id="confirmLogout">Yes, Logout</button>
            <button class="modal-btn cancel" id="cancelLogout">No, Cancel</button>
        </div>
    </div>
</div>

<!-- ========== LOADING OVERLAY ========== -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <div class="loading-text">
            <i class="fas fa-spinner fa-spin"></i> Logging out...
        </div>
    </div>
</div>

<!-- ========== JAVASCRIPT ========== -->
<script>
    // ========== TOAST NOTIFICATIONS ==========
    function showToast(message, type = 'info', duration = 3500) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-triangle'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info}"></i>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.closest('.toast').remove()">
                <i class="fas fa-times"></i>
            </button>
            <div class="toast-progress"></div>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) toast.remove();
                }, 400);
            }
        }, duration);
    }

    // ---------- SIDEBAR TOGGLE ----------
    const sidebar = document.getElementById('sidebar');
    const toggleInline = document.getElementById('toggleInline');
    const toggleStandalone = document.getElementById('toggleStandalone');

    function updateToggleIcons(collapsed) {
        const arrow = collapsed ? '❯' : '❮';
        toggleInline.innerHTML = arrow;
        toggleStandalone.innerHTML = arrow;
    }

    function isMobile() {
        return window.innerWidth <= 768;
    }

    if (isMobile()) {
        sidebar.classList.add('collapsed');
        updateToggleIcons(true);
        toggleInline.style.pointerEvents = 'none';
        toggleInline.style.opacity = '0.5';
        toggleStandalone.style.pointerEvents = 'none';
        toggleStandalone.style.opacity = '0.5';
    } else {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            updateToggleIcons(true);
        } else {
            updateToggleIcons(false);
        }
        toggleInline.style.pointerEvents = 'auto';
        toggleInline.style.opacity = '1';
        toggleStandalone.style.pointerEvents = 'auto';
        toggleStandalone.style.opacity = '1';
    }

    function toggleSidebar() {
        if (isMobile()) return;
        sidebar.classList.toggle('collapsed');
        const collapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', collapsed);
        updateToggleIcons(collapsed);
    }

    toggleInline.addEventListener('click', toggleSidebar);
    toggleStandalone.addEventListener('click', toggleSidebar);

    window.addEventListener('resize', function() {
        if (isMobile()) {
            sidebar.classList.add('collapsed');
            updateToggleIcons(true);
            toggleInline.style.pointerEvents = 'none';
            toggleInline.style.opacity = '0.5';
            toggleStandalone.style.pointerEvents = 'none';
            toggleStandalone.style.opacity = '0.5';
        } else {
            toggleInline.style.pointerEvents = 'auto';
            toggleInline.style.opacity = '1';
            toggleStandalone.style.pointerEvents = 'auto';
            toggleStandalone.style.opacity = '1';
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                updateToggleIcons(true);
            } else {
                sidebar.classList.remove('collapsed');
                updateToggleIcons(false);
            }
        }
    });

    // ---------- TAB SWITCHING ----------
    function showTab(tab) {
        const studentForm = document.getElementById('studentForm');
        const staffForm = document.getElementById('staffForm');
        const btnStudent = document.getElementById('btnStudent');
        const btnStaff = document.getElementById('btnStaff');
        
        if (tab === 'student') {
            studentForm.style.display = 'block';
            staffForm.style.display = 'none';
            btnStudent.classList.add('active');
            btnStaff.classList.remove('active');
        } else {
            studentForm.style.display = 'none';
            staffForm.style.display = 'block';
            btnStaff.classList.add('active');
            btnStudent.classList.remove('active');
        }
    }

    // ---------- COURSE DETAILS ----------
    function updateCourseDetails() {
        const select = document.getElementById('course_id');
        const selectedOption = select.options[select.selectedIndex];
        const courseDetails = document.getElementById('courseDetails');
        const yearSelect = document.getElementById('year_of_study');
        const departmentDisplay = document.getElementById('department_display');
        
        if (selectedOption && selectedOption.value) {
            const level = selectedOption.dataset.level;
            const duration = parseInt(selectedOption.dataset.duration);
            const dept = selectedOption.dataset.dept;
            
            let levelText = '';
            if (level === 'certificate') levelText = '🎓 Certificate Programme';
            else if (level === 'diploma') levelText = '📜 Diploma Programme';
            else if (level === 'bachelor') levelText = '🎓 Bachelor Degree';
            else if (level === 'master') levelText = '🏆 Master Degree';
            
            document.getElementById('course_level').innerHTML = `<span class="badge badge-blue">${levelText}</span>`;
            document.getElementById('course_duration').innerHTML = `${duration} year${duration > 1 ? 's' : ''}`;
            departmentDisplay.value = dept;
            courseDetails.style.display = 'block';
            
            // Populate year of study dropdown
            yearSelect.innerHTML = '<option value="">-- Select Year of Study --</option>';
            for (let i = 1; i <= duration; i++) {
                let yearText = '';
                if (i === 1) yearText = 'Year 1 (First Year)';
                else if (i === 2) yearText = 'Year 2 (Second Year)';
                else if (i === 3) yearText = 'Year 3 (Third Year)';
                else if (i === 4) yearText = 'Year 4 (Fourth Year)';
                else yearText = 'Year ' + i;
                yearSelect.innerHTML += `<option value="${i}">${yearText}</option>`;
            }
        } else {
            courseDetails.style.display = 'none';
            departmentDisplay.value = '';
            yearSelect.innerHTML = '<option value="">-- Select Course First --</option>';
        }
    }

    // ---------- VALIDATION FUNCTIONS ----------
    function validateStudentForm() {
        let isValid = true;
        
        // Registration number validation - allow 3+ characters for prefix
        const regNumber = document.getElementById('reg_number');
        const regNumberError = document.getElementById('reg_number_error');
        const regNumberPattern = /^[A-Z]{3,}-\d{2}-\d{4}-\d{4}$/;
        if (!regNumberPattern.test(regNumber.value)) {
            regNumberError.innerHTML = 'Format must be: XXX-XX-XXXX-XXXX (e.g., BCS-00-0000-0000 or BIRM-01-0001-2024)';
            regNumberError.style.display = 'block';
            regNumber.classList.add('error');
            isValid = false;
        } else {
            regNumberError.style.display = 'none';
            regNumber.classList.remove('error');
        }
        
        // Email validation
        const email = document.getElementById('email');
        const emailError = document.getElementById('email_error');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            emailError.innerHTML = 'Please enter a valid email address';
            emailError.style.display = 'block';
            email.classList.add('error');
            isValid = false;
        } else {
            emailError.style.display = 'none';
            email.classList.remove('error');
        }
        
        // Phone number validation
        const phone = document.getElementById('phone');
        const phoneError = document.getElementById('phone_error');
        if (phone.value && phone.value.length > 10) {
            phoneError.innerHTML = 'Phone number must not exceed 10 digits';
            phoneError.style.display = 'block';
            phone.classList.add('error');
            isValid = false;
        } else if (phone.value && !/^\d+$/.test(phone.value)) {
            phoneError.innerHTML = 'Phone number must contain only digits';
            phoneError.style.display = 'block';
            phone.classList.add('error');
            isValid = false;
        } else {
            phoneError.style.display = 'none';
            phone.classList.remove('error');
        }
        
        // Password validation
        const password = document.getElementById('password');
        const passwordError = document.getElementById('password_error');
        if (password.value.length < 6) {
            passwordError.innerHTML = 'Password must be at least 6 characters';
            passwordError.style.display = 'block';
            password.classList.add('error');
            isValid = false;
        } else {
            passwordError.style.display = 'none';
            password.classList.remove('error');
        }
        
        // Confirm password validation
        const confirmPassword = document.getElementById('confirm_password');
        const confirmPasswordError = document.getElementById('confirm_password_error');
        if (password.value !== confirmPassword.value) {
            confirmPasswordError.innerHTML = 'Passwords do not match';
            confirmPasswordError.style.display = 'block';
            confirmPassword.classList.add('error');
            isValid = false;
        } else {
            confirmPasswordError.style.display = 'none';
            confirmPassword.classList.remove('error');
        }
        
        return isValid;
    }

    function validateStaffForm() {
        let isValid = true;
        
        const email = document.getElementById('staff_email');
        const emailError = document.getElementById('staff_email_error');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            emailError.innerHTML = 'Please enter a valid email address';
            emailError.style.display = 'block';
            email.classList.add('error');
            isValid = false;
        } else {
            emailError.style.display = 'none';
            email.classList.remove('error');
        }
        
        const phone = document.getElementById('staff_phone');
        const phoneError = document.getElementById('staff_phone_error');
        if (phone.value && phone.value.length > 10) {
            phoneError.innerHTML = 'Phone number must not exceed 10 digits';
            phoneError.style.display = 'block';
            phone.classList.add('error');
            isValid = false;
        } else if (phone.value && !/^\d+$/.test(phone.value)) {
            phoneError.innerHTML = 'Phone number must contain only digits';
            phoneError.style.display = 'block';
            phone.classList.add('error');
            isValid = false;
        } else {
            phoneError.style.display = 'none';
            phone.classList.remove('error');
        }
        
        const password = document.getElementById('staff_password');
        const passwordError = document.getElementById('staff_password_error');
        if (password.value.length < 6) {
            passwordError.innerHTML = 'Password must be at least 6 characters';
            passwordError.style.display = 'block';
            password.classList.add('error');
            isValid = false;
        } else {
            passwordError.style.display = 'none';
            password.classList.remove('error');
        }
        
        const confirmPassword = document.getElementById('staff_confirm_password');
        const confirmPasswordError = document.getElementById('staff_confirm_password_error');
        if (password.value !== confirmPassword.value) {
            confirmPasswordError.innerHTML = 'Passwords do not match';
            confirmPasswordError.style.display = 'block';
            confirmPassword.classList.add('error');
            isValid = false;
        } else {
            confirmPasswordError.style.display = 'none';
            confirmPassword.classList.remove('error');
        }
        
        return isValid;
    }

    // ---------- EVENT LISTENERS ----------
    const studentForm = document.getElementById('studentRegistrationForm');
    if (studentForm) {
        studentForm.addEventListener('submit', function(e) {
            if (!validateStudentForm()) {
                e.preventDefault();
                showToast('Please fix the errors in the form', 'error');
            }
        });
    }

    const staffForm = document.getElementById('staffRegistrationForm');
    if (staffForm) {
        staffForm.addEventListener('submit', function(e) {
            if (!validateStaffForm()) {
                e.preventDefault();
                showToast('Please fix the errors in the form', 'error');
            }
        });
    }

    // Real-time validation
    document.getElementById('reg_number')?.addEventListener('input', function() {
        const pattern = /^[A-Z]{3,}-\d{2}-\d{4}-\d{4}$/;
        const error = document.getElementById('reg_number_error');
        if (!pattern.test(this.value) && this.value) {
            error.innerHTML = 'Format: XXX-XX-XXXX-XXXX (e.g., BCS-00-0000-0000 or BIRM-01-0001-2024)';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    document.getElementById('phone')?.addEventListener('input', function() {
        const error = document.getElementById('phone_error');
        if (this.value.length > 10) {
            error.innerHTML = 'Maximum 10 digits allowed';
            error.style.display = 'block';
            this.classList.add('error');
        } else if (this.value && !/^\d+$/.test(this.value)) {
            error.innerHTML = 'Only digits allowed';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    document.getElementById('password')?.addEventListener('input', function() {
        const error = document.getElementById('password_error');
        if (this.value.length < 6 && this.value) {
            error.innerHTML = 'Minimum 6 characters required';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    document.getElementById('confirm_password')?.addEventListener('input', function() {
        const password = document.getElementById('password');
        const error = document.getElementById('confirm_password_error');
        if (password.value !== this.value && this.value) {
            error.innerHTML = 'Passwords do not match';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    document.getElementById('staff_phone')?.addEventListener('input', function() {
        const error = document.getElementById('staff_phone_error');
        if (this.value.length > 10) {
            error.innerHTML = 'Maximum 10 digits allowed';
            error.style.display = 'block';
            this.classList.add('error');
        } else if (this.value && !/^\d+$/.test(this.value)) {
            error.innerHTML = 'Only digits allowed';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    document.getElementById('staff_password')?.addEventListener('input', function() {
        const error = document.getElementById('staff_password_error');
        if (this.value.length < 6 && this.value) {
            error.innerHTML = 'Minimum 6 characters required';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    document.getElementById('staff_confirm_password')?.addEventListener('input', function() {
        const password = document.getElementById('staff_password');
        const error = document.getElementById('staff_confirm_password_error');
        if (password.value !== this.value && this.value) {
            error.innerHTML = 'Passwords do not match';
            error.style.display = 'block';
            this.classList.add('error');
        } else {
            error.style.display = 'none';
            this.classList.remove('error');
        }
    });

    // ---------- LOGOUT ----------
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        logoutModal.classList.add('active');
    });

    confirmLogout.addEventListener('click', function() {
        logoutModal.classList.remove('active');
        document.getElementById('loadingOverlay').classList.add('active');
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 500);
    });

    cancelLogout.addEventListener('click', function() {
        logoutModal.classList.remove('active');
    });

    logoutModal.addEventListener('click', function(e) {
        if (e.target === this) {
            logoutModal.classList.remove('active');
        }
    });
</script>
</body>
</html>