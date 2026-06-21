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

// ========== GET STAFF ROLES (Rector excluded) ==========
$staff_roles = [
    'hod' => 'HOD',
    'dean' => 'Dean of Students',
    'examination_officer' => 'Examination Officer',
    'president' => 'IAASO President',
    'deputy_rector' => 'Deputy Rector',
    'accountant' => 'Accountant',
    'it_officer' => 'IT Officer',
    'director' => 'Director'
];

// ========== GET STAFF PERFORMANCE DATA ==========
$staff_data = [];
$overall_stats = [
    'total_staff' => 0,
    'total_assigned' => 0,
    'total_resolved' => 0,
    'total_pending' => 0,
    'avg_resolution' => 0
];

foreach ($staff_roles as $role => $display) {
    // Get staff members with this role
    $staff_sql = "SELECT id, full_name, email, phone_number, profile_picture, created_at 
                  FROM users 
                  WHERE role = ? AND is_active = 1";
    $staff_stmt = mysqli_prepare($conn, $staff_sql);
    mysqli_stmt_bind_param($staff_stmt, "s", $role);
    mysqli_stmt_execute($staff_stmt);
    $staff_res = mysqli_stmt_get_result($staff_stmt);
    
    while ($staff = mysqli_fetch_assoc($staff_res)) {
        $staff_id = $staff['id'];
        
        // Get complaint stats for this staff
        $stats_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
                      FROM complaints 
                      WHERE assigned_to = ?";
        $stats_stmt = mysqli_prepare($conn, $stats_sql);
        mysqli_stmt_bind_param($stats_stmt, "i", $staff_id);
        mysqli_stmt_execute($stats_stmt);
        $stats_res = mysqli_stmt_get_result($stats_stmt);
        $stats = mysqli_fetch_assoc($stats_res);
        mysqli_stmt_close($stats_stmt);
        
        $total = (int)$stats['total'];
        $resolved = (int)$stats['resolved'];
        $pending = (int)$stats['pending'];
        $in_progress = (int)$stats['in_progress'];
        $escalated = (int)$stats['escalated'];
        
        // Calculate resolution rate
        $resolution_rate = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;
        
        // Get average response time (in hours)
        $avg_sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, c.created_at, r.created_at)) as avg_hours
                    FROM complaints c
                    JOIN responses r ON r.complaint_id = c.id
                    WHERE c.assigned_to = ? 
                    AND r.user_id = c.assigned_to
                    AND r.id = (SELECT MIN(id) FROM responses WHERE complaint_id = c.id)";
        $avg_stmt = mysqli_prepare($conn, $avg_sql);
        mysqli_stmt_bind_param($avg_stmt, "i", $staff_id);
        mysqli_stmt_execute($avg_stmt);
        $avg_res = mysqli_stmt_get_result($avg_stmt);
        $avg_row = mysqli_fetch_assoc($avg_res);
        $avg_response = $avg_row['avg_hours'] ? round($avg_row['avg_hours'], 1) : 0;
        mysqli_stmt_close($avg_stmt);
        
        // Get last activity
        $last_activity_sql = "SELECT MAX(created_at) as last_activity FROM responses WHERE user_id = ?";
        $last_stmt = mysqli_prepare($conn, $last_activity_sql);
        mysqli_stmt_bind_param($last_stmt, "i", $staff_id);
        mysqli_stmt_execute($last_stmt);
        $last_res = mysqli_stmt_get_result($last_stmt);
        $last_row = mysqli_fetch_assoc($last_res);
        $last_activity = $last_row['last_activity'] ?? $staff['created_at'];
        mysqli_stmt_close($last_stmt);
        
        // Determine rating based on resolution rate
        if ($resolution_rate >= 70) {
            $rating = 'Excellent';
            $rating_class = 'rating-excellent';
            $rating_icon = '⭐';
        } elseif ($resolution_rate >= 50) {
            $rating = 'Good';
            $rating_class = 'rating-good';
            $rating_icon = '⭐';
        } else {
            $rating = 'Needs Improvement';
            $rating_class = 'rating-poor';
            $rating_icon = '⭐';
        }
        
        $staff_data[] = [
            'id' => $staff_id,
            'name' => $staff['full_name'],
            'email' => $staff['email'],
            'phone' => $staff['phone_number'] ?? 'N/A',
            'role' => $display,
            'role_key' => $role,
            'profile_pic' => $staff['profile_picture'] ?? '',
            'total' => $total,
            'resolved' => $resolved,
            'pending' => $pending,
            'in_progress' => $in_progress,
            'escalated' => $escalated,
            'resolution_rate' => $resolution_rate,
            'avg_response' => $avg_response,
            'last_activity' => $last_activity,
            'rating' => $rating,
            'rating_class' => $rating_class,
            'rating_icon' => $rating_icon
        ];
        
        // Update overall stats
        $overall_stats['total_staff']++;
        $overall_stats['total_assigned'] += $total;
        $overall_stats['total_resolved'] += $resolved;
        $overall_stats['total_pending'] += $pending;
    }
    mysqli_stmt_close($staff_stmt);
}

