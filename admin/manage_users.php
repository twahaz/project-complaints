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

$active_page = 'manage_users';

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

// ========== HANDLE AJAX UPDATE REQUEST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_ajax'])) {
    header('Content-Type: application/json');
    
    $user_id = intval($_POST['user_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $reg_number = isset($_POST['reg_number']) ? trim($_POST['reg_number']) : null;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $response = ['success' => false, 'message' => ''];
    
    // Don't allow admin to edit own account
    if ($user_id == $admin_id) {
        $response['message'] = "You cannot edit your own account here!";
        echo json_encode($response);
        exit();
    }
    
    // Check if email already exists for another user
    $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $email, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_fetch_assoc($check_result)) {
        $response['message'] = "Email already exists for another user!";
        echo json_encode($response);
        exit();
    }
    mysqli_stmt_close($check_stmt);
    
    // Check if reg_number exists for another user
    if (!empty($reg_number)) {
        $check_reg_sql = "SELECT id FROM users WHERE reg_number = ? AND id != ?";
        $check_reg_stmt = mysqli_prepare($conn, $check_reg_sql);
        mysqli_stmt_bind_param($check_reg_stmt, "si", $reg_number, $user_id);
        mysqli_stmt_execute($check_reg_stmt);
        $check_reg_result = mysqli_stmt_get_result($check_reg_stmt);
        if (mysqli_fetch_assoc($check_reg_result)) {
            $response['message'] = "Registration number already exists for another user!";
            echo json_encode($response);
            exit();
        }
        mysqli_stmt_close($check_reg_stmt);
    }
    
    // Handle password reset (admin can reset without current password)
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $response['message'] = "New password must be at least 6 characters!";
            echo json_encode($response);
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            $response['message'] = "New password and confirm password do not match!";
            echo json_encode($response);
            exit();
        }
        
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, role = ?, reg_number = ?, password_hash = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssssssi", $full_name, $email, $phone, $role, $reg_number, $hashed, $user_id);
    } else {
        $update_sql = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, role = ?, reg_number = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sssssi", $full_name, $email, $phone, $role, $reg_number, $user_id);
    }
    
    if (mysqli_stmt_execute($update_stmt)) {
        $response['success'] = true;
        $response['message'] = "User updated successfully!";
        
        // Log the action
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'update', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        $description = "Updated user: $full_name (ID: $user_id)";
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_stmt_bind_param($log_stmt, "iss", $admin_id, $description, $ip);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    } else {
        $response['message'] = "Database error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($update_stmt);
    
    echo json_encode($response);
    exit();
}

// ========== HANDLE USER SOFT DELETE ==========
if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    if ($delete_id != $admin_id) {
        // Get user info for logging
        $user_sql = "SELECT full_name FROM users WHERE id = ?";
        $user_stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($user_stmt, "i", $delete_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user_data = mysqli_fetch_assoc($user_result);
        $deleted_user_name = $user_data['full_name'];
        mysqli_stmt_close($user_stmt);
        
        // SOFT DELETE - Just set is_active = 0, complaints remain intact
        $delete_sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $delete_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
        
        // Log the action
        $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'delete', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        $description = "Soft deleted user: $deleted_user_name (ID: $delete_id) - Account deactivated";
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_stmt_bind_param($log_stmt, "iss", $admin_id, $description, $ip);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        $_SESSION['toast_message'] = "User deactivated successfully! Complaints remain intact.";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "You cannot delete your own account!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: manage_users.php");
    exit();
}

// ========== HANDLE USER REACTIVATION ==========
if (isset($_GET['reactivate_user'])) {
    $reactivate_id = intval($_GET['reactivate_user']);
    if ($reactivate_id != $admin_id) {
        $reactivate_sql = "UPDATE users SET is_active = 1 WHERE id = ?";
        $reactivate_stmt = mysqli_prepare($conn, $reactivate_sql);
        mysqli_stmt_bind_param($reactivate_stmt, "i", $reactivate_id);
        mysqli_stmt_execute($reactivate_stmt);
        mysqli_stmt_close($reactivate_stmt);
        
        $_SESSION['toast_message'] = "User reactivated successfully!";
        $_SESSION['toast_type'] = "success";
    }
    header("Location: manage_users.php");
    exit();
}

