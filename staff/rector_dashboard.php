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

// Handle reply to complaint (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_complaint'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $message = trim($_POST['message']);
    $new_status = $_POST['status'] ?? 'resolved';
    if (!empty($message)) {
        $insert_sql = "INSERT INTO responses (complaint_id, user_id, message) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "iis", $complaint_id, $rector_id, $message);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
        $update_sql = "UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $complaint_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        $_SESSION['flash_message'] = "Reply sent successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: rector_dashboard.php?page=complaints&sub=all");
        exit();
    } else {
        $_SESSION['flash_message'] = "Message cannot be empty.";
        $_SESSION['flash_type'] = "error";
        header("Location: rector_dashboard.php?page=view-complaint&id=" . $complaint_id);
        exit();
    }
}

// Handle status update only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $complaint_id = intval($_POST['complaint_id']);
    $new_status = $_POST['status'];
    $update_sql = "UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $complaint_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    $_SESSION['flash_message'] = "Status updated to " . ucfirst($new_status);
    $_SESSION['flash_type'] = "success";
    header("Location: rector_dashboard.php?page=complaints&sub=all");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_full_name = trim($_POST['full_name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $errors = [];
    if (empty($new_full_name)) $errors[] = "Full name is required.";
    $new_profile_picture = $_SESSION['profile_picture'] ?? '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $max_size = 2 * 1024 * 1024;
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF images are allowed.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "Image size must be less than 2MB.";
        } else {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'rector_' . $rector_id . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $new_profile_picture = 'uploads/profiles/' . $new_filename;
                if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])) {
                    unlink('../' . $_SESSION['profile_picture']);
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }
    if (empty($errors)) {
        $update_sql = "UPDATE users SET full_name = ?, phone_number = ?, profile_picture = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sssi", $new_full_name, $new_phone, $new_profile_picture, $rector_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        $_SESSION['full_name'] = $new_full_name;
        $_SESSION['profile_picture'] = $new_profile_picture;
        $_SESSION['flash_message'] = "Profile updated successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: rector_dashboard.php?page=profile");
        exit();
    } else {
        $_SESSION['flash_message'] = implode("<br>", $errors);
        $_SESSION['flash_type'] = "error";
        header("Location: rector_dashboard.php?page=profile");
        exit();
    }
}

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $errors = [];
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($errors)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pass_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $pass_stmt = mysqli_prepare($conn, $pass_sql);
        mysqli_stmt_bind_param($pass_stmt, "si", $hashed, $rector_id);
        mysqli_stmt_execute($pass_stmt);
        mysqli_stmt_close($pass_stmt);
        $_SESSION['flash_message'] = "Password changed successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: rector_dashboard.php?page=profile");
        exit();
    } else {
        $_SESSION['flash_message'] = implode("<br>", $errors);
        $_SESSION['flash_type'] = "error";
        header("Location: rector_dashboard.php?page=profile");
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

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$sub_page = isset($_GET['sub']) ? $_GET['sub'] : 'all';
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Filters & pagination for complaints lists
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$current_list_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = 20;
$offset = ($current_list_page - 1) * $limit;

// ========== DATA FOR CHARTS ==========
$current_year = date('Y');
$months = [];
for ($i = 1; $i <= 12; $i++) {
    $months[] = date('M', mktime(0,0,0,$i,1,$current_year));
}

// 1. Monthly trends (total complaints per month, assigned to rector)
$monthly_counts = [];
for ($m = 1; $m <= 12; $m++) {
    $start_date = "$current_year-$m-01";
    $end_date = date('Y-m-t', strtotime($start_date)) . ' 23:59:59';
    $count_sql = "SELECT COUNT(*) as cnt FROM complaints 
                  WHERE assigned_to = ? 
                  AND created_at BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, "iss", $rector_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    $monthly_counts[] = (int)$row['cnt'];
    mysqli_stmt_close($stmt);
}

// 2. Complaints by category (total per category, assigned to rector, current year)
$cat_sql = "SELECT c.id, c.name, COUNT(co.id) as total
            FROM categories c
            LEFT JOIN complaints co ON co.category_id = c.id 
                AND co.assigned_to = ? 
                AND YEAR(co.created_at) = ?
            WHERE c.is_active = 1
            GROUP BY c.id, c.name
            ORDER BY c.name";
$cat_stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($cat_stmt, "ii", $rector_id, $current_year);
mysqli_stmt_execute($cat_stmt);
$cat_result = mysqli_stmt_get_result($cat_stmt);
$category_labels = [];
$category_data = [];
$category_colors = [];
while ($row = mysqli_fetch_assoc($cat_result)) {
    $category_labels[] = $row['name'];
    $category_data[] = (int)$row['total'];
    // generate random soft colors
    $category_colors[] = 'rgba(' . rand(100,200) . ',' . rand(100,200) . ',' . rand(100,200) . ',0.7)';
}
mysqli_stmt_close($cat_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Rector Dashboard - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ========== RESET & BASE ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f4f7fc;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            width: 280px;
            background: #0047AB;
            color: white;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: width 0.3s ease;
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
            z-index: 100;
        }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header {
            padding: 24px 16px 16px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 20px;
        }
        .row-cfms {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand { font-size: 1.6rem; font-weight: 700; color: white; white-space: nowrap; }
        .toggle-inline {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .row-tagline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }
        .tagline { font-size: 0.7rem; color: rgba(255,255,255,0.75); white-space: nowrap; }
        .toggle-standalone {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar:not(.collapsed) .toggle-inline { display: none; }
        .sidebar.collapsed .row-tagline { display: none; }
        .sidebar.collapsed .row-cfms {
            flex-direction: column;
            justify-content: center;
            gap: 12px;
        }
        .sidebar.collapsed .brand { font-size: 1rem; }
        .sidebar.collapsed .toggle-inline { display: flex; }
        .sidebar-menu {
            flex: 1;
            padding: 0 12px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 12px;
            margin: 10px 0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
        }
        .menu-item i { width: 28px; font-size: 1.3rem; text-align: center; }
        .menu-item span { font-size: 0.95rem; }
        .sidebar.collapsed .menu-item span { opacity: 0; visibility: hidden; width: 0; }
        .sidebar.collapsed .menu-item { justify-content: center; padding: 14px 0; }
        .menu-item:hover { background: rgba(255,255,255,0.12); color: white; }
        .menu-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .logout-item {
            margin-top: auto;
            margin-bottom: 28px;
            border-top: 1px solid rgba(255,255,255,0.15);
            padding-top: 20px;
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 80px; }
        .top-bar {
            background: white;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,71,171,0.1);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .welcome-message { font-size: 1.2rem; font-weight: 500; color: #0047AB; }
        .profile-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .profile-pic {
            width: 44px;
            height: 44px;
            background: #0047AB;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            overflow: hidden;
        }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-details { text-align: right; }
        .profile-details .name { font-weight: 700; color: #1f2c40; }
        .dashboard-body { padding: 32px 36px; }
        .summary-row {
            display: flex;
            gap: 28px;
            margin-bottom: 42px;
            flex-wrap: wrap;
        }
        .summary-card {
            background: white;
            border-radius: 24px;
            padding: 24px 20px;
            flex: 1;
            min-width: 180px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,71,171,0.08);
        }
        .summary-card i { font-size: 2.2rem; color: #0047AB; margin-bottom: 12px; }
        .summary-card .number { font-size: 2.2rem; font-weight: 800; color: #1f2c40; }
        .summary-card .label { color: #5a6e8a; font-size: 0.85rem; }
        .content-area {
            background: white;
            border-radius: 28px;
            padding: 28px 32px;
            min-height: 300px;
            border: 1px solid rgba(0,71,171,0.06);
        }
        .chart-row {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 20px;
        }
        .chart-box {
            flex: 1;
            min-width: 300px;
        }
        .chart-box h4 {
            margin-bottom: 15px;
            text-align: center;
            color: #1f2c40;
        }
        canvas {
            max-width: 100%;
            height: auto;
        }
        /* Filters bar, tables, etc. (same as before) */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group label { display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 5px; }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
        }
        .filter-group button {
            background: #0047AB;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 12px;
            cursor: pointer;
        }
        .complaints-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            font-size: 0.9rem;
        }
        .complaints-table th,
        .complaints-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }
        .complaints-table th {
            background: #f8fafc;
            font-weight: 600;
            white-space: nowrap;
        }
        .complaints-table td {
            white-space: nowrap;
        }
        .complaints-table td:first-child,
        .complaints-table th:first-child {
            padding-left: 20px;
        }
        .complaints-table td:last-child,
        .complaints-table th:last-child {
            padding-right: 20px;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 20px;
            margin-top: 20px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-in-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #d1fae5; color: #065f46; }
        .badge-escalated { background: #fee2e2; color: #991b1b; }
        .btn-sm {
            padding: 6px 14px;
            border-radius: 30px;
            background: #0047AB;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-block;
        }
        .pagination {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            text-decoration: none;
            color: #0047AB;
        }
        .pagination .current { background: #0047AB; color: white; border-color: #0047AB; }
        .complaint-detail-grid {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px,1fr));
            gap: 16px;
        }
        .description-box {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .conversation {
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 8px;
        }
        .message {
            background: #f9fafb;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 16px;
            border-left: 4px solid #0047AB;
        }
        .message.student-message { background: #e0f2fe; border-left-color: #2ecc71; }
        .message.staff-message { background: #f1f5f9; border-left-color: #0047AB; }
        .reply-section, .status-section {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
        }
        .btn-submit {
            background: #0047AB;
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .alert { padding: 12px 16px; border-radius: 16px; margin-bottom: 24px; }
        .alert.success { background: #e0f2e9; color: #1e7b4c; border-left: 4px solid #1e7b4c; }
        .alert.error { background: #fee2e2; color: #b91c1c; border-left: 4px solid #b91c1c; }

        /* SLA Card Styles */
        .sla-card {
            background: linear-gradient(135deg, #f0f4ff 0%, #e9effa 100%);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            border-left: 5px solid #0047AB;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .sla-title {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0047AB;
        }
        .sla-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }
        .sla-item {
            flex: 1;
            min-width: 180px;
        }
        .sla-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #2c3e66;
            letter-spacing: 0.5px;
        }
        .sla-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e2a47;
            margin-top: 4px;
        }
        .deadline-timer {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 700;
            background: #ffffffaa;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
        }
        .sla-overdue {
            color: #b91c1c;
            background: #fee2e2;
            border-radius: 40px;
            padding: 4px 12px;
            display: inline-block;
            font-weight: 600;
        }
        .sla-resolved {
            color: #065f46;
            background: #d1fae5;
            border-radius: 40px;
            padding: 4px 12px;
            display: inline-block;
        }

        /* Tab styles for complaints */
        .complaints-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: 0.2s;
            color: #5a6e8a;
            text-decoration: none;
        }
        .tab-btn.active {
            background: #0047AB;
            color: white;
        }
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
            border-radius: 28px;
            max-width: 380px;
            width: 90%;
            padding: 28px 24px;
            text-align: center;
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        }
        .modal-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 20px;
        }
        .modal-btn {
            padding: 10px 24px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .modal-btn.confirm { background: #0047AB; color: white; }
        .modal-btn.cancel { background: #e0e7f0; color: #1f2c40; }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1100;
            visibility: hidden;
            opacity: 0;
        }
        .loading-overlay.active { visibility: visible; opacity: 1; }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e0e7f0;
            border-top-color: #0047AB;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { margin-top: 16px; font-weight: 500; color: #0047AB; }
        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .brand { font-size: 1rem !important; }
            .row-tagline, .tagline, .menu-item span { display: none; }
            .row-cfms { flex-direction: column !important; gap: 12px; }
            .toggle-inline { display: flex !important; }
            .main-content { margin-left: 80px; }
            .dashboard-body { padding: 20px; }
            .top-bar { padding: 12px 20px; }
            .welcome-message { font-size: 1rem; }
            .filters-bar { flex-direction: column; }
            .complaint-detail-grid { grid-template-columns: 1fr; }
            .summary-card { min-width: 140px; padding: 18px 12px; }
            .complaints-table th, .complaints-table td { padding: 10px 8px; font-size: 0.8rem; white-space: nowrap; }
            .complaints-tabs { flex-wrap: wrap; }
            .sla-stats { flex-direction: column; gap: 12px; }
            .chart-row { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="row-cfms">
            <span class="brand">CFMS</span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">Rector Portal</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="?page=dashboard" class="menu-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="?page=complaints&sub=all" class="menu-item <?php echo $page == 'complaints' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i><span>Complaints</span>
        </a>
        <a href="?page=escalation_center" class="menu-item <?php echo $page == 'escalation_center' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i><span>Escalation Center</span>
        </a>
        <a href="?page=staff_monitoring" class="menu-item <?php echo $page == 'staff_monitoring' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i><span>Staff Monitoring</span>
        </a>
        <a href="?page=analytics" class="menu-item <?php echo $page == 'analytics' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i><span>Analytics & Reports</span>
        </a>
        <a href="?page=announcements" class="menu-item <?php echo $page == 'announcements' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        <a href="?page=notifications" class="menu-item <?php echo $page == 'notifications' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i><span>Notifications</span>
        </a>
        <a href="?page=profile" class="menu-item <?php echo $page == 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i><span>Change Profile</span>
        </a>
    </div>
    <div class="logout-item">
        <div class="menu-item" id="logoutBtn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <div class="welcome-message">Welcome, <span><?php echo htmlspecialchars($rector_name); ?></span></div>
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

    <div class="dashboard-body">
        <?php if ($flash_message): ?>
            <div class="alert <?php echo $flash_type; ?>"><?php echo $flash_message; ?></div>
        <?php endif; ?>

        <!-- DASHBOARD (with two graphs) -->
        <?php if ($page == 'dashboard'): ?>
            <?php
            $stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
                FROM complaints WHERE assigned_to = ?";
            $stats_stmt = mysqli_prepare($conn, $stats_sql);
            mysqli_stmt_bind_param($stats_stmt, "i", $rector_id);
            mysqli_stmt_execute($stats_stmt);
            $stats_result = mysqli_stmt_get_result($stats_stmt);
            $stats = mysqli_fetch_assoc($stats_result);
            mysqli_stmt_close($stats_stmt);
            ?>
            <div class="summary-row">
                <div class="summary-card"><i class="fas fa-file-alt"></i><div class="number"><?php echo $stats['total']; ?></div><div class="label">Total Complaints</div></div>
                <div class="summary-card"><i class="fas fa-clock"></i><div class="number"><?php echo $stats['pending']; ?></div><div class="label">Pending</div></div>
                <div class="summary-card"><i class="fas fa-spinner"></i><div class="number"><?php echo $stats['in_progress']; ?></div><div class="label">In Progress</div></div>
                <div class="summary-card"><i class="fas fa-check-circle"></i><div class="number"><?php echo $stats['resolved']; ?></div><div class="label">Resolved</div></div>
                <div class="summary-card"><i class="fas fa-exclamation-triangle"></i><div class="number"><?php echo $stats['escalated']; ?></div><div class="label">Escalated</div></div>
            </div>
            <div class="content-area">
                <div class="chart-row">
                    <div class="chart-box">
                        <h4>Monthly Trends (<?php echo $current_year; ?>)</h4>
                        <canvas id="monthlyTrendsChart" width="400" height="300"></canvas>
                    </div>
                    <div class="chart-box">
                        <h4>Complaints by Category (<?php echo $current_year; ?>)</h4>
                        <canvas id="categoryChart" width="400" height="300"></canvas>
                    </div>
                </div>
                <p class="text-muted" style="margin-top: 20px; font-size:0.8rem; color:#5a6e8a; text-align:center;">
                    <i class="fas fa-info-circle"></i> Data inaitwa kwa misingi ya complaints zilizopewa Rector (assigned to you) kwa mwaka <?php echo $current_year; ?>.
                </p>
            </div>

            <script>
                // Monthly trends (bar chart)
                const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($months); ?>,
                        datasets: [{
                            label: 'Number of Complaints',
                            data: <?php echo json_encode($monthly_counts); ?>,
                            backgroundColor: 'rgba(0, 71, 171, 0.6)',
                            borderColor: 'rgba(0, 71, 171, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Number of Complaints' }, ticks: { stepSize: 1, precision: 0 } },
                            x: { title: { display: true, text: 'Month' } }
                        }
                    }
                });

                // Complaints by category (pie chart)
                const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                new Chart(categoryCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($category_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($category_data); ?>,
                            backgroundColor: <?php echo json_encode($category_colors); ?>,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                            tooltip: { callbacks: { label: function(context) { return context.label + ': ' + context.raw + ' complaints'; } } }
                        }
                    }
                });
            </script>

        <!-- COMPLAINTS (with tabs) -->
        <?php elseif ($page == 'complaints'): ?>
            <div class="content-area">
                <div class="complaints-tabs">
                    <a href="?page=complaints&sub=all" class="tab-btn <?php echo $sub_page == 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?page=complaints&sub=pending" class="tab-btn <?php echo $sub_page == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?page=complaints&sub=escalated" class="tab-btn <?php echo $sub_page == 'escalated' ? 'active' : ''; ?>">Escalated</a>
                    <a href="?page=complaints&sub=resolved" class="tab-btn <?php echo $sub_page == 'resolved' ? 'active' : ''; ?>">Resolved</a>
                </div>
                <?php
                $status_filter = '';
                if ($sub_page == 'pending') $status_filter = "AND status = 'pending'";
                elseif ($sub_page == 'escalated') $status_filter = "AND status = 'escalated'";
                elseif ($sub_page == 'resolved') $status_filter = "AND status = 'resolved'";
                $where = "assigned_to = ? $status_filter";
                if ($priority_filter) $where .= " AND priority = ?";
                if ($search) $where .= " AND (complaint_number LIKE ? OR student_id IN (SELECT id FROM users WHERE full_name LIKE ?))";
                $order_by = match($sort) {
                    'oldest' => 'created_at ASC',
                    'priority_high' => "FIELD(priority, 'high','medium','low')",
                    'priority_low' => "FIELD(priority, 'low','medium','high')",
                    default => 'created_at DESC',
                };
                $count_sql = "SELECT COUNT(*) as total FROM complaints WHERE $where";
                $count_stmt = mysqli_prepare($conn, $count_sql);
                if ($priority_filter && $search) {
                    $search_param = "%$search%";
                    mysqli_stmt_bind_param($count_stmt, "iss", $rector_id, $priority_filter, $search_param);
                } elseif ($priority_filter) {
                    mysqli_stmt_bind_param($count_stmt, "is", $rector_id, $priority_filter);
                } elseif ($search) {
                    $search_param = "%$search%";
                    mysqli_stmt_bind_param($count_stmt, "is", $rector_id, $search_param);
                } else {
                    mysqli_stmt_bind_param($count_stmt, "i", $rector_id);
                }
                mysqli_stmt_execute($count_stmt);
                $total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
                mysqli_stmt_close($count_stmt);
                $total_pages = ceil($total_rows / $limit);
                $data_sql = "SELECT c.id, c.complaint_number, c.title, c.status, c.created_at, c.updated_at, u.full_name, c.priority
                            FROM complaints c
                            JOIN users u ON c.student_id = u.id
                            WHERE $where
                            ORDER BY $order_by
                            LIMIT ? OFFSET ?";
                $data_stmt = mysqli_prepare($conn, $data_sql);
                if ($priority_filter && $search) {
                    $search_param = "%$search%";
                    mysqli_stmt_bind_param($data_stmt, "issii", $rector_id, $priority_filter, $search_param, $limit, $offset);
                } elseif ($priority_filter) {
                    mysqli_stmt_bind_param($data_stmt, "isii", $rector_id, $priority_filter, $limit, $offset);
                } elseif ($search) {
                    $search_param = "%$search%";
                    mysqli_stmt_bind_param($data_stmt, "isii", $rector_id, $search_param, $limit, $offset);
                } else {
                    mysqli_stmt_bind_param($data_stmt, "iii", $rector_id, $limit, $offset);
                }
                mysqli_stmt_execute($data_stmt);
                $data_result = mysqli_stmt_get_result($data_stmt);
                ?>
                <form method="GET" class="filters-bar">
                    <input type="hidden" name="page" value="complaints">
                    <input type="hidden" name="sub" value="<?php echo $sub_page; ?>">
                    <div class="filter-group"><label>Search (Student / Complaint #)</label><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"></div>
                    <div class="filter-group"><label>Priority</label>
                        <select name="priority"><option value="">All</option><option value="high" <?php echo $priority_filter=='high'?'selected':''; ?>>High</option><option value="medium" <?php echo $priority_filter=='medium'?'selected':''; ?>>Medium</option><option value="low" <?php echo $priority_filter=='low'?'selected':''; ?>>Low</option></select>
                    </div>
                    <div class="filter-group"><label>Sort by</label>
                        <select name="sort"><option value="newest" <?php echo $sort=='newest'?'selected':''; ?>>Newest first</option><option value="oldest" <?php echo $sort=='oldest'?'selected':''; ?>>Oldest first</option><option value="priority_high" <?php echo $sort=='priority_high'?'selected':''; ?>>Priority (High to Low)</option><option value="priority_low" <?php echo $sort=='priority_low'?'selected':''; ?>>Priority (Low to High)</option></select>
                    </div>
                    <div class="filter-group"><button type="submit">Apply Filters</button></div>
                </form>
                <div class="table-responsive">
                    <table class="complaints-table">
                        <thead>
                            <tr>
                                <th>ID</th><th>Student</th><th>Title</th><th>Priority</th><th>Status</th><th>Date</th>
                                <th>Resolution Time</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($data_result) == 0): ?>
                                <tr><td colspan="8" style="text-align:center;">No complaints found.<?php echo ' '; ?></td>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($data_result)):
                                    $status_class = match($row['status']) {
                                        'pending' => 'badge-pending',
                                        'in_progress' => 'badge-in-progress',
                                        'resolved' => 'badge-resolved',
                                        'escalated' => 'badge-escalated',
                                        default => ''
                                    };
                                    $resolution_time = '--';
                                    if ($row['status'] == 'resolved' && !empty($row['updated_at'])) {
                                        $created = new DateTime($row['created_at']);
                                        $resolved = new DateTime($row['updated_at']);
                                        $diff = $created->diff($resolved);
                                        $resolution_time = $diff->format('%a days, %h hours, %i minutes');
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['complaint_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo ucfirst($row['priority']); ?></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo $resolution_time; ?></td>
                                    <td><a href="?page=view-complaint&id=<?php echo $row['id']; ?>" class="btn-sm">View</a></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_list_page): ?><span class="current"><?php echo $i; ?></span>
                        <?php else: ?><a href="?page=complaints&sub=<?php echo $sub_page; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>

        <!-- VIEW COMPLAINT (with SLA card) -->
        <?php elseif ($page == 'view-complaint' && $complaint_id): ?>
            <?php
            $detail_sql = "SELECT c.*, u.full_name as student_name, u.reg_number FROM complaints c JOIN users u ON c.student_id = u.id WHERE c.id = ? AND c.assigned_to = ?";
            $detail_stmt = mysqli_prepare($conn, $detail_sql);
            mysqli_stmt_bind_param($detail_stmt, "ii", $complaint_id, $rector_id);
            mysqli_stmt_execute($detail_stmt);
            $detail_result = mysqli_stmt_get_result($detail_stmt);
            $complaint = mysqli_fetch_assoc($detail_result);
            mysqli_stmt_close($detail_stmt);
            if (!$complaint) { echo '<div class="alert error">Complaint not found or not assigned to you.</div>'; } else {
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
                    <div class="sla-card">
                        <div class="sla-title"><i class="fas fa-hourglass-half"></i> SLA Monitoring</div>
                        <div class="sla-stats">
                            <div class="sla-item"><div class="sla-label"><i class="far fa-clock"></i> Time since submission</div><div class="sla-value"><?php echo $elapsedFormatted; ?></div></div>
                            <?php if ($is_resolved && $resolutionFormatted): ?>
                                <div class="sla-item"><div class="sla-label"><i class="fas fa-check-circle"></i> Total resolution time</div><div class="sla-value"><span class="sla-resolved"><?php echo $resolutionFormatted; ?></span></div></div>
                            <?php else: ?>
                                <div class="sla-item"><div class="sla-label"><i class="fas fa-tachometer-alt"></i> SLA target (<?php echo ucfirst($complaint['priority']); ?> priority)</div><div class="sla-value"><?php echo $slaHours; ?> hours (<?php echo round($slaHours/24,1); ?> days)</div></div>
                                <div class="sla-item"><div class="sla-label"><i class="fas fa-calendar-alt"></i> Deadline</div><div class="sla-value"><?php echo $deadline->format('d/m/Y H:i'); ?></div></div>
                                <div class="sla-item"><div class="sla-label"><i class="fas fa-hourglass-end"></i> Remaining / Status</div><div class="sla-value">
                                    <?php if ($slaStatus === 'active'): ?>
                                        <span id="countdownTimer" class="deadline-timer" data-deadline="<?php echo $deadlineTimestamp * 1000; ?>">-- : -- : --</span>
                                    <?php elseif ($slaStatus === 'overdue'): ?>
                                        <span class="sla-overdue"><i class="fas fa-exclamation-triangle"></i> Overdue by <?php echo $overdueFormatted; ?></span>
                                    <?php endif; ?>
                                </div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h4>Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?></h4>
                    <div class="complaint-detail-grid">
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($complaint['student_name']); ?> (<?php echo $complaint['reg_number']; ?>)</p>
                        <p><strong>Title:</strong> <?php echo htmlspecialchars($complaint['title']); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($complaint['location'] ?: 'Not provided'); ?></p>
                        <p><strong>Incident Date:</strong> <?php echo date('d/m/Y', strtotime($complaint['incident_date'])); ?></p>
                        <p><strong>Priority:</strong> <?php echo ucfirst($complaint['priority']); ?></p>
                        <p><strong>Status:</strong> <span class="badge <?php echo match($complaint['status']) { 'pending'=>'badge-pending','in_progress'=>'badge-in-progress','resolved'=>'badge-resolved','escalated'=>'badge-escalated', default=>'' }; ?>"><?php echo ucfirst($complaint['status']); ?></span></p>
                        <?php if ($complaint['attachment_path']): ?>
                        <p><strong>Evidence:</strong> <a href="../<?php echo $complaint['attachment_path']; ?>" target="_blank" class="download-link">Download</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="description-box"><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                    <h5>Conversation</h5>
                    <div class="conversation">
                        <?php while ($resp = mysqli_fetch_assoc($resp_result)): ?>
                            <div class="message <?php echo ($resp['role']=='student')?'student-message':'staff-message'; ?>">
                                <div class="message-header"><strong><?php echo htmlspecialchars($resp['full_name']); ?> (<?php echo ucfirst($resp['role']); ?>)</strong> <small><?php echo date('d/m/Y H:i', strtotime($resp['created_at'])); ?></small></div>
                                <div class="message-body"><?php echo nl2br(htmlspecialchars($resp['message'])); ?></div>
                            </div>
                        <?php endwhile; mysqli_stmt_close($resp_stmt); ?>
                        <?php if ($resp_stmt && mysqli_num_rows($resp_result)==0): ?><p>No messages yet. Use the form below to reply.</p><?php endif; ?>
                    </div>
                    <div class="reply-section">
                        <h5>Reply to Student</h5>
                        <form method="POST">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                            <div class="form-group"><label>Your Message</label><textarea name="message" rows="4" required></textarea></div>
                            <div class="form-group"><label>Update Status</label>
                                <select name="status">
                                    <option value="in_progress" <?php echo $complaint['status']=='in_progress'?'selected':''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $complaint['status']=='resolved'?'selected':''; ?>>Resolved</option>
                                </select>
                            </div>
                            <button type="submit" name="reply_complaint" class="btn-submit">Send Reply & Update Status</button>
                        </form>
                    </div>
                    <div class="status-section">
                        <h5>Change Status Only</h5>
                        <form method="POST">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint_id; ?>">
                            <div class="form-group"><label>New Status</label>
                                <select name="status">
                                    <option value="pending" <?php echo $complaint['status']=='pending'?'selected':''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $complaint['status']=='in_progress'?'selected':''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $complaint['status']=='resolved'?'selected':''; ?>>Resolved</option>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn-submit">Update Status Only</button>
                        </form>
                    </div>
                </div>
            <?php } ?>

        <!-- ESCALATION CENTER -->
        <?php elseif ($page == 'escalation_center'): ?>
            <div class="content-area">
                <h4>Escalation Center</h4>
                <p>Complaints that have been escalated to the Rector (from Deputy Rector).</p>
                <?php
                $escalated_where = "assigned_to = ? AND status = 'escalated'";
                $esc_sql = "SELECT c.id, c.complaint_number, c.title, c.created_at, u.full_name as student_name,
                                   staff.full_name as escalated_by, staff.role as escalated_by_role
                            FROM complaints c
                            JOIN users u ON c.student_id = u.id
                            JOIN users staff ON c.assigned_to = staff.id
                            WHERE $escalated_where
                            ORDER BY c.created_at DESC";
                $esc_stmt = mysqli_prepare($conn, $esc_sql);
                mysqli_stmt_bind_param($esc_stmt, "i", $rector_id);
                mysqli_stmt_execute($esc_stmt);
                $esc_result = mysqli_stmt_get_result($esc_stmt);
                ?>
                <div class="table-responsive">
                    <table class="complaints-table">
                        <thead>
                            <tr><th>ID</th><th>Student</th><th>Title</th><th>Assigned To</th><th>Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($esc_result) == 0): ?>
                                <tr><td colspan="6" style="text-align:center;">No escalated complaints at the moment.<?php echo ' '; ?></td>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($esc_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['complaint_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['escalated_by']); ?> (<?php echo ucfirst(str_replace('_', ' ', $row['escalated_by_role'])); ?>)</div> </td>
                                        <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div> </td>
                                        <td><a href="?page=view-complaint&id=<?php echo $row['id']; ?>" class="btn-sm">View</a></div> </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php mysqli_stmt_close($esc_stmt); ?>
            </div>

        <!-- STAFF MONITORING (placeholder) -->
        <?php elseif ($page == 'staff_monitoring'): ?>
            <div class="content-area"><h4>Staff Monitoring</h4><p>Performance metrics and activity logs for staff members will be displayed here.</p><p>(Placeholder – to be implemented)</p></div>

        <!-- ANALYTICS & REPORTS (placeholder) -->
        <?php elseif ($page == 'analytics'): ?>
            <div class="content-area"><h4>Analytics & Reports</h4><p>Charts, graphs, and summary reports of complaints by category, priority, department, etc.</p><p>(Placeholder – to be implemented)</p></div>

        <!-- ANNOUNCEMENTS (placeholder) -->
        <?php elseif ($page == 'announcements'): ?>
            <div class="content-area"><h4>Announcements</h4><p>University‑wide announcements will be listed here.</p><p>(Placeholder – to be implemented)</p></div>

        <!-- NOTIFICATIONS (placeholder) -->
        <?php elseif ($page == 'notifications'): ?>
            <div class="content-area"><h4>Notifications</h4><p>System notifications (new complaints, escalations, etc.) will appear here.</p><p>(Placeholder – to be implemented)</p></div>

        <!-- CHANGE PROFILE -->
        <?php elseif ($page == 'profile'): ?>
            <div class="content-area">
                <h4>Edit Profile</h4>
                <?php
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
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($rector_name); ?>" required></div>
                    <div class="form-group"><label>Email (cannot be changed)</label><input type="email" value="<?php echo htmlspecialchars($rector_email); ?>" disabled></div>
                    <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>"></div>
                    <div class="form-group"><label>Profile Picture</label><input type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                        <?php if (!empty($_SESSION['profile_picture'])): ?><div class="current-profile-pic">Current: <?php echo basename($_SESSION['profile_picture']); ?></div><?php endif; ?>
                    </div>
                    <button type="submit" class="btn-submit">Update Profile</button>
                </form>
                <hr style="margin: 30px 0;">
                <h4>Change Password</h4>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                    <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
                    <button type="submit" class="btn-submit">Change Password</button>
                </form>
            </div>

        <?php else: ?>
            <div class="content-area"><h4>Page not found</h4><p>Select a menu option from the sidebar.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Logout Modal -->
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
<div id="loadingOverlay" class="loading-overlay">
    <div style="text-align:center;"><div class="spinner"></div><div class="loading-text">Logging out...</div></div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const toggleInline = document.getElementById('toggleInline');
    const toggleStandalone = document.getElementById('toggleStandalone');
    function updateToggleIcons(collapsed) { const arrow = collapsed ? '❯' : '❮'; toggleInline.innerHTML = arrow; toggleStandalone.innerHTML = arrow; }
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) { sidebar.classList.add('collapsed'); updateToggleIcons(true); } else { updateToggleIcons(false); }
    function toggleSidebar() { sidebar.classList.toggle('collapsed'); const collapsed = sidebar.classList.contains('collapsed'); localStorage.setItem('sidebarCollapsed', collapsed); updateToggleIcons(collapsed); }
    toggleInline.addEventListener('click', toggleSidebar);
    toggleStandalone.addEventListener('click', toggleSidebar);
    const logoutBtn = document.getElementById('logoutBtn');
    const modal = document.getElementById('logoutModal');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const confirmBtn = document.getElementById('confirmLogout');
    const cancelBtn = document.getElementById('cancelLogout');
    logoutBtn.addEventListener('click', (e) => { e.preventDefault(); modal.classList.add('active'); });
    confirmBtn.addEventListener('click', () => { modal.classList.remove('active'); loadingOverlay.classList.add('active'); setTimeout(() => { window.location.href = '../logout.php'; }, 500); });
    cancelBtn.addEventListener('click', () => { modal.classList.remove('active'); });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
    
    // SLA COUNTDOWN TIMER
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