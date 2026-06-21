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

$active_page = 'dashboard';

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

// Get statistics for dashboard - Remove active_announcements
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM complaints) as total_complaints,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM users WHERE role != 'student') as total_staff,
    (SELECT COUNT(*) FROM system_logs WHERE DATE(created_at) = CURDATE()) as today_logs";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get latest 5 users
$recent_users_sql = "SELECT id, full_name, email, role, is_active, created_at FROM users ORDER BY id DESC LIMIT 5";
$recent_users_result = mysqli_query($conn, $recent_users_sql);

// Get latest announcements
$announcements_sql = "SELECT a.id, a.title, a.message, a.created_at, u.full_name as sender_name 
                     FROM announcements a
                     LEFT JOIN users u ON a.created_by = u.id
                     WHERE a.is_active = 1 
                     ORDER BY a.created_at DESC LIMIT 4";
$announcements_result = mysqli_query($conn, $announcements_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           COMPLETE STYLES - Consistent with Rector Dashboard
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
        .summary-card .icon-wrapper.indigo { background: #e0e7ff; color: #4338ca; }

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
        .badge-new { background: #1a56db; color: white; font-size: 0.55rem; padding: 2px 10px; border-radius: 30px; font-weight: 600; text-transform: uppercase; }

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
        .role-deputy_rector { background: #f3e8ff; color: #6b21a5; }
        .role-rector { background: #fce7f3; color: #be185d; }
        .role-president { background: #fef3c7; color: #b45309; }
        .role-examination_officer { background: #e0e7ff; color: #4338ca; }

        /* ---------- TABLES ---------- */
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
            min-height: 300px;
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

        /* ---------- ANNOUNCEMENT SIDEBAR ---------- */
        .announcements-sidebar {
            background: white;
            border-radius: 20px;
            padding: 24px 22px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 300px;
        }
        .announcements-sidebar h4 {
            font-size: 1rem;
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

        /* ---------- GRID LAYOUT ---------- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 70% 30%;
            gap: 24px;
            margin-top: 24px;
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
           RESPONSIVE - Mobile Sidebar Spacing Fixed
           ============================================ */

        @media (max-width: 1024px) {
            .dashboard-body { padding: 20px 24px; }
            .top-bar { padding: 14px 24px; }
            .dashboard-grid { grid-template-columns: 1fr; }
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

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .content-area {
                padding: 16px;
                border-radius: 12px;
            }
            .content-area h4 {
                font-size: 0.85rem;
            }

            .announcements-sidebar {
                padding: 16px;
                min-height: auto;
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

            .announcement-summary .a-title {
                font-size: 0.8rem;
            }
            .announcement-summary .a-sender {
                font-size: 0.7rem;
            }
            .announcement-summary .a-time {
                font-size: 0.6rem;
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

            .content-area {
                padding: 12px;
                border-radius: 10px;
            }
            .content-area h4 {
                font-size: 0.75rem;
            }

            .announcements-sidebar {
                padding: 12px;
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

            .announcement-summary .a-title {
                font-size: 0.75rem;
            }
            .announcement-summary .a-sender {
                font-size: 0.65rem;
            }
            .announcement-summary .a-time {
                font-size: 0.55rem;
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
        <a href="admin_dashboard.php" class="menu-item active">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="manage_users.php" class="menu-item">
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
        <!-- Statistics Cards - Active Announcements Removed -->
        <div class="summary-row">
            <div class="summary-card">
                <div class="icon-wrapper blue"><i class="fas fa-file-alt"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['total_complaints']); ?></div>
                    <div class="label">Total Complaints</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper purple"><i class="fas fa-users"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="label">Total Users</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper green"><i class="fas fa-user-graduate"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['total_students']); ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper yellow"><i class="fas fa-user-tie"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['total_staff']); ?></div>
                    <div class="label">Total Staff</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="icon-wrapper indigo"><i class="fas fa-chart-line"></i></div>
                <div class="info">
                    <div class="number"><?php echo number_format($stats['today_logs']); ?></div>
                    <div class="label">Today's Logs</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid: Recent Users & Announcements -->
        <div class="dashboard-grid">
            <!-- Recent Users -->
            <div class="content-area">
                <h4><i class="fas fa-clock" style="color:#1a56db;"></i> Recently Added Users</h4>
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recent_users_result) == 0): ?>
                                <tr><td colspan="4"><div class="no-data"><i class="fas fa-users"></i> No users found.</div></td></tr>
                            <?php else: ?>
                                <?php while ($user = mysqli_fetch_assoc($recent_users_result)): 
                                    $role_class = match($user['role']) {
                                        'student' => 'role-student',
                                        'hod' => 'role-hod',
                                        'dean' => 'role-dean',
                                        'accountant' => 'role-accountant',
                                        'director' => 'role-director',
                                        'admin' => 'role-admin',
                                        'it_officer' => 'role-it_officer',
                                        'deputy_rector' => 'role-deputy_rector',
                                        'rector' => 'role-rector',
                                        'president' => 'role-president',
                                        'examination_officer' => 'role-examination_officer',
                                        default => 'role-student'
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:#0a2a5e;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <div style="font-size:0.7rem; color:#8ba0bc;"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td><span class="role-badge <?php echo $role_class; ?>"><?php echo str_replace('_', ' ', ucfirst($user['role'])); ?></span></td>
                                    <td><span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td style="font-size:0.75rem;"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 16px; text-align: center;">
                    <a href="manage_users.php" class="btn-sm" style="background: #6b85a0;">View All Users <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Announcements Sidebar -->
            <div class="announcements-sidebar">
                <h4>
                    <i class="fas fa-bullhorn"></i> Announcements
                    <a href="announcements.php" class="view-all">View All →</a>
                </h4>
                
                <?php if (mysqli_num_rows($announcements_result) == 0): ?>
                    <div class="no-announcements-sidebar">
                        <i class="fas fa-inbox"></i>
                        No announcements at the moment.
                    </div>
                <?php else: ?>
                    <?php while ($ann = mysqli_fetch_assoc($announcements_result)): 
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
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========== LOGOUT MODAL ========== -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-container">
        <i class="fas fa-sign-out-alt"></i>
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout from your account?</p>
        <div class="modal-buttons">
            <button class="modal-btn confirm" id="confirmLogout">Yes, Logout</button>
            <button class="modal-btn cancel" id="cancelLogout">Cancel</button>
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
</script>
</body>
</html>