// Get all users with filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$users_where = "1=1";
if ($search) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $users_where .= " AND (full_name LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%' OR reg_number LIKE '%$search_escaped%')";
}
if ($role_filter) {
    $users_where .= " AND role = '$role_filter'";
}
if ($status_filter === 'active') {
    $users_where .= " AND is_active = 1";
} elseif ($status_filter === 'inactive') {
    $users_where .= " AND is_active = 0";
}

$users_sql = "SELECT id, full_name, email, role, is_active, phone_number, created_at, reg_number FROM users WHERE $users_where ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_sql);

// Get stats
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
    (SELECT COUNT(*) FROM users WHERE is_active = 0) as inactive_users,
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students
    FROM users LIMIT 1";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Users - Admin Panel</title>
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
        .summary-card .icon-wrapper.green { background: #d1fae5; color: #065f46; }
        .summary-card .icon-wrapper.purple { background: #ede9fe; color: #6d28d9; }
        .summary-card .icon-wrapper.yellow { background: #fef3c7; color: #b45309; }
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

        /* ========== BUTTONS ========== */
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
        .btn-sm.danger {
            background: #dc2626;
        }
        .btn-sm.danger:hover {
            background: #991b1b;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        .btn-sm.primary {
            background: #1a56db;
        }
        .btn-sm.primary:hover {
            background: #0d3b8a;
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        .btn-sm.success {
            background: #10b981;
        }
        .btn-sm.success:hover {
            background: #065f46;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-sm.warning {
            background: #f59e0b;
            color: #1f2c40;
        }
        .btn-sm.warning:hover {
            background: #d97706;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
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
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }

        .role-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .role-student { background: #dbeafe; color: #1e40af; }
        .role-hod { background: #cffafe; color: #0e7490; }
        .role-dean { background: #e0e7ff; color: #4338ca; }
        .role-accountant { background: #fef3c7; color: #b45309; }
        .role-director { background: #f1f5f9; color: #475569; }
        .role-admin { background: #fce7f3; color: #be185d; }
        .role-it_officer { background: #dcfce7; color: #166534; }
        .role-examination_officer { background: #fef9c3; color: #854d0e; }
        .role-president { background: #e0f2fe; color: #0369a1; }
        .role-deputy_rector { background: #f3e8ff; color: #6b21a5; }
        .role-rector { background: #fef08a; color: #713f12; }

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
        .filter-btn {
            background: #1a56db;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            background: #0d3b8a;
            transform: translateY(-1px);
        }

        /* ---------- TABLE ---------- */
        .table-responsive { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .users-table thead th {
            background: #f8fafc;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            color: #4a5a7a;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e5edf5;
        }
        .users-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f0f4f9;
            color: #1f2c40;
            vertical-align: middle;
        }
        .users-table tbody tr:hover {
            background: #fafcff;
        }
        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ---------- CONTENT AREA ---------- */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 24px 28px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 350px;
        }
        .content-area h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .content-area h4 i {
            color: #1a56db;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #8ba0bc;
        }
        .no-data i {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
            color: #dbeafe;
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
            max-width: 650px;
            width: 90%;
            padding: 32px 28px;
            text-align: left;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-container .modal-icon {
            text-align: center;
            font-size: 2.5rem;
            color: #1a56db;
            margin-bottom: 12px;
        }
        .modal-container h3 {
            color: #0a2a5e;
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }
        .modal-container p {
            color: #6b85a0;
            text-align: center;
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
        .form-group .helper-text {
            font-size: 0.78rem;
            color: #8ba0bc;
            margin-top: 4px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-divider {
            border-top: 1px solid #e5edf5;
            margin: 24px 0 20px 0;
            position: relative;
        }
        .form-divider span {
            position: absolute;
            top: -11px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 16px;
            color: #6b85a0;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
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
        .modal-btn.danger { background: #dc2626; color: white; }
        .modal-btn.danger:hover { background: #991b1b; transform: translateY(-2px); }
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

            .summary-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .summary-card {
                padding: 12px 14px;
                min-height: 70px;
                gap: 10px;
                border-radius: 12px;
            }
            .summary-card .icon-wrapper {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            .summary-card .info .number {
                font-size: 1.2rem;
            }
            .summary-card .info .label {
                font-size: 0.65rem;
            }

            .filters-bar {
                flex-direction: column;
                padding: 12px 16px;
                gap: 10px;
            }
            .filter-group {
                min-width: unset;
                width: 100%;
            }
            .filter-group label {
                font-size: 0.6rem;
            }
            .filter-group input, .filter-group select {
                font-size: 0.75rem;
                padding: 6px 10px;
            }
            .filter-btn {
                width: 100%;
                justify-content: center;
            }

            .content-area {
                padding: 16px;
                border-radius: 12px;
            }
            .content-area h4 {
                font-size: 0.85rem;
            }

            .users-table thead th,
            .users-table tbody td {
                padding: 8px 10px;
                font-size: 0.7rem;
            }
            .role-badge {
                font-size: 0.55rem;
                padding: 2px 8px;
            }
            .badge {
                font-size: 0.55rem;
                padding: 2px 8px;
            }
            .btn-sm {
                font-size: 0.55rem;
                padding: 3px 10px;
            }

            .modal-container {
                padding: 20px 16px;
                max-width: 95%;
            }
            .modal-container h3 {
                font-size: 1rem;
            }
            .modal-btn {
                padding: 8px 16px;
                font-size: 0.75rem;
                min-width: 70px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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

            .summary-row {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }
            .summary-card {
                padding: 8px 10px;
                min-height: 56px;
                gap: 8px;
                border-radius: 10px;
            }
            .summary-card .icon-wrapper {
                width: 28px;
                height: 28px;
                font-size: 0.75rem;
                border-radius: 8px;
            }
            .summary-card .info .number {
                font-size: 1rem;
            }
            .summary-card .info .label {
                font-size: 0.55rem;
            }

            .filters-bar {
                padding: 8px 10px;
                gap: 8px;
                border-radius: 10px;
            }
            .filter-group label {
                font-size: 0.55rem;
            }
            .filter-group input, .filter-group select {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
            .filter-btn {
                font-size: 0.7rem;
                padding: 6px 12px;
            }

            .content-area {
                padding: 12px;
                border-radius: 10px;
            }
            .content-area h4 {
                font-size: 0.75rem;
            }

            .users-table thead th,
            .users-table tbody td {
                padding: 6px 6px;
                font-size: 0.6rem;
            }
            .role-badge {
                font-size: 0.5rem;
                padding: 2px 6px;
            }
            .badge {
                font-size: 0.5rem;
                padding: 2px 6px;
            }
            .btn-sm {
                font-size: 0.5rem;
                padding: 3px 8px;
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
        <a href="manage_users.php" class="menu-item active">
            <i class="fas fa-users"></i><span>Manage Users</span>
        </a>
        <a href="add_user.php" class="menu-item">
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
        <!-- Statistics Cards -->
        <div class="summary-row">
            <div class="summary-card">
                <div class="icon-wrapper blue"><i class="fas fa-users"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                    <div class="label">Total Users</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper green"><i class="fas fa-user-check"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                    <div class="label">Active Users</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper red"><i class="fas fa-user-slash"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['inactive_users'] ?? 0); ?></div>
                    <div class="label">Inactive Users</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper purple"><i class="fas fa-user-graduate"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['total_students'] ?? 0); ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%; align-items: flex-end;">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email or reg number">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-user-tag"></i> Role</label>
                    <select name="role_filter">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter=='student'?'selected':''; ?>>Student</option>
                        <option value="hod" <?php echo $role_filter=='hod'?'selected':''; ?>>HOD</option>
                        <option value="dean" <?php echo $role_filter=='dean'?'selected':''; ?>>Dean</option>
                        <option value="accountant" <?php echo $role_filter=='accountant'?'selected':''; ?>>Accountant</option>
                        <option value="director" <?php echo $role_filter=='director'?'selected':''; ?>>Director</option>
                        <option value="examination_officer" <?php echo $role_filter=='examination_officer'?'selected':''; ?>>Examination Officer</option>
                        <option value="president" <?php echo $role_filter=='president'?'selected':''; ?>>President</option>
                        <option value="deputy_rector" <?php echo $role_filter=='deputy_rector'?'selected':''; ?>>Deputy Rector</option>
                        <option value="rector" <?php echo $role_filter=='rector'?'selected':''; ?>>Rector</option>
                        <option value="it_officer" <?php echo $role_filter=='it_officer'?'selected':''; ?>>IT Officer</option>
                        <option value="admin" <?php echo $role_filter=='admin'?'selected':''; ?>>Admin</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-circle"></i> Status</label>
                    <select name="status_filter">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter=='active'?'selected':''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter=='inactive'?'selected':''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="manage_users.php" class="filter-btn" style="background: #6b85a0; text-decoration: none; display: inline-block; padding: 8px 20px; border-radius: 10px; color: white; font-weight: 500; margin-left: 8px;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="content-area">
            <h4><i class="fas fa-users"></i> All Users</h4>
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Reg Number</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($users_result) == 0): ?>
                            <tr><td colspan="9"><div class="no-data"><i class="fas fa-users"></i> No users found.</div></td></tr>
                        <?php else: ?>
                            <?php while ($user = mysqli_fetch_assoc($users_result)):
                                $role_class = match($user['role']) {
                                    'student' => 'role-student',
                                    'hod' => 'role-hod',
                                    'dean' => 'role-dean',
                                    'accountant' => 'role-accountant',
                                    'director' => 'role-director',
                                    'admin' => 'role-admin',
                                    'it_officer' => 'role-it_officer',
                                    'examination_officer' => 'role-examination_officer',
                                    'president' => 'role-president',
                                    'deputy_rector' => 'role-deputy_rector',
                                    'rector' => 'role-rector',
                                    default => 'role-student'
                                };
                            ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <div style="font-weight:600; color:#0a2a5e;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </td>
                                <td style="font-size:0.8rem;"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td style="font-size:0.8rem;"><?php echo htmlspecialchars($user['reg_number'] ?: '-'); ?></td>
                                <td style="font-size:0.8rem;"><?php echo htmlspecialchars($user['phone_number'] ?: '-'); ?></td>
                                <td><span class="role-badge <?php echo $role_class; ?>"><?php echo str_replace('_', ' ', ucfirst($user['role'])); ?></span></td>
                                <td><span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td style="font-size:0.75rem;"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button onclick="openEditModal(<?php echo $user['id']; ?>)" class="btn-sm primary"><i class="fas fa-edit"></i> Edit</button>
                                    <?php if ($user['id'] != $admin_id): ?>
                                        <?php if ($user['is_active'] == 1): ?>
                                            <button onclick="confirmDeactivate(<?php echo $user['id']; ?>)" class="btn-sm danger"><i class="fas fa-user-slash"></i> Deactivate</button>
                                        <?php else: ?>
                                            <button onclick="confirmReactivate(<?php echo $user['id']; ?>)" class="btn-sm success"><i class="fas fa-user-check"></i> Reactivate</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-icon"><i class="fas fa-edit"></i></div>
        <h3>Edit User</h3>
        <form id="editUserForm">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="edit_full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="edit_phone" name="phone" placeholder="Enter phone number">
                </div>
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" id="edit_reg_number" name="reg_number" placeholder="e.g., IAA-2024-001">
                    <div class="helper-text">Only for students</div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Role <span style="color:#dc2626;">*</span></label>
                <select id="edit_role" name="role">
                    <option value="student">Student</option>
                    <option value="hod">HOD</option>
                    <option value="dean">Dean</option>
                    <option value="accountant">Accountant</option>
                    <option value="director">Director</option>
                    <option value="examination_officer">Examination Officer</option>
                    <option value="president">President</option>
                    <option value="deputy_rector">Deputy Rector</option>
                    <option value="rector">Rector</option>
                    <option value="it_officer">IT Officer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="form-divider"><span>Reset Password (Optional)</span></div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="edit_new_password" name="new_password" placeholder="Enter new password">
                    <div class="helper-text">Minimum 6 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="edit_confirm_password" name="confirm_password" placeholder="Confirm new password">
                </div>
            </div>
            
            <div class="modal-buttons">
                <button type="button" class="modal-btn confirm" id="saveUserBtn"><i class="fas fa-save"></i> Save Changes</button>
                <button type="button" class="modal-btn cancel" id="closeEditModal"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Deactivate Confirmation Modal -->
<div id="deactivateModal" class="modal-overlay">
    <div class="modal-container">
        <div style="text-align:center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc2626; margin-bottom: 12px;"></i>
            <h3>Confirm Deactivate</h3>
            <p>Are you sure you want to deactivate this user?</p>
            <p style="font-size: 0.85rem; color: #6b85a0; margin-top: 8px;">The user will not be able to login. All complaints remain intact.</p>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn danger" id="confirmDeactivateBtn"><i class="fas fa-user-slash"></i> Yes, Deactivate</button>
            <button class="modal-btn cancel" id="cancelDeactivateBtn"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<!-- Reactivate Confirmation Modal -->
<div id="reactivateModal" class="modal-overlay">
    <div class="modal-container">
        <div style="text-align:center;">
            <i class="fas fa-user-check" style="font-size: 3rem; color: #10b981; margin-bottom: 12px;"></i>
            <h3>Confirm Reactivate</h3>
            <p>Are you sure you want to reactivate this user?</p>
            <p style="font-size: 0.85rem; color: #6b85a0; margin-top: 8px;">The user will be able to login again.</p>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn confirm" id="confirmReactivateBtn"><i class="fas fa-user-check"></i> Yes, Reactivate</button>
            <button class="modal-btn cancel" id="cancelReactivateBtn"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-container">
        <div style="text-align:center;">
            <i class="fas fa-sign-out-alt" style="font-size: 2.5rem; color: #0a2a5e; margin-bottom: 12px;"></i>
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
        </div>
        <div class="modal-buttons">
            <button class="modal-btn confirm" id="confirmLogout"><i class="fas fa-sign-out-alt"></i> Yes, Logout</button>
            <button class="modal-btn cancel" id="cancelLogout"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <div class="loading-text"><i class="fas fa-spinner fa-spin"></i> Processing...</div>
    </div>
</div>

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

    <?php if (isset($_SESSION['toast_message'])): ?>
        showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type']; ?>');
        <?php unset($_SESSION['toast_message']); ?>
        <?php unset($_SESSION['toast_type']); ?>
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

    // ---------- EDIT USER MODAL ----------
    function openEditModal(userId) {
        document.getElementById('loadingOverlay').classList.add('active');
        
        fetch('get_user.php?id=' + userId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');
                if (data.success) {
                    document.getElementById('edit_user_id').value = data.user.id;
                    document.getElementById('edit_full_name').value = data.user.full_name;
                    document.getElementById('edit_email').value = data.user.email;
                    document.getElementById('edit_phone').value = data.user.phone_number || '';
                    document.getElementById('edit_reg_number').value = data.user.reg_number || '';
                    document.getElementById('edit_role').value = data.user.role;
                    document.getElementById('edit_new_password').value = '';
                    document.getElementById('edit_confirm_password').value = '';
                    document.getElementById('editModal').classList.add('active');
                } else {
                    showToast('Error loading user data: ' + data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').classList.remove('active');
                showToast('Error loading user data. Please try again.', 'error');
            });
    }

    document.getElementById('saveUserBtn').addEventListener('click', function() {
        const userId = document.getElementById('edit_user_id').value;
        const fullName = document.getElementById('edit_full_name').value;
        const email = document.getElementById('edit_email').value;
        const phone = document.getElementById('edit_phone').value;
        const regNumber = document.getElementById('edit_reg_number').value;
        const role = document.getElementById('edit_role').value;
        const newPassword = document.getElementById('edit_new_password').value;
        const confirmPassword = document.getElementById('edit_confirm_password').value;
        
        if (!fullName || !email) {
            showToast('Please fill in all required fields.', 'error');
            return;
        }
        
        if (newPassword || confirmPassword) {
            if (!newPassword) {
                showToast('New password is required.', 'error');
                return;
            }
            if (newPassword.length < 6) {
                showToast('New password must be at least 6 characters.', 'error');
                return;
            }
            if (newPassword !== confirmPassword) {
                showToast('New password and confirm password do not match.', 'error');
                return;
            }
        }
        
        const formData = new URLSearchParams();
        formData.append('update_user_ajax', '1');
        formData.append('user_id', userId);
        formData.append('full_name', fullName);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('reg_number', regNumber);
        formData.append('role', role);
        formData.append('new_password', newPassword);
        formData.append('confirm_password', confirmPassword);
        
        document.getElementById('loadingOverlay').classList.add('active');
        document.getElementById('editModal').classList.remove('active');
        
        fetch('manage_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingOverlay').classList.remove('active');
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error: ' + data.message, 'error');
                document.getElementById('editModal').classList.add('active');
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').classList.remove('active');
            showToast('Error updating user. Please try again.', 'error');
            document.getElementById('editModal').classList.add('active');
        });
    });

    document.getElementById('closeEditModal').addEventListener('click', function() {
        document.getElementById('editModal').classList.remove('active');
    });

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            document.getElementById('editModal').classList.remove('active');
        }
    });

    // ---------- DEACTIVATE MODAL ----------
    let deactivateUserId = null;

    function confirmDeactivate(userId) {
        deactivateUserId = userId;
        document.getElementById('deactivateModal').classList.add('active');
    }

    document.getElementById('confirmDeactivateBtn').addEventListener('click', function() {
        if (deactivateUserId) {
            window.location.href = 'manage_users.php?delete_user=' + deactivateUserId;
        }
    });

    document.getElementById('cancelDeactivateBtn').addEventListener('click', function() {
        document.getElementById('deactivateModal').classList.remove('active');
        deactivateUserId = null;
    });

    document.getElementById('deactivateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            document.getElementById('deactivateModal').classList.remove('active');
            deactivateUserId = null;
        }
    });

    // ---------- REACTIVATE MODAL ----------
    let reactivateUserId = null;

    function confirmReactivate(userId) {
        reactivateUserId = userId;
        document.getElementById('reactivateModal').classList.add('active');
    }

    document.getElementById('confirmReactivateBtn').addEventListener('click', function() {
        if (reactivateUserId) {
            window.location.href = 'manage_users.php?reactivate_user=' + reactivateUserId;
        }
    });

    document.getElementById('cancelReactivateBtn').addEventListener('click', function() {
        document.getElementById('reactivateModal').classList.remove('active');
        reactivateUserId = null;
    });

    document.getElementById('reactivateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            document.getElementById('reactivateModal').classList.remove('active');
            reactivateUserId = null;
        }
    });

    // ---------- LOGOUT ----------
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogoutBtn = document.getElementById('confirmLogout');
    const cancelLogoutBtn = document.getElementById('cancelLogout');

    logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        logoutModal.classList.add('active');
    });

    confirmLogoutBtn.addEventListener('click', function() {
        logoutModal.classList.remove('active');
        document.getElementById('loadingOverlay').classList.add('active');
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 500);
    });

    cancelLogoutBtn.addEventListener('click', function() {
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