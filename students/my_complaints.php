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
$email = $_SESSION['email'];

// ========== SEARCH, SORT, PAGINATION ==========
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$allowed_sort = ['complaint_number', 'title', 'category', 'status', 'created_at'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'created_at';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

$search_condition = '';
$search_params = [];
$search_types = '';

if (!empty($search)) {
    $search_condition = " AND (c.complaint_number LIKE ? OR c.title LIKE ? OR cat.name LIKE ?)";
    $search_pattern = "%$search%";
    $search_params = [$search_pattern, $search_pattern, $search_pattern];
    $search_types = 'sss';
}

// Count total
$count_sql = "SELECT COUNT(*) as total 
              FROM complaints c
              JOIN categories cat ON c.category_id = cat.id
              WHERE c.student_id = ? $search_condition";
$count_stmt = mysqli_prepare($conn, $count_sql);

if (!empty($search)) {
    mysqli_stmt_bind_param($count_stmt, "i" . $search_types, $student_id, ...$search_params);
} else {
    mysqli_stmt_bind_param($count_stmt, "i", $student_id);
}

mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_row = mysqli_fetch_assoc($count_result);
$total_complaints = $total_row['total'];
$total_pages = ceil($total_complaints / $limit);
mysqli_stmt_close($count_stmt);

// Fetch complaints
$sql = "SELECT c.id, c.complaint_number, c.title, cat.name AS category, 
               c.status, c.created_at
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.student_id = ? $search_condition
        ORDER BY $sort $order
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($search)) {
    $params = array_merge([$student_id], $search_params, [$limit, $offset]);
    $types = "i" . $search_types . "ii";
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "iii", $student_id, $limit, $offset);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$complaints = [];
while ($row = mysqli_fetch_assoc($result)) {
    $complaints[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

function sortUrl($column, $current_sort, $current_order) {
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    $params['page'] = 1;
    return '?' . http_build_query($params);
}

function sortIcon($column, $current_sort, $current_order) {
    if ($current_sort !== $column) {
        return '<i class="fas fa-sort" style="color: #a0b4c8; font-size: 0.7rem; margin-left: 4px;"></i>';
    }
    return $current_order === 'ASC' 
        ? '<i class="fas fa-sort-up" style="color: #1a56db; font-size: 0.7rem; margin-left: 4px;"></i>' 
        : '<i class="fas fa-sort-down" style="color: #1a56db; font-size: 0.7rem; margin-left: 4px;"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Complaints - IAA CFMS</title>
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

        .btn-view {
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
        .btn-view:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
            color: white;
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

        /* ---------- CONTENT AREA ---------- */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            min-height: 350px;
        }
        .content-area .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }
        .content-area h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0a2a5e;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .content-area h3 i {
            color: #1a56db;
        }
        .content-area .total-badge {
            font-size: 0.8rem;
            font-weight: 400;
            color: #6b85a0;
            background: #f0f4f9;
            padding: 4px 14px;
            border-radius: 30px;
        }

        /* ========== SEARCH BAR ========== */
        .search-section {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: #f0f4f9;
            border-radius: 30px;
            padding: 0 16px;
            border: 1.5px solid #e5edf5;
            transition: all 0.2s;
            min-width: 200px;
        }
        .search-box:focus-within {
            border-color: #1a56db;
            background: white;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
        }
        .search-box i {
            color: #8ba0bc;
            font-size: 0.9rem;
        }
        .search-box input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            font-size: 0.9rem;
            width: 100%;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .search-box input::placeholder {
            color: #a0b4c8;
        }
        .search-box .clear-btn {
            background: none;
            border: none;
            color: #8ba0bc;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 4px;
            display: none;
        }
        .search-box .clear-btn.visible {
            display: block;
        }
        .search-box .clear-btn:hover {
            color: #dc2626;
        }

        .search-btn {
            padding: 10px 24px;
            background: #1a56db;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .search-btn:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        .reset-btn {
            padding: 10px 20px;
            background: transparent;
            color: #6b85a0;
            border: 1.5px solid #e5edf5;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .reset-btn:hover {
            background: #f0f4f9;
            border-color: #8ba0bc;
        }

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
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
        }
        .complaints-table thead th:hover {
            background: #edf2f7;
        }
        .complaints-table thead th a {
            color: #4a5a7a;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .complaints-table thead th a:hover {
            color: #0a2a5e;
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
        .badge-closed { background: #e2e3e5; color: #383d41; }

        .empty-row td {
            text-align: center;
            padding: 50px 20px;
            color: #8ba0bc;
            font-size: 0.95rem;
        }
        .empty-row td i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 12px;
            color: #d4e2f7;
        }
        .empty-row td a {
            color: #1a56db;
            font-weight: 600;
            text-decoration: none;
        }
        .empty-row td a:hover {
            text-decoration: underline;
        }

        /* ========== PAGINATION ========== */
        .pagination-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f0f4f9;
        }
        .pagination-info {
            color: #6b85a0;
            font-size: 0.85rem;
        }
        .pagination-info strong {
            color: #0a2a5e;
        }
        .pagination-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .pagination-links a, .pagination-links span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #e5edf5;
            background: white;
            color: #4a5a7a;
        }
        .pagination-links a:hover {
            background: #f0f4f9;
            border-color: #1a56db;
            color: #1a56db;
        }
        .pagination-links .active {
            background: #1a56db;
            color: white;
            border-color: #1a56db;
        }
        .pagination-links .disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pagination-links .dots {
            border: none;
            background: transparent;
            cursor: default;
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

            .content-area { padding: 16px; border-radius: 16px; min-height: auto; }
            .content-area .header-section { flex-direction: column; align-items: stretch; gap: 12px; }
            .content-area h3 { font-size: 1.05rem; }
            .search-section { flex-direction: column; }
            .search-box { min-width: unset; width: 100%; }

            .complaints-table thead th,
            .complaints-table tbody td { padding: 8px 8px; font-size: 0.7rem; }
            .complaints-table thead th { font-size: 0.6rem; }
            .btn-view { padding: 4px 12px; font-size: 0.6rem; }
            .empty-row td { font-size: 0.8rem; padding: 30px 15px; }
            .empty-row td i { font-size: 2rem; }

            .pagination-section { flex-direction: column; align-items: center; }
            .pagination-info { text-align: center; }
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
            .content-area h3 { font-size: 0.9rem; }
            .search-box { padding: 0 12px; }
            .search-box input { padding: 8px 10px; font-size: 0.8rem; }
            .search-btn { padding: 8px 16px; font-size: 0.75rem; }
            .reset-btn { padding: 8px 14px; font-size: 0.75rem; }

            .complaints-table thead th,
            .complaints-table tbody td { padding: 6px 6px; font-size: 0.6rem; }
            .complaints-table thead th { font-size: 0.55rem; }
            .btn-view { padding: 3px 10px; font-size: 0.55rem; }
            .badge { font-size: 0.55rem; padding: 2px 8px; }
            .empty-row td { font-size: 0.7rem; padding: 20px 10px; }
            .empty-row td i { font-size: 1.5rem; }

            .pagination-links a, .pagination-links span { min-width: 32px; height: 32px; font-size: 0.75rem; padding: 0 8px; }

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
            .complaints-table thead th,
            .complaints-table tbody td { padding: 4px 4px; font-size: 0.5rem; }
            .complaints-table thead th { font-size: 0.5rem; }
            .btn-view { padding: 2px 8px; font-size: 0.5rem; }
            .badge { font-size: 0.5rem; padding: 2px 6px; }
            .content-area h3 { font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<!-- ========== UPDATED SIDEBAR ========== -->
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
        
        <!-- My Complaints (Active) -->
        <a href="my_complaints.php" class="menu-item active">
            <i class="fas fa-list-ul"></i><span>My Complaints</span>
        </a>
        
        <!-- Feedback -->
        <a href="my_feedback.php" class="menu-item">
            <i class="fas fa-comment-dots"></i><span>Feedback</span>
        </a>
        
        <!-- Announcements -->
        <a href="student_announcements.php" class="menu-item">
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
            <!-- Header with Search -->
            <div class="header-section">
                <h3>
                    <i class="fas fa-list-ul"></i> My Complaints
                    <span class="total-badge">Total: <?php echo $total_complaints; ?></span>
                </h3>
                
                <!-- Search Form -->
                <form method="GET" class="search-section" id="searchForm">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" id="searchInput" 
                               placeholder="Search by #, title, category..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="button" class="clear-btn <?php echo !empty($search) ? 'visible' : ''; ?>" 
                                id="clearSearch" title="Clear search">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="my_complaints.php" class="reset-btn">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Table -->
            <div class="table-responsive">
                <table class="complaints-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?php echo sortUrl('complaint_number', $sort, $order); ?>">
                                    Complaint #
                                    <?php echo sortIcon('complaint_number', $sort, $order); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('title', $sort, $order); ?>">
                                    Title
                                    <?php echo sortIcon('title', $sort, $order); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('category', $sort, $order); ?>">
                                    Category
                                    <?php echo sortIcon('category', $sort, $order); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('status', $sort, $order); ?>">
                                    Status
                                    <?php echo sortIcon('status', $sort, $order); ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo sortUrl('created_at', $sort, $order); ?>">
                                    Date
                                    <?php echo sortIcon('created_at', $sort, $order); ?>
                                </a>
                            </th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($complaints) > 0): ?>
                            <?php foreach ($complaints as $c): ?>
                                <?php
                                $statusClass = '';
                                switch ($c['status']) {
                                    case 'pending': $statusClass = 'badge-pending'; break;
                                    case 'in_progress': $statusClass = 'badge-in-progress'; break;
                                    case 'resolved': $statusClass = 'badge-resolved'; break;
                                    case 'escalated': $statusClass = 'badge-escalated'; break;
                                    case 'closed': $statusClass = 'badge-closed'; break;
                                    default: $statusClass = 'badge-pending';
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($c['complaint_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($c['title']); ?></td>
                                    <td><?php echo htmlspecialchars($c['category']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                                    <td><a href="view_complaint.php?id=<?php echo $c['id']; ?>" class="btn-view">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="6">
                                    <i class="fas fa-inbox"></i>
                                    <?php if (!empty($search)): ?>
                                        No complaints found matching "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                        <br><a href="my_complaints.php">Clear search</a>
                                    <?php else: ?>
                                        You have not submitted any complaints yet.
                                        <br><a href="new_complaint.php">Create your first complaint</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Showing <strong><?php echo count($complaints); ?></strong> of <strong><?php echo $total_complaints; ?></strong> complaints
                    <?php if (!empty($search)): ?>
                        <span style="color: #8ba0bc;">(filtered)</span>
                    <?php endif; ?>
                </div>
                <div class="pagination-links">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="dots">…</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="dots">…</span>';
                        }
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
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

    // ---------- CLEAR SEARCH ----------
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearch');
    const searchForm = document.getElementById('searchForm');

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            this.classList.remove('visible');
            searchForm.submit();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
        });
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