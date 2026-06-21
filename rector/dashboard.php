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

// ========== AJAX ENDPOINT FOR CATEGORY PERCENTAGES ==========
if (isset($_GET['ajax']) && $_GET['ajax'] == 'category_percent') {
    // Define the 9 categories we want to show
    $categories_list = [
        'Examination case', 
        'Accountant', 
        'Hostel', 
        'Academic',
        'Infrastructure', 
        'Service', 
        'Gender issue', 
        'Students Government', 
        'IT Support'
    ];
    
    // Get total complaints
    $total_sql = "SELECT COUNT(*) as total FROM complaints";
    $total_stmt = mysqli_query($conn, $total_sql);
    $total = $total_stmt ? (int)mysqli_fetch_assoc($total_stmt)['total'] : 0;
    if ($total_stmt) mysqli_free_result($total_stmt);
    
    // Get counts for each category
    $placeholders = implode(',', array_fill(0, count($categories_list), '?'));
    $cat_sql = "SELECT c.name, COUNT(co.id) as cnt
                FROM categories c
                LEFT JOIN complaints co ON co.category_id = c.id
                WHERE c.name IN ($placeholders)
                GROUP BY c.name
                ORDER BY FIELD(c.name, " . $placeholders . ")";
    
    $cat_stmt = mysqli_prepare($conn, $cat_sql);
    if ($cat_stmt) {
        $params = array_merge($categories_list, $categories_list);
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($cat_stmt, $types, ...$params);
        mysqli_stmt_execute($cat_stmt);
        $cat_result = mysqli_stmt_get_result($cat_stmt);
        
        $category_counts = [];
        while ($row = mysqli_fetch_assoc($cat_result)) {
            $category_counts[$row['name']] = (int)$row['cnt'];
        }
        mysqli_stmt_close($cat_stmt);
    }
    
    // Build final arrays
    $categories = [];
    $percentages = [];
    foreach ($categories_list as $cat) {
        $categories[] = $cat;
        $count = isset($category_counts[$cat]) ? $category_counts[$cat] : 0;
        $percent = ($total > 0) ? round(($count / $total) * 100, 1) : 0;
        $percentages[] = $percent;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'categories' => $categories, 
        'percentages' => $percentages, 
        'total' => $total
    ]);
    exit();
}

// Get profile data
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

// Summary stats for cards (complaints assigned to rector)
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
    FROM complaints WHERE assigned_to = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
if ($stats_stmt) {
    mysqli_stmt_bind_param($stats_stmt, "i", $rector_id);
    mysqli_stmt_execute($stats_stmt);
    $stats_result = mysqli_stmt_get_result($stats_stmt);
    $stats = mysqli_fetch_assoc($stats_result);
    mysqli_stmt_close($stats_stmt);
} else {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'escalated' => 0];
}

// Get recent complaints for dashboard (only rector's assigned)
$recent_sql = "SELECT c.id, c.complaint_number, c.title, c.status, c.created_at, u.full_name 
              FROM complaints c 
              JOIN users u ON c.student_id = u.id 
              WHERE c.assigned_to = ? 
              ORDER BY c.created_at DESC LIMIT 5";
$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $rector_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);

// Get latest announcements
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
        mysqli_stmt_bind_param($ann_stmt, "ii", $dept_id, $rector_id);
        mysqli_stmt_execute($ann_stmt);
        $ann_result = mysqli_stmt_get_result($ann_stmt);
        while ($ann = mysqli_fetch_assoc($ann_result)) {
            $announcements[] = $ann;
        }
        mysqli_stmt_close($ann_stmt);
    }
}

// Data for department pie chart
$dept_sql = "SELECT d.name as department_name, COUNT(c.id) as total
             FROM departments d
             LEFT JOIN users u ON u.department_id = d.id
             LEFT JOIN complaints c ON c.student_id = u.id
             WHERE d.id IS NOT NULL
             GROUP BY d.id, d.name
             ORDER BY total DESC";
