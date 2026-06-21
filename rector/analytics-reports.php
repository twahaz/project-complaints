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

// Handle AJAX requests for analytics data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'analytics') {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Monthly trends
    $monthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $start = "$year-$m-01";
        $end = date('Y-m-t', strtotime($start)) . ' 23:59:59';
        $sql = "SELECT COUNT(*) as cnt FROM complaints WHERE created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $start, $end);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $monthly[] = (int)$row['cnt'];
        mysqli_stmt_close($stmt);
    }
    
    // ========== CATEGORY BREAKDOWN - ONLY SPECIFIC CATEGORIES ==========
    $allowed_categories = [
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
    
    $placeholders = implode(',', array_fill(0, count($allowed_categories), '?'));
    $cat_sql = "SELECT c.name, COUNT(co.id) as cnt
                FROM categories c
                LEFT JOIN complaints co ON co.category_id = c.id AND YEAR(co.created_at) = ?
                WHERE c.name IN ($placeholders)
                GROUP BY c.name
                ORDER BY FIELD(c.name, " . $placeholders . ")";
    
    $cat_stmt = mysqli_prepare($conn, $cat_sql);
    if ($cat_stmt) {
        $params = array_merge([$year], $allowed_categories, $allowed_categories);
        $types = 'i' . str_repeat('s', count($allowed_categories) * 2);
        mysqli_stmt_bind_param($cat_stmt, $types, ...$params);
        mysqli_stmt_execute($cat_stmt);
        $cat_res = mysqli_stmt_get_result($cat_stmt);
        
        $categories = [];
        $cat_counts = [];
        while ($row = mysqli_fetch_assoc($cat_res)) {
            $categories[] = $row['name'];
            $cat_counts[] = (int)$row['cnt'];
        }
        mysqli_stmt_close($cat_stmt);
    }
    
    // Department breakdown
    $dept_sql = "SELECT d.name, COUNT(c.id) as cnt
                 FROM departments d
                 LEFT JOIN users u ON u.department_id = d.id
                 LEFT JOIN complaints c ON c.student_id = u.id AND YEAR(c.created_at) = ?
                 GROUP BY d.id
                 ORDER BY d.name";
    $dept_stmt = mysqli_prepare($conn, $dept_sql);
    mysqli_stmt_bind_param($dept_stmt, "i", $year);
    mysqli_stmt_execute($dept_stmt);
    $dept_res = mysqli_stmt_get_result($dept_stmt);
    $depts = [];
    $dept_counts = [];
    while ($row = mysqli_fetch_assoc($dept_res)) {
        $depts[] = $row['name'];
        $dept_counts[] = (int)$row['cnt'];
    }
    mysqli_stmt_close($dept_stmt);
    
    // Priority breakdown
    $priority_sql = "SELECT priority, COUNT(*) as cnt FROM complaints WHERE YEAR(created_at) = ? GROUP BY priority";
    $priority_stmt = mysqli_prepare($conn, $priority_sql);
    mysqli_stmt_bind_param($priority_stmt, "i", $year);
    mysqli_stmt_execute($priority_stmt);
    $priority_res = mysqli_stmt_get_result($priority_stmt);
    $priorities = [];
    $priority_counts = [];
    while ($row = mysqli_fetch_assoc($priority_res)) {
        $priorities[] = ucfirst($row['priority']);
        $priority_counts[] = (int)$row['cnt'];
    }
    mysqli_stmt_close($priority_stmt);
    
    // Status breakdown
    $status_sql = "SELECT status, COUNT(*) as cnt FROM complaints WHERE YEAR(created_at) = ? GROUP BY status";
    $status_stmt = mysqli_prepare($conn, $status_sql);
    mysqli_stmt_bind_param($status_stmt, "i", $year);
    mysqli_stmt_execute($status_stmt);
    $status_res = mysqli_stmt_get_result($status_stmt);
    $statuses = [];
    $status_counts = [];
    while ($row = mysqli_fetch_assoc($status_res)) {
        $statuses[] = ucfirst($row['status']);
        $status_counts[] = (int)$row['cnt'];
    }
    mysqli_stmt_close($status_stmt);
    
    // Staff Performance Data
    $staff_roles = [
        'hod' => 'HOD',
        'dean' => 'Dean of Students',
        'examination_officer' => 'Examination',
        'president' => 'IAASO',
        'deputy_rector' => 'Deputy Rector',
        'rector' => 'Rector'
    ];
    
    $staff_labels = [];
    $staff_total = [];
    $staff_resolved = [];
    $staff_pending = [];
    $staff_avg_response = [];
    $staff_rating = [];
    
    foreach ($staff_roles as $role => $display) {
        $total_sql = "SELECT COUNT(*) as cnt FROM complaints c JOIN users u ON c.assigned_to = u.id WHERE u.role = ? AND YEAR(c.created_at) = ?";
        $total_stmt = mysqli_prepare($conn, $total_sql);
        mysqli_stmt_bind_param($total_stmt, "si", $role, $year);
        mysqli_stmt_execute($total_stmt);
        $total_res = mysqli_stmt_get_result($total_stmt);
        $total_row = mysqli_fetch_assoc($total_res);
        $total = (int)$total_row['cnt'];
        mysqli_stmt_close($total_stmt);
        
        $resolved_sql = "SELECT COUNT(*) as cnt FROM complaints c JOIN users u ON c.assigned_to = u.id WHERE u.role = ? AND YEAR(c.created_at) = ? AND c.status = 'resolved'";
        $resolved_stmt = mysqli_prepare($conn, $resolved_sql);
        mysqli_stmt_bind_param($resolved_stmt, "si", $role, $year);
        mysqli_stmt_execute($resolved_stmt);
        $resolved_res = mysqli_stmt_get_result($resolved_stmt);
        $resolved_row = mysqli_fetch_assoc($resolved_res);
        $resolved = (int)$resolved_row['cnt'];
        mysqli_stmt_close($resolved_stmt);
        
        $pending_sql = "SELECT COUNT(*) as cnt FROM complaints c JOIN users u ON c.assigned_to = u.id WHERE u.role = ? AND YEAR(c.created_at) = ? AND c.status = 'pending'";
        $pending_stmt = mysqli_prepare($conn, $pending_sql);
        mysqli_stmt_bind_param($pending_stmt, "si", $role, $year);
        mysqli_stmt_execute($pending_stmt);
        $pending_res = mysqli_stmt_get_result($pending_stmt);
        $pending_row = mysqli_fetch_assoc($pending_res);
        $pending = (int)$pending_row['cnt'];
        mysqli_stmt_close($pending_stmt);
        
        $avg_sql = "SELECT AVG(TIMESTAMPDIFF(DAY, c.created_at, r.created_at)) as avg_days
                    FROM complaints c
                    JOIN responses r ON r.complaint_id = c.id
                    JOIN users u ON c.assigned_to = u.id
                    WHERE u.role = ? AND YEAR(c.created_at) = ?
                    AND r.user_id = c.assigned_to
                    AND r.id = (SELECT MIN(id) FROM responses WHERE complaint_id = c.id)";
        $avg_stmt = mysqli_prepare($conn, $avg_sql);
        mysqli_stmt_bind_param($avg_stmt, "si", $role, $year);
        mysqli_stmt_execute($avg_stmt);
        $avg_res = mysqli_stmt_get_result($avg_stmt);
        $avg_row = mysqli_fetch_assoc($avg_res);
        $avg_days = $avg_row['avg_days'] ?? null;
        $avg_response = $avg_days !== null ? round($avg_days, 1) : 0;
        mysqli_stmt_close($avg_stmt);
        
        $resolved_rate = $total > 0 ? ($resolved / $total) * 100 : 0;
        if ($resolved_rate >= 70) $rating = 'Excellent';
        elseif ($resolved_rate >= 50) $rating = 'Good';
        else $rating = 'Needs Improvement';
        
        $staff_labels[] = $display;
        $staff_total[] = $total;
        $staff_resolved[] = $resolved;
        $staff_pending[] = $pending;
        $staff_avg_response[] = $avg_response;
        $staff_rating[] = $rating;
    }
    
    // Overall stats
    $total_sql_all = "SELECT COUNT(*) as total, 
                      SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                      SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
                      FROM complaints WHERE YEAR(created_at) = ?";
    $total_stmt_all = mysqli_prepare($conn, $total_sql_all);
    mysqli_stmt_bind_param($total_stmt_all, "i", $year);
    mysqli_stmt_execute($total_stmt_all);
    $total_res_all = mysqli_stmt_get_result($total_stmt_all);
    $totals = mysqli_fetch_assoc($total_res_all);
    mysqli_stmt_close($total_stmt_all);
    
    $avg_res_sql = "SELECT AVG(DATEDIFF(updated_at, created_at)) as avg_days 
                    FROM complaints 
                    WHERE YEAR(created_at) = ? AND status = 'resolved' AND updated_at IS NOT NULL";
    $avg_res_stmt = mysqli_prepare($conn, $avg_res_sql);
    mysqli_stmt_bind_param($avg_res_stmt, "i", $year);
    mysqli_stmt_execute($avg_res_stmt);
    $avg_res_res = mysqli_stmt_get_result($avg_res_stmt);
    $avg_res_row = mysqli_fetch_assoc($avg_res_res);
    $avg_resolution_days = round($avg_res_row['avg_days'] ?? 0, 1);
    mysqli_stmt_close($avg_res_stmt);
    
    $total_complaints = (int)$totals['total'];
    $escalated_count = (int)$totals['escalated'];
    $escalation_rate = $total_complaints > 0 ? round(($escalated_count / $total_complaints) * 100, 1) : 0;
    $pending_rate = $total_complaints > 0 ? round((($total_complaints - $totals['resolved'] - $escalated_count) / $total_complaints) * 100, 1) : 0;
    
    // Compare with previous year
    $prev_year = $year - 1;
    $prev_total_sql = "SELECT COUNT(*) as total FROM complaints WHERE YEAR(created_at) = ?";
    $prev_stmt = mysqli_prepare($conn, $prev_total_sql);
    mysqli_stmt_bind_param($prev_stmt, "i", $prev_year);
    mysqli_stmt_execute($prev_stmt);
    $prev_res = mysqli_stmt_get_result($prev_stmt);
    $prev_row = mysqli_fetch_assoc($prev_res);
    $prev_total = (int)$prev_row['total'];
    mysqli_stmt_close($prev_stmt);
    $trend = ($prev_total > 0) ? round((($total_complaints - $prev_total) / $prev_total) * 100, 1) : 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'year' => $year,
        'monthly' => $monthly,
        'categories' => $categories,
        'category_counts' => $cat_counts,
        'departments' => $depts,
        'department_counts' => $dept_counts,
        'priorities' => $priorities,
        'priority_counts' => $priority_counts,
        'statuses' => $statuses,
        'status_counts' => $status_counts,
        'total' => $total_complaints,
        'resolved' => (int)$totals['resolved'],
        'resolved_rate' => $total_complaints > 0 ? round(($totals['resolved'] / $total_complaints) * 100, 1) : 0,
        'pending_rate' => $pending_rate,
        'escalated_count' => $escalated_count,
        'escalation_rate' => $escalation_rate,
        'avg_resolution_days' => $avg_resolution_days,
        'staff_labels' => $staff_labels,
        'staff_total' => $staff_total,
        'staff_resolved' => $staff_resolved,
        'staff_pending' => $staff_pending,
        'staff_avg_response' => $staff_avg_response,
        'staff_rating' => $staff_rating,
        'trend_vs_prev' => $trend,
        'prev_total' => $prev_total
    ]);
    exit();
}

