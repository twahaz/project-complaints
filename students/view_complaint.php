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
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch complaint details and ensure ownership
$sql = "SELECT c.*, cat.name AS category_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND c.student_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $complaint_id, $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$complaint = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$complaint) {
    header("Location: my_complaints.php?error=notfound");
    exit();
}

// Fetch all responses
$resp_sql = "SELECT r.*, u.full_name, u.role
             FROM responses r
             JOIN users u ON r.user_id = u.id
             WHERE r.complaint_id = ?
             ORDER BY r.created_at ASC";
$resp_stmt = mysqli_prepare($conn, $resp_sql);
mysqli_stmt_bind_param($resp_stmt, "i", $complaint_id);
mysqli_stmt_execute($resp_stmt);
$resp_result = mysqli_stmt_get_result($resp_stmt);
$responses = [];
while ($row = mysqli_fetch_assoc($resp_result)) {
    $responses[] = $row;
}
mysqli_stmt_close($resp_stmt);

// Check if rating exists
$rating_sql = "SELECT id FROM ratings WHERE complaint_id = ?";
$rating_stmt = mysqli_prepare($conn, $rating_sql);
mysqli_stmt_bind_param($rating_stmt, "i", $complaint_id);
mysqli_stmt_execute($rating_stmt);
$rating_result = mysqli_stmt_get_result($rating_stmt);
$has_rating = mysqli_fetch_assoc($rating_result) ? true : false;
mysqli_stmt_close($rating_stmt);
mysqli_close($conn);

$success_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'feedback_submitted') $success_msg = "Thank you for your feedback!";
}

