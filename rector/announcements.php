<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/connection.php';

$rector_id = $_SESSION['user_id'];
$rector_name = $_SESSION['full_name'];
$rector_email = $_SESSION['email'];

// ========== HANDLE POST REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create Announcement
    if (isset($_POST['create_announcement'])) {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_type = $_POST['target_type'] ?? 'all';
        $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $expiry_days = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : 30;
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Title is required.";
        }
        if (empty($message)) {
            $errors[] = "Message is required.";
        }
        
        if (empty($errors)) {
            $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
            
            $insert_sql = "INSERT INTO announcements (title, message, target_type, target_id, created_by, is_active, expiry_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            if ($insert_stmt) {
                mysqli_stmt_bind_param($insert_stmt, "sssiiss", $title, $message, $target_type, $target_id, $rector_id, $is_active, $expiry_date);
                mysqli_stmt_execute($insert_stmt);
                mysqli_stmt_close($insert_stmt);
            }
            
            $_SESSION['flash_message'] = "Announcement created successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: announcements.php");
            exit();
        } else {
            $_SESSION['flash_message'] = implode("<br>", $errors);
            $_SESSION['flash_type'] = "error";
            header("Location: announcements.php?action=create");
            exit();
        }
    }
    
    // Update Announcement
    if (isset($_POST['update_announcement'])) {
        $announcement_id = intval($_POST['announcement_id']);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_type = $_POST['target_type'] ?? 'all';
        $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Title is required.";
        }
        if (empty($message)) {
            $errors[] = "Message is required.";
        }
        
        if (empty($errors)) {
            $update_sql = "UPDATE announcements SET title = ?, message = ?, target_type = ?, target_id = ?, is_active = ? WHERE id = ? AND created_by = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "sssiiii", $title, $message, $target_type, $target_id, $is_active, $announcement_id, $rector_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            
            $_SESSION['flash_message'] = "Announcement updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: announcements.php");
            exit();
        } else {
            $_SESSION['flash_message'] = implode("<br>", $errors);
            $_SESSION['flash_type'] = "error";
            header("Location: announcements.php?action=edit&id=" . $announcement_id);
            exit();
        }
    }
    
    // Delete Announcement
    if (isset($_POST['delete_announcement'])) {
        $announcement_id = intval($_POST['announcement_id']);
        
        $delete_sql = "DELETE FROM announcements WHERE id = ? AND created_by = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "ii", $announcement_id, $rector_id);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }
        
        $_SESSION['flash_message'] = "Announcement deleted successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: announcements.php");
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

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ========== GET PROFILE DATA ==========
$prof_sql = "SELECT phone_number, profile_picture FROM users WHERE id = ?";
$prof_stmt = mysqli_prepare($conn, $prof_sql);
mysqli_stmt_bind_param($prof_stmt, "i", $rector_id);
mysqli_stmt_execute($prof_stmt);
$prof_result = mysqli_stmt_get_result($prof_stmt);
$prof_data = mysqli_fetch_assoc($prof_result);
$phone = $prof_data['phone_number'] ?? '';
$profile_pic = $prof_data['profile_picture'] ?? '';
if (!isset($_SESSION['profile_picture']) && $profile_pic) $_SESSION['profile_picture'] = $profile_pic;
mysqli_stmt_close($prof_stmt);

// ========== GET ANNOUNCEMENT FOR EDIT ==========
$edit_announcement = null;
if ($action === 'edit' && $edit_id > 0) {
    $edit_sql = "SELECT * FROM announcements WHERE id = ? AND created_by = ?";
    $edit_stmt = mysqli_prepare($conn, $edit_sql);
    mysqli_stmt_bind_param($edit_stmt, "ii", $edit_id, $rector_id);
    mysqli_stmt_execute($edit_stmt);
    $edit_result = mysqli_stmt_get_result($edit_stmt);
    $edit_announcement = mysqli_fetch_assoc($edit_result);
    mysqli_stmt_close($edit_stmt);
}

