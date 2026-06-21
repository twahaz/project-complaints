<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'it_officer') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/connection.php';

$it_officer_id = $_SESSION['user_id'];
$it_officer_name = $_SESSION['full_name'];
$it_officer_email = $_SESSION['email'];

// Fetch department info
$dept_sql = "SELECT name FROM departments WHERE id = (SELECT department_id FROM users WHERE id = ?)";
$dept_stmt = mysqli_prepare($conn, $dept_sql);
mysqli_stmt_bind_param($dept_stmt, "i", $it_officer_id);
mysqli_stmt_execute($dept_stmt);
$dept_result = mysqli_stmt_get_result($dept_stmt);
$dept_row = mysqli_fetch_assoc($dept_result);
$dept_name = $dept_row['name'] ?? 'IT Department';
mysqli_stmt_close($dept_stmt);

// Category for IT Officer
$it_category = ['IT Support'];
$category_in_list = "'" . implode("','", $it_category) . "'";

// Handle reply to complaint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_complaint'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $message = trim($_POST['message']);
    $new_status = $_POST['status'] ?? 'in_progress';
    
    $check_sql = "SELECT c.status, cat.name as category_name 
                  FROM complaints c 
                  JOIN categories cat ON c.category_id = cat.id 
                  WHERE c.id = ? AND cat.name IN ($category_in_list)";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $complaint_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if (!$check_row) {
        $_SESSION['flash_message'] = "Complaint not found or not in your category.";
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=complaints");
        exit();
    }
    
    if ($check_row['status'] === 'escalated') {
        $_SESSION['flash_message'] = "This complaint has been escalated and is read‑only.";
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
        exit();
    }
    
    if (!empty($message)) {
        $insert_sql = "INSERT INTO responses (complaint_id, user_id, message) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "iis", $complaint_id, $it_officer_id, $message);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
        
        $update_assigned = "UPDATE complaints SET assigned_to = ? WHERE id = ? AND assigned_to IS NULL";
        $assign_stmt = mysqli_prepare($conn, $update_assigned);
        mysqli_stmt_bind_param($assign_stmt, "ii", $it_officer_id, $complaint_id);
        mysqli_stmt_execute($assign_stmt);
        mysqli_stmt_close($assign_stmt);
        
        if ($new_status === 'escalated') {
            $deputy_sql = "SELECT id FROM users WHERE role = 'deputy_rector' LIMIT 1";
            $deputy_stmt = mysqli_prepare($conn, $deputy_sql);
            mysqli_stmt_execute($deputy_stmt);
            $deputy_result = mysqli_stmt_get_result($deputy_stmt);
            $deputy_row = mysqli_fetch_assoc($deputy_result);
            $deputy_id = $deputy_row['id'] ?? null;
            mysqli_stmt_close($deputy_stmt);
            if ($deputy_id) {
                $update_sql = "UPDATE complaints SET status = ?, escalated_to = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $deputy_id, $complaint_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                $_SESSION['flash_message'] = "Reply sent! Complaint escalated to Deputy Rector.";
            } else {
                $_SESSION['flash_message'] = "Deputy Rector not found.";
                $_SESSION['flash_type'] = "error";
                header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
                exit();
            }
        } else {
            $update_sql = "UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $new_status, $complaint_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            $_SESSION['flash_message'] = "Reply sent successfully!";
        }
        $_SESSION['flash_type'] = "success";
        header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
        exit();
    } else {
        $_SESSION['flash_message'] = "Message cannot be empty.";
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
        exit();
    }
}

// Handle status update only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $new_status = $_POST['status'];
    
    $check_sql = "SELECT c.status, cat.name as category_name 
                  FROM complaints c 
                  JOIN categories cat ON c.category_id = cat.id 
                  WHERE c.id = ? AND cat.name IN ($category_in_list)";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $complaint_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if (!$check_row) {
        $_SESSION['flash_message'] = "Complaint not found or not in your category.";
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=complaints");
        exit();
    }
    
    if ($check_row['status'] === 'escalated') {
        $_SESSION['flash_message'] = "This complaint has been escalated and is read‑only.";
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
        exit();
    }
    
    $update_assigned = "UPDATE complaints SET assigned_to = ? WHERE id = ? AND assigned_to IS NULL";
    $assign_stmt = mysqli_prepare($conn, $update_assigned);
    mysqli_stmt_bind_param($assign_stmt, "ii", $it_officer_id, $complaint_id);
    mysqli_stmt_execute($assign_stmt);
    mysqli_stmt_close($assign_stmt);
    
    if ($new_status === 'escalated') {
        $deputy_sql = "SELECT id FROM users WHERE role = 'deputy_rector' LIMIT 1";
        $deputy_stmt = mysqli_prepare($conn, $deputy_sql);
        mysqli_stmt_execute($deputy_stmt);
        $deputy_result = mysqli_stmt_get_result($deputy_stmt);
        $deputy_row = mysqli_fetch_assoc($deputy_result);
        $deputy_id = $deputy_row['id'] ?? null;
        mysqli_stmt_close($deputy_stmt);
        if ($deputy_id) {
            $update_sql = "UPDATE complaints SET status = ?, escalated_to = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $deputy_id, $complaint_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            $_SESSION['flash_message'] = "Complaint escalated to Deputy Rector.";
        } else {
            $_SESSION['flash_message'] = "Deputy Rector not found.";
            $_SESSION['flash_type'] = "error";
            header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
            exit();
        }
    } else {
        $update_sql = "UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $complaint_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        $_SESSION['flash_message'] = "Status updated to " . ucfirst($new_status);
    }
    $_SESSION['flash_type'] = "success";
    header("Location: it_officer_dashboard.php?page=view-complaint&id=" . $complaint_id);
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_full_name = trim($_POST['full_name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $errors = [];
    if (empty($new_full_name)) $errors[] = "Full name is required.";
    $new_profile_picture = $_SESSION['profile_picture'] ?? '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 2 * 1024 * 1024;
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Image size must be less than 2MB.";
        } else {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'it_officer_' . $it_officer_id . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $new_profile_picture = 'uploads/profiles/' . $new_filename;
                if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])) {
                    unlink('../' . $_SESSION['profile_picture']);
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }
    if (empty($errors)) {
        $update_sql = "UPDATE users SET full_name = ?, phone_number = ?, profile_picture = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sssi", $new_full_name, $new_phone, $new_profile_picture, $it_officer_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        $_SESSION['full_name'] = $new_full_name;
        $_SESSION['profile_picture'] = $new_profile_picture;
        $_SESSION['flash_message'] = "Profile updated successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: it_officer_dashboard.php?page=profile");
        exit();
    } else {
        $_SESSION['flash_message'] = implode("<br>", $errors);
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=profile");
        exit();
    }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $errors = [];
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($errors)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pass_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $pass_stmt = mysqli_prepare($conn, $pass_sql);
        mysqli_stmt_bind_param($pass_stmt, "si", $hashed, $it_officer_id);
        mysqli_stmt_execute($pass_stmt);
        mysqli_stmt_close($pass_stmt);
        $_SESSION['flash_message'] = "Password changed successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: it_officer_dashboard.php?page=change-password");
        exit();
    } else {
        $_SESSION['flash_message'] = implode("<br>", $errors);
        $_SESSION['flash_type'] = "error";
        header("Location: it_officer_dashboard.php?page=change-password");
        exit();
    }
}

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