// ========== FIND TOP PERFORMER (Highest Resolution Rate) ==========
// Only include staff with at least 1 complaint
$staff_with_complaints = array_filter($staff_data, function($s) {
    return $s['total'] > 0;
});

// Sort by resolution rate (descending)
usort($staff_with_complaints, function($a, $b) {
    return $b['resolution_rate'] - $a['resolution_rate'];
});

// Get top performer (staff with highest resolution rate)
$top_performer = !empty($staff_with_complaints) ? $staff_with_complaints[0] : null;

// Sort main staff list by total complaints (highest first)
usort($staff_data, function($a, $b) {
    return $b['total'] - $a['total'];
});

// Calculate average resolution rate
$overall_resolution_rate = $overall_stats['total_assigned'] > 0 
    ? round(($overall_stats['total_resolved'] / $overall_stats['total_assigned']) * 100, 1) 
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Staff Monitoring - Rector Panel</title>
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

        /* ========== PAGE HEADER ========== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .page-header h2 {
            font-size: 1.3rem;
            color: #0a2a5e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header h2 i {
            color: #1a56db;
        }
        .page-header .subtitle {
            color: #6b85a0;
            font-size: 0.85rem;
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

        /* ========== TOP PERFORMER ========== */
        .top-performer {
            background: white;
            border-radius: 20px;
            padding: 24px 28px;
            margin-bottom: 28px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            border-left: 4px solid #f59e0b;
        }
        .top-performer .badge {
            background: #f59e0b;
            color: white;
            padding: 4px 16px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .top-performer .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0a2a5e, #003d7a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }
        .top-performer .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .top-performer .info h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0a2a5e;
        }
        .top-performer .info .role {
            color: #6b85a0;
            font-size: 0.85rem;
        }
        .top-performer .metrics {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-left: auto;
        }
        .top-performer .metrics .item {
            text-align: center;
        }
        .top-performer .metrics .item .num {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0a2a5e;
        }
        .top-performer .metrics .item .lbl {
            font-size: 0.7rem;
            color: #6b85a0;
        }

        /* ========== STAFF TABLE ========== */
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

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .staff-table thead th {
            background: #f8fafc;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            color: #4a5a7a;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e5edf5;
            white-space: nowrap;
        }
        .staff-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f0f4f9;
            color: #1f2c40;
            vertical-align: middle;
        }
        .staff-table tbody tr:hover {
            background: #fafcff;
        }
        .staff-table tbody tr:last-child td {
            border-bottom: none;
        }
        .staff-table .staff-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0a2a5e, #003d7a);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            overflow: hidden;
        }
        .staff-table .staff-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-table .staff-name {
            font-weight: 600;
            color: #0a2a5e;
        }
        .staff-table .staff-role {
            font-size: 0.7rem;
            color: #6b85a0;
        }
        .staff-table .rating-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .rating-excellent { background: #d1fae5; color: #065f46; }
        .rating-good { background: #fef3c7; color: #b45309; }
        .rating-poor { background: #fee2e2; color: #991b1b; }

        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .status-dot.high { background: #dc2626; }
        .status-dot.medium { background: #f59e0b; }
        .status-dot.low { background: #10b981; }

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

            .page-header h2 {
                font-size: 1rem;
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

            .top-performer {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
            .top-performer .metrics {
                margin-left: 0;
                justify-content: center;
                width: 100%;
            }
            .top-performer .metrics .item .num {
                font-size: 1.1rem;
            }

            .content-area {
                padding: 16px;
                border-radius: 12px;
            }
            .content-area h4 {
                font-size: 0.85rem;
            }

            .staff-table thead th,
            .staff-table tbody td {
                padding: 8px 10px;
                font-size: 0.7rem;
            }
            .staff-table .staff-avatar {
                width: 28px;
                height: 28px;
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

            .page-header h2 {
                font-size: 0.9rem;
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

            .top-performer {
                padding: 16px;
            }
            .top-performer .avatar {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
            }
            .top-performer .info h4 {
                font-size: 0.9rem;
            }
            .top-performer .metrics {
                gap: 12px;
            }
            .top-performer .metrics .item .num {
                font-size: 1rem;
            }
            .top-performer .metrics .item .lbl {
                font-size: 0.6rem;
            }

            .content-area {
                padding: 12px;
                border-radius: 10px;
            }
            .content-area h4 {
                font-size: 0.75rem;
            }

            .staff-table thead th,
            .staff-table tbody td {
                padding: 6px 6px;
                font-size: 0.6rem;
            }
            .staff-table .staff-avatar {
                width: 24px;
                height: 24px;
                font-size: 0.5rem;
            }
            .staff-table .rating-badge {
                font-size: 0.55rem;
                padding: 1px 8px;
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
        <a href="staff-monitoring.php" class="menu-item active">
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
        <!-- Page Header -->
        <div class="page-header">
            <h2>
                <i class="fas fa-users"></i> Staff Performance Monitoring
                <span class="subtitle">Track staff performance and complaint resolution</span>
            </h2>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="number"><?php echo $overall_stats['total_staff']; ?></div>
                <div class="label">Total Staff</div>
            </div>
            <div class="stat-card">
                <div class="icon">📋</div>
                <div class="number"><?php echo $overall_stats['total_assigned']; ?></div>
                <div class="label">Total Assigned Complaints</div>
            </div>
            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="number"><?php echo $overall_resolution_rate; ?>%</div>
                <div class="label">Overall Resolution Rate</div>
            </div>
            <div class="stat-card">
                <div class="icon">⏳</div>
                <div class="number"><?php echo $overall_stats['total_pending']; ?></div>
                <div class="label">Pending Complaints</div>
            </div>
        </div>

        <!-- Top Performer -->
        <?php if ($top_performer && $top_performer['total'] > 0): ?>
        <div class="top-performer">
            <div class="badge">🏆 Top Performer</div>
            <div class="avatar">
                <?php if (!empty($top_performer['profile_pic']) && file_exists('../' . $top_performer['profile_pic'])): ?>
                    <img src="../<?php echo htmlspecialchars($top_performer['profile_pic']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($top_performer['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="info">
                <h4><?php echo htmlspecialchars($top_performer['name']); ?></h4>
                <div class="role"><?php echo htmlspecialchars($top_performer['role']); ?></div>
            </div>
            <div class="metrics">
                <div class="item">
                    <div class="num"><?php echo $top_performer['total']; ?></div>
                    <div class="lbl">Assigned</div>
                </div>
                <div class="item">
                    <div class="num"><?php echo $top_performer['resolved']; ?></div>
                    <div class="lbl">Resolved</div>
                </div>
                <div class="item">
                    <div class="num"><?php echo $top_performer['resolution_rate']; ?>%</div>
                    <div class="lbl">Resolution Rate</div>
                </div>
                <div class="item">
                    <div class="num" style="color: <?php echo $top_performer['rating'] == 'Excellent' ? '#10b981' : ($top_performer['rating'] == 'Good' ? '#f59e0b' : '#dc2626'); ?>;">
                        <?php echo $top_performer['rating_icon']; ?>
                    </div>
                    <div class="lbl">Rating</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Staff Table -->
        <div class="content-area">
            <h4><i class="fas fa-list"></i> All Staff Performance</h4>
            
            <?php if (empty($staff_data)): ?>
                <div style="text-align:center; padding:40px 0; color:#8ba0bc;">
                    <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                    No staff data available.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Role</th>
                            <th style="text-align:center;">Assigned</th>
                            <th style="text-align:center;">Resolved</th>
                            <th style="text-align:center;">Pending</th>
                            <th style="text-align:center;">In Progress</th>
                            <th style="text-align:center;">Escalated</th>
                            <th style="text-align:center;">Rate</th>
                            <th style="text-align:center;">Avg Response</th>
                            <th style="text-align:center;">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_data as $staff): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="staff-avatar">
                                        <?php if (!empty($staff['profile_pic']) && file_exists('../' . $staff['profile_pic'])): ?>
                                            <img src="../<?php echo htmlspecialchars($staff['profile_pic']); ?>" alt="Profile">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($staff['name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="staff-name"><?php echo htmlspecialchars($staff['name']); ?></div>
                                        <div class="staff-role"><?php echo htmlspecialchars($staff['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span style="font-size:0.7rem; background:#f0f4f9; padding:2px 10px; border-radius:20px;"><?php echo htmlspecialchars($staff['role']); ?></span></td>
                            <td style="text-align:center; font-weight:600;"><?php echo $staff['total']; ?></td>
                            <td style="text-align:center; color:#10b981; font-weight:600;"><?php echo $staff['resolved']; ?></td>
                            <td style="text-align:center; color:#f59e0b; font-weight:600;"><?php echo $staff['pending']; ?></td>
                            <td style="text-align:center; color:#3b82f6; font-weight:600;"><?php echo $staff['in_progress']; ?></td>
                            <td style="text-align:center; color:#dc2626; font-weight:600;"><?php echo $staff['escalated']; ?></td>
                            <td style="text-align:center; font-weight:600;">
                                <?php echo $staff['resolution_rate']; ?>%
                            </td>
                            <td style="text-align:center;">
                                <?php 
                                    if ($staff['avg_response'] > 0) {
                                        if ($staff['avg_response'] < 1) {
                                            echo round($staff['avg_response'] * 60) . ' min';
                                        } else {
                                            echo $staff['avg_response'] . 'h';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="rating-badge <?php echo $staff['rating_class']; ?>">
                                    <?php echo $staff['rating_icon']; ?> <?php echo $staff['rating']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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