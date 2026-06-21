<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'examination_officer') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/connection.php';

$officer_id = $_SESSION['user_id'];
$officer_name = $_SESSION['full_name'];
$officer_email = $_SESSION['email'];

$active_page = 'announcements';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Get profile data
$prof_sql = "SELECT phone_number, profile_picture FROM users WHERE id = ?";
$prof_stmt = mysqli_prepare($conn, $prof_sql);
mysqli_stmt_bind_param($prof_stmt, "i", $officer_id);
mysqli_stmt_execute($prof_stmt);
$prof_result = mysqli_stmt_get_result($prof_stmt);
$prof_data = mysqli_fetch_assoc($prof_result);
$phone = $prof_data['phone_number'] ?? '';
$profile_pic = $prof_data['profile_picture'] ?? '';
if (!isset($_SESSION['profile_picture']) && $profile_pic) $_SESSION['profile_picture'] = $profile_pic;
mysqli_stmt_close($prof_stmt);

// Handle create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_type = $_POST['target_type'];
    $target_id = !empty($_POST['target_id']) ? intval($_POST['target_id']) : null;
    $expiry_days = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : 30;
    
    $errors = [];
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($message)) $errors[] = "Message is required.";
    
    if (empty($errors)) {
        $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
        
        $insert_sql = "INSERT INTO announcements (title, message, target_type, target_id, created_by, expiry_date, is_active, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "sssiis", $title, $message, $target_type, $target_id, $officer_id, $expiry_date);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $_SESSION['flash_message'] = "Announcement created successfully!";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Database error: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "error";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        $_SESSION['flash_message'] = implode("<br>", $errors);
        $_SESSION['flash_type'] = "error";
    }
    header("Location: announcements.php");
    exit();
}

// Handle delete announcement
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $delete_sql = "DELETE FROM announcements WHERE id = ? AND created_by = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "ii", $delete_id, $officer_id);
    mysqli_stmt_execute($delete_stmt);
    mysqli_stmt_close($delete_stmt);
    $_SESSION['flash_message'] = "Announcement deleted successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: announcements.php");
    exit();
}

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Get departments for dropdown
$departments = [];
$dept_sql = "SELECT id, name FROM departments ORDER BY name";
$dept_result = mysqli_query($conn, $dept_sql);
while ($dept = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $dept;
}

// Get users for dropdown
$users_sql = "SELECT id, full_name, email, role FROM users ORDER BY full_name";
$users_result = mysqli_query($conn, $users_sql);
$users_list = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $users_list[] = $user;
}

// Get announcements sent by officer
$sent_sql = "SELECT a.*, u.full_name as creator_name, u.role as creator_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.created_by = ?
            ORDER BY a.created_at DESC";
$sent_stmt = mysqli_prepare($conn, $sent_sql);
mysqli_stmt_bind_param($sent_stmt, "i", $officer_id);
mysqli_stmt_execute($sent_stmt);
$sent_result = mysqli_stmt_get_result($sent_stmt);

// Get announcements received by officer (from others)
$received_sql = "SELECT a.*, u.full_name as creator_name, u.role as creator_role
                FROM announcements a
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.created_by != ?
                AND a.is_active = 1
                AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
                AND (a.target_type = 'all' 
                     OR a.target_type = 'staff'
                     OR (a.target_type = 'department' AND a.target_id = ?)
                     OR (a.target_type = 'individual' AND a.target_id = ?))
                ORDER BY a.created_at DESC";