$active_page = $_GET['page'] ?? 'dashboard';
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get profile data
$prof_sql = "SELECT phone_number, profile_picture FROM users WHERE id = ?";
$prof_stmt = mysqli_prepare($conn, $prof_sql);
mysqli_stmt_bind_param($prof_stmt, "i", $it_officer_id);
mysqli_stmt_execute($prof_stmt);
$prof_result = mysqli_stmt_get_result($prof_stmt);
$prof_data = mysqli_fetch_assoc($prof_result);
$phone = $prof_data['phone_number'] ?? '';
$profile_pic = $prof_data['profile_picture'] ?? '';
if (!isset($_SESSION['profile_picture']) && $profile_pic) $_SESSION['profile_picture'] = $profile_pic;
mysqli_stmt_close($prof_stmt);

// Get statistics for dashboard
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN c.status = 'escalated' THEN 1 ELSE 0 END) as escalated
    FROM complaints c 
    JOIN categories cat ON c.category_id = cat.id 
    WHERE cat.name IN ($category_in_list)";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

// Get LATEST 5 complaints for dashboard
$recent_sql = "SELECT c.id, c.complaint_number, c.title, c.status, c.created_at, u.full_name 
              FROM complaints c 
              JOIN users u ON c.student_id = u.id 
              JOIN categories cat ON c.category_id = cat.id
              WHERE cat.name IN ($category_in_list)
              ORDER BY c.created_at DESC LIMIT 5";
$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);