function formatRole($role) {
    return match($role) {
        'deputy_rector' => 'Deputy Rector',
        'rector' => 'Rector',
        'examination_officer' => 'Examination Officer',
        'president' => 'President (IAASO)',
        'dean' => 'Dean of Students',
        'hod' => 'Head of Department',
        'accountant' => 'Accountant',
        'student' => 'Student',
        'admin' => 'Administrator',
        default => ucfirst(str_replace('_', ' ', $role))
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Complaint Details - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           UI SANA NA DASHBOARD - RESPONSIVE KAMILI
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

        .back-btn {
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
            flex-shrink: 0;
        }
        .back-btn:hover {
            background: #1a56db;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
        }
        .back-btn i { font-size: 1rem; }

        .btn-primary {
            display: inline-block;
            padding: 10px 28px;
            background: #1a56db;
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 86, 219, 0.3);
        }

        .btn-submit {
            background: #1a56db;
            color: white;
            border: none;
            padding: 12px 32px;
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

        .download-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2ff;
            padding: 6px 14px;
            border-radius: 30px;
            color: #1a56db;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: 0.2s;
        }
        .download-link:hover {
            background: #1a56db;
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

        .dashboard-body { 
            padding: 28px 36px;
            background: #ebf4fe;
            flex: 1;
        }

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
            margin-bottom: 24px;
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

        /* ========== COMPLAINT DETAIL GRID - FIXED ========== */
        .complaint-detail-grid {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px 24px;
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
        }

        .description-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
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
        }

        .badge {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-in_progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #d1fae5; color: #065f46; }
        .badge-escalated { background: #fee2e2; color: #991b1b; }
        .badge-closed { background: #e2e3e5; color: #383d41; }

        .conversation-section h4 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .conversation-section h4 i {
            color: #1a56db;
        }

        .conversation {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5edf5;
            border-radius: 16px;
            padding: 16px 20px;
            background: #fafcff;
        }
        .message {
            margin-bottom: 16px;
            padding: 14px 18px;
            border-radius: 14px;
        }
        .message:last-child {
            margin-bottom: 0;
        }
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
        .message-header .sender .role-tag {
            font-weight: 400;
            color: #6b85a0;
            font-size: 0.7rem;
        }
        .message-header .time {
            color: #8ba0bc;
            font-size: 0.7rem;
        }
        .message-body {
            line-height: 1.6;
            color: #1f2c40;
            font-size: 0.9rem;
        }
        .no-messages {
            color: #8ba0bc;
            text-align: center;
            padding: 24px 0;
            font-size: 0.9rem;
        }
        .no-messages i {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 8px;
            color: #d4e2f7;
        }

        .followup-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5edf5;
        }
        .followup-section h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #0a2a5e;
            margin-bottom: 12px;
        }
        .followup-section textarea {
            width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            border: 1.5px solid #e5edf5;
            font-family: inherit;
            font-size: 0.95rem;
            transition: border 0.2s;
            resize: vertical;
            min-height: 100px;
            background: #fafcff;
        }
        .followup-section textarea:focus {
            border-color: #1a56db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.08);
        }

        .feedback-prompt {
            margin-top: 24px;
            padding: 24px 28px;
            background: #dbeafe;
            border-radius: 16px;
            text-align: center;
        }
        .feedback-prompt p {
            color: #1e40af;
            font-weight: 500;
            margin-bottom: 12px;
        }
        .feedback-prompt .already-rated {
            color: #065f46;
            font-weight: 500;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #065f46;
        }
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #991b1b;
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

        .spinner-small {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 8px;
        }

        /* ============================================
           RESPONSIVE KAMILI - FIXED SMALL SCREEN
           ============================================ */

        /* Tablets (768px - 1024px) */
        @media (max-width: 1024px) {
            .dashboard-body { padding: 20px 24px; }
            .top-bar { padding: 14px 24px; }
            .content-area { padding: 20px 24px; min-height: auto; }
            .complaint-detail-grid { 
                grid-template-columns: repeat(2, 1fr);
                padding: 16px 20px;
            }
        }

        /* Small Tablets & Large Phones (480px - 768px) */
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

            .back-btn span { display: none; }
            .back-btn { padding: 8px 14px; gap: 6px; font-size: 0.8rem; }
            .back-btn i { font-size: 1rem; }

            .profile-details .name { font-size: 0.8rem; }
            .profile-details .reg { font-size: 0.6rem; }
            .profile-pic { width: 36px; height: 36px; }

            .content-area { padding: 16px; border-radius: 16px; }
            .content-area .header-section { 
                flex-direction: column; 
                align-items: stretch; 
                gap: 12px; 
            }
            .content-area .header-section .back-btn {
                align-self: flex-start;
            }
            .content-area h3 { 
                font-size: 1.05rem; 
                flex-wrap: wrap;
            }
            
            /* FIXED: Complaint detail grid - 2 columns on tablet */
            .complaint-detail-grid { 
                grid-template-columns: 1fr 1fr !important;
                padding: 14px 16px !important;
                gap: 10px 16px !important;
                border-radius: 14px;
            }
            .complaint-detail-grid .detail-item .label { font-size: 0.6rem !important; }
            .complaint-detail-grid .detail-item .value { font-size: 0.82rem !important; }
            
            .description-box { 
                padding: 14px 16px; 
            }
            .description-box .value { font-size: 0.88rem; }

            .conversation { 
                padding: 12px 14px;
                max-height: 300px;
            }
            .message { padding: 12px 14px; }
            .message-header { 
                font-size: 0.75rem; 
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .message-header .time { font-size: 0.65rem; }
            .message-body { font-size: 0.82rem; }

            .followup-section { margin-top: 16px; padding-top: 16px; }
            .followup-section textarea { 
                min-height: 70px; 
                font-size: 0.85rem; 
                padding: 12px 14px; 
            }
            .btn-submit { 
                padding: 10px 24px; 
                font-size: 0.85rem; 
                width: 100%;
                justify-content: center;
            }

            .feedback-prompt { 
                padding: 16px 20px; 
                margin-top: 16px;
            }
            .feedback-prompt p { font-size: 0.85rem; }

            .btn-primary {
                padding: 8px 20px;
                font-size: 0.85rem;
                width: 100%;
                text-align: center;
            }
        }

        /* Mobile Phones (under 480px) - FIXED */
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
            .back-btn { padding: 6px 12px; font-size: 0.7rem; }
            .back-btn i { font-size: 0.85rem; }
            .profile-pic { width: 32px; height: 32px; }
            .profile-details .name { font-size: 0.7rem; }
            .profile-details .reg { font-size: 0.5rem; }

            .content-area { padding: 12px; border-radius: 12px; }
            .content-area h3 { font-size: 0.9rem; }

            /* FIXED: Complaint detail grid - 2 columns on mobile too */
            .complaint-detail-grid { 
                grid-template-columns: 1fr 1fr !important;
                padding: 12px 14px !important;
                gap: 10px 16px !important;
                border-radius: 12px;
            }
            .complaint-detail-grid .detail-item {
                display: flex;
                flex-direction: column;
                gap: 1px;
            }
            .complaint-detail-grid .detail-item .label { 
                font-size: 0.55rem !important;
                color: #8ba0bc;
            }
            .complaint-detail-grid .detail-item .value { 
                font-size: 0.78rem !important;
                font-weight: 500;
                color: #1f2c40;
                word-break: break-word;
            }
            
            /* Evidence link fix */
            .complaint-detail-grid .detail-item .value .download-link {
                font-size: 0.7rem !important;
                padding: 4px 10px !important;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            
            .description-box { 
                padding: 10px 12px;
                border-radius: 12px;
            }
            .description-box .value { font-size: 0.82rem; }

            .conversation { 
                padding: 10px 12px;
                max-height: 250px;
                border-radius: 12px;
            }
            .message { padding: 10px 12px; }
            .message-header { font-size: 0.7rem; }
            .message-body { font-size: 0.78rem; }

            .followup-section { margin-top: 12px; padding-top: 12px; }
            .followup-section textarea { 
                min-height: 60px; 
                font-size: 0.8rem; 
                padding: 10px 12px;
                border-radius: 12px;
            }
            .btn-submit { 
                padding: 8px 20px; 
                font-size: 0.8rem; 
            }

            .feedback-prompt { 
                padding: 14px 16px; 
                border-radius: 12px;
                margin-top: 12px;
            }
            .feedback-prompt p { font-size: 0.8rem; }

            .btn-primary {
                padding: 8px 16px;
                font-size: 0.8rem;
                border-radius: 30px;
            }

            .modal-container { padding: 24px 20px; }
            .modal-container h3 { font-size: 1rem; }
            .modal-btn { padding: 8px 20px; font-size: 0.8rem; min-width: 80px; }

            .loading-content { padding: 30px 30px; }
            .spinner { width: 36px; height: 36px; }
            .loading-text { font-size: 0.85rem; }
        }

        /* Extra Small Phones (under 380px) */
        @media (max-width: 380px) {
            .sidebar { width: 55px !important; }
            .sidebar:not(.collapsed) { width: 55px !important; }
            .sidebar.collapsed { width: 55px !important; }
            .main-content { margin-left: 55px !important; }
            .sidebar.collapsed ~ .main-content { margin-left: 55px !important; }
            .menu-item i { font-size: 0.95rem !important; }

            .content-area h3 { font-size: 0.8rem; }
            
            .complaint-detail-grid { 
                grid-template-columns: 1fr 1fr !important;
                padding: 8px 10px !important;
                gap: 6px 12px !important;
            }
            .complaint-detail-grid .detail-item .label { font-size: 0.5rem !important; }
            .complaint-detail-grid .detail-item .value { font-size: 0.7rem !important; }
            .complaint-detail-grid .detail-item .value .download-link {
                font-size: 0.6rem !important;
                padding: 2px 8px !important;
            }
            
            .description-box .value { font-size: 0.78rem; }
            .message-body { font-size: 0.74rem; }
            .followup-section textarea { font-size: 0.75rem; }
            .btn-submit { font-size: 0.75rem; padding: 6px 16px; }
        }
    </style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="row-cfms">
            <span class="brand">CFMS <span>| Student</span></span>
            <button class="toggle-inline" id="toggleInline">❮</button>
        </div>
        <div class="row-tagline">
            <span class="tagline">Complaint Management</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>

    <div class="sidebar-menu">
        <a href="student_dashboard.php?page=dashboard" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="my_complaints.php" class="menu-item active">
            <i class="fas fa-file-alt"></i><span>My Complaints</span>
        </a>
        <a href="my_feedback.php" class="menu-item">
            <i class="fas fa-comment-dots"></i><span>Feedback</span>
        </a>
        <a href="announcements.php" class="menu-item">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
        <a href="student_dashboard.php?page=profile" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="student_dashboard.php?page=change-password" class="menu-item">
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
            <!-- Header with Back Button -->
            <div class="header-section">
                <h3>
                    <i class="fas fa-file-alt"></i> 
                    Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?>
                </h3>
                <a href="my_complaints.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Complaints</span>
                </a>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Complaint Details Grid - FIXED -->
            <div class="complaint-detail-grid">
                <div class="detail-item">
                    <span class="label">Title</span>
                    <span class="value"><?php echo htmlspecialchars($complaint['title']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Category</span>
                    <span class="value"><?php echo htmlspecialchars($complaint['category_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="badge <?php echo 'badge-'.str_replace('_', '_', $complaint['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="label">Priority</span>
                    <span class="value"><?php echo ucfirst($complaint['priority']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Location</span>
                    <span class="value"><?php echo htmlspecialchars($complaint['location'] ?: 'Not provided'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Incident Date</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($complaint['incident_date'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Submitted</span>
                    <span class="value"><?php echo date('d/m/Y H:i', strtotime($complaint['created_at'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Evidence</span>
                    <span class="value">
                        <?php if ($complaint['attachment_path']): ?>
                            <a href="../<?php echo $complaint['attachment_path']; ?>" target="_blank" class="download-link">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php else: ?>
                            <span style="color:#6b85a0;">None</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Description -->
            <div class="description-box">
                <div class="label">Description</div>
                <div class="value"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
            </div>

            <!-- Conversation -->
            <div class="conversation-section">
                <h4><i class="fas fa-comments"></i> Conversation</h4>
                <div class="conversation" id="conversation">
                    <?php if (!empty($responses)): ?>
                        <?php foreach ($responses as $resp): ?>
                            <div class="message <?php echo ($resp['role'] == 'student') ? 'student-message' : 'staff-message'; ?>">
                                <div class="message-header">
                                    <span class="sender">
                                        <?php echo htmlspecialchars($resp['full_name']); ?>
                                        <span class="role-tag">(<?php echo formatRole($resp['role']); ?>)</span>
                                    </span>
                                    <span class="time">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('d/m/Y H:i', strtotime($resp['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="message-body"><?php echo nl2br(htmlspecialchars($resp['message'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-messages">
                            <i class="fas fa-inbox"></i>
                            No messages yet. Staff will respond soon.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Follow-up Section -->
            <?php if ($complaint['status'] != 'closed' && $complaint['status'] != 'resolved'): ?>
                <div class="followup-section">
                    <h4><i class="fas fa-reply"></i> Add a Follow-up Comment</h4>
                    <div id="followupAlert" class="alert" style="display:none;"></div>
                    <form id="followupForm">
                        <textarea id="followupComment" rows="3" placeholder="Write your follow-up comment here..."></textarea>
                        <button type="submit" id="sendFollowupBtn" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Send Follow-up
                        </button>
                    </form>
                </div>
            <?php elseif ($complaint['status'] == 'resolved' && !$has_rating): ?>
                <div class="feedback-prompt">
                    <p><i class="fas fa-star" style="color: #f59e0b;"></i> This complaint has been resolved. Please rate your experience.</p>
                    <a href="feedback.php?id=<?php echo $complaint_id; ?>" class="btn-primary">
                        <i class="fas fa-star"></i> Give Feedback
                    </a>
                </div>
            <?php elseif ($complaint['status'] == 'resolved' && $has_rating): ?>
                <div class="feedback-prompt">
                    <p class="already-rated">
                        <i class="fas fa-check-circle"></i> You have already rated this complaint. Thank you for your feedback!
                    </p>
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
    // Sidebar Toggle
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

    // Logout Modal
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

    // Follow-up AJAX
    const followupForm = document.getElementById('followupForm');
    const followupComment = document.getElementById('followupComment');
    const sendBtn = document.getElementById('sendFollowupBtn');
    const followupAlert = document.getElementById('followupAlert');
    const conversationDiv = document.getElementById('conversation');

    if (followupForm) {
        followupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const comment = followupComment.value.trim();
            if (!comment) {
                followupAlert.style.display = 'block';
                followupAlert.className = 'alert error';
                followupAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a comment.';
                setTimeout(() => { followupAlert.style.display = 'none'; }, 3000);
                return;
            }

            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = 'Sending... <span class="spinner-small"></span>';
            sendBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('complaint_id', <?php echo $complaint_id; ?>);
                formData.append('followup_comment', comment);

                const response = await fetch('submit_followup.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    const newMessage = `
                        <div class="message student-message">
                            <div class="message-header">
                                <span class="sender">
                                    <?php echo htmlspecialchars($full_name); ?>
                                    <span class="role-tag">(Student)</span>
                                </span>
                                <span class="time">
                                    <i class="far fa-clock"></i> Just now
                                </span>
                            </div>
                            <div class="message-body">${escapeHtml(comment)}</div>
                        </div>
                    `;
                    conversationDiv.insertAdjacentHTML('beforeend', newMessage);
                    conversationDiv.scrollTop = conversationDiv.scrollHeight;
                    followupComment.value = '';

                    followupAlert.style.display = 'block';
                    followupAlert.className = 'alert success';
                    followupAlert.innerHTML = '<i class="fas fa-check-circle"></i> Follow-up added successfully!';
                    setTimeout(() => { followupAlert.style.display = 'none'; }, 3000);
                } else {
                    followupAlert.style.display = 'block';
                    followupAlert.className = 'alert error';
                    followupAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error: ' + result.message;
                    setTimeout(() => { followupAlert.style.display = 'none'; }, 3000);
                }
            } catch (err) {
                followupAlert.style.display = 'block';
                followupAlert.className = 'alert error';
                followupAlert.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
                setTimeout(() => { followupAlert.style.display = 'none'; }, 3000);
            } finally {
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
            }
        });
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
</script>

</body>
</html>