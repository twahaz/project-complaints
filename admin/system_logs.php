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

$active_page = 'system_logs';

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

// Pagination variables
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$user_filter = isset($_GET['user_filter']) ? intval($_GET['user_filter']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = "1=1";
if ($search) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (l.description LIKE '%$search_escaped%' OR l.action LIKE '%$search_escaped%' OR l.ip_address LIKE '%$search_escaped%' OR u.full_name LIKE '%$search_escaped%')";
}
if ($action_filter) {
    $where .= " AND l.action = '$action_filter'";
}
if ($user_filter) {
    $where .= " AND l.user_id = $user_filter";
}
if ($date_from) {
    $where .= " AND DATE(l.created_at) >= '$date_from'";
}
if ($date_to) {
    $where .= " AND DATE(l.created_at) <= '$date_to'";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM system_logs l 
              LEFT JOIN users u ON l.user_id = u.id 
              WHERE $where";
$count_result = mysqli_query($conn, $count_sql);
$total_row = mysqli_fetch_assoc($count_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Get logs with filters and pagination
$logs_sql = "SELECT l.*, u.full_name as user_name, u.email as user_email, u.role as user_role
             FROM system_logs l 
             LEFT JOIN users u ON l.user_id = u.id 
             WHERE $where 
             ORDER BY l.created_at DESC 
             LIMIT $limit OFFSET $offset";
$logs_result = mysqli_query($conn, $logs_sql);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_logs,
    COUNT(DISTINCT DATE(created_at)) as active_days,
    SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as logins,
    SUM(CASE WHEN action = 'logout' THEN 1 ELSE 0 END) as logouts,
    SUM(CASE WHEN action = 'create' THEN 1 ELSE 0 END) as creates,
    SUM(CASE WHEN action = 'update' THEN 1 ELSE 0 END) as updates,
    SUM(CASE WHEN action = 'delete' THEN 1 ELSE 0 END) as deletes
    FROM system_logs";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get weekly activity
$weekly_sql = "SELECT 
    DATE(created_at) as log_date,
    COUNT(*) as count
    FROM system_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY log_date DESC";
$weekly_result = mysqli_query($conn, $weekly_sql);
$weekly_data = [];
while ($row = mysqli_fetch_assoc($weekly_result)) {
    $weekly_data[] = $row;
}

// Get users for filter
$users_sql = "SELECT id, full_name, email FROM users WHERE role != 'student' ORDER BY full_name";
$users_result = mysqli_query($conn, $users_sql);
$users_list = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $users_list[] = $user;
}

// Get action types for filter
$action_types = ['login', 'logout', 'create', 'update', 'delete'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>System Logs - Admin Panel</title>
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
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 18px 16px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(10,42,94,0.10);
        }
        .summary-card .icon {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }
        .summary-card .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0a2a5e;
            line-height: 1.2;
        }
        .summary-card .label {
            color: #6b85a0;
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 2px;
        }
        .summary-card .icon.blue { color: #1a56db; }
        .summary-card .icon.green { color: #10b981; }
        .summary-card .icon.yellow { color: #f59e0b; }
        .summary-card .icon.purple { color: #6d28d9; }
        .summary-card .icon.red { color: #dc2626; }
        .summary-card .icon.indigo { color: #4338ca; }
        .summary-card .icon.orange { color: #ea580c; }

        /* ========== FILTERS BAR ========== */
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .filter-item {
            flex: 1;
            min-width: 140px;
        }
        .filter-item label {
            display: none;
        }
        .filter-item input, .filter-item select {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid #e5edf5;
            border-radius: 10px;
            font-size: 0.85rem;
            background: #fafcff;
            transition: border 0.2s;
            font-family: 'Inter', sans-serif;
            color: #1f2c40;
        }
        .filter-item input:focus, .filter-item select:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.08);
        }
        .filter-item input::placeholder {
            color: #8ba0bc;
        }
        .filter-item .filter-btn {
            background: #1a56db;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
        }
        .filter-item .filter-btn:hover {
            background: #0d3b8a;
        }
        .filter-item .filter-btn.clear-btn {
            background: #6b85a0;
        }
        .filter-item .filter-btn.clear-btn:hover {
            background: #4a5a7a;
        }

        /* ========== WEEKLY ACTIVITY ========== */
        .weekly-activity {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
        }
        .weekly-activity h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .weekly-activity h4 i {
            color: #1a56db;
        }
        .activity-bars {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        .activity-item {
            text-align: center;
            flex: 1;
            min-width: 40px;
        }
        .bar {
            background: #1a56db;
            height: 0;
            min-height: 4px;
            border-radius: 8px;
            transition: height 0.3s;
            margin-bottom: 6px;
            width: 30px;
            margin-left: auto;
            margin-right: auto;
        }
        .bar-label {
            font-size: 0.65rem;
            color: #6b85a0;
            font-weight: 500;
        }
        .bar-count {
            font-size: 0.6rem;
            color: #1a56db;
            font-weight: 600;
        }

        /* ========== BUTTONS ========== */
        .btn-sm {
            padding: 5px 14px;
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

        /* ========== BADGE ========== */
        .badge-action {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-login { background: #dbeafe; color: #1e40af; }
        .badge-logout { background: #fef3c7; color: #b45309; }
        .badge-create { background: #d1fae5; color: #065f46; }
        .badge-update { background: #e0e7ff; color: #4338ca; }
        .badge-delete { background: #fee2e2; color: #991b1b; }

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
        .role-staff { background: #e2e8f0; color: #475569; }
        .role-system { background: #e2e8f0; color: #475569; }

        /* ---------- TABLE ---------- */
        .table-responsive { 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .logs-table thead th {
            background: #f8fafc;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: #4a5a7a;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e5edf5;
        }
        .logs-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f4f9;
            color: #1f2c40;
            vertical-align: middle;
        }
        .logs-table tbody tr:hover {
            background: #fafcff;
        }
        .logs-table tbody tr:last-child td {
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

        /* ---------- PAGINATION ---------- */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 14px;
            border-radius: 8px;
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
        .pagination .disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .pagination-info {
            text-align: center;
            margin-top: 12px;
            font-size: 0.8rem;
            color: #6b85a0;
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
            .summary-row { grid-template-columns: repeat(3, 1fr); }
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
                padding: 12px 10px;
            }
            .summary-card .number {
                font-size: 1.2rem;
            }
            .summary-card .label {
                font-size: 0.6rem;
            }

            .filters-bar {
                padding: 12px 14px;
            }
            .filters-row {
                gap: 8px;
            }
            .filter-item {
                min-width: 100%;
                flex: 1 1 100%;
            }

            .weekly-activity {
                padding: 16px;
            }
            .activity-bars {
                gap: 8px;
            }
            .bar {
                width: 24px;
            }

            .content-area {
                padding: 16px;
                border-radius: 12px;
            }
            .content-area h4 {
                font-size: 0.85rem;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 8px 10px;
                font-size: 0.7rem;
            }
            .badge-action {
                font-size: 0.55rem;
                padding: 2px 8px;
            }
            .role-badge {
                font-size: 0.55rem;
                padding: 2px 8px;
            }

            .pagination a, .pagination span {
                padding: 4px 10px;
                font-size: 0.75rem;
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
                padding: 10px 8px;
            }
            .summary-card .number {
                font-size: 1rem;
            }
            .summary-card .label {
                font-size: 0.55rem;
            }
            .summary-card .icon {
                font-size: 1.2rem;
            }

            .filters-bar {
                padding: 10px 12px;
            }
            .filter-item {
                min-width: 100%;
            }
            .filter-item input, .filter-item select {
                padding: 6px 10px;
                font-size: 0.75rem;
            }
            .filter-item .filter-btn {
                font-size: 0.75rem;
                padding: 6px 12px;
            }

            .weekly-activity {
                padding: 12px;
            }
            .bar {
                width: 20px;
            }

            .content-area {
                padding: 12px;
                border-radius: 10px;
            }
            .content-area h4 {
                font-size: 0.75rem;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 6px 6px;
                font-size: 0.6rem;
            }

            .pagination a, .pagination span {
                padding: 3px 8px;
                font-size: 0.65rem;
            }
            .pagination-info {
                font-size: 0.7rem;
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

            .summary-row {
                grid-template-columns: 1fr 1fr;
                gap: 4px;
            }
            .summary-card {
                padding: 8px 6px;
            }
            .summary-card .number {
                font-size: 0.9rem;
            }
            .summary-card .label {
                font-size: 0.5rem;
            }
            .summary-card .icon {
                font-size: 1rem;
            }

            .logs-table thead th,
            .logs-table tbody td {
                padding: 4px 4px;
                font-size: 0.5rem;
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
        <a href="add_user.php" class="menu-item">
            <i class="fas fa-user-plus"></i><span>Add User</span>
        </a>
        <a href="all_complaints.php" class="menu-item">
            <i class="fas fa-file-alt"></i><span>All Complaints</span>
        </a>
        <a href="announcements.php" class="menu-item">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        <a href="system_logs.php" class="menu-item active">
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
                <div class="icon blue"><i class="fas fa-chart-line"></i></div>
                <div class="number"><?php echo number_format($stats['total_logs']); ?></div>
                <div class="label">Total Logs</div>
            </div>
            <div class="summary-card">
                <div class="icon green"><i class="fas fa-calendar"></i></div>
                <div class="number"><?php echo $stats['active_days']; ?></div>
                <div class="label">Active Days</div>
            </div>
            <div class="summary-card">
                <div class="icon yellow"><i class="fas fa-sign-in-alt"></i></div>
                <div class="number"><?php echo number_format($stats['logins']); ?></div>
                <div class="label">Logins</div>
            </div>
            <div class="summary-card">
                <div class="icon purple"><i class="fas fa-sign-out-alt"></i></div>
                <div class="number"><?php echo number_format($stats['logouts']); ?></div>
                <div class="label">Logouts</div>
            </div>
            <div class="summary-card">
                <div class="icon indigo"><i class="fas fa-plus-circle"></i></div>
                <div class="number"><?php echo number_format($stats['creates']); ?></div>
                <div class="label">Created</div>
            </div>
            <div class="summary-card">
                <div class="icon orange"><i class="fas fa-edit"></i></div>
                <div class="number"><?php echo number_format($stats['updates']); ?></div>
                <div class="label">Updated</div>
            </div>
            <div class="summary-card">
                <div class="icon red"><i class="fas fa-trash"></i></div>
                <div class="number"><?php echo number_format($stats['deletes']); ?></div>
                <div class="label">Deleted</div>
            </div>
        </div>

        <!-- Weekly Activity Chart -->
        <div class="weekly-activity">
            <h4><i class="fas fa-chart-bar"></i> Weekly Activity (Last 7 Days)</h4>
            <div class="activity-bars">
                <?php 
                $max_count = !empty($weekly_data) ? max(array_column($weekly_data, 'count')) : 1;
                $day_names = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $day_names[$date] = date('D', strtotime($date));
                }
                for ($i = 6; $i >= 0; $i--):
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $count = 0;
                    foreach ($weekly_data as $wd) {
                        if ($wd['log_date'] == $date) {
                            $count = $wd['count'];
                            break;
                        }
                    }
                    $height = $max_count > 0 ? ($count / $max_count) * 60 : 0;
                ?>
                <div class="activity-item">
                    <div class="bar" style="height: <?php echo max(4, $height); ?>px; background: <?php echo $count > 0 ? '#1a56db' : '#e5edf5'; ?>;"></div>
                    <div class="bar-label"><?php echo $day_names[$date]; ?></div>
                    <div class="bar-count"><?php echo $count; ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Filters Bar - Clean design without labels -->
        <div class="filters-bar">
            <form method="GET" class="filters-row">
                <input type="hidden" name="page" value="1">
                
                <div class="filter-item">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search action, description, IP, user...">
                </div>
                <div class="filter-item">
                    <select name="action_filter">
                        <option value="">All Actions</option>
                        <?php foreach ($action_types as $action): ?>
                            <option value="<?php echo $action; ?>" <?php echo $action_filter == $action ? 'selected' : ''; ?>>
                                <?php echo ucfirst($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <select name="user_filter">
                        <option value="0">All Users</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From">
                </div>
                <div class="filter-item">
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To">
                </div>
                <div class="filter-item" style="flex: 0 0 auto; min-width: auto;">
                    <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Filter</button>
                </div>
                <div class="filter-item" style="flex: 0 0 auto; min-width: auto;">
                    <a href="system_logs.php" class="filter-btn clear-btn" style="display:block; text-align:center; text-decoration:none; padding:8px 20px; border-radius:10px; color:white; font-weight:500; font-size:0.85rem; background:#6b85a0;"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="content-area">
            <h4><i class="fas fa-history"></i> System Logs</h4>
            <div class="table-responsive">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($logs_result) == 0): ?>
                            <tr><td colspan="6"><div class="no-data"><i class="fas fa-inbox"></i> No logs found.</div></td></tr>
                        <?php else: ?>
                            <?php while ($log = mysqli_fetch_assoc($logs_result)):
                                $action_class = match($log['action']) {
                                    'login' => 'badge-login',
                                    'logout' => 'badge-logout',
                                    'create' => 'badge-create',
                                    'update' => 'badge-update',
                                    'delete' => 'badge-delete',
                                    default => ''
                                };
                                $role_class = match($log['user_role']) {
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
                                    default => 'role-staff'
                                };
                            ?>
                            <tr>
                                <td style="font-size:0.75rem;"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <div style="font-weight:500; color:#0a2a5e;"><?php echo htmlspecialchars($log['user_name'] ?: 'System'); ?></div>
                                    <div style="font-size:0.65rem; color:#8ba0bc;"><?php echo $log['user_email'] ?: 'N/A'; ?></div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $role_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $log['user_role'] ?: 'System')); ?>
                                    </span>
                                </td>
                                <td><span class="badge-action <?php echo $action_class; ?>"><?php echo ucfirst($log['action']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td style="font-size:0.75rem; font-family:monospace;"><?php echo htmlspecialchars($log['ip_address']); ?></td>
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
                    <a href="?page=1&search=<?php echo urlencode($search); ?>&action_filter=<?php echo $action_filter; ?>&user_filter=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo $action_filter; ?>&user_filter=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                    <span class="disabled"><i class="fas fa-angle-left"></i></span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<span>...</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo $action_filter; ?>&user_filter=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <span>...</span>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo $action_filter; ?>&user_filter=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo $action_filter; ?>&user_filter=<?php echo $user_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-right"></i></span>
                    <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                <?php endif; ?>
            </div>
            <div class="pagination-info">
                Showing <?php echo (($page - 1) * $limit) + 1; ?> to <?php echo min($page * $limit, $total_records); ?> of <?php echo $total_records; ?> logs
            </div>
            <?php endif; ?>
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