// Get latest announcements (4 tu - summary with sender)
$announcements = [];
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $announcement_sql = "SELECT a.id, a.title, a.message, a.created_at, u.full_name as sender_name 
                         FROM announcements a
                         LEFT JOIN users u ON a.created_by = u.id
                         WHERE a.is_active = 1 
                         AND (a.target_type = 'all' 
                              OR a.target_type = 'staff'
                              OR (a.target_type = 'department' AND a.target_id = ?)
                              OR (a.target_type = 'individual' AND a.target_id = ?))
                         ORDER BY a.created_at DESC LIMIT 4";
    $ann_stmt = mysqli_prepare($conn, $announcement_sql);
    if ($ann_stmt) {
        $dept_id = $_SESSION['department_id'] ?? null;
        mysqli_stmt_bind_param($ann_stmt, "ii", $dept_id, $it_officer_id);
        mysqli_stmt_execute($ann_stmt);
        $ann_result = mysqli_stmt_get_result($ann_stmt);
        while ($ann = mysqli_fetch_assoc($ann_result)) {
            $announcements[] = $ann;
        }
        mysqli_stmt_close($ann_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>IT Officer Dashboard - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           UI SANA NA STUDENT DASHBOARD
           ============================================ */
        
        /* ---------- RESET & BASE ---------- */
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

        .toast.hide {
            animation: toastSlideOut 0.4s ease-in forwards;
        }

        .toast-success { border-left: 4px solid #10b981; }
        .toast-success i { color: #10b981; }
        .toast-error { border-left: 4px solid #dc2626; }
        .toast-error i { color: #dc2626; }
        .toast-info { border-left: 4px solid #1a56db; }
        .toast-info i { color: #1a56db; }

        .toast i {
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .toast .toast-message {
            flex: 1;
            color: #1f2c40;
        }
        .toast .toast-close {
            background: none;
            border: none;
            color: #8ba0bc;
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            transition: color 0.2s;
            flex-shrink: 0;
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
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
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

        /* ========== SIDEBAR HEADER ========== */
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

        /* ========== SIDEBAR MENU - BLUE SAFI (#1a56db) ========== */
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

        /* ========== LOGOUT - FIXED, NO TRANSFORM ========== */
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

        /* ========== SUMMARY CARDS ========== */
        .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(10,42,94,0.06);
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(10,42,94,0.10);
        }

        .summary-card .icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .summary-card .icon-wrapper.blue { background: #dbeafe; color: #1e40af; }
        .summary-card .icon-wrapper.yellow { background: #fef3c7; color: #b45309; }
        .summary-card .icon-wrapper.purple { background: #ede9fe; color: #6d28d9; }
        .summary-card .icon-wrapper.green { background: #d1fae5; color: #065f46; }
        .summary-card .icon-wrapper.red { background: #fee2e2; color: #991b1b; }

        .summary-card .info { flex: 1; }
        .summary-card .info .number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0a2a5e;
            line-height: 1.2;
        }
        .summary-card .info .label {
            color: #6b85a0;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* ========== GRIDI 70% / 30% ========== */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 70% 30%;
            gap: 24px;
            margin-top: 0;
        }

        /* ---------- TABLE SECTION (70%) ---------- */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 24px 28px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 350px;
        }
        .content-area h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ---------- ANNOUNCEMENTS SIDEBAR (30%) ---------- */
        .announcements-sidebar {
            background: white;
            border-radius: 20px;
            padding: 24px 22px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 350px;
        }
        .announcements-sidebar h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .announcements-sidebar h4 i {
            color: #f59e0b;
        }
        .announcements-sidebar .view-all {
            font-size: 0.75rem;
            color: #1a56db;
            text-decoration: none;
            font-weight: 600;
            margin-left: auto;
        }
        .announcements-sidebar .view-all:hover {
            text-decoration: underline;
        }

        /* Announcement Item - Title, Sender, Time ONLY */
        .announcement-summary {
            padding: 14px 0;
            border-bottom: 1px solid #f0f4f9;
        }
        .announcement-summary:last-child {
            border-bottom: none;
        }
        .announcement-summary .a-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #0a2a5e;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .announcement-summary .a-title .badge-new {
            background: #1a56db;
            color: white;
            font-size: 0.55rem;
            padding: 2px 10px;
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .announcement-summary .a-sender {
            font-size: 0.78rem;
            color: #6b85a0;
            margin-top: 3px;
        }
        .announcement-summary .a-sender i {
            margin-right: 4px;
            color: #1a56db;
        }
        .announcement-summary .a-time {
            font-size: 0.65rem;
            color: #8ba0bc;
            margin-top: 3px;
        }
        .announcement-summary .a-time i {
            margin-right: 4px;
        }

        .no-announcements-sidebar {
            color: #8ba0bc;
            text-align: center;
            padding: 30px 0;
            font-size: 0.9rem;
        }
        .no-announcements-sidebar i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 8px;
        }

        /* ---------- BUTTONS - BLUE ========== */
        .btn-sm {
            padding: 6px 20px;
            border-radius: 30px;
            background: #1a56db;
            color: white;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-sm:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
            color: white;
        }

        .btn-secondary-sm {
            padding: 6px 20px;
            border-radius: 30px;
            background: #6b85a0;
            color: white;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-secondary-sm:hover {
            background: #4a5a7a;
            transform: translateY(-2px);
            color: white;
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
        }
        .btn-submit:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 86, 219, 0.3);
        }

        /* ---------- BADGE ---------- */
        .badge {
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-in-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #d1fae5; color: #065f46; }
        .badge-escalated { background: #fee2e2; color: #991b1b; }

        /* ---------- TABLE ---------- */
        .table-responsive { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }

        .complaints-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .complaints-table thead th {
            background: #f8fafc;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            color: #4a5a7a;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e5edf5;
        }
        .complaints-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f0f4f9;
            color: #1f2c40;
            font-size: 0.85rem;
        }
        .complaints-table tbody tr:hover {
            background: #fafcff;
        }
        .complaints-table tbody tr:last-child td { border-bottom: none; }

        /* ---------- FILTERS BAR ---------- */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 16px;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 3px;
            color: #4a5a7a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid #e5edf5;
            border-radius: 10px;
            font-size: 0.85rem;
            background: white;
            transition: border 0.2s;
        }
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.08);
        }
        .filter-group .filter-btn {
            background: #1a56db;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
        }
        .filter-group .filter-btn:hover {
            background: #0d3b8a;
            transform: translateY(-1px);
        }

        /* ---------- PAGINATION ---------- */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 14px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #e5edf5;
            background: white;
            color: #4a5a7a;
        }
        .pagination a:hover {
            background: #f0f4f9;
            border-color: #1a56db;
            color: #1a56db;
        }
        .pagination .current {
            background: #1a56db;
            color: white;
            border-color: #1a56db;
        }

        /* ---------- COMPLAINT DETAIL ---------- */
        .complaint-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .complaint-detail-grid .detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .complaint-detail-grid .detail-item .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8ba0bc;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .complaint-detail-grid .detail-item .value {
            font-size: 0.95rem;
            color: #1f2c40;
            font-weight: 500;
        }

        .description-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .description-box .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8ba0bc;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
        }
        .description-box .value {
            line-height: 1.7;
            color: #1f2c40;
            font-size: 0.95rem;
        }

        /* ---------- CONVERSATION ---------- */
        .conversation {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5edf5;
            border-radius: 16px;
            padding: 16px 20px;
            background: #fafcff;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 16px;
            padding: 14px 18px;
            border-radius: 14px;
        }
        .message:last-child { margin-bottom: 0; }
        .student-message {
            background: #e0f2fe;
            border-left: 4px solid #1a56db;
        }
        .staff-message {
            background: #f1f5f9;
            border-left: 4px solid #10b981;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 0.82rem;
        }
        .message-header .sender {
            font-weight: 600;
            color: #0a2a5e;
        }
        .message-header .time {
            color: #8ba0bc;
            font-size: 0.7rem;
        }
        .message-body {
            line-height: 1.6;
            color: #1f2c40;
            font-size: 0.9rem;
        }

        /* ---------- SLA CARD ---------- */
        .sla-card {
            background: linear-gradient(135deg, #f0f4ff 0%, #e9effa 100%);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-left: 4px solid #1a56db;
            border: 1px solid #dbeafe;
        }
        .sla-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0a2a5e;
        }
        .sla-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }
        .sla-item .sla-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #6b85a0;
            letter-spacing: 0.3px;
        }
        .sla-item .sla-value {
            font-size: 1rem;
            font-weight: 600;
            color: #0a2a5e;
            margin-top: 2px;
        }
        .deadline-timer {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 700;
            background: #ffffffcc;
            display: inline-block;
            padding: 4px 14px;
            border-radius: 30px;
            border: 1px solid #dbeafe;
        }
        .sla-overdue {
            color: #991b1b;
            background: #fee2e2;
            border-radius: 30px;
            padding: 4px 14px;
            display: inline-block;
            font-weight: 600;
        }
        .sla-resolved {
            color: #065f46;
            background: #d1fae5;
            border-radius: 30px;
            padding: 4px 14px;
            display: inline-block;
        }

        /* ---------- REPLY & STATUS SECTIONS ---------- */
        .reply-section, .status-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5edf5;
        }
        .reply-section h5, .status-section h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 12px;
        }

        /* ---------- FORMS ---------- */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #0a2a5e;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e5edf5;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: border 0.2s;
            background: #fafcff;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.08);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

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
            .content-area { padding: 20px 24px; min-height: auto; }
            .summary-row { grid-template-columns: repeat(3, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr; gap: 20px; }
        }

        @media (max-width: 768px) {
            .sidebar { width: 70px !important; overflow: hidden; }
            .sidebar:not(.collapsed) { width: 70px !important; }
            .sidebar.collapsed { width: 70px !important; }
            
            .brand { font-size: 0.8rem !important; }
            .brand span { display: none !important; }
            .row-tagline { display: none !important; }
            .row-cfms { flex-direction: column !important; gap: 4px !important; justify-content: center !important; align-items: center !important; }
            .toggle-inline { display: flex !important; }
            .toggle-standalone { display: none !important; }
            
            .menu-item { justify-content: center !important; padding: 12px 0 !important; margin: 4px 0 !important; gap: 0 !important; border-radius: 10px !important; }
            .menu-item span { display: none !important; }
            .menu-item i { font-size: 1.3rem !important; width: 100% !important; text-align: center !important; margin: 0 !important; }
            .menu-item:hover { transform: none !important; }
            
            .logout-item .menu-item span { display: none !important; }
            .logout-item .menu-item i { font-size: 1.3rem !important; }

            .main-content { margin-left: 70px !important; }
            .sidebar.collapsed ~ .main-content { margin-left: 70px !important; }

            .dashboard-body { padding: 16px; }
            .top-bar { padding: 12px 16px; gap: 10px; }

            .profile-details .name { font-size: 0.8rem; }
            .profile-details .reg { font-size: 0.6rem; }
            .profile-pic { width: 36px; height: 36px; }

            .content-area { padding: 16px; border-radius: 16px; }
            .content-area h4 { font-size: 0.95rem; }

            .summary-row { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .summary-card { padding: 16px 14px; min-height: 80px; }
            .summary-card .icon-wrapper { width: 40px; height: 40px; font-size: 1.2rem; }
            .summary-card .info .number { font-size: 1.4rem; }

            .dashboard-grid { grid-template-columns: 1fr; gap: 16px; }

            .filters-bar { flex-direction: column; padding: 12px 16px; }
            .filter-group { min-width: unset; width: 100%; }

            .complaints-table thead th,
            .complaints-table tbody td { padding: 8px 8px; font-size: 0.7rem; }
            .complaints-table thead th { font-size: 0.6rem; }
            .btn-sm { padding: 4px 12px; font-size: 0.6rem; }

            .complaint-detail-grid { grid-template-columns: 1fr; padding: 16px; gap: 10px; }
            .sla-stats { grid-template-columns: 1fr; gap: 10px; }

            .announcements-sidebar { min-height: auto; padding: 16px; }

            .reply-section, .status-section { margin-top: 16px; padding-top: 16px; }
            .btn-submit { width: 100%; justify-content: center; }

            .toast-container {
                top: 12px;
                right: 12px;
                max-width: calc(100% - 24px);
            }
        }

        @media (max-width: 480px) {
            .sidebar { width: 60px !important; }
            .sidebar:not(.collapsed) { width: 60px !important; }
            .sidebar.collapsed { width: 60px !important; }
            
            .brand { font-size: 0.7rem !important; }
            .menu-item { padding: 10px 0 !important; margin: 3px 0 !important; }
            .menu-item i { font-size: 1.1rem !important; }
            .logout-item .menu-item i { font-size: 1.1rem !important; }

            .main-content { margin-left: 60px !important; }
            .sidebar.collapsed ~ .main-content { margin-left: 60px !important; }

            .dashboard-body { padding: 12px; }
            .top-bar { padding: 10px 12px; gap: 8px; }
            .profile-pic { width: 32px; height: 32px; }
            .profile-details .name { font-size: 0.7rem; }
            .profile-details .reg { font-size: 0.5rem; }

            .content-area { padding: 12px; border-radius: 12px; }
            .content-area h4 { font-size: 0.85rem; }

            .summary-row { grid-template-columns: 1fr 1fr; gap: 8px; }
            .summary-card { padding: 12px 10px; min-height: 70px; gap: 10px; border-radius: 12px; }
            .summary-card .icon-wrapper { width: 32px; height: 32px; font-size: 0.9rem; }
            .summary-card .info .number { font-size: 1.1rem; }
            .summary-card .info .label { font-size: 0.6rem; }

            .complaints-table thead th,
            .complaints-table tbody td { padding: 6px 6px; font-size: 0.6rem; }
            .complaints-table thead th { font-size: 0.55rem; }
            .btn-sm { padding: 3px 10px; font-size: 0.55rem; }

            .complaint-detail-grid { padding: 12px; gap: 8px; }
            .complaint-detail-grid .detail-item .label { font-size: 0.6rem; }
            .complaint-detail-grid .detail-item .value { font-size: 0.82rem; }

            .description-box { padding: 12px 16px; }
            .description-box .value { font-size: 0.85rem; }

            .conversation { padding: 12px 14px; max-height: 250px; }
            .message { padding: 10px 14px; }
            .message-header { font-size: 0.7rem; flex-direction: column; align-items: flex-start; gap: 4px; }
            .message-body { font-size: 0.82rem; }

            .sla-card { padding: 16px; }
            .sla-stats .sla-item .sla-value { font-size: 0.9rem; }
            .deadline-timer { font-size: 0.9rem; padding: 2px 10px; }

            .announcements-sidebar { padding: 12px; }
            .announcement-summary .a-title { font-size: 0.75rem; }
            .announcement-summary .a-sender { font-size: 0.65rem; }
            .announcement-summary .a-time { font-size: 0.55rem; }

            .btn-submit { padding: 10px 20px; font-size: 0.85rem; }

            .modal-container { padding: 24px 20px; }
            .modal-container h3 { font-size: 1rem; }
            .modal-btn { padding: 8px 20px; font-size: 0.8rem; min-width: 80px; }

            .loading-content { padding: 30px 30px; }
            .spinner { width: 36px; height: 36px; }
            .loading-text { font-size: 0.85rem; }

            .toast-container {
                top: 8px;
                right: 8px;
                max-width: calc(100% - 16px);
            }
            .toast {
                font-size: 0.8rem;
                padding: 12px 16px;
            }
        }

        @media (max-width: 380px) {
            .sidebar { width: 55px !important; }
            .sidebar:not(.collapsed) { width: 55px !important; }
            .sidebar.collapsed { width: 55px !important; }
            .main-content { margin-left: 55px !important; }
            .sidebar.collapsed ~ .main-content { margin-left: 55px !important; }
            .menu-item i { font-size: 0.95rem !important; }

            .summary-row { grid-template-columns: 1fr 1fr; gap: 6px; }
            .summary-card { padding: 10px 8px; min-height: 60px; gap: 8px; }
            .summary-card .icon-wrapper { width: 28px; height: 28px; font-size: 0.75rem; }
            .summary-card .info .number { font-size: 0.95rem; }
            .summary-card .info .label { font-size: 0.55rem; }
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
            <span class="brand">CFMS <span>| IT Officer</span></span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">IT Support</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>

    <div class="sidebar-menu">
        <a href="it_officer_dashboard.php?page=dashboard" class="menu-item <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="it_officer_dashboard.php?page=complaints" class="menu-item <?php echo $active_page == 'complaints' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i><span>All Complaints</span>
        </a>
        <a href="it_officer_dashboard.php?page=pending" class="menu-item <?php echo $active_page == 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i><span>Pending</span>
        </a>
        <a href="it_officer_dashboard.php?page=resolved" class="menu-item <?php echo $active_page == 'resolved' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i><span>Resolved</span>
        </a>
        <a href="it_officer_dashboard.php?page=escalated" class="menu-item <?php echo $active_page == 'escalated' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i><span>Escalated</span>
        </a>
        <a href="it_officer_dashboard.php?page=profile" class="menu-item <?php echo $active_page == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="it_officer_dashboard.php?page=change-password" class="menu-item <?php echo $active_page == 'change-password' ? 'active' : ''; ?>">
            <i class="fas fa-key"></i><span>Change Password</span>
        </a>
        <a href="announcements.php" class="menu-item">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
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
                <i class="fas fa-laptop-code" style="color: #1a56db;"></i> Welcome, <?php echo htmlspecialchars($it_officer_name); ?>
            </div>
            <div style="font-size: 0.75rem; color: #6b85a0;"><?php echo htmlspecialchars($dept_name); ?></div>
        </div>
        <div class="profile-info">
            <div class="profile-pic">
                <?php if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($it_officer_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="name"><?php echo htmlspecialchars($it_officer_name); ?></div>
                <div class="reg">IT Officer</div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD BODY -->
    <div class="dashboard-body">
        <?php if ($active_page == 'dashboard'): ?>
            <!-- Statistics Cards -->
            <div class="summary-row">
                <div class="summary-card">
                    <div class="icon-wrapper blue"><i class="fas fa-file-alt"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Total Complaints</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-wrapper yellow"><i class="fas fa-clock"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['pending']; ?></div>
                        <div class="label">Pending</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-wrapper purple"><i class="fas fa-spinner"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['in_progress']; ?></div>
                        <div class="label">In Progress</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-wrapper green"><i class="fas fa-check-circle"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['resolved']; ?></div>
                        <div class="label">Resolved</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-wrapper red"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="info">
                        <div class="number"><?php echo $stats['escalated']; ?></div>
                        <div class="label">Escalated</div>
                    </div>
                </div>
            </div>

            <!-- GRIDI 70% TABLE / 30% ANNOUNCEMENTS -->
            <div class="dashboard-grid">
                <!-- 70% - Recent Complaints Table -->
                <div class="content-area">
                    <h4><i class="fas fa-clock" style="color: #1a56db;"></i> Recent Complaints</h4>
                    <div class="table-responsive">
                        <table class="complaints-table">
                            <thead>
                                <tr><th>Complaint #</th><th>Student</th><th>Title</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($recent_result) == 0): ?>
                                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#8ba0bc;">No complaints found.</td></tr>
                                <?php else: ?>
                                    <?php while ($row = mysqli_fetch_assoc($recent_result)):
                                        $status_class = match($row['status']) {
                                            'pending' => 'badge-pending',
                                            'in_progress' => 'badge-in-progress',
                                            'resolved' => 'badge-resolved',
                                            'escalated' => 'badge-escalated',
                                            default => ''
                                        };
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $row['complaint_number']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                            <td><?php echo date('d/m/y', strtotime($row['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="it_officer_dashboard.php?page=complaints" class="btn-sm" style="background: #6b85a0;">View All Complaints <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- 30% - Latest Announcements -->
                <div class="announcements-sidebar">
                    <h4>
                        <i class="fas fa-bullhorn"></i> Announcements
                        <a href="announcements.php" class="view-all">View All →</a>
                    </h4>
                    
                    <?php if (empty($announcements)): ?>
                        <div class="no-announcements-sidebar">
                            <i class="fas fa-inbox"></i>
                            No announcements at the moment.
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): 
                            $is_new = (time() - strtotime($ann['created_at'])) < (3 * 24 * 60 * 60);
                            $sender_name = !empty($ann['sender_name']) ? $ann['sender_name'] : 'System';
                        ?>
                            <div class="announcement-summary">
                                <div class="a-title">
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                    <?php if ($is_new): ?>
                                        <span class="badge-new">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="a-sender">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($sender_name); ?>
                                </div>
                                <div class="a-time">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('d M Y, h:i A', strtotime($ann['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_page == 'complaints' || $active_page == 'pending' || $active_page == 'resolved' || $active_page == 'escalated'): ?>
            <?php
            $status_filter = '';
            if ($active_page == 'pending') $status_filter = "AND c.status = 'pending'";
            elseif ($active_page == 'resolved') $status_filter = "AND c.status = 'resolved'";
            elseif ($active_page == 'escalated') $status_filter = "AND c.status = 'escalated'";
            $where = "cat.name IN ($category_in_list) $status_filter";
            if ($priority_filter) $where .= " AND c.priority = ?";
            if ($search) $where .= " AND (u.full_name LIKE ? OR c.complaint_number LIKE ?)";
            $order_by = match($sort) {
                'oldest' => 'c.created_at ASC',
                'priority_high' => "FIELD(c.priority, 'high','medium','low')",
                'priority_low' => "FIELD(c.priority, 'low','medium','high')",
                default => 'c.created_at DESC',
            };
            $count_sql = "SELECT COUNT(*) as total FROM complaints c JOIN users u ON c.student_id = u.id JOIN categories cat ON c.category_id = cat.id WHERE $where";
            $count_stmt = mysqli_prepare($conn, $count_sql);
            if ($priority_filter && $search) {
                $search_param = "%$search%";
                mysqli_stmt_bind_param($count_stmt, "ss", $priority_filter, $search_param);
            } elseif ($priority_filter) {
                mysqli_stmt_bind_param($count_stmt, "s", $priority_filter);
            } elseif ($search) {
                $search_param = "%$search%";
                mysqli_stmt_bind_param($count_stmt, "s", $search_param);
            }
            mysqli_stmt_execute($count_stmt);
            $total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
            mysqli_stmt_close($count_stmt);
            $total_pages = ceil($total_rows / $limit);
            $data_sql = "SELECT c.id, c.complaint_number, c.title, c.status, c.created_at, c.updated_at, u.full_name, c.priority FROM complaints c JOIN users u ON c.student_id = u.id JOIN categories cat ON c.category_id = cat.id WHERE $where ORDER BY $order_by LIMIT ? OFFSET ?";
            $data_stmt = mysqli_prepare($conn, $data_sql);
            if ($priority_filter && $search) {
                $search_param = "%$search%";
                mysqli_stmt_bind_param($data_stmt, "ssii", $priority_filter, $search_param, $limit, $offset);
            } elseif ($priority_filter) {
                mysqli_stmt_bind_param($data_stmt, "sii", $priority_filter, $limit, $offset);
            } elseif ($search) {
                $search_param = "%$search%";
                mysqli_stmt_bind_param($data_stmt, "sii", $search_param, $limit, $offset);
            } else {
                mysqli_stmt_bind_param($data_stmt, "ii", $limit, $offset);
            }
            mysqli_stmt_execute($data_stmt);
            $data_result = mysqli_stmt_get_result($data_stmt);
            ?>
            <div class="content-area">
                <h4><?php echo ucfirst(str_replace('_', ' ', $active_page)); ?> Complaints</h4>
                
                <!-- Filters -->
                <form method="GET" class="filters-bar">
                    <input type="hidden" name="page" value="<?php echo $active_page; ?>">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student / Complaint #">
                    </div>
                    <div class="filter-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="">All</option>
                            <option value="high" <?php echo $priority_filter=='high'?'selected':''; ?>>High</option>
                            <option value="medium" <?php echo $priority_filter=='medium'?'selected':''; ?>>Medium</option>
                            <option value="low" <?php echo $priority_filter=='low'?'selected':''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort by</label>
                        <select name="sort">
                            <option value="newest" <?php echo $sort=='newest'?'selected':''; ?>>Newest first</option>
                            <option value="oldest" <?php echo $sort=='oldest'?'selected':''; ?>>Oldest first</option>
                            <option value="priority_high" <?php echo $sort=='priority_high'?'selected':''; ?>>Priority (High to Low)</option>
                            <option value="priority_low" <?php echo $sort=='priority_low'?'selected':''; ?>>Priority (Low to High)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply</button>
                    </div>
                </form>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="complaints-table">
                        <thead>
                            <tr><th>Complaint #</th><th>Student</th><th>Title</th><th>Priority</th><th>Status</th><th>Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($data_result) == 0): ?>
                                <tr><td colspan="7" style="text-align:center; padding:30px; color:#8ba0bc;">No complaints found.</td></tr>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($data_result)):
                                    $status_class = match($row['status']) {
                                        'pending' => 'badge-pending',
                                        'in_progress' => 'badge-in-progress',
                                        'resolved' => 'badge-resolved',
                                        'escalated' => 'badge-escalated',
                                        default => ''
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?php echo $row['complaint_number']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td><?php echo ucfirst($row['priority']); ?></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                        <td><a href="it_officer_dashboard.php?page=view-complaint&id=<?php echo $row['id']; ?>" class="btn-sm">View</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="it_officer_dashboard.php?page=<?php echo $active_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $page-1; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="it_officer_dashboard.php?page=<?php echo $active_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="it_officer_dashboard.php?page=<?php echo $active_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $page+1; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_page == 'view-complaint' && $complaint_id): ?>
            <?php
            $detail_sql = "SELECT c.*, u.full_name as student_name, u.reg_number, u.email as student_email 
                          FROM complaints c 
                          JOIN users u ON c.student_id = u.id 
                          JOIN categories cat ON c.category_id = cat.id
                          WHERE c.id = ? AND cat.name IN ($category_in_list)";
            $detail_stmt = mysqli_prepare($conn, $detail_sql);
            mysqli_stmt_bind_param($detail_stmt, "i", $complaint_id);
            mysqli_stmt_execute($detail_stmt);
            $detail_result = mysqli_stmt_get_result($detail_stmt);
            $complaint = mysqli_fetch_assoc($detail_result);
            mysqli_stmt_close($detail_stmt);
            
            if (!$complaint) { 
                echo '<div class="content-area"><p style="color:#991b1b;">Complaint not found or not in your category.</p></div>';
            } else {
                if (is_null($complaint['assigned_to'])) {
                    $auto_assign = "UPDATE complaints SET assigned_to = ? WHERE id = ?";
                    $auto_stmt = mysqli_prepare($conn, $auto_assign);
                    mysqli_stmt_bind_param($auto_stmt, "ii", $it_officer_id, $complaint_id);
                    mysqli_stmt_execute($auto_stmt);
                    mysqli_stmt_close($auto_stmt);
                    $complaint['assigned_to'] = $it_officer_id;
                }
                
                $is_escalated = ($complaint['status'] === 'escalated');
                $is_resolved = ($complaint['status'] === 'resolved');
                
                $created = new DateTime($complaint['created_at']);
                $now = new DateTime();
                $elapsed = $created->diff($now);
                $elapsedFormatted = $elapsed->format('%a days, %h hours, %i minutes');
                
                $slaHours = 120;
                switch(strtolower($complaint['priority'])) {
                    case 'high': $slaHours = 48; break;
                    case 'medium': $slaHours = 120; break;
                    case 'low': $slaHours = 168; break;
                }
                $deadline = clone $created;
                $deadline->modify("+{$slaHours} hours");
                $deadlineTimestamp = $deadline->getTimestamp();
                $nowTimestamp = $now->getTimestamp();
                $remainingSeconds = $deadlineTimestamp - $nowTimestamp;
                
                $resolutionFormatted = null;
                if ($is_resolved && !empty($complaint['updated_at'])) {
                    $resolvedTime = new DateTime($complaint['updated_at']);
                    $resolutionDuration = $created->diff($resolvedTime);
                    $resolutionFormatted = $resolutionDuration->format('%a days, %h hours, %i minutes');
                }
                
                $slaStatus = '';
                $overdueFormatted = '';
                if ($is_resolved) {
                    $slaStatus = 'resolved';
                } else {
                    if ($remainingSeconds < 0) {
                        $slaStatus = 'overdue';
                        $overdueInterval = $deadline->diff($now);
                        $overdueFormatted = $overdueInterval->format('%a days, %h hours, %i minutes');
                    } else {
                        $slaStatus = 'active';
                    }
                }
                
                $resp_sql = "SELECT r.*, u.full_name, u.role FROM responses r JOIN users u ON r.user_id = u.id WHERE r.complaint_id = ? ORDER BY r.created_at ASC";
                $resp_stmt = mysqli_prepare($conn, $resp_sql);
                mysqli_stmt_bind_param($resp_stmt, "i", $complaint_id);
                mysqli_stmt_execute($resp_stmt);
                $resp_result = mysqli_stmt_get_result($resp_stmt);
                ?>
                <div class="content-area">
                    <?php if ($is_escalated): ?>
                        <div style="background:#fef3c7; color:#856404; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:12px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-lock"></i> This complaint has been escalated and is now <strong>read‑only</strong>.
                        </div>
                    <?php endif; ?>
                    
                    <!-- SLA Card -->
                    <div class="sla-card">
                        <div class="sla-title"><i class="fas fa-hourglass-half"></i> SLA Monitoring</div>
                        <div class="sla-stats">
                            <div class="sla-item">
                                <div class="sla-label"><i class="far fa-clock"></i> Time since submission</div>
                                <div class="sla-value"><?php echo $elapsedFormatted; ?></div>
                            </div>
                            <?php if ($is_resolved && $resolutionFormatted): ?>
                                <div class="sla-item">
                                    <div class="sla-label"><i class="fas fa-check-circle"></i> Total resolution time</div>
                                    <div class="sla-value"><span class="sla-resolved"><?php echo $resolutionFormatted; ?></span></div>
                                </div>
                            <?php else: ?>
                                <div class="sla-item">
                                    <div class="sla-label"><i class="fas fa-tachometer-alt"></i> SLA target (<?php echo ucfirst($complaint['priority']); ?> priority)</div>
                                    <div class="sla-value"><?php echo $slaHours; ?> hours (<?php echo round($slaHours/24,1); ?> days)</div>
                                </div>
                                <div class="sla-item">
                                    <div class="sla-label"><i class="fas fa-calendar-alt"></i> Deadline</div>
                                    <div class="sla-value"><?php echo $deadline->format('d/m/Y H:i'); ?></div>
                                </div>
                                <div class="sla-item">
                                    <div class="sla-label"><i class="fas fa-hourglass-end"></i> Remaining / Status</div>
                                    <div class="sla-value">
                                        <?php if ($slaStatus === 'active'): ?>
                                            <span id="countdownTimer" class="deadline-timer" data-deadline="<?php echo $deadlineTimestamp * 1000; ?>">-- : -- : --</span>
                                        <?php elseif ($slaStatus === 'overdue'): ?>
                                            <span class="sla-overdue"><i class="fas fa-exclamation-triangle"></i> Overdue by <?php echo $overdueFormatted; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h4 style="color:#0a2a5e; margin-bottom:16px;">Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?></h4>
                    
                    <!-- Complaint Details -->
                    <div class="complaint-detail-grid">
                        <div class="detail-item">
                            <span class="label">Student</span>
                            <span class="value"><?php echo htmlspecialchars($complaint['student_name']); ?> (<?php echo $complaint['reg_number']; ?>)</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Title</span>
                            <span class="value"><?php echo htmlspecialchars($complaint['title']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Location</span>
                            <span class="value"><?php echo htmlspecialchars($complaint['location'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Incident Date</span>
                            <span class="value"><?php echo date('d/m/Y', strtotime($complaint['incident_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Priority</span>
                            <span class="value"><?php echo ucfirst($complaint['priority']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="badge <?php 
                                    if ($complaint['status']=='pending') echo 'badge-pending';
                                    elseif ($complaint['status']=='in_progress') echo 'badge-in-progress';
                                    elseif ($complaint['status']=='resolved') echo 'badge-resolved';
                                    elseif ($complaint['status']=='escalated') echo 'badge-escalated';
                                ?>"><?php echo ucfirst($complaint['status']); ?></span>
                            </span>
                        </div>
                        <?php if ($complaint['attachment_path']): ?>
                        <div class="detail-item">
                            <span class="label">Evidence</span>
                            <span class="value"><a href="../<?php echo $complaint['attachment_path']; ?>" target="_blank" class="btn-sm" style="background:#10b981;">Download</a></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <div class="description-box">
                        <div class="label">Description</div>
                        <div class="value"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                    </div>
                    
                    <!-- Conversation -->
                    <h5 style="font-size:0.95rem; font-weight:700; color:#0a2a5e; margin-bottom:12px;"><i class="fas fa-comments" style="color:#1a56db;"></i> Conversation</h5>
                    <div class="conversation">
                        <?php while ($resp = mysqli_fetch_assoc($resp_result)): ?>
                            <div class="message <?php echo ($resp['role'] == 'student') ? 'student-message' : 'staff-message'; ?>">
                                <div class="message-header">
                                    <span class="sender">
                                        <?php echo htmlspecialchars($resp['full_name']); ?>
                                        <span style="font-weight:400; color:#6b85a0; font-size:0.7rem;">(<?php echo ucfirst($resp['role']); ?>)</span>
                                    </span>
                                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($resp['created_at'])); ?></span>
                                </div>
                                <div class="message-body"><?php echo nl2br(htmlspecialchars($resp['message'])); ?></div>
                            </div>
                        <?php endwhile; mysqli_stmt_close($resp_stmt); ?>
                        <?php if (mysqli_num_rows($resp_result) == 0): ?>
                            <p style="color:#8ba0bc; text-align:center; padding:20px 0;">No messages yet.</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$is_escalated): ?>
                        <!-- Reply Section -->
                        <div class="reply-section">
                            <h5><i class="fas fa-reply" style="color:#1a56db;"></i> Reply to Student</h5>
                            <form method="POST">
                                <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                <div class="form-group">
                                    <label>Your Message</label>
                                    <textarea name="message" rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Update Status</label>
                                    <select name="status">
                                        <option value="in_progress">In Progress</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="escalated">Escalate to Deputy Rector</option>
                                    </select>
                                </div>
                                <button type="submit" name="reply_complaint" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Reply</button>
                            </form>
                        </div>
                        
                        <!-- Status Only Section -->
                        <div class="status-section">
                            <h5><i class="fas fa-edit" style="color:#1a56db;"></i> Change Status Only</h5>
                            <form method="POST">
                                <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                                <div class="form-group">
                                    <label>New Status</label>
                                    <select name="status">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="escalated">Escalate to Deputy Rector</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn-submit"><i class="fas fa-save"></i> Update Status</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="background:#e9ecef; color:#495057; border-left:4px solid #6c757d; padding:12px 16px; border-radius:12px; margin-top:20px; display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-eye"></i> This complaint is read‑only because it has been escalated.
                        </div>
                    <?php endif; ?>
                </div>
            <?php } ?>

        <?php elseif ($active_page == 'profile'): ?>
            <div class="content-area">
                <h4><i class="fas fa-user-circle" style="color:#1a56db;"></i> Edit Profile</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($it_officer_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email (cannot be changed)</label>
                        <input type="email" value="<?php echo htmlspecialchars($it_officer_email); ?>" disabled style="background:#f0f4f9; cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <div style="font-size:0.8rem; color:#8ba0bc; margin-top:4px;">Allowed: JPG, PNG, GIF (Max 2MB)</div>
                        <?php if (!empty($profile_pic)): ?>
                            <div style="margin-top:8px; font-size:0.85rem; color:#6b85a0;">
                                <i class="fas fa-image"></i> Current: <?php echo basename($profile_pic); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>

        <?php elseif ($active_page == 'change-password'): ?>
            <div class="content-area">
                <h4><i class="fas fa-key" style="color:#1a56db;"></i> Change Password</h4>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required placeholder="Enter new password">
                        <div style="font-size:0.8rem; color:#8ba0bc; margin-top:4px;">Password must be at least 6 characters</div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="Confirm new password">
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Change Password</button>
                </form>
            </div>

        <?php else: ?>
            <div class="content-area"><h4>Page not found</h4><p style="color:#6b85a0; margin-top:10px;">Select a menu option from the sidebar.</p></div>
        <?php endif; ?>
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
            info: 'fa-info-circle'
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

    function showToastSuccess(message) {
        showToast(message, 'success');
    }
    function showToastError(message) {
        showToast(message, 'error');
    }
    function showToastInfo(message) {
        showToast(message, 'info');
    }

    // ---------- DISPLAY FLASH MESSAGE FROM PHP ----------
    <?php if (!empty($flash_message)): ?>
        <?php if ($flash_type === 'success'): ?>
            showToastSuccess('<?php echo addslashes($flash_message); ?>');
        <?php else: ?>
            showToastError('<?php echo addslashes($flash_message); ?>');
        <?php endif; ?>
    <?php endif; ?>

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

    // ---------- LOGOUT MODAL ----------
    const logoutBtn = document.getElementById('logoutBtn');
    const modal = document.getElementById('logoutModal');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const confirmBtn = document.getElementById('confirmLogout');
    const cancelBtn = document.getElementById('cancelLogout');

    logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        modal.classList.add('active');
    });

    confirmBtn.addEventListener('click', () => {
        modal.classList.remove('active');
        loadingOverlay.classList.add('active');
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 500);
    });

    cancelBtn.addEventListener('click', () => {
        modal.classList.remove('active');
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });

    // ---------- COUNTDOWN TIMER ----------
    const countdownElement = document.getElementById('countdownTimer');
    if (countdownElement && countdownElement.dataset.deadline) {
        const deadlineMs = parseInt(countdownElement.dataset.deadline);
        function updateCountdown() {
            const nowMs = new Date().getTime();
            const distance = deadlineMs - nowMs;
            if (distance < 0) {
                countdownElement.innerHTML = '<span class="sla-overdue">⏰ SLA Expired</span>';
                return;
            }
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (86400000)) / (3600000));
            const minutes = Math.floor((distance % 3600000) / 60000);
            const seconds = Math.floor((distance % 60000) / 1000);
            let display = "";
            if (days > 0) display += days + "d ";
            display += hours.toString().padStart(2,'0') + ":" + minutes.toString().padStart(2,'0') + ":" + seconds.toString().padStart(2,'0');
            countdownElement.innerHTML = display;
        }
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // ---------- PROFILE & PASSWORD FORM SUBMISSIONS ----------
    document.querySelectorAll('form[action*="update_profile"]').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('.btn-submit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        });
    });

    document.querySelectorAll('form[action*="change_password"]').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('.btn-submit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
        });
    });
</script>
</body>
</html>