// ========== GET ALL ANNOUNCEMENTS ==========
$announcements_sql = "SELECT a.*, u.full_name as creator_name, u.role as creator_role
                     FROM announcements a
                     LEFT JOIN users u ON a.created_by = u.id
                     WHERE a.created_by = ?
                     ORDER BY a.created_at DESC";
$announcements_stmt = mysqli_prepare($conn, $announcements_sql);
mysqli_stmt_bind_param($announcements_stmt, "i", $rector_id);
mysqli_stmt_execute($announcements_stmt);
$announcements_result = mysqli_stmt_get_result($announcements_stmt);

// ========== GET DEPARTMENTS FOR TARGET SELECTION ==========
$departments_sql = "SELECT id, name FROM departments ORDER BY name";
$departments_result = mysqli_query($conn, $departments_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Announcements - Rector Panel</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           COMPLETE STYLES
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
        .btn-sm.success {
            background: #10b981;
        }
        .btn-sm.success:hover {
            background: #065f46;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
            font-size: 1.1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
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

        /* ---------- ANNOUNCEMENT CARDS ---------- */
        .announcement-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            border: 1px solid #e5edf5;
            transition: all 0.2s;
        }
        .announcement-card:hover {
            border-color: #1a56db;
            box-shadow: 0 4px 16px rgba(26, 86, 219, 0.08);
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
        .announcement-card .card-header .badge {
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-all { background: #dbeafe; color: #1e40af; }
        .badge-staff { background: #fef3c7; color: #b45309; }
        .badge-students { background: #ede9fe; color: #6d28d9; }
        .badge-department { background: #fce7f3; color: #be185d; }
        .badge-individual { background: #d1fae5; color: #065f46; }

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

        /* ---------- EMPTY STATE ---------- */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #8ba0bc;
        }
        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: 16px;
            color: #dbeafe;
        }
        .empty-state h5 {
            font-size: 1.1rem;
            color: #4a5a7a;
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 0.9rem;
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
            .content-area { padding: 20px 24px; min-height: auto; }
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
                padding: 10px 0 !important;
                margin: 4px 0 !important;
                gap: 0 !important;
                border-radius: 8px !important;
            }
            .menu-item span { display: none !important; }
            .menu-item i {
                font-size: 1.1rem !important;
                width: 100% !important;
                text-align: center !important;
                margin: 0 !important;
            }
            .menu-item:hover { transform: none !important; }
            
            .logout-item .menu-item span { display: none !important; }
            .logout-item .menu-item i { font-size: 1.1rem !important; }

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
            .content-area h4 { font-size: 0.85rem; }

            .content-header {
                flex-direction: column;
                align-items: stretch;
            }
            .content-header .btn-sm {
                text-align: center;
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

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .btn-submit {
                padding: 10px 20px;
                font-size: 0.85rem;
                width: 100%;
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
                padding: 8px 0 !important;
                margin: 3px 0 !important;
            }
            .menu-item i {
                font-size: 1rem !important;
            }
            .logout-item .menu-item i {
                font-size: 1rem !important;
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
            .content-area h4 { font-size: 0.75rem; }

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
    </style>
</head>
<body>

<!-- ========== TOAST CONTAINER ========== -->
<div class="toast-container" id="toastContainer"></div>

<!-- ========== SIDEBAR ========== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="row-cfms">
            <span class="brand">CFMS <span>| Rector</span></span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">Rector Portal</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>

    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="complaints.php" class="menu-item">
            <i class="fas fa-file-alt"></i><span>Complaints</span>
        </a>
        <a href="escalation-center.php" class="menu-item">
            <i class="fas fa-arrow-up"></i><span>Escalation Center</span>
        </a>
        <a href="staff-monitoring.php" class="menu-item">
            <i class="fas fa-users"></i><span>Staff Monitoring</span>
        </a>
        <a href="analytics-reports.php" class="menu-item">
            <i class="fas fa-chart-line"></i><span>Analytics & Reports</span>
        </a>
        <a href="announcements.php" class="menu-item active">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        <a href="profile.php" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="change-password.php" class="menu-item">
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
                <i class="fas fa-university" style="color: #1a56db;"></i> Welcome, <?php echo htmlspecialchars($rector_name); ?>
            </div>
            <div style="font-size: 0.75rem; color: #6b85a0;">Rector</div>
        </div>
        <div class="profile-info">
            <div class="profile-pic">
                <?php if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($rector_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="name"><?php echo htmlspecialchars($rector_name); ?></div>
                <div class="reg">Rector</div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD BODY -->
    <div class="dashboard-body">
        <?php if ($flash_message): ?>
            <div class="alert <?php echo $flash_type; ?>"><?php echo $flash_message; ?></div>
        <?php endif; ?>

        <?php if ($action === 'create' || ($action === 'edit' && $edit_announcement)): ?>
            <!-- ========== CREATE / EDIT ANNOUNCEMENT FORM ========== -->
            <div class="content-area">
                <div style="margin-bottom: 16px;">
                    <a href="announcements.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Announcements</span>
                    </a>
                </div>

                <h4>
                    <i class="fas fa-<?php echo ($action === 'edit') ? 'edit' : 'plus-circle'; ?>" style="color:#1a56db;"></i>
                    <?php echo ($action === 'edit') ? 'Edit Announcement' : 'Create New Announcement'; ?>
                </h4>

                <form method="POST">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="update_announcement" value="1">
                        <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="create_announcement" value="1">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" required placeholder="Enter announcement title" 
                               value="<?php echo $edit_announcement ? htmlspecialchars($edit_announcement['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" rows="6" required placeholder="Write your announcement message..."><?php echo $edit_announcement ? htmlspecialchars($edit_announcement['message']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Target Audience</label>
                            <select name="target_type" id="targetType">
                                <option value="all" <?php echo ($edit_announcement && $edit_announcement['target_type'] == 'all') ? 'selected' : ''; ?>>Everyone</option>
                                <option value="staff" <?php echo ($edit_announcement && $edit_announcement['target_type'] == 'staff') ? 'selected' : ''; ?>>Staff Only</option>
                                <option value="students" <?php echo ($edit_announcement && $edit_announcement['target_type'] == 'students') ? 'selected' : ''; ?>>Students Only</option>
                                <option value="department" <?php echo ($edit_announcement && $edit_announcement['target_type'] == 'department') ? 'selected' : ''; ?>>Specific Department</option>
                            </select>
                        </div>

                        <div class="form-group" id="departmentField" style="<?php echo ($edit_announcement && $edit_announcement['target_type'] == 'department') ? '' : 'display:none;'; ?>">
                            <label>Select Department</label>
                            <select name="target_id">
                                <option value="">Select Department</option>
                                <?php while ($dept = mysqli_fetch_assoc($departments_result)): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($edit_announcement && $edit_announcement['target_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_active">
                                <option value="1" <?php echo ($edit_announcement && $edit_announcement['is_active'] == 1) ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo ($edit_announcement && $edit_announcement['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <div class="helper-text">Inactive announcements will not be visible to users.</div>
                        </div>

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
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-<?php echo ($action === 'edit') ? 'save' : 'paper-plane'; ?>"></i>
                        <?php echo ($action === 'edit') ? ' Update Announcement' : ' Publish Announcement'; ?>
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- ========== ANNOUNCEMENTS LIST ========== -->
            <div class="content-area">
                <div class="content-header">
                    <h4><i class="fas fa-bullhorn" style="color:#1a56db;"></i> Announcements</h4>
                    <a href="announcements.php?action=create" class="btn-sm" style="padding:8px 24px; font-size:0.85rem;">
                        <i class="fas fa-plus-circle"></i> Create Announcement
                    </a>
                </div>

                <?php if (mysqli_num_rows($announcements_result) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h5>No Announcements Yet</h5>
                        <p>Create your first announcement to communicate with students and staff.</p>
                        <a href="announcements.php?action=create" class="btn-sm" style="margin-top:16px; padding:10px 30px;">
                            <i class="fas fa-plus-circle"></i> Create Announcement
                        </a>
                    </div>
                <?php else: ?>
                    <?php while ($ann = mysqli_fetch_assoc($announcements_result)): 
                        $target_labels = [
                            'all' => ['label' => 'Everyone', 'class' => 'badge-all'],
                            'staff' => ['label' => 'Staff Only', 'class' => 'badge-staff'],
                            'students' => ['label' => 'Students Only', 'class' => 'badge-students'],
                            'department' => ['label' => 'Department', 'class' => 'badge-department'],
                            'individual' => ['label' => 'Specific User', 'class' => 'badge-individual'],
                        ];
                        $target_info = $target_labels[$ann['target_type']] ?? $target_labels['all'];
                        $status_class = $ann['is_active'] ? 'badge-active' : 'badge-inactive';
                        $status_text = $ann['is_active'] ? 'Active' : 'Inactive';
                        $is_expired = $ann['expiry_date'] && strtotime($ann['expiry_date']) < time();
                        
                        // Get department name if target_type is department
                        $dept_name = '';
                        if ($ann['target_type'] == 'department' && $ann['target_id']) {
                            $dept_sql = "SELECT name FROM departments WHERE id = ?";
                            $dept_stmt = mysqli_prepare($conn, $dept_sql);
                            mysqli_stmt_bind_param($dept_stmt, "i", $ann['target_id']);
                            mysqli_stmt_execute($dept_stmt);
                            $dept_result2 = mysqli_stmt_get_result($dept_stmt);
                            $dept_row = mysqli_fetch_assoc($dept_result2);
                            if ($dept_row) {
                                $dept_name = $dept_row['name'];
                            }
                            mysqli_stmt_close($dept_stmt);
                        }
                    ?>
                        <div class="announcement-card">
                            <div class="card-header">
                                <div class="title">
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                    <?php if ($is_expired): ?>
                                        <span class="badge badge-inactive" style="font-size:0.55rem; margin-left:8px;">Expired</span>
                                    <?php endif; ?>
                                </div>
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
                                    <span><i class="far fa-user"></i> <?php echo htmlspecialchars($ann['creator_name'] ?? 'System'); ?></span>
                                    <span><i class="far fa-calendar-alt"></i> <?php echo date('d M Y, h:i A', strtotime($ann['created_at'])); ?></span>
                                    <?php if ($dept_name): ?>
                                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($dept_name); ?></span>
                                    <?php endif; ?>
                                    <?php if ($ann['expiry_date']): ?>
                                        <span><i class="far fa-hourglass"></i> Expires: <?php echo date('d M Y', strtotime($ann['expiry_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="actions">
                                    <a href="announcements.php?action=edit&id=<?php echo $ann['id']; ?>" class="btn-sm" style="background:#6b85a0;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn-sm danger" onclick="confirmDelete(<?php echo $ann['id']; ?>, '<?php echo addslashes($ann['title']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php mysqli_stmt_close($announcements_stmt); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== DELETE CONFIRMATION MODAL ========== -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-container">
        <i class="fas fa-exclamation-triangle"></i>
        <h3>Delete Announcement</h3>
        <p>Are you sure you want to delete "<span id="deleteTitle"></span>"? This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_announcement" value="1">
            <input type="hidden" name="announcement_id" id="deleteId" value="">
            <div class="modal-buttons">
                <button type="submit" class="modal-btn confirm">Yes, Delete</button>
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </form>
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

    // ---------- TARGET AUDIENCE TOGGLE ----------
    const targetType = document.getElementById('targetType');
    const departmentField = document.getElementById('departmentField');

    if (targetType) {
        targetType.addEventListener('change', function() {
            if (this.value === 'department') {
                departmentField.style.display = 'block';
            } else {
                departmentField.style.display = 'none';
            }
        });
    }

    // ---------- DELETE CONFIRMATION ----------
    function confirmDelete(id, title) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteTitle').textContent = title;
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    // Close modal on outside click
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // ---------- FORM SUBMISSION ----------
    document.querySelector('form[action*="announcements"]')?.addEventListener('submit', function() {
        const btn = this.querySelector('.btn-submit');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
    });
</script>

</body>
</html>