$dept_result = mysqli_query($conn, $dept_sql);
$dept_labels = [];
$dept_data = [];
$dept_colors = [];
$dept_total_complaints = 0;
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $dept_labels[] = $row['department_name'];
        $dept_data[] = (int)$row['total'];
        $dept_total_complaints += (int)$row['total'];
        $dept_colors[] = 'rgba(' . rand(80, 200) . ',' . rand(80, 200) . ',' . rand(80, 200) . ',0.7)';
    }
    mysqli_free_result($dept_result);
}
if (empty($dept_labels)) {
    $dept_labels = ['No Data'];
    $dept_data = [0];
    $dept_colors = ['#cccccc'];
    $dept_total_complaints = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard - Rector Panel</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        /* ============================================
           UI SANA - RECTOR PANEL
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

        /* ---------- BUTTONS ---------- */
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
        }

        /* ---------- ANNOUNCEMENTS SIDEBAR ---------- */
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

        /* ---------- CHARTS ---------- */
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 10px;
        }
        .chart-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e5edf5;
        }
        .chart-box h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 12px;
            text-align: center;
        }
        .chart-box canvas {
            width: 100% !important;
            max-height: 280px;
        }
        .chart-info {
            text-align: center;
            font-size: 0.75rem;
            color: #8ba0bc;
            margin-top: 8px;
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
            .chart-row { grid-template-columns: 1fr; }
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
                padding: 12px;
                border-radius: 12px;
            }
            .content-area h4 { font-size: 0.85rem; }

            .summary-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin-bottom: 16px;
            }
            .summary-card {
                padding: 12px 10px;
                min-height: 70px;
                gap: 10px;
                border-radius: 12px;
            }
            .summary-card .icon-wrapper {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            .summary-card .info .number {
                font-size: 1.1rem;
            }
            .summary-card .info .label {
                font-size: 0.6rem;
            }

            .complaints-table thead th,
            .complaints-table tbody td {
                padding: 6px 6px;
                font-size: 0.6rem;
            }
            .complaints-table thead th { font-size: 0.55rem; }
            .btn-sm {
                padding: 3px 10px;
                font-size: 0.55rem;
            }

            .announcements-sidebar {
                padding: 12px;
                min-height: auto;
            }
            .announcement-summary .a-title {
                font-size: 0.75rem;
            }
            .announcement-summary .a-sender {
                font-size: 0.6rem;
            }
            .announcement-summary .a-time {
                font-size: 0.55rem;
            }

            .modal-container {
                padding: 20px 16px;
            }
            .modal-container h3 { font-size: 1rem; }
            .modal-btn {
                padding: 8px 16px;
                font-size: 0.75rem;
                min-width: 70px;
            }

            .loading-content {
                padding: 24px 24px;
            }
            .spinner { width: 32px; height: 32px; }
            .loading-text { font-size: 0.8rem; }

            .toast-container {
                top: 8px;
                right: 8px;
                max-width: calc(100% - 16px);
            }
            .toast {
                font-size: 0.75rem;
                padding: 10px 14px;
            }

            .chart-box { padding: 12px; }
            .chart-box h4 { font-size: 0.75rem; }
            .chart-box canvas { max-height: 180px; }
        }

        @media (max-width: 480px) {
            .sidebar { width: 60px !important; }
            .sidebar:not(.collapsed) { width: 60px !important; }
            .sidebar.collapsed { width: 60px !important; }
            
            .brand { font-size: 0.7rem !important; }
            .menu-item {
                padding: 8px 0 !important;
                margin: 3px 0 !important;
            }
            .menu-item i { font-size: 1rem !important; }
            .logout-item .menu-item i { font-size: 1rem !important; }

            .main-content { margin-left: 60px !important; }
            .sidebar.collapsed ~ .main-content {
                margin-left: 60px !important;
            }

            .dashboard-body { padding: 8px; }
            .top-bar { padding: 8px 10px; gap: 6px; }
            .profile-pic { width: 28px; height: 28px; }
            .profile-details .name { font-size: 0.6rem; }
            .profile-details .reg { font-size: 0.45rem; }

            .content-area {
                padding: 8px;
                border-radius: 10px;
            }
            .content-area h4 { font-size: 0.75rem; }

            .summary-row {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                margin-bottom: 12px;
            }
            .summary-card {
                padding: 8px 6px;
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
                font-size: 0.95rem;
            }
            .summary-card .info .label {
                font-size: 0.55rem;
            }

            .complaints-table thead th,
            .complaints-table tbody td {
                padding: 4px 4px;
                font-size: 0.5rem;
            }
            .complaints-table thead th { font-size: 0.5rem; }
            .btn-sm {
                padding: 2px 8px;
                font-size: 0.5rem;
            }

            .announcements-sidebar {
                padding: 8px;
            }
            .announcement-summary .a-title {
                font-size: 0.7rem;
            }
            .announcement-summary .a-sender {
                font-size: 0.55rem;
            }
            .announcement-summary .a-time {
                font-size: 0.5rem;
            }

            .modal-container {
                padding: 16px 14px;
            }
            .modal-container h3 { font-size: 0.9rem; }
            .modal-btn {
                padding: 6px 14px;
                font-size: 0.7rem;
                min-width: 60px;
            }

            .loading-content {
                padding: 20px 20px;
            }
            .spinner { width: 28px; height: 28px; }
            .loading-text { font-size: 0.75rem; }

            .toast-container {
                top: 6px;
                right: 6px;
                max-width: calc(100% - 12px);
            }
            .toast {
                font-size: 0.7rem;
                padding: 8px 12px;
            }
            .toast i { font-size: 1rem; }

            .chart-box { padding: 10px; }
            .chart-box h4 { font-size: 0.7rem; }
            .chart-box canvas { max-height: 160px; }
        }

        @media (max-width: 380px) {
            .sidebar { width: 55px !important; }
            .sidebar:not(.collapsed) { width: 55px !important; }
            .sidebar.collapsed { width: 55px !important; }
            .main-content { margin-left: 55px !important; }
            .sidebar.collapsed ~ .main-content {
                margin-left: 55px !important;
            }
            .menu-item i { font-size: 0.9rem !important; }

            .summary-row {
                grid-template-columns: 1fr 1fr;
                gap: 4px;
            }
            .summary-card {
                padding: 6px 4px;
                min-height: 48px;
                gap: 6px;
            }
            .summary-card .icon-wrapper {
                width: 24px;
                height: 24px;
                font-size: 0.6rem;
            }
            .summary-card .info .number {
                font-size: 0.8rem;
            }
            .summary-card .info .label {
                font-size: 0.5rem;
            }

            .complaints-table thead th,
            .complaints-table tbody td {
                padding: 3px 3px;
                font-size: 0.45rem;
            }
            .complaints-table thead th { font-size: 0.45rem; }
            .btn-sm {
                padding: 2px 6px;
                font-size: 0.45rem;
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
        <a href="dashboard.php" class="menu-item active">
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

        <!-- Charts -->
        <div class="content-area">
            <div class="chart-row">
                <div class="chart-box">
                    <h4>Complaints by Category</h4>
                    <canvas id="categoryPercentChart"></canvas>
                    <div class="chart-info" id="chartInfo"></div>
                </div>
                <div class="chart-box">
                    <h4>Complaints by Department</h4>
                    <canvas id="departmentPieChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Complaints & Announcements -->
        <div style="display: grid; grid-template-columns: 70% 30%; gap: 24px; margin-top: 24px;">
            <div class="content-area">
                <h4><i class="fas fa-clock" style="color: #1a56db;"></i> Recent Complaints</h4>
                <div class="table-responsive">
                    <table class="complaints-table">
                        <thead>
                            <tr><th>Complaint #</th><th>Student</th><th>Title</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recent_result) == 0): ?>
                                <tr><td colspan="5" style="text-align:center; padding:30px; color:#8ba0bc;">No complaints assigned yet.</td></tr>
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
                <?php mysqli_stmt_close($recent_stmt); ?>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="complaints.php" class="btn-sm" style="background: #6b85a0;">View All Complaints <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

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

        <!-- Chart Scripts -->
        <script>
            Chart.register(ChartDataLabels);

            async function loadCategoryData() {
                try {
                    const response = await fetch(`dashboard.php?ajax=category_percent`);
                    const data = await response.json();
                    const ctx = document.getElementById('categoryPercentChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.categories,
                            datasets: [{
                                label: 'Percentage (%)',
                                data: data.percentages,
                                backgroundColor: 'rgba(26, 86, 219, 0.7)',
                                borderColor: 'rgba(26, 86, 219, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    title: { display: true, text: 'Percentage (%)' },
                                    ticks: { callback: function(val) { return val + '%'; } }
                                },
                                x: {
                                    title: { display: true, text: 'Category' },
                                    ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 }
                                }
                            },
                            plugins: {
                                tooltip: { callbacks: { label: (ctx) => `${ctx.raw}%` } },
                                legend: { display: false }
                            }
                        }
                    });
                    document.getElementById('chartInfo').innerHTML = `Total complaints in system: ${data.total}`;
                } catch(e) { console.error(e); }
            }
            loadCategoryData();

            const deptLabelsRaw = <?php echo json_encode($dept_labels); ?>;
            const deptCounts = <?php echo json_encode($dept_data); ?>;
            const deptColors = <?php echo json_encode($dept_colors); ?>;
            const totalDeptComplaints = <?php echo $dept_total_complaints; ?>;

            const ctxPie = document.getElementById('departmentPieChart').getContext('2d');
            new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: deptLabelsRaw,
                    datasets: [{
                        data: deptCounts,
                        backgroundColor: deptColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { boxWidth: 12, font: { size: 10 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = deptLabelsRaw[context.dataIndex] || '';
                                    let count = context.raw;
                                    let percent = totalDeptComplaints > 0 ? (count / totalDeptComplaints * 100).toFixed(1) : 0;
                                    return `${label}: ${count} (${percent}%)`;
                                }
                            }
                        },
                        datalabels: {
                            color: 'white',
                            backgroundColor: 'rgba(0,0,0,0.6)',
                            borderRadius: 12,
                            padding: { left: 5, right: 5, top: 3, bottom: 3 },
                            font: { weight: 'bold', size: 11 },
                            formatter: (value) => {
                                let percent = totalDeptComplaints > 0 ? (value / totalDeptComplaints * 100).toFixed(1) : 0;
                                return `${percent}%`;
                            },
                            anchor: 'center',
                            align: 'center'
                        }
                    }
                }
            });
        </script>
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