$received_stmt = mysqli_prepare($conn, $received_sql);
mysqli_stmt_bind_param($received_stmt, "iii", $officer_id, $officer_id, $officer_id);
mysqli_stmt_execute($received_stmt);
$received_result = mysqli_stmt_get_result($received_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Announcements - Examination Officer Panel</title>
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

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
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

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            background: transparent;
            color: #1a56db;
            border: 2px solid #1a56db;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .btn-back:hover {
            background: #1a56db;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
        }
        .btn-back i { font-size: 1rem; }

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
            min-height: 120px;
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

        /* ========== BADGE ========== */
        .badge {
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-all { background: #dbeafe; color: #1e40af; }
        .badge-students { background: #cffafe; color: #0e7490; }
        .badge-staff { background: #fef3c7; color: #b45309; }
        .badge-department { background: #e0e7ff; color: #4338ca; }
        .badge-individual { background: #fce7f3; color: #be185d; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-expired { background: #fef3c7; color: #b45309; }

        .creator-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .creator-admin { background: #fce7f3; color: #be185d; }
        .creator-hod { background: #cffafe; color: #0e7490; }
        .creator-dean { background: #e0e7ff; color: #4338ca; }
        .creator-accountant { background: #fef3c7; color: #b45309; }
        .creator-it { background: #dcfce7; color: #166534; }
        .creator-exam { background: #fef9c3; color: #854d0e; }
        .creator-other { background: #e2e8f0; color: #475569; }

        /* ========== ANNOUNCEMENT CARDS ========== */
        .announcement-card {
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            border: 1px solid #e5edf5;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .announcement-card:hover {
            border-color: #1a56db;
            box-shadow: 0 4px 16px rgba(26, 86, 219, 0.08);
        }
        .announcement-card.sent {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }
        .announcement-card.received {
            border-left: 4px solid #3b82f6;
            background: #eff6ff;
        }
        .announcement-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .announcement-card .card-header .title {
            font-size: 1rem;
            font-weight: 700;
            color: #0a2a5e;
        }
        .announcement-card .card-body {
            color: #4a5a7a;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .announcement-card .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid #e5edf5;
            font-size: 0.78rem;
            color: #8ba0bc;
        }
        .announcement-card .card-footer .actions {
            display: flex;
            gap: 8px;
        }
        .announcement-card .card-footer .meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .announcement-card .card-footer .meta i {
            margin-right: 4px;
        }

        /* ========== EMPTY STATE ========== */
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
        .no-data p {
            margin: 0;
        }

        /* ========== SECTION TITLE ========== */
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin: 24px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5edf5;
        }
        .section-title .count {
            background: #e5edf5;
            padding: 1px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #4a5a7a;
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
        .modal-container i { font-size: 2.5rem; color: #dc2626; margin-bottom: 12px; }
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
        .modal-btn.confirm { background: #dc2626; color: white; }
        .modal-btn.confirm:hover { background: #991b1b; transform: translateY(-2px); }
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

            .content-area {
                padding: 16px;
                border-radius: 12px;
            }
            .content-area h4 {
                font-size: 0.85rem;
            }

            .content-header {
                flex-direction: column;
                align-items: stretch;
            }
            .content-header .btn-sm {
                text-align: center;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .announcement-card {
                padding: 16px;
            }
            .announcement-card .card-header .title {
                font-size: 0.9rem;
            }
            .announcement-card .card-body {
                font-size: 0.82rem;
            }
            .announcement-card .card-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .announcement-card .card-footer .actions {
                width: 100%;
                justify-content: flex-start;
            }

            .btn-submit {
                width: 100%;
                padding: 10px 20px;
                font-size: 0.85rem;
            }

            .btn-back {
                padding: 6px 14px;
                font-size: 0.75rem;
            }
            .btn-back span {
                display: none;
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

            .content-header .btn-sm {
                font-size: 0.75rem;
                padding: 6px 16px;
            }

            .form-group label {
                font-size: 0.8rem;
            }
            .form-group input, .form-group select, .form-group textarea {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            .announcement-card {
                padding: 12px;
            }
            .announcement-card .card-header .title {
                font-size: 0.8rem;
            }
            .announcement-card .card-body {
                font-size: 0.75rem;
            }
            .announcement-card .card-footer {
                font-size: 0.65rem;
            }

            .btn-sm {
                padding: 4px 12px;
                font-size: 0.6rem;
            }

            .btn-submit {
                padding: 8px 16px;
                font-size: 0.8rem;
            }

            .btn-back {
                padding: 4px 10px;
                font-size: 0.65rem;
            }
            .btn-back span {
                display: none;
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
            <span class="brand">CFMS <span>| Exam</span></span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">Examination Portal</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>

    <div class="sidebar-menu">
        <a href="examination_dashboard.php?page=dashboard" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="examination_dashboard.php?page=complaints" class="menu-item">
            <i class="fas fa-file-alt"></i><span>All Complaints</span>
        </a>
        <a href="examination_dashboard.php?page=pending" class="menu-item">
            <i class="fas fa-clock"></i><span>Pending</span>
        </a>
        <a href="examination_dashboard.php?page=resolved" class="menu-item">
            <i class="fas fa-check-circle"></i><span>Resolved</span>
        </a>
        <a href="examination_dashboard.php?page=escalated" class="menu-item">
            <i class="fas fa-exclamation-triangle"></i><span>Escalated</span>
        </a>
        <a href="announcements.php" class="menu-item active">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        <a href="examination_dashboard.php?page=profile" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="examination_dashboard.php?page=change-password" class="menu-item">
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
                <i class="fas fa-university" style="color: #1a56db;"></i> Welcome, <?php echo htmlspecialchars($officer_name); ?>
            </div>
            <div style="font-size: 0.75rem; color: #6b85a0;">Examination Officer</div>
        </div>
        <div class="profile-info">
            <div class="profile-pic">
                <?php if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($officer_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="name"><?php echo htmlspecialchars($officer_name); ?></div>
                <div class="reg">Examination Officer</div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD BODY -->
    <div class="dashboard-body">
        <?php if ($flash_message): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast('<?php echo addslashes($flash_message); ?>', '<?php echo $flash_type; ?>');
                });
            </script>
        <?php endif; ?>

        <?php if ($action === 'create'): ?>
            <!-- ========== CREATE ANNOUNCEMENT FORM ========== -->
            <div class="content-area">
                <div style="margin-bottom: 16px;">
                    <a href="announcements.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Announcements</span>
                    </a>
                </div>

                <h4><i class="fas fa-plus-circle" style="color:#1a56db;"></i> Create New Announcement</h4>

                <form method="POST">
                    <input type="hidden" name="create_announcement" value="1">
                    
                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="Enter announcement title" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" rows="6" placeholder="Write your announcement message..." required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Target Audience</label>
                            <select name="target_type" id="target_type" onchange="toggleTargetField()">
                                <option value="all">Everyone</option>
                                <option value="students">Students Only</option>
                                <option value="staff">Staff Only</option>
                                <option value="department">Specific Department</option>
                                <option value="individual">Specific User</option>
                            </select>
                        </div>
                        <div class="form-group" id="target_field" style="display: none;">
                            <label>Select Target</label>
                            <select name="target_id" id="target_select">
                                <option value="">-- Select --</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry (Days)</label>
                            <select name="expiry_days">
                                <option value="7">7 days</option>
                                <option value="14">14 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                                <option value="180">180 days</option>
                                <option value="365">1 year</option>
                            </select>
                            <div class="helper-text">After expiry, announcement will no longer be shown.</div>
                        </div>
                        <div class="form-group" style="display:flex; align-items:flex-end;">
                            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Publish Announcement</button>
                        </div>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- ========== ANNOUNCEMENTS LIST ========== -->
            <div class="content-area">
                <div class="content-header">
                    <h4><i class="fas fa-bullhorn" style="color:#f59e0b;"></i> Announcements</h4>
                    <a href="announcements.php?action=create" class="btn-sm" style="padding:8px 24px; font-size:0.85rem;">
                        <i class="fas fa-plus-circle"></i> Create Announcement
                    </a>
                </div>

                <!-- Announcements I Sent -->
                <div class="section-title">
                    <i class="fas fa-paper-plane" style="color:#10b981;"></i> 
                    Announcements I Sent
                    <span class="count"><?php echo mysqli_num_rows($sent_result); ?></span>
                </div>

                <?php if (mysqli_num_rows($sent_result) == 0): ?>
                    <div class="announcement-card sent">
                        <div class="no-data">
                            <i class="fas fa-paper-plane"></i>
                            <p>You haven't sent any announcements yet.</p>
                            <a href="announcements.php?action=create" class="btn-sm" style="margin-top:12px; padding:8px 24px;">
                                <i class="fas fa-plus-circle"></i> Create First Announcement
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php while ($ann = mysqli_fetch_assoc($sent_result)): 
                        $target_labels = [
                            'all' => ['label' => 'Everyone', 'class' => 'badge-all'],
                            'staff' => ['label' => 'Staff Only', 'class' => 'badge-staff'],
                            'students' => ['label' => 'Students Only', 'class' => 'badge-students'],
                            'department' => ['label' => 'Department', 'class' => 'badge-department'],
                            'individual' => ['label' => 'Specific User', 'class' => 'badge-individual'],
                        ];
                        $target_info = $target_labels[$ann['target_type']] ?? $target_labels['all'];
                        
                        $is_expired = $ann['expiry_date'] && strtotime($ann['expiry_date']) < time();
                        if ($ann['is_active'] && !$is_expired) {
                            $status_class = 'badge-active';
                            $status_text = 'Active';
                        } elseif ($is_expired) {
                            $status_class = 'badge-expired';
                            $status_text = 'Expired';
                        } else {
                            $status_class = 'badge-inactive';
                            $status_text = 'Inactive';
                        }
                    ?>
                        <div class="announcement-card sent">
                            <div class="card-header">
                                <div class="title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <span class="badge <?php echo $target_info['class']; ?>"><?php echo $target_info['label']; ?></span>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                            </div>
                            <div class="card-footer">
                                <div class="meta">
                                    <span><i class="far fa-calendar-alt"></i> <?php echo date('d M Y, h:i A', strtotime($ann['created_at'])); ?></span>
                                    <?php if ($ann['expiry_date']): ?>
                                        <span><i class="far fa-hourglass"></i> Expires: <?php echo date('d M Y', strtotime($ann['expiry_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="actions">
                                    <a href="announcements.php?delete=<?php echo $ann['id']; ?>" class="btn-sm danger" onclick="return confirm('Are you sure you want to delete this announcement?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php mysqli_stmt_close($sent_stmt); ?>

                <!-- Announcements Received -->
                <div class="section-title">
                    <i class="fas fa-inbox" style="color:#3b82f6;"></i> 
                    Announcements Received
                    <span class="count"><?php echo mysqli_num_rows($received_result); ?></span>
                </div>

                <?php if (mysqli_num_rows($received_result) == 0): ?>
                    <div class="announcement-card received">
                        <div class="no-data">
                            <i class="fas fa-inbox"></i>
                            <p>No announcements received yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php while ($ann = mysqli_fetch_assoc($received_result)): 
                        $target_labels = [
                            'all' => ['label' => 'Everyone', 'class' => 'badge-all'],
                            'staff' => ['label' => 'Staff Only', 'class' => 'badge-staff'],
                            'students' => ['label' => 'Students Only', 'class' => 'badge-students'],
                            'department' => ['label' => 'Department', 'class' => 'badge-department'],
                            'individual' => ['label' => 'Specific User', 'class' => 'badge-individual'],
                        ];
                        $target_info = $target_labels[$ann['target_type']] ?? $target_labels['all'];
                        
                        $creator_class = match($ann['creator_role']) {
                            'admin' => 'creator-admin',
                            'hod' => 'creator-hod',
                            'dean' => 'creator-dean',
                            'accountant' => 'creator-accountant',
                            'it_officer' => 'creator-it',
                            'examination_officer' => 'creator-exam',
                            default => 'creator-other'
                        };
                        $creator_label = match($ann['creator_role']) {
                            'admin' => '👑 Admin',
                            'hod' => '📚 HOD',
                            'dean' => '🎓 Dean',
                            'accountant' => '💰 Accountant',
                            'it_officer' => '💻 IT Officer',
                            'examination_officer' => '📝 Examination Officer',
                            default => '👔 Staff'
                        };
                    ?>
                        <div class="announcement-card received">
                            <div class="card-header">
                                <div class="title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                <span class="badge <?php echo $target_info['class']; ?>"><?php echo $target_info['label']; ?></span>
                            </div>
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                            </div>
                            <div class="card-footer">
                                <div class="meta">
                                    <span>
                                        <i class="fas fa-user"></i> 
                                        From: <span class="creator-badge <?php echo $creator_class; ?>"><?php echo $creator_label; ?></span>
                                        <?php echo htmlspecialchars($ann['creator_name']); ?>
                                    </span>
                                    <span><i class="far fa-calendar-alt"></i> <?php echo date('d M Y, h:i A', strtotime($ann['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php mysqli_stmt_close($received_stmt); ?>
            </div>
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

    <?php if (!empty($flash_message)): ?>
        <?php if ($flash_type === 'success'): ?>
            showToast('<?php echo addslashes($flash_message); ?>', 'success');
        <?php else: ?>
            showToast('<?php echo addslashes($flash_message); ?>', 'error');
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

    // ---------- TARGET FIELD TOGGLE ----------
    function toggleTargetField() {
        const targetType = document.getElementById('target_type').value;
        const targetField = document.getElementById('target_field');
        const targetSelect = document.getElementById('target_select');
        
        if (targetType === 'department') {
            targetSelect.innerHTML = '<option value="">-- Select Department --</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['id']; ?>"><?php echo addslashes($dept['name']); ?></option><?php endforeach; ?>';
            targetField.style.display = 'block';
        } else if (targetType === 'individual') {
            targetSelect.innerHTML = '<option value="">-- Select User --</option><?php foreach ($users_list as $user): ?><option value="<?php echo $user['id']; ?>"><?php echo addslashes($user['full_name']); ?> (<?php echo $user['email']; ?>)</option><?php endforeach; ?>';
            targetField.style.display = 'block';
        } else {
            targetField.style.display = 'none';
        }
    }

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
</script>
</body>
</html>