// Handle raw data export for Excel
if (isset($_GET['ajax']) && $_GET['ajax'] == 'raw_data') {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $sql = "SELECT c.id, c.complaint_number, u.full_name as student, d.name as department, cat.name as category, c.status, c.created_at as date, c.priority
            FROM complaints c
            JOIN users u ON c.student_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            JOIN categories cat ON c.category_id = cat.id
            WHERE YEAR(c.created_at) = ?
            ORDER BY c.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Get available years
$years_sql = "SELECT DISTINCT YEAR(created_at) as yr FROM complaints ORDER BY yr DESC";
$years_result = mysqli_query($conn, $years_sql);
$available_years = [];
while ($yr = mysqli_fetch_assoc($years_result)) {
    $available_years[] = $yr['yr'];
}
if (empty($available_years)) {
    $available_years = [date('Y')];
}
$current_year = isset($_GET['year']) ? intval($_GET['year']) : $available_years[0];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Analytics & Reports - Rector Panel</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
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

        /* ========== REPORT HEADER ========== */
        .report-header {
            background: white;
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .report-header h2 {
            font-size: 1.2rem;
            color: #0a2a5e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .report-header h2 i {
            color: #1a56db;
        }
        .report-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .report-btn {
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            font-size: 0.85rem;
        }
        .report-btn.pdf {
            background: #dc2626;
            color: white;
        }
        .report-btn.pdf:hover {
            background: #991b1b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        .report-btn.excel {
            background: #16a34a;
            color: white;
        }
        .report-btn.excel:hover {
            background: #065f46;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #4a5a7a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }
        .filter-group select {
            width: 100%;
            padding: 10px 16px;
            border-radius: 12px;
            border: 1.5px solid #e5edf5;
            background: white;
            font-size: 0.9rem;
            transition: border 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.08);
        }
        .filter-btn {
            background: #1a56db;
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-btn:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }

        /* ========== STATS CARDS ========== */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 22px 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(10,42,94,0.10);
        }
        .stat-card .icon {
            font-size: 1.8rem;
            margin-bottom: 6px;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 800;
            color: #0a2a5e;
            line-height: 1.2;
        }
        .stat-card .label {
            color: #6b85a0;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 2px;
        }
        .stat-card .trend {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
        }
        .stat-card .trend.up { color: #10b981; }
        .stat-card .trend.down { color: #dc2626; }
        .stat-card .trend.neutral { color: #6b85a0; }

        /* ========== CHART GRID ========== */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 24px;
        }
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
        }
        .chart-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-card h3 i {
            color: #1a56db;
        }
        .chart-card canvas {
            width: 100% !important;
            max-height: 280px;
        }

        /* ========== STAFF PERFORMANCE TABLE ========== */
        .staff-section {
            margin-top: 20px;
            overflow-x: auto;
        }
        .staff-section table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .staff-section th {
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
        .staff-section td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f4f9;
            color: #1f2c40;
        }
        .staff-section tr:hover {
            background: #fafcff;
        }
        .staff-section .rating-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .rating-excellent { background: #d1fae5; color: #065f46; }
        .rating-good { background: #fef3c7; color: #b45309; }
        .rating-poor { background: #fee2e2; color: #991b1b; }

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
            .chart-grid { grid-template-columns: 1fr; }
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

            .report-header {
                flex-direction: column;
                text-align: center;
                padding: 16px 20px;
            }
            .report-buttons {
                justify-content: center;
                width: 100%;
            }
            .report-btn {
                flex: 1;
                justify-content: center;
                padding: 8px 16px;
                font-size: 0.75rem;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 16px 20px;
                gap: 12px;
            }
            .filter-group {
                min-width: auto;
            }
            .filter-btn {
                justify-content: center;
                width: 100%;
            }

            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .stat-card {
                padding: 16px 12px;
            }
            .stat-card .number {
                font-size: 1.4rem;
            }
            .stat-card .label {
                font-size: 0.7rem;
            }

            .chart-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .chart-card {
                padding: 16px;
            }
            .chart-card h3 {
                font-size: 0.85rem;
            }
            .chart-card canvas {
                max-height: 200px;
            }

            .staff-section {
                font-size: 0.75rem;
            }
            .staff-section th, .staff-section td {
                padding: 6px 8px;
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

            .report-header {
                padding: 12px 16px;
            }
            .report-header h2 {
                font-size: 0.9rem;
            }
            .report-btn {
                font-size: 0.65rem;
                padding: 6px 12px;
            }

            .filter-bar {
                padding: 12px 16px;
            }
            .filter-group label {
                font-size: 0.6rem;
            }
            .filter-group select {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
            .filter-btn {
                font-size: 0.8rem;
                padding: 8px 16px;
            }

            .stats-cards {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .stat-card {
                padding: 12px 8px;
            }
            .stat-card .number {
                font-size: 1.2rem;
            }
            .stat-card .label {
                font-size: 0.6rem;
            }
            .stat-card .icon {
                font-size: 1.2rem;
            }

            .chart-card {
                padding: 12px;
            }
            .chart-card h3 {
                font-size: 0.75rem;
            }
            .chart-card canvas {
                max-height: 160px;
            }

            .staff-section th, .staff-section td {
                padding: 4px 6px;
                font-size: 0.6rem;
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
        <a href="analytics-reports.php" class="menu-item active">
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
        <!-- Report Header -->
        <div class="report-header">
            <h2><i class="fas fa-chart-line"></i> Analytics & Reports</h2>
            <div class="report-buttons">
                <button id="pdfReportBtn" class="report-btn pdf">
                    <i class="fas fa-file-pdf"></i> PDF Report
                </button>
                <button id="excelReportBtn" class="report-btn excel">
                    <i class="fas fa-file-excel"></i> Excel Export
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Select Year</label>
                <select id="yearSelect">
                    <?php foreach ($available_years as $yr): ?>
                        <option value="<?php echo $yr; ?>" <?php echo $yr == $current_year ? 'selected' : ''; ?>>
                            <?php echo $yr; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group" style="flex: 0 0 auto;">
                <button id="refreshBtn" class="filter-btn">
                    <i class="fas fa-chart-line"></i> Load Report
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards" id="statsCards">
            <div class="stat-card">
                <div class="icon">📋</div>
                <div class="number" id="totalComplaints">--</div>
                <div class="label">Total Complaints</div>
                <div class="trend" id="trendIndicator"></div>
            </div>
            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="number" id="resolvedRate">--</div>
                <div class="label">Resolution Rate</div>
            </div>
            <div class="stat-card">
                <div class="icon">⏱️</div>
                <div class="number" id="avgResolution">--</div>
                <div class="label">Avg Resolution (Days)</div>
            </div>
            <div class="stat-card">
                <div class="icon">📈</div>
                <div class="number" id="escalationRate">--</div>
                <div class="label">Escalation Rate</div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="chart-grid" id="chartsGrid">
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Trends</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Complaints by Category</h3>
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-building"></i> Complaints by Department</h3>
                <canvas id="deptChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-flag"></i> Complaints by Priority</h3>
                <canvas id="priorityChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-circle"></i> Complaints by Status</h3>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-users"></i> Staff Performance</h3>
                <canvas id="staffChart"></canvas>
                <div class="staff-section">
                    <table id="staffTable">
                        <thead>
                            <tr>
                                <th>Staff Role</th>
                                <th style="text-align:center;">Assigned</th>
                                <th style="text-align:center;">Resolved</th>
                                <th style="text-align:center;">Pending</th>
                                <th style="text-align:center;">Avg Response (days)</th>
                                <th style="text-align:center;">Rating</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
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
    Chart.register(ChartDataLabels);
    let monthlyChart, categoryChart, deptChart, priorityChart, statusChart, staffChart;
    let currentYear = <?php echo $current_year; ?>;
    let analyticsData = null;
    
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

    async function loadAnalytics(year) {
        try {
            const response = await fetch(`analytics-reports.php?ajax=analytics&year=${year}`);
            const data = await response.json();
            analyticsData = data;
            
            // Update stats cards
            document.getElementById('totalComplaints').innerText = data.total;
            document.getElementById('resolvedRate').innerText = data.resolved_rate + '%';
            document.getElementById('avgResolution').innerText = data.avg_resolution_days + ' days';
            document.getElementById('escalationRate').innerText = data.escalation_rate + '%';
            
            // Update trend indicator
            const trendEl = document.getElementById('trendIndicator');
            if (data.trend_vs_prev > 0) {
                trendEl.innerHTML = `<i class="fas fa-arrow-up"></i> ${data.trend_vs_prev}% from ${data.year-1}`;
                trendEl.className = 'trend up';
            } else if (data.trend_vs_prev < 0) {
                trendEl.innerHTML = `<i class="fas fa-arrow-down"></i> ${Math.abs(data.trend_vs_prev)}% from ${data.year-1}`;
                trendEl.className = 'trend down';
            } else {
                trendEl.innerHTML = `<i class="fas fa-minus"></i> Same as ${data.year-1}`;
                trendEl.className = 'trend neutral';
            }
            
            // Monthly Chart
            if (monthlyChart) monthlyChart.destroy();
            monthlyChart = new Chart(document.getElementById('monthlyChart'), {
                type: 'line',
                data: {
                    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                    datasets: [{
                        label: 'Complaints',
                        data: data.monthly,
                        borderColor: '#1a56db',
                        backgroundColor: 'rgba(26, 86, 219, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#1a56db',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Complaints', color: '#6b85a0' },
                            grid: { color: 'rgba(0,0,0,0.04)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
            
            // Category Chart - Only show categories with data
            const hasData = data.category_counts.some(count => count > 0);
            if (categoryChart) categoryChart.destroy();
            
            if (hasData) {
                const catColors = data.categories.map(() => {
                    const colors = ['#1a56db', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a'];
                    return colors[Math.floor(Math.random() * colors.length)];
                });
                categoryChart = new Chart(document.getElementById('categoryChart'), {
                    type: 'doughnut',
                    data: {
                        labels: data.categories,
                        datasets: [{
                            data: data.category_counts,
                            backgroundColor: catColors,
                            borderWidth: 2,
                            borderColor: 'white'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { boxWidth: 12, font: { size: 10 }, padding: 12 }
                            },
                            datalabels: {
                                color: 'white',
                                backgroundColor: 'rgba(0,0,0,0.6)',
                                borderRadius: 12,
                                padding: { left: 5, right: 5, top: 3, bottom: 3 },
                                font: { weight: 'bold', size: 10 },
                                formatter: (val, ctx) => {
                                    let total = data.category_counts.reduce((a,b) => a + b, 0);
                                    let pct = total > 0 ? (val / total * 100).toFixed(1) : 0;
                                    return pct > 5 ? `${pct}%` : '';
                                },
                                anchor: 'center',
                                align: 'center'
                            }
                        }
                    }
                });
            } else {
                // Show empty state
                document.getElementById('categoryChart').parentElement.innerHTML = `
                    <h3><i class="fas fa-chart-pie"></i> Complaints by Category</h3>
                    <p style="text-align:center; color:#8ba0bc; padding:30px 0;">
                        <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                        No data available for selected year
                    </p>
                `;
            }
            
            // Department Chart
            if (deptChart) deptChart.destroy();
            const deptColors = data.departments.map(() => {
                const colors = ['#10b981', '#34d399', '#6ee7b7', '#a7f3d0', '#d1fae5', '#059669', '#047857'];
                return colors[Math.floor(Math.random() * colors.length)];
            });
            deptChart = new Chart(document.getElementById('deptChart'), {
                type: 'doughnut',
                data: {
                    labels: data.departments,
                    datasets: [{
                        data: data.department_counts,
                        backgroundColor: deptColors,
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { boxWidth: 12, font: { size: 10 }, padding: 12 }
                        },
                        datalabels: {
                            color: 'white',
                            backgroundColor: 'rgba(0,0,0,0.6)',
                            borderRadius: 12,
                            padding: { left: 5, right: 5, top: 3, bottom: 3 },
                            font: { weight: 'bold', size: 10 },
                            formatter: (val, ctx) => {
                                let total = data.department_counts.reduce((a,b) => a + b, 0);
                                let pct = total > 0 ? (val / total * 100).toFixed(1) : 0;
                                return pct > 5 ? `${pct}%` : '';
                            },
                            anchor: 'center',
                            align: 'center'
                        }
                    }
                }
            });
            
            // Priority Chart
            if (priorityChart) priorityChart.destroy();
            const priorityColors = ['#dc2626', '#f59e0b', '#10b981'];
            priorityChart = new Chart(document.getElementById('priorityChart'), {
                type: 'pie',
                data: {
                    labels: data.priorities,
                    datasets: [{
                        data: data.priority_counts,
                        backgroundColor: priorityColors,
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { boxWidth: 12, font: { size: 10 }, padding: 12 }
                        },
                        datalabels: {
                            color: 'white',
                            backgroundColor: 'rgba(0,0,0,0.6)',
                            borderRadius: 12,
                            padding: { left: 5, right: 5, top: 3, bottom: 3 },
                            font: { weight: 'bold', size: 10 },
                            formatter: (val, ctx) => {
                                let total = data.priority_counts.reduce((a,b) => a + b, 0);
                                let pct = total > 0 ? (val / total * 100).toFixed(1) : 0;
                                return `${pct}%`;
                            },
                            anchor: 'center',
                            align: 'center'
                        }
                    }
                }
            });
            
            // Status Chart
            if (statusChart) statusChart.destroy();
            const statusColors = ['#f59e0b', '#3b82f6', '#10b981', '#dc2626'];
            statusChart = new Chart(document.getElementById('statusChart'), {
                type: 'bar',
                data: {
                    labels: data.statuses,
                    datasets: [{
                        label: 'Number of Complaints',
                        data: data.status_counts,
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#dc2626', '#8b5cf6'],
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Count', color: '#6b85a0' },
                            grid: { color: 'rgba(0,0,0,0.04)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end',
                            align: 'end',
                            color: '#1f2c40',
                            font: { weight: 'bold', size: 10 }
                        }
                    }
                }
            });
            
            // Staff Chart
            if (staffChart) staffChart.destroy();
            staffChart = new Chart(document.getElementById('staffChart'), {
                type: 'bar',
                data: {
                    labels: data.staff_labels,
                    datasets: [
                        {
                            label: 'Total Assigned',
                            data: data.staff_total,
                            backgroundColor: 'rgba(26, 86, 219, 0.7)',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                        {
                            label: 'Resolved',
                            data: data.staff_resolved,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderRadius: 4,
                            borderSkipped: false
                        },
                        {
                            label: 'Pending',
                            data: data.staff_pending,
                            backgroundColor: 'rgba(245, 158, 11, 0.7)',
                            borderRadius: 4,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Complaints', color: '#6b85a0' },
                            grid: { color: 'rgba(0,0,0,0.04)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { boxWidth: 12, font: { size: 10 }, padding: 10 }
                        }
                    }
                }
            });
            
            // Staff Table
            const tbody = document.querySelector('#staffTable tbody');
            tbody.innerHTML = '';
            const ratingClasses = {
                'Excellent': 'rating-excellent',
                'Good': 'rating-good',
                'Needs Improvement': 'rating-poor'
            };
            for (let i = 0; i < data.staff_labels.length; i++) {
                const ratingClass = ratingClasses[data.staff_rating[i]] || 'rating-poor';
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${data.staff_labels[i]}</strong></td>
                        <td style="text-align:center;">${data.staff_total[i]}</td>
                        <td style="text-align:center;">${data.staff_resolved[i]}</td>
                        <td style="text-align:center;">${data.staff_pending[i]}</td>
                        <td style="text-align:center;">${data.staff_avg_response[i]}</td>
                        <td style="text-align:center;">
                            <span class="rating-badge ${ratingClass}">${data.staff_rating[i]}</span>
                        </td>
                    </tr>
                `;
            }
            
        } catch (error) {
            console.error('Error loading analytics:', error);
            showToast('Failed to load analytics data. Please try again.', 'error');
        }
    }
    
    // Generate professional PDF report
    function generateProfessionalReport() {
        if (!analyticsData) {
            showToast('Please wait for data to load.', 'info');
            return;
        }
        const d = analyticsData;
        const year = d.year;
        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        
        let deptRows = '';
        for (let i = 0; i < d.departments.length; i++) {
            const pct = d.total > 0 ? Math.round(d.department_counts[i] / d.total * 100) : 0;
            deptRows += `<tr><td>${d.departments[i]}</td><td>${d.department_counts[i]}</td><td>${pct}%</td></tr>`;
        }
        
        let staffRows = '';
        for (let i = 0; i < d.staff_labels.length; i++) {
            staffRows += `<tr><td>${d.staff_labels[i]}</td><td>${d.staff_total[i]}</td><td>${d.staff_resolved[i]}</td><td>${d.staff_pending[i]}</td><td>${d.staff_avg_response[i]}</td><td>${d.staff_rating[i]}</td></tr>`;
        }
        
        let catRows = '';
        for (let i = 0; i < d.categories.length; i++) {
            const pct = d.total > 0 ? Math.round(d.category_counts[i] / d.total * 100) : 0;
            catRows += `<tr><td>${d.categories[i]}</td><td>${d.category_counts[i]}</td><td>${pct}%</td></tr>`;
        }
        
        let monthlyRows = '';
        for (let i = 0; i < 12; i++) {
            monthlyRows += `<tr><td>${monthNames[i]}</td><td>${d.monthly[i]}</td></tr>`;
        }
        
        const html = `<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Complaint Report ${year}</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; margin: 40px; line-height: 1.6; color: #1f2c40; }
                .header { text-align: center; border-bottom: 3px solid #1a56db; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { color: #0a2a5e; margin: 0; font-size: 2rem; }
                .header h2 { color: #1a56db; margin: 5px 0; font-weight: 400; }
                .header p { color: #6b85a0; margin: 5px 0; }
                .section { margin-bottom: 30px; page-break-inside: avoid; }
                .section h3 { background: #1a56db; color: white; padding: 8px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 1.1rem; }
                .metrics { display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0; }
                .metric-card { background: #f8fafc; padding: 15px 20px; border-radius: 10px; flex: 1; min-width: 120px; text-align: center; border: 1px solid #e5edf5; }
                .metric-number { font-size: 1.8rem; font-weight: 700; color: #0a2a5e; }
                .metric-label { color: #6b85a0; font-size: 0.85rem; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 0.9rem; }
                th { background: #f0f4ff; padding: 10px 12px; text-align: left; border: 1px solid #e5edf5; font-weight: 600; }
                td { padding: 8px 12px; border: 1px solid #e5edf5; }
                .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #e5edf5; font-size: 0.8rem; color: #8ba0bc; }
                @media print { body { margin: 20px; } .section { page-break-inside: avoid; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Institute of Accountancy Arusha (IAA)</h1>
                <h2>Student Complaint Management Report</h2>
                <p><strong>Period:</strong> ${year} | <strong>Prepared by:</strong> Rector's Office / CFMS</p>
                <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
            </div>
            
            <div class="section">
                <h3>📊 Executive Summary</h3>
                <p>During the year <strong>${year}</strong>, a total of <strong>${d.total}</strong> complaints were received.</p>
                <ul>
                    <li><strong>Resolution Rate:</strong> ${d.resolved_rate}%</li>
                    <li><strong>Escalation Rate:</strong> ${d.escalation_rate}%</li>
                    <li><strong>Average Resolution Time:</strong> ${d.avg_resolution_days} days</li>
                    <li><strong>Trend vs ${year-1}:</strong> ${d.trend_vs_prev > 0 ? '↑' : d.trend_vs_prev < 0 ? '↓' : '→'} ${Math.abs(d.trend_vs_prev)}%</li>
                </ul>
            </div>
            
            <div class="section">
                <h3>📈 Key Metrics</h3>
                <div class="metrics">
                    <div class="metric-card"><div class="metric-number">${d.total}</div><div class="metric-label">Total Complaints</div></div>
                    <div class="metric-card"><div class="metric-number">${d.resolved_rate}%</div><div class="metric-label">Resolved Rate</div></div>
                    <div class="metric-card"><div class="metric-number">${d.pending_rate}%</div><div class="metric-label">Pending Rate</div></div>
                    <div class="metric-card"><div class="metric-number">${d.escalation_rate}%</div><div class="metric-label">Escalation Rate</div></div>
                    <div class="metric-card"><div class="metric-number">${d.avg_resolution_days}</div><div class="metric-label">Avg Resolution (days)</div></div>
                </div>
            </div>
            
            <div class="section">
                <h3>🏢 Complaints by Department</h3>
                <table>
                    <thead><tr><th>Department</th><th>Count</th><th>% of Total</th></tr></thead>
                    <tbody>${deptRows}</tbody>
                </table>
            </div>
            
            <div class="section">
                <h3>📂 Complaints by Category</h3>
                <table>
                    <thead><tr><th>Category</th><th>Count</th><th>% of Total</th></tr></thead>
                    <tbody>${catRows}</tbody>
                </table>
            </div>
            
            <div class="section">
                <h3>📅 Monthly Distribution</h3>
                <table>
                    <thead><tr><th>Month</th><th>Complaints</th></tr></thead>
                    <tbody>${monthlyRows}</tbody>
                </table>
            </div>
            
            <div class="section">
                <h3>👥 Staff Performance</h3>
                <table>
                    <thead><tr><th>Role</th><th>Assigned</th><th>Resolved</th><th>Pending</th><th>Avg Response (days)</th><th>Rating</th></tr></thead>
                    <tbody>${staffRows}</tbody>
                </table>
                <p style="font-size:0.8rem; color:#6b85a0; margin-top:8px;">
                    ⭐ Excellent (≥70% resolved) | ⭐ Good (50-69%) | ⭐ Needs Improvement (&lt;50%)
                </p>
            </div>
            
            <div class="footer">
                Generated by IAA Complaint Management System | Rector Portal
            </div>
        </body>
        </html>`;
        
        const win = window.open('', '_blank');
        if (win) {
            win.document.write(html);
            win.document.close();
            setTimeout(() => win.print(), 500);
        } else {
            showToast('Please allow popups to generate PDF.', 'error');
        }
    }
    
    // Export to Excel
    async function downloadProfessionalExcel() {
        if (!analyticsData) {
            showToast('Please wait for data to load.', 'info');
            return;
        }
        const year = currentYear;
        try {
            const rawResponse = await fetch(`analytics-reports.php?ajax=raw_data&year=${year}`);
            const rawData = await rawResponse.json();
            const wb = XLSX.utils.book_new();
            
            // Dashboard sheet
            const dashboardData = [
                ['INSTITUTE OF ACCOUNTANCY ARUSHA (IAA)'],
                ['Student Complaint Management Report'],
                ['Period:', year],
                ['Prepared by:', 'Rector\'s Office / CFMS System'],
                [''],
                ['📊 KEY METRICS'],
                ['Metric', 'Value'],
                ['Total Complaints', analyticsData.total],
                ['Resolved', analyticsData.resolved],
                ['Pending', analyticsData.total - analyticsData.resolved - analyticsData.escalated_count],
                ['Escalated', analyticsData.escalated_count],
                ['Resolution Rate (%)', analyticsData.resolved_rate],
                ['Escalation Rate (%)', analyticsData.escalation_rate],
                ['Avg Resolution (days)', analyticsData.avg_resolution_days],
                ['Trend vs Previous Year (%)', analyticsData.trend_vs_prev],
                ['Previous Year Total', analyticsData.prev_total],
                [''],
                ['📈 COMPLAINTS BY DEPARTMENT'],
                ['Department', 'Count', '% of Total'],
                ...analyticsData.departments.map((d, i) => [d, analyticsData.department_counts[i], ((analyticsData.department_counts[i] / analyticsData.total) * 100).toFixed(1) + '%']),
                [''],
                ['📂 COMPLAINTS BY CATEGORY'],
                ['Category', 'Count', '% of Total'],
                ...analyticsData.categories.map((c, i) => [c, analyticsData.category_counts[i], ((analyticsData.category_counts[i] / analyticsData.total) * 100).toFixed(1) + '%']),
                [''],
                ['📅 MONTHLY DISTRIBUTION'],
                ['Month', 'Complaints'],
                ...['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'].map((m, i) => [m, analyticsData.monthly[i]])
            ];
            const wsDashboard = XLSX.utils.aoa_to_sheet(dashboardData);
            wsDashboard['!cols'] = [{wch:25}, {wch:15}, {wch:15}];
            XLSX.utils.book_append_sheet(wb, wsDashboard, 'Dashboard');
            
            // Raw Data
            const rawSheetData = [['ID', 'Complaint #', 'Student', 'Department', 'Category', 'Status', 'Date', 'Priority']];
            rawData.forEach(row => rawSheetData.push([row.id, row.complaint_number, row.student, row.department || 'N/A', row.category, row.status, row.date, row.priority]));
            const wsRaw = XLSX.utils.aoa_to_sheet(rawSheetData);
            XLSX.utils.book_append_sheet(wb, wsRaw, 'Raw Data');
            
            // Staff Performance
            const staffPerf = [['Staff Role', 'Assigned', 'Resolved', 'Pending', 'Avg Response (days)', 'Rating']];
            analyticsData.staff_labels.forEach((l, i) => staffPerf.push([l, analyticsData.staff_total[i], analyticsData.staff_resolved[i], analyticsData.staff_pending[i], analyticsData.staff_avg_response[i], analyticsData.staff_rating[i]]));
            const wsStaff = XLSX.utils.aoa_to_sheet(staffPerf);
            XLSX.utils.book_append_sheet(wb, wsStaff, 'Staff Performance');
            
            XLSX.writeFile(wb, `Complaint_Report_${year}.xlsx`);
            showToast('Excel file downloaded successfully!', 'success');
        } catch (error) {
            console.error('Excel export error:', error);
            showToast('Failed to export Excel. Please try again.', 'error');
        }
    }
    
    // ========== EVENT LISTENERS ==========
    document.getElementById('refreshBtn').addEventListener('click', () => {
        currentYear = parseInt(document.getElementById('yearSelect').value);
        loadAnalytics(currentYear);
    });
    
    document.getElementById('pdfReportBtn').addEventListener('click', generateProfessionalReport);
    document.getElementById('excelReportBtn').addEventListener('click', downloadProfessionalExcel);
    
    // Load initial data
    loadAnalytics(currentYear);
    
    // ========== SIDEBAR TOGGLE ==========
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

    // ========== LOGOUT MODAL ==========
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