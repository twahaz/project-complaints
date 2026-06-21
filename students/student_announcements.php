<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/connection.php';

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$reg_number = $_SESSION['reg_number'];
$student_email = $_SESSION['email'];

// Get student's department and year of study
$student_dept_id = $_SESSION['department_id'] ?? null;
$student_year = $_SESSION['year_of_study'] ?? null;

if (!$student_dept_id || !$student_year) {
    $fetch_sql = "SELECT department_id, year_of_study FROM users WHERE id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    if ($fetch_stmt) {
        mysqli_stmt_bind_param($fetch_stmt, "i", $student_id);
        mysqli_stmt_execute($fetch_stmt);
        $fetch_result = mysqli_stmt_get_result($fetch_stmt);
        $user_data = mysqli_fetch_assoc($fetch_result);
        $student_dept_id = $user_data['department_id'] ?? null;
        $student_year = $user_data['year_of_study'] ?? null;
        $_SESSION['department_id'] = $student_dept_id;
        $_SESSION['year_of_study'] = $student_year;
        mysqli_stmt_close($fetch_stmt);
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total announcements count
$count_sql = "SELECT COUNT(*) as total FROM announcements 
              WHERE is_active = 1 
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              AND (target_type = 'all' 
                   OR target_type = 'students'
                   OR (target_type = 'department' AND target_id = ?)
                   OR (target_type = 'individual' AND target_id = ?))";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "ii", $student_dept_id, $student_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_row = mysqli_fetch_assoc($count_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($count_stmt);

// Get announcements
$announcement_sql = "SELECT a.*, u.full_name as creator_name, u.role as creator_role
                     FROM announcements a
                     LEFT JOIN users u ON a.created_by = u.id
                     WHERE a.is_active = 1 
                     AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
                     AND (a.target_type = 'all' 
                          OR a.target_type = 'students'
                          OR (a.target_type = 'department' AND a.target_id = ?)
                          OR (a.target_type = 'individual' AND a.target_id = ?))
                     ORDER BY a.created_at DESC
                     LIMIT ? OFFSET ?";
$ann_stmt = mysqli_prepare($conn, $announcement_sql);
mysqli_stmt_bind_param($ann_stmt, "iiii", $student_dept_id, $student_id, $limit, $offset);
mysqli_stmt_execute($ann_stmt);
$announcements_result = mysqli_stmt_get_result($ann_stmt);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_announcements,
    COUNT(DISTINCT created_by) as total_creators
    FROM announcements 
    WHERE is_active = 1 
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    AND (target_type = 'all' 
         OR target_type = 'students'
         OR (target_type = 'department' AND target_id = ?)
         OR (target_type = 'individual' AND target_id = ?))";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "ii", $student_dept_id, $student_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

// Helper function to get creator badge class
function getCreatorBadgeClass($role) {
    return match($role) {
        'admin' => 'creator-admin',
        'hod' => 'creator-hod',
        'dean' => 'creator-dean',
        'accountant' => 'creator-accountant',
        'it_officer' => 'creator-it_officer',
        'deputy_rector' => 'creator-deputy',
        'rector' => 'creator-rector',
        default => 'creator-other'
    };
}

function getCreatorLabel($role) {
    return match($role) {
        'admin' => 'Administrator',
        'hod' => 'HOD',
        'dean' => 'Dean',
        'accountant' => 'Accountant',
        'it_officer' => 'IT Officer',
        'deputy_rector' => 'Deputy Rector',
        'rector' => 'Rector',
        default => ucfirst(str_replace('_', ' ', $role))
    };
}

function getTargetLabel($target_type) {
    return match($target_type) {
        'all' => '📢 Everyone',
        'students' => '🎓 Students',
        'department' => '🏛️ Department',
        'individual' => '👤 Personal',
        default => '📢 Everyone'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Announcements - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           UI SANA NA DASHBOARD
           ============================================ */
        
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #ebf4fe;
            display: flex;
            height: 100vh;
            overflow: hidden;
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

        /* ========== SIDEBAR MENU - UPDATED ========== */
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

        .create-btn-border {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: #1a56db;
            color: white;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
            border: none;
            flex-shrink: 0;
        }
        .create-btn-border:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35);
            color: white;
        }
        .create-btn-border i { font-size: 1.1rem; }

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

        /* ---------- CONTENT AREA ---------- */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 350px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .content-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0a2a5e;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .content-header h3 i {
            color: #f59e0b;
        }

        .stats-badge {
            background: #f0f4ff;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.8rem;
            color: #0a2a5e;
            font-weight: 500;
            border: 1px solid #dbeafe;
        }
        .stats-badge i {
            margin-right: 6px;
            color: #1a56db;
        }

        /* ---------- ANNOUNCEMENT CARDS ---------- */
        .announcement-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            border-left: 4px solid #1a56db;
            transition: all 0.2s;
        }
        .announcement-card:hover {
            transform: translateX(4px);
            background: #f0f4ff;
            box-shadow: 0 4px 12px rgba(10,42,94,0.06);
        }
        .announcement-card:last-child {
            margin-bottom: 0;
        }

        .announcement-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .announcement-title .target-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
            white-space: nowrap;
        }

        .announcement-message {
            color: #4a5a7a;
            line-height: 1.7;
            margin-bottom: 12px;
            font-size: 0.92rem;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.75rem;
            color: #8ba0bc;
            padding-top: 10px;
            border-top: 1px solid #e5edf5;
        }
        .creator-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .creator-info i {
            color: #6b85a0;
        }
        .creator-info strong {
            color: #0a2a5e;
            font-weight: 600;
        }

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
        .creator-it_officer { background: #dcfce7; color: #166534; }
        .creator-deputy { background: #ede9fe; color: #6d28d9; }
        .creator-rector { background: #fce7f3; color: #be185d; }
        .creator-other { background: #e2e8f0; color: #475569; }

        .announcement-meta .date-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .announcement-meta .date-info .expiry {
            color: #dc2626;
            font-weight: 500;
        }

        /* ---------- EMPTY STATE ---------- */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #8ba0bc;
        }
        .no-data i {
            font-size: 3rem;
            display: block;
            margin-bottom: 16px;
            color: #d4e2f7;
        }
        .no-data p {
            font-size: 0.95rem;
        }
        .no-data .sub-text {
            font-size: 0.85rem;
            color: #a0b4c8;
            margin-top: 4px;
        }

        /* ---------- PAGINATION ---------- */
        .pagination-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f0f4f9;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
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
        .pagination .disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pagination .dots {
            border: none;
            background: transparent;
            cursor: default;
        }

        .pagination-info {
            font-size: 0.8rem;
            color: #8ba0bc;
        }
        .pagination-info strong {
            color: #0a2a5e;
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
        .loading-text i {
            margin-right: 8px;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */

        @media (max-width: 1024px) {
            .dashboard-body { padding: 20px 24px; }
            .top-bar { padding: 14px 24px; }
            .content-area { padding: 20px 24px; min-height: auto; }
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
            .create-btn-border span { display: none; }
            .create-btn-border { padding: 10px 16px; gap: 6px; font-size: 0.85rem; }
            .create-btn-border i { margin: 0; font-size: 1rem; }

            .profile-details .name { font-size: 0.8rem; }
            .profile-details .reg { font-size: 0.6rem; }
            .profile-pic { width: 36px; height: 36px; }

            .content-area { padding: 16px; border-radius: 16px; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .content-header h3 { font-size: 1.05rem; }
            .stats-badge { font-size: 0.75rem; padding: 6px 14px; }

            .announcement-card { padding: 16px 18px; border-radius: 14px; }
            .announcement-title { font-size: 0.95rem; }
            .announcement-message { font-size: 0.85rem; }
            .announcement-meta { flex-direction: column; align-items: flex-start; gap: 8px; font-size: 0.7rem; }
            .creator-info { flex-wrap: wrap; }

            .pagination a, .pagination span { min-width: 34px; height: 34px; font-size: 0.75rem; padding: 0 10px; }
            .pagination-info { font-size: 0.7rem; }

            .no-data { padding: 40px 15px; }
            .no-data i { font-size: 2.5rem; }
            .no-data p { font-size: 0.85rem; }
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
            .create-btn-border { padding: 8px 12px; font-size: 0.75rem; }
            .create-btn-border i { font-size: 0.85rem; }
            .profile-pic { width: 32px; height: 32px; }
            .profile-details .name { font-size: 0.7rem; }
            .profile-details .reg { font-size: 0.5rem; }

            .content-area { padding: 12px; border-radius: 12px; }
            .content-header h3 { font-size: 0.9rem; }
            .stats-badge { font-size: 0.65rem; padding: 4px 10px; }

            .announcement-card { padding: 14px; border-radius: 12px; }
            .announcement-title { font-size: 0.85rem; }
            .announcement-title .target-badge { font-size: 0.5rem; padding: 1px 8px; }
            .announcement-message { font-size: 0.8rem; }
            .announcement-meta { font-size: 0.65rem; }

            .creator-badge { font-size: 0.55rem; padding: 1px 8px; }

            .pagination a, .pagination span { min-width: 30px; height: 30px; font-size: 0.7rem; padding: 0 8px; }
            .pagination-info { font-size: 0.65rem; }

            .no-data { padding: 30px 10px; }
            .no-data i { font-size: 2rem; }
            .no-data p { font-size: 0.8rem; }

            .modal-container { padding: 24px 20px; }
            .modal-container h3 { font-size: 1rem; }
            .modal-btn { padding: 8px 20px; font-size: 0.8rem; min-width: 80px; }

            .loading-content { padding: 30px 30px; }
            .spinner { width: 36px; height: 36px; }
            .loading-text { font-size: 0.85rem; }
        }

        @media (max-width: 380px) {
            .sidebar { width: 55px !important; }
            .sidebar:not(.collapsed) { width: 55px !important; }
            .sidebar.collapsed { width: 55px !important; }
            .main-content { margin-left: 55px !important; }
            .sidebar.collapsed ~ .main-content { margin-left: 55px !important; }
            .menu-item i { font-size: 0.95rem !important; }

            .content-header h3 { font-size: 0.8rem; }
            .announcement-title { font-size: 0.8rem; }
            .announcement-message { font-size: 0.75rem; }
            .announcement-meta { font-size: 0.6rem; }
        }
    </style>
</head>
<body>

<!-- ========== SIDEBAR (UPDATED TO MATCH MY_COMPLAINTS.PHP) ========== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="row-cfms">
            <span class="brand">CFMS <span>| Student</span></span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">Student Portal</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>

    <div class="sidebar-menu">
        <!-- Dashboard -->
        <a href="student_dashboard.php" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        
        <!-- New Complaint -->
        <a href="new_complaint.php" class="menu-item">
            <i class="fas fa-plus-circle"></i><span>New Complaint</span>
        </a>
        
        <!-- My Complaints -->
        <a href="my_complaints.php" class="menu-item">
            <i class="fas fa-list-ul"></i><span>My Complaints</span>
        </a>
        
        <!-- Feedback -->
        <a href="my_feedback.php" class="menu-item">
            <i class="fas fa-comment-dots"></i><span>Feedback</span>
        </a>
        
        <!-- Announcements (Active) -->
        <a href="student_announcements.php" class="menu-item active">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        
        <!-- Profile -->
        <a href="profile.php" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        
        <!-- Change Password -->
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
        <a href="new_complaint.php" class="create-btn-border">
            <i class="fas fa-plus-circle"></i><span> Create New Complaint</span>
        </a>
        <div class="profile-info">
            <div class="profile-pic">
                <?php if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="reg"><?php echo htmlspecialchars($reg_number); ?></div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD BODY -->
    <div class="dashboard-body">
        <div class="content-area">
            <!-- Header -->
            <div class="content-header">
                <h3>
                    <i class="fas fa-bullhorn"></i> Announcements
                </h3>
                <div class="stats-badge">
                    <i class="fas fa-envelope-open-text"></i> 
                    <?php echo $stats['total_announcements']; ?> Announcements 
                    from <?php echo $stats['total_creators']; ?> staff
                </div>
            </div>

            <!-- Announcements List -->
            <?php if (mysqli_num_rows($announcements_result) == 0): ?>
                <div class="no-data">
                    <i class="fas fa-bell-slash"></i>
                    <p>No announcements at the moment.</p>
                    <p class="sub-text">Check back later for updates from the administration.</p>
                </div>
            <?php else: ?>
                <?php while ($ann = mysqli_fetch_assoc($announcements_result)): ?>
                    <div class="announcement-card">
                        <div class="announcement-title">
                            <?php echo htmlspecialchars($ann['title']); ?>
                            <span class="target-badge">
                                <?php echo getTargetLabel($ann['target_type']); ?>
                            </span>
                        </div>
                        <div class="announcement-message">
                            <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                        </div>
                        <div class="announcement-meta">
                            <div class="creator-info">
                                <i class="fas fa-user-circle"></i>
                                <span>Posted by: <strong><?php echo htmlspecialchars($ann['creator_name']); ?></strong></span>
                                <span class="creator-badge <?php echo getCreatorBadgeClass($ann['creator_role']); ?>">
                                    <?php echo getCreatorLabel($ann['creator_role']); ?>
                                </span>
                            </div>
                            <div class="date-info">
                                <span>
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('d M Y, h:i A', strtotime($ann['created_at'])); ?>
                                </span>
                                <?php if ($ann['expiry_date']): ?>
                                    <span class="expiry">
                                        <i class="far fa-hourglass-half"></i> 
                                        Expires: <?php echo date('d M Y', strtotime($ann['expiry_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php mysqli_stmt_close($ann_stmt); ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-section">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1"><i class="fas fa-angle-double-left"></i></a>
                            <a href="?page=<?php echo $page-1; ?>"><i class="fas fa-angle-left"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="disabled"><i class="fas fa-angle-left"></i></span>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<span class="dots">…</span>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <span class="dots">…</span>
                            <a href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>"><i class="fas fa-angle-right"></i></a>
                            <a href="?page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-right"></i></span>
                            <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="pagination-info">
                        Showing <strong><?php echo (($page - 1) * $limit) + 1; ?></strong> to 
                        <strong><?php echo min($page * $limit, $total_records); ?></strong> of 
                        <strong><?php echo $total_records; ?></strong> announcements
                    </div>
                </div>
                <?php endif; ?>
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