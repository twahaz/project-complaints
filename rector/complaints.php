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

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ========== FILTERS & PAGINATION FOR LIST VIEW ==========
$sub_page = isset($_GET['sub']) ? $_GET['sub'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$current_list_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = 20;
$offset = ($current_list_page - 1) * $limit;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>All Complaints - Rector Panel</title>
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

        .tab-btn {
            background: none;
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: 0.2s;
            color: #6b85a0;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .tab-btn.active {
            background: #1a56db;
            color: white;
        }
        .tab-btn:hover {
            background: #f0f4f9;
            color: #0a2a5e;
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
        .badge-rejected { background: #fecaca; color: #991b1b; }

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

        /* ---------- COMPLAINT DETAIL - IMPROVED FOR MOBILE ---------- */
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
            word-break: break-word;
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
            word-break: break-word;
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
            word-break: break-word;
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
            word-break: break-word;
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

        /* ---------- READ-ONLY NOTICE ---------- */
        .readonly-notice {
            background: #f0f4f9;
            padding: 16px 20px;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
            color: #6b85a0;
            border: 1px dashed #cbd5e1;
        }
        .readonly-notice i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: #1a56db;
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
            font-size: 1.1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .complaints-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            border-bottom: 1px solid #e5edf5;
            padding-bottom: 12px;
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
           RESPONSIVE - IMPROVED FOR MOBILE
           ============================================ */

        @media (max-width: 1024px) {
            .dashboard-body { padding: 20px 24px; }
            .top-bar { padding: 14px 24px; }
            .content-area { padding: 20px 24px; min-height: auto; }
        }

        @media (max-width: 768px) {
            /* Sidebar */
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
                padding: 12px;
                border-radius: 12px;
            }
            .content-area h4 { font-size: 0.85rem; }

            /* Complaint Detail - Mobile Improvements */
            .complaint-detail-grid {
                grid-template-columns: 1fr !important;
                padding: 12px !important;
                gap: 10px !important;
                border-radius: 12px;
            }
            .complaint-detail-grid .detail-item .label {
                font-size: 0.6rem !important;
            }
            .complaint-detail-grid .detail-item .value {
                font-size: 0.82rem !important;
                word-break: break-word;
            }

            .description-box {
                padding: 12px !important;
                border-radius: 12px;
            }
            .description-box .value {
                font-size: 0.85rem !important;
                line-height: 1.6;
            }

            .conversation {
                padding: 10px 12px !important;
                max-height: 300px;
                border-radius: 12px;
            }
            .message {
                padding: 10px 14px !important;
                margin-bottom: 12px;
                border-radius: 10px;
            }
            .message-header {
                font-size: 0.7rem !important;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .message-body {
                font-size: 0.82rem !important;
                line-height: 1.5;
            }

            .sla-card {
                padding: 14px 16px !important;
                border-radius: 12px;
            }
            .sla-stats {
                grid-template-columns: 1fr 1fr !important;
                gap: 10px;
            }
            .sla-item .sla-label {
                font-size: 0.6rem !important;
            }
            .sla-item .sla-value {
                font-size: 0.85rem !important;
                word-break: break-word;
            }
            .deadline-timer {
                font-size: 0.85rem !important;
                padding: 3px 10px;
            }

            .readonly-notice {
                padding: 12px 16px !important;
                font-size: 0.82rem !important;
            }
            .readonly-notice i {
                font-size: 1.2rem !important;
            }

            .filters-bar {
                flex-direction: column;
                padding: 10px 12px;
                gap: 10px;
                border-radius: 12px;
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
                font-size: 0.75rem;
                padding: 6px 14px;
            }

            .complaints-table thead th,
            .complaints-table tbody td {
                padding: 6px 6px;
                font-size: 0.6rem;
            }
            .complaints-table thead th {
                font-size: 0.55rem;
            }
            .btn-sm {
                padding: 3px 10px;
                font-size: 0.55rem;
            }

            .btn-back {
                padding: 6px 14px;
                font-size: 0.75rem;
            }
            .btn-back span {
                display: none;
            }

            .tab-btn {
                padding: 4px 12px;
                font-size: 0.7rem;
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

            .pagination a, .pagination span {
                min-width: 32px;
                height: 32px;
                font-size: 0.7rem;
                padding: 0 8px;
            }

            .complaints-tabs {
                gap: 4px;
                padding-bottom: 8px;
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
                padding: 8px !important;
                border-radius: 10px;
            }
            .content-area h4 { font-size: 0.75rem; }

            /* Complaint Detail - Small Screen */
            .complaint-detail-grid {
                padding: 10px !important;
                gap: 8px !important;
                border-radius: 10px;
            }
            .complaint-detail-grid .detail-item .label {
                font-size: 0.55rem !important;
            }
            .complaint-detail-grid .detail-item .value {
                font-size: 0.75rem !important;
            }

            .description-box {
                padding: 10px !important;
            }
            .description-box .value {
                font-size: 0.78rem !important;
            }

            .conversation {
                padding: 8px 10px !important;
                max-height: 250px;
                border-radius: 10px;
            }
            .message {
                padding: 8px 12px !important;
                margin-bottom: 10px;
                border-radius: 8px;
            }
            .message-header {
                font-size: 0.6rem !important;
            }
            .message-body {
                font-size: 0.75rem !important;
            }

            .sla-card {
                padding: 10px 12px !important;
                border-radius: 10px;
            }
            .sla-stats {
                grid-template-columns: 1fr !important;
                gap: 6px;
            }
            .sla-item .sla-label {
                font-size: 0.55rem !important;
            }
            .sla-item .sla-value {
                font-size: 0.8rem !important;
            }
            .deadline-timer {
                font-size: 0.75rem !important;
                padding: 2px 8px;
            }

            .readonly-notice {
                padding: 10px 12px !important;
                font-size: 0.75rem !important;
            }
            .readonly-notice i {
                font-size: 1rem !important;
            }

            .complaints-table thead th,
            .complaints-table tbody td {
                padding: 4px 4px;
                font-size: 0.5rem;
            }
            .complaints-table thead th {
                font-size: 0.5rem;
            }
            .btn-sm {
                padding: 2px 8px;
                font-size: 0.5rem;
            }

            .btn-back {
                padding: 4px 10px;
                font-size: 0.65rem;
            }
            .btn-back span {
                display: none;
            }

            .tab-btn {
                padding: 3px 10px;
                font-size: 0.6rem;
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
                font-size: 0.65rem;
                padding: 4px 12px;
            }

            .pagination a, .pagination span {
                min-width: 28px;
                height: 28px;
                font-size: 0.6rem;
                padding: 0 6px;
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

            .complaints-tabs {
                gap: 3px;
                padding-bottom: 6px;
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
            .menu-item i {
                font-size: 0.9rem !important;
            }

            .complaints-table thead th,
            .complaints-table tbody td {
                padding: 3px 3px;
                font-size: 0.45rem;
            }
            .complaints-table thead th {
                font-size: 0.45rem;
            }
            .btn-sm {
                padding: 2px 6px;
                font-size: 0.45rem;
            }

            .complaint-detail-grid .detail-item .value {
                font-size: 0.7rem !important;
            }
            .description-box .value {
                font-size: 0.72rem !important;
            }
            .message-body {
                font-size: 0.7rem !important;
            }
            .sla-item .sla-value {
                font-size: 0.75rem !important;
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
        <a href="complaints.php" class="menu-item active">
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
        <a href="announcements.php" class="menu-item">
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

        <?php if ($action === 'view' && $complaint_id): ?>
            <!-- ========== VIEW COMPLAINT DETAIL (READ-ONLY) ========== -->
            <?php
            $detail_sql = "SELECT c.*, u.full_name as student_name, u.reg_number 
                          FROM complaints c 
                          JOIN users u ON c.student_id = u.id 
                          WHERE c.id = ?";
            $detail_stmt = mysqli_prepare($conn, $detail_sql);
            mysqli_stmt_bind_param($detail_stmt, "i", $complaint_id);
            mysqli_stmt_execute($detail_stmt);
            $detail_result = mysqli_stmt_get_result($detail_stmt);
            $complaint = mysqli_fetch_assoc($detail_result);
            mysqli_stmt_close($detail_stmt);
            
            if (!$complaint) { 
                echo '<div class="content-area"><p style="color:#991b1b;">Complaint not found.</p></div>';
            } else {
                $is_resolved = ($complaint['status'] == 'resolved');
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
                $remainingSeconds = $deadlineTimestamp - $now->getTimestamp();
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
                    <!-- Back Button -->
                    <div style="margin-bottom: 16px;">
                        <a href="complaints.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Complaints</span>
                        </a>
                    </div>

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
                    
                    <h4 style="color:#0a2a5e; margin-bottom:16px; font-size:1rem;">
                        <i class="fas fa-file-alt" style="color:#1a56db;"></i> 
                        Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?>
                    </h4>
                    
                    <div class="complaint-detail-grid">
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-user"></i> Student</span>
                            <span class="value"><?php echo htmlspecialchars($complaint['student_name']); ?> (<?php echo $complaint['reg_number']; ?>)</span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-tag"></i> Title</span>
                            <span class="value"><?php echo htmlspecialchars($complaint['title']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-user-tie"></i> Assigned To</span>
                            <span class="value">
                                <?php 
                                $staff_sql = "SELECT full_name, role FROM users WHERE id = ?";
                                $staff_stmt = mysqli_prepare($conn, $staff_sql);
                                mysqli_stmt_bind_param($staff_stmt, "i", $complaint['assigned_to']);
                                mysqli_stmt_execute($staff_stmt);
                                $staff_result = mysqli_stmt_get_result($staff_stmt);
                                $staff_data = mysqli_fetch_assoc($staff_result);
                                if ($staff_data) {
                                    echo htmlspecialchars($staff_data['full_name']) . ' (' . ucfirst(str_replace('_', ' ', $staff_data['role'])) . ')';
                                } else {
                                    echo 'Not assigned';
                                }
                                mysqli_stmt_close($staff_stmt);
                                ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-map-marker-alt"></i> Location</span>
                            <span class="value"><?php echo htmlspecialchars($complaint['location'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-calendar-day"></i> Incident Date</span>
                            <span class="value"><?php echo date('d/m/Y', strtotime($complaint['incident_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-flag"></i> Priority</span>
                            <span class="value"><?php echo ucfirst($complaint['priority']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><i class="fas fa-circle"></i> Status</span>
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
                            <span class="label"><i class="fas fa-paperclip"></i> Evidence</span>
                            <span class="value"><a href="../<?php echo $complaint['attachment_path']; ?>" target="_blank" class="btn-sm" style="background:#10b981; font-size:0.7rem;">Download</a></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="description-box">
                        <div class="label"><i class="fas fa-align-left"></i> Description</div>
                        <div class="value"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                    </div>
                    
                    <h5 style="font-size:0.95rem; font-weight:700; color:#0a2a5e; margin-bottom:12px;">
                        <i class="fas fa-comments" style="color:#1a56db;"></i> Conversation
                    </h5>
                    <div class="conversation">
                        <?php 
                        $has_messages = false;
                        while ($resp = mysqli_fetch_assoc($resp_result)): 
                            $has_messages = true;
                        ?>
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
                        <?php if (!$has_messages): ?>
                            <p style="color:#8ba0bc; text-align:center; padding:20px 0;">No messages yet.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- READ-ONLY NOTICE - Rector cannot reply or change status -->
                    <div class="readonly-notice">
                        <i class="fas fa-eye"></i>
                        <strong>View-Only Mode</strong>
                        <p style="margin-top:4px; font-size:0.85rem;">As Rector, you can view all complaints but cannot reply or change status. This is for oversight purposes only.</p>
                    </div>
                </div>
            <?php } ?>

        <?php else: ?>
            <!-- ========== ALL COMPLAINTS LIST VIEW ========== -->
            <div class="content-area">
                <h4><i class="fas fa-file-alt" style="color:#1a56db;"></i> All System Complaints</h4>
                <p style="color:#6b85a0; margin-bottom:16px; font-size:0.85rem;">As Rector, you have visibility to all complaints in the system for oversight purposes.</p>

                <div class="complaints-tabs">
                    <a href="complaints.php?sub=all" class="tab-btn <?php echo $sub_page == 'all' ? 'active' : ''; ?>">All</a>
                    <a href="complaints.php?sub=pending" class="tab-btn <?php echo $sub_page == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="complaints.php?sub=in_progress" class="tab-btn <?php echo $sub_page == 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                    <a href="complaints.php?sub=escalated" class="tab-btn <?php echo $sub_page == 'escalated' ? 'active' : ''; ?>">Escalated</a>
                    <a href="complaints.php?sub=resolved" class="tab-btn <?php echo $sub_page == 'resolved' ? 'active' : ''; ?>">Resolved</a>
                </div>

                <?php
                // Build query with filters - ALL complaints in system
                $where = "1=1";
                if ($sub_page == 'pending') $where .= " AND status = 'pending'";
                elseif ($sub_page == 'in_progress') $where .= " AND status = 'in_progress'";
                elseif ($sub_page == 'escalated') $where .= " AND status = 'escalated'";
                elseif ($sub_page == 'resolved') $where .= " AND status = 'resolved'";
                
                if ($priority_filter) $where .= " AND priority = '" . mysqli_real_escape_string($conn, $priority_filter) . "'";
                if ($search) {
                    $search_escaped = mysqli_real_escape_string($conn, $search);
                    $where .= " AND (complaint_number LIKE '%$search_escaped%' OR student_id IN (SELECT id FROM users WHERE full_name LIKE '%$search_escaped%'))";
                }
                
                $order_by = match($sort) {
                    'oldest' => 'c.created_at ASC',
                    'priority_high' => "FIELD(c.priority, 'high','medium','low')",
                    'priority_low' => "FIELD(c.priority, 'low','medium','high')",
                    default => 'c.created_at DESC',
                };
                
                // Get total count
                $count_sql = "SELECT COUNT(*) as total FROM complaints c WHERE $where";
                $count_result = mysqli_query($conn, $count_sql);
                $total_rows = mysqli_fetch_assoc($count_result)['total'];
                $total_pages = ceil($total_rows / $limit);
                
                // Get data
                $data_sql = "SELECT c.id, c.complaint_number, c.title, c.status, c.created_at, c.updated_at, 
                                    u.full_name as student_name, 
                                    staff.full_name as assigned_staff_name,
                                    staff.role as assigned_staff_role,
                                    c.priority
                            FROM complaints c
                            JOIN users u ON c.student_id = u.id
                            LEFT JOIN users staff ON c.assigned_to = staff.id
                            WHERE $where
                            ORDER BY $order_by
                            LIMIT $limit OFFSET $offset";
                $data_result = mysqli_query($conn, $data_sql);
                ?>

                <form method="GET" class="filters-bar">
                    <input type="hidden" name="sub" value="<?php echo $sub_page; ?>">
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

                <div class="table-responsive">
                    <table class="complaints-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Title</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($data_result) == 0): ?>
                                <tr><td colspan="8" style="text-align:center; padding:30px; color:#8ba0bc;">No complaints found.</td></tr>
                            <?php else: while ($row = mysqli_fetch_assoc($data_result)):
                                $status_class = match($row['status']) {
                                    'pending' => 'badge-pending',
                                    'in_progress' => 'badge-in-progress',
                                    'resolved' => 'badge-resolved',
                                    'escalated' => 'badge-escalated',
                                    default => ''
                                };
                                $assigned_display = $row['assigned_staff_name'] 
                                    ? htmlspecialchars($row['assigned_staff_name']) . ' (' . ucfirst(str_replace('_', ' ', $row['assigned_staff_role'] ?? '')) . ')' 
                                    : 'Not assigned';
                            ?>
                            <tr>
                                <td><strong><?php echo $row['complaint_number']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo ucfirst($row['priority']); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                <td style="font-size:0.7rem;"><?php echo $assigned_display; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                <td><a href="complaints.php?action=view&id=<?php echo $row['id']; ?>" class="btn-sm">View</a></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_list_page > 1): ?>
                        <a href="complaints.php?sub=<?php echo $sub_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $current_list_page-1; ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_list_page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="complaints.php?sub=<?php echo $sub_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($current_list_page < $total_pages): ?>
                        <a href="complaints.php?sub=<?php echo $sub_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $current_list_page+1; ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
</script>

</body>
</html>