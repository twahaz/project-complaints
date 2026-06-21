<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];
$reg_number = $_SESSION['reg_number'];

$categories = [
    'Examination case' => 'fa-file-excel',
    'Accountant' => 'fa-calculator',
    'Hostel' => 'fa-home',
    'Academic' => 'fa-chalkboard-user',
    'Infrastructure' => 'fa-building',
    'Service' => 'fa-concierge-bell',
    'Gender issue' => 'fa-venus-mars',
    'Students Government' => 'fa-users',
    'IT Support' => 'fa-laptop-code',
    'Other' => 'fa-ticket-alt'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>New Complaint - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           UI MPYA - SANA NA DASHBOARD
           ============================================ */
        
        /* ---------- RESET & BASE ---------- */
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

        .toast.hide {
            animation: toastSlideOut 0.4s ease-in forwards;
        }

        .toast-success { border-left: 4px solid #10b981; }
        .toast-success i { color: #10b981; }
        .toast-error { border-left: 4px solid #dc2626; }
        .toast-error i { color: #dc2626; }
        .toast-info { border-left: 4px solid #1a56db; }
        .toast-info i { color: #1a56db; }

        .toast i {
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .toast .toast-message {
            flex: 1;
            color: #1f2c40;
        }
        .toast .toast-close {
            background: none;
            border: none;
            color: #8ba0bc;
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            transition: color 0.2s;
            flex-shrink: 0;
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
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
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

        /* ---------- LEFT PANEL (Steps) ---------- */
        .left-panel {
            width: 320px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: #0b2b4b;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
            z-index: 100;
            padding: 32px 24px;
            border-right: 1px solid rgba(0, 71, 171, 0.1);
            transition: width 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1), padding 0.3s ease;
            overflow-y: auto;
        }
        .left-panel.collapsed {
            width: 80px;
            padding: 32px 8px;
        }
        .left-panel.collapsed .brand-with-toggle {
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .left-panel.collapsed .brand-text h2 {
            font-size: 1rem;
            margin-bottom: 0;
            text-align: center;
        }
        .left-panel.collapsed .brand-text p {
            display: none;
        }
        .left-panel.collapsed .step-title {
            font-size: 0.7rem;
            white-space: normal;
            text-align: center;
            border-left: none;
            padding-left: 0;
            margin-top: 20px;
            writing-mode: horizontal-tb;
            transform: none;
        }
        .left-panel.collapsed .step-label {
            display: none;
        }
        .left-panel.collapsed .step-item {
            justify-content: center;
            gap: 0;
        }
        .left-panel.collapsed .step-number {
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
        }

        .logo-area { margin-bottom: 40px; }
        .brand-with-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: flex-direction 0.3s ease;
        }
        .brand-text h2 {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #0047AB, #2a9d8f);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 4px;
            transition: all 0.2s;
        }
        .brand-text p {
            font-size: 0.75rem;
            color: #5a6e8a;
        }
        .sidebar-toggle {
            width: 32px;
            height: 32px;
            background: #1a56db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            font-size: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: transform 0.4s ease, background 0.2s;
            flex-shrink: 0;
            border: none;
        }
        .sidebar-toggle:hover {
            background: #0d3b8a;
            transform: scale(1.05);
        }
        .left-panel.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        .step-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0047AB;
            margin-bottom: 28px;
            border-left: 3px solid #1a56db;
            padding-left: 12px;
            transition: all 0.3s ease;
        }
        .mobile-step-title {
            display: none;
            font-size: 1.3rem;
            font-weight: 600;
            color: #0047AB;
            margin-bottom: 24px;
            border-left: 3px solid #1a56db;
            padding-left: 12px;
        }

        .step-list {
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
            margin-left: 0;
        }
        .step-list::before {
            content: '';
            position: absolute;
            width: 3px;
            background: linear-gradient(to bottom, #0047AB var(--progress), #cbd5e1 var(--progress));
            left: var(--line-left, 20px);
            top: var(--line-top, 0);
            bottom: var(--line-bottom, 0);
            border-radius: 2px;
            transition: left 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1), background 0.2s;
            z-index: 0;
        }
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 24px 12px;
            margin: 8px 0;
            border-radius: 16px;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
            z-index: 1;
            background: transparent;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            color: #5a6e8a;
            flex-shrink: 0;
            transition: all 0.2s;
            position: relative;
            z-index: 2;
        }
        .step-item.active .step-number {
            background: #1a56db;
            border-color: #1a56db;
            color: white;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.2);
        }
        .step-item.completed .step-number {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        .step-item.completed .step-number i {
            font-size: 1rem;
        }
        .step-label {
            font-size: 1rem;
            font-weight: 500;
            color: #1f2c40;
            padding-top: 8px;
            white-space: nowrap;
            transition: opacity 0.2s, width 0.2s;
        }
        .step-item.active .step-label {
            color: #1a56db;
            font-weight: 600;
        }

        /* ---------- RIGHT PANEL ---------- */
        .right-panel {
            flex: 1;
            margin-left: 320px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: #ebf4fe;
            padding: 40px 60px;
            transition: margin-left 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .left-panel.collapsed ~ .right-panel {
            margin-left: 80px;
        }

        .step-content {
            display: none;
            background: white;
            border-radius: 20px;
            padding: 32px 36px;
            box-shadow: 0 2px 12px rgba(10,42,94,0.05);
            border: 1px solid rgba(255,255,255,0.6);
            animation: stepSlideIn 0.35s ease-out;
        }
        @keyframes stepSlideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .step-content.active {
            display: block;
        }

        .form-container {
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
        }

        /* ---------- FORM STYLES ---------- */
        .form-group {
            margin-bottom: 28px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #0a2a5e;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-group label .required {
            color: #dc2626;
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e5edf5;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: border 0.2s;
            background: #fafcff;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.08);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 28px;
        }
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        .character-count {
            text-align: right;
            font-size: 0.75rem;
            color: #8ba0bc;
            margin-top: 4px;
        }

        /* ---------- CATEGORY GRID ---------- */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .category-card {
            background: #f8fafc;
            border: 1.5px solid #e5edf5;
            border-radius: 16px;
            padding: 20px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .category-card:hover {
            border-color: #1a56db;
            background: #f0f4ff;
            transform: translateY(-2px);
        }
        .category-card.selected {
            background: #1a56db;
            color: white;
            border-color: #1a56db;
            box-shadow: 0 4px 16px rgba(26,86,219,0.2);
        }
        .category-card i {
            font-size: 2rem;
            margin-bottom: 12px;
            display: block;
            color: #1a56db;
        }
        .category-card.selected i {
            color: white;
        }
        .category-card.selected span {
            color: white;
        }
        .category-card span {
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* ---------- PREVIEW ---------- */
        .preview-card {
            margin-bottom: 30px;
        }
        .preview-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e5edf5;
        }
        .preview-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #0a2a5e;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #e5edf5;
            padding-bottom: 10px;
        }
        .preview-section h4 i {
            color: #1a56db;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px 16px;
        }
        .preview-grid .label {
            font-weight: 500;
            color: #6b85a0;
            font-size: 0.85rem;
        }
        .preview-grid .value {
            color: #1f2c40;
            font-weight: 500;
            word-break: break-word;
        }
        .preview-description {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-top: 8px;
            border: 1px solid #e5edf5;
            line-height: 1.7;
            color: #1f2c40;
        }
        .evidence-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2ff;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.85rem;
            color: #1a56db;
        }
        .file-info {
            font-size: 0.8rem;
            color: #8ba0bc;
            margin-top: 6px;
        }
        #fileNameDisplay {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #10b981;
        }

        /* ---------- BUTTONS ---------- */
        .btn {
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #1a56db;
            color: white;
        }
        .btn-primary:hover {
            background: #0d3b8a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26,86,219,0.3);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #f0f4f9;
            color: #4a5a7a;
        }
        .btn-secondary:hover {
            background: #e5edf5;
            transform: translateY(-2px);
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
            gap: 12px;
            flex-wrap: wrap;
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
            .right-panel { padding: 30px 32px; }
            .category-grid { grid-template-columns: repeat(2, 1fr); }
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

            .profile-details .name { font-size: 0.8rem; }
            .profile-details .reg { font-size: 0.6rem; }
            .profile-pic { width: 36px; height: 36px; }

            .content-area { padding: 16px; border-radius: 16px; }
            .content-area h3 { font-size: 1.05rem; }

            .left-panel { width: 80px; padding: 24px 8px; }
            .left-panel .brand-with-toggle { flex-direction: column; justify-content: center; gap: 8px; }
            .left-panel .brand-text h2 { font-size: 1rem; text-align: center; }
            .left-panel .brand-text p { display: none; }
            .left-panel .step-title { font-size: 0.7rem; text-align: center; border-left: none; padding-left: 0; margin-top: 20px; }
            .mobile-step-title { display: block; }
            .left-panel .step-label { display: none; }
            .left-panel .step-item { justify-content: center; gap: 0; padding: 16px 0; }
            .left-panel .step-number { width: 36px; height: 36px; font-size: 0.9rem; }

            .right-panel { 
                margin-left: 80px; 
                padding: 20px 16px; 
            }
            .step-content { padding: 20px; border-radius: 16px; }

            .category-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .category-card { padding: 16px 12px; }
            .category-card i { font-size: 1.5rem; }

            .form-row { flex-direction: column; gap: 0; }
            .form-row .form-group { margin-bottom: 28px; }

            .preview-grid { grid-template-columns: 1fr; gap: 6px 0; }
            .preview-section { padding: 16px; }

            .nav-buttons { flex-direction: column; }
            .nav-buttons .btn { width: 100%; justify-content: center; }

            .toast-container {
                top: 12px;
                right: 12px;
                max-width: calc(100% - 24px);
            }
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
            .profile-pic { width: 32px; height: 32px; }
            .profile-details .name { font-size: 0.7rem; }
            .profile-details .reg { font-size: 0.5rem; }

            .content-area { padding: 12px; border-radius: 12px; }
            .content-area h3 { font-size: 0.9rem; }

            .right-panel { padding: 12px; }
            .step-content { padding: 16px; }

            .category-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .category-card { padding: 12px 8px; border-radius: 12px; }
            .category-card i { font-size: 1.2rem; margin-bottom: 6px; }
            .category-card span { font-size: 0.7rem; }

            .form-group input, .form-group select, .form-group textarea { font-size: 0.85rem; padding: 10px 14px; }

            .btn { padding: 10px 20px; font-size: 0.85rem; }

            .modal-container { padding: 24px 20px; }
            .modal-container h3 { font-size: 1rem; }
            .modal-btn { padding: 8px 20px; font-size: 0.8rem; min-width: 80px; }

            .loading-content { padding: 30px 30px; }
            .spinner { width: 36px; height: 36px; }
            .loading-text { font-size: 0.85rem; }

            .toast-container {
                top: 8px;
                right: 8px;
                max-width: calc(100% - 16px);
            }
            .toast {
                font-size: 0.8rem;
                padding: 12px 16px;
            }
        }

        @media (max-width: 380px) {
            .sidebar { width: 55px !important; }
            .sidebar:not(.collapsed) { width: 55px !important; }
            .sidebar.collapsed { width: 55px !important; }
            .main-content { margin-left: 55px !important; }
            .sidebar.collapsed ~ .main-content { margin-left: 55px !important; }
            .menu-item i { font-size: 0.95rem !important; }

            .category-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .category-card { padding: 10px 6px; }
            .category-card i { font-size: 1rem; }
            .category-card span { font-size: 0.65rem; }
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
        <a href="my_complaints.php" class="menu-item">
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

<!-- ========== LEFT PANEL (Steps) ========== -->
<div class="left-panel" id="leftPanel">
    <div class="logo-area">
        <div class="brand-with-toggle">
            <div class="brand-text">
                <h2>CFMS</h2>
                <p>Complaint Management</p>
            </div>
            <div class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-chevron-left"></i>
            </div>
        </div>
    </div>
    <div class="step-title">Create New Complaint</div>
    <div class="step-list" id="stepList">
        <div class="step-item active" data-step="1">
            <div class="step-number"><span>1</span></div>
            <div class="step-label">Choose Category</div>
        </div>
        <div class="step-item" data-step="2">
            <div class="step-number"><span>2</span></div>
            <div class="step-label">Complaint Details</div>
        </div>
        <div class="step-item" data-step="3">
            <div class="step-number"><span>3</span></div>
            <div class="step-label">Supporting Evidence</div>
        </div>
        <div class="step-item" data-step="4">
            <div class="step-number"><span>4</span></div>
            <div class="step-label">Preview</div>
        </div>
        <div class="step-item" data-step="5">
            <div class="step-number"><span>5</span></div>
            <div class="step-label">Submit</div>
        </div>
    </div>
</div>

<!-- ========== RIGHT PANEL ========== -->
<div class="right-panel">
    <div class="form-container">
        <div class="mobile-step-title">Create New Complaint</div>

        <!-- Step 1 -->
        <div id="step-1" class="step-content active">
            <h3 style="margin-bottom: 24px; color: #0a2a5e;">Select Complaint Category</h3>
            <div class="category-grid" id="categoryGrid">
                <?php foreach ($categories as $cat => $icon): ?>
                    <div class="category-card" data-category="<?php echo htmlspecialchars($cat); ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                        <span><?php echo htmlspecialchars($cat); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="nav-buttons">
                <a href="student_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button id="nextStep1" class="btn btn-primary" disabled>Next</button>
            </div>
        </div>

        <!-- Step 2 -->
        <div id="step-2" class="step-content">
            <h3 style="margin-bottom: 24px; color: #0a2a5e;">Complaint Details</h3>
            <div class="form-group">
                <label>Title <span class="required">*</span></label>
                <input type="text" id="complaintTitle" placeholder="Brief title of your complaint" maxlength="200">
            </div>
            <div class="form-group">
                <label>Description <span class="required">*</span> (max 500 characters)</label>
                <textarea id="complaintDesc" rows="5" maxlength="500" placeholder="Describe your complaint in detail..."></textarea>
                <div class="character-count"><span id="descCount">0</span>/500 characters</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Location (optional)</label>
                    <input type="text" id="complaintLocation" placeholder="e.g., Hostel Block C, Room 12">
                </div>
                <div class="form-group">
                    <label>Date incident occurred <span class="required">*</span></label>
                    <input type="date" id="incidentDate">
                </div>
            </div>
            <div class="form-group">
                <label>Priority <span class="required">*</span></label>
                <select id="priority">
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="nav-buttons">
                <button id="prevStep2" class="btn btn-secondary">Back</button>
                <button id="nextStep2" class="btn btn-primary">Next</button>
            </div>
        </div>

        <!-- Step 3 -->
        <div id="step-3" class="step-content">
            <h3 style="margin-bottom: 24px; color: #0a2a5e;">Supporting Evidence</h3>
            <div class="form-group">
                <label>Upload evidence (optional)</label>
                <input type="file" id="evidenceFile" accept="image/jpeg,image/png,image/jpg,application/pdf,video/mp4,audio/mpeg">
                <div class="file-info">Allowed: JPG, PNG, PDF, MP4, MP3 (max 10MB)</div>
                <div id="fileNameDisplay"></div>
            </div>
            <div class="nav-buttons">
                <button id="prevStep3" class="btn btn-secondary">Back</button>
                <button id="nextStep3" class="btn btn-primary">Next</button>
            </div>
        </div>

        <!-- Step 4 -->
        <div id="step-4" class="step-content">
            <h3 style="margin-bottom: 24px; color: #0a2a5e;">Review Your Complaint</h3>
            <div class="preview-card">
                <div class="preview-section">
                    <h4><i class="fas fa-info-circle"></i> Overview</h4>
                    <div class="preview-grid">
                        <div class="label">Category:</div>
                        <div class="value" id="previewCategory">Not selected</div>
                        <div class="label">Priority:</div>
                        <div class="value" id="previewPriority">Medium</div>
                        <div class="label">Incident Date:</div>
                        <div class="value" id="previewDate">Not set</div>
                        <div class="label">Location:</div>
                        <div class="value" id="previewLocation">Not provided</div>
                    </div>
                </div>
                <div class="preview-section">
                    <h4><i class="fas fa-heading"></i> Title</h4>
                    <div id="previewTitle" class="value" style="font-weight:500;">Not provided</div>
                </div>
                <div class="preview-section">
                    <h4><i class="fas fa-align-left"></i> Description</h4>
                    <div id="previewDesc" class="preview-description">Not provided</div>
                </div>
                <div class="preview-section">
                    <h4><i class="fas fa-paperclip"></i> Supporting Evidence</h4>
                    <div id="previewEvidence" class="evidence-badge"><i class="fas fa-file-alt"></i> None</div>
                </div>
            </div>
            <div class="nav-buttons">
                <button id="prevStep4" class="btn btn-secondary">Back to Edit</button>
                <button id="nextStep4" class="btn btn-primary">Proceed to Submit</button>
            </div>
        </div>

        <!-- Step 5 -->
        <div id="step-5" class="step-content">
            <h3 style="margin-bottom: 24px; color: #0a2a5e;">Final Submission</h3>
            <div class="preview-card">
                <div class="preview-section">
                    <h4><i class="fas fa-check-circle"></i> Confirm Details</h4>
                    <div class="preview-grid">
                        <div class="label">Category:</div>
                        <div class="value" id="finalCategory">Not selected</div>
                        <div class="label">Title:</div>
                        <div class="value" id="finalTitle">Not provided</div>
                        <div class="label">Priority:</div>
                        <div class="value" id="finalPriority">Medium</div>
                        <div class="label">Incident Date:</div>
                        <div class="value" id="finalDate">Not set</div>
                        <div class="label">Location:</div>
                        <div class="value" id="finalLocation">Not provided</div>
                        <div class="label">Evidence:</div>
                        <div class="value" id="finalEvidence">None</div>
                    </div>
                    <div style="margin-top:16px;">
                        <strong style="color: #0a2a5e;">Description:</strong>
                        <div id="finalDesc" style="color: #4a5a7a; margin-top: 4px; line-height: 1.6;">Not provided</div>
                    </div>
                </div>
            </div>
            <div class="nav-buttons">
                <button id="prevStep5" class="btn btn-secondary">Back</button>
                <button id="submitComplaint" class="btn btn-primary">Submit Complaint</button>
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
        <div class="loading-text">Logging out, please wait...</div>
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

    // ===== NEW COMPLAINT LOGIC (WITH TOAST) =====
    let currentStep = 1;
    let selectedCategory = '';
    let complaintTitle = '';
    let complaintDesc = '';
    let complaintLocation = '';
    let incidentDate = '';
    let priority = 'medium';
    let selectedFile = null;

    const stepItems = document.querySelectorAll('.step-item');
    const stepContents = document.querySelectorAll('.step-content');
    const categoryCards = document.querySelectorAll('.category-card');
    const nextStep1 = document.getElementById('nextStep1');
    const nextStep2 = document.getElementById('nextStep2');
    const nextStep3 = document.getElementById('nextStep3');
    const nextStep4 = document.getElementById('nextStep4');
    const prevStep2 = document.getElementById('prevStep2');
    const prevStep3 = document.getElementById('prevStep3');
    const prevStep4 = document.getElementById('prevStep4');
    const prevStep5 = document.getElementById('prevStep5');
    const submitBtn = document.getElementById('submitComplaint');

    const titleInput = document.getElementById('complaintTitle');
    const descInput = document.getElementById('complaintDesc');
    const locationInput = document.getElementById('complaintLocation');
    const dateInput = document.getElementById('incidentDate');
    const prioritySelect = document.getElementById('priority');
    const evidenceFile = document.getElementById('evidenceFile');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const descCountSpan = document.getElementById('descCount');

    const previewCategory = document.getElementById('previewCategory');
    const previewTitle = document.getElementById('previewTitle');
    const previewDesc = document.getElementById('previewDesc');
    const previewLocation = document.getElementById('previewLocation');
    const previewDate = document.getElementById('previewDate');
    const previewPriority = document.getElementById('previewPriority');
    const previewEvidence = document.getElementById('previewEvidence');
    const finalCategory = document.getElementById('finalCategory');
    const finalTitle = document.getElementById('finalTitle');
    const finalDesc = document.getElementById('finalDesc');
    const finalLocation = document.getElementById('finalLocation');
    const finalDate = document.getElementById('finalDate');
    const finalPriority = document.getElementById('finalPriority');
    const finalEvidence = document.getElementById('finalEvidence');
    const stepList = document.getElementById('stepList');

    function updateSteps() {
        stepContents.forEach((content, idx) => {
            content.classList.toggle('active', idx + 1 === currentStep);
        });
        stepItems.forEach((item, idx) => {
            const stepNum = idx + 1;
            const numberDiv = item.querySelector('.step-number');
            item.classList.remove('active', 'completed');
            if (stepNum === currentStep) {
                item.classList.add('active');
                numberDiv.innerHTML = `<span>${stepNum}</span>`;
            } else if (stepNum < currentStep) {
                item.classList.add('completed');
                numberDiv.innerHTML = `<i class="fas fa-check"></i>`;
            } else {
                numberDiv.innerHTML = `<span>${stepNum}</span>`;
            }
        });
        const totalSteps = 5;
        const completedSteps = currentStep - 1;
        const progressPercent = (completedSteps / (totalSteps - 1)) * 100;
        stepList.style.setProperty('--progress', `${progressPercent}%`);
        requestAnimationFrame(() => positionVerticalLine());
    }

    function positionVerticalLine() {
        const firstStep = stepItems[0];
        const lastStep = stepItems[stepItems.length - 1];
        if (!firstStep || !lastStep) return;
        const firstCircle = firstStep.querySelector('.step-number');
        const lastCircle = lastStep.querySelector('.step-number');
        if (!firstCircle || !lastCircle) return;
        const containerRect = stepList.getBoundingClientRect();
        const firstRect = firstCircle.getBoundingClientRect();
        const lastRect = lastCircle.getBoundingClientRect();
        const firstCenterY = firstRect.top + firstRect.height / 2 - containerRect.top;
        const lastCenterY = lastRect.top + lastRect.height / 2 - containerRect.top;
        const firstCenterX = firstRect.left + firstRect.width / 2 - containerRect.left;
        stepList.style.setProperty('--line-top', `${firstCenterY}px`);
        stepList.style.setProperty('--line-bottom', `calc(100% - ${lastCenterY}px)`);
        stepList.style.setProperty('--line-left', `${firstCenterX}px`);
    }

    // Left panel toggle
    const leftPanel = document.getElementById('leftPanel');
    const panelToggle = document.getElementById('sidebarToggle');
    if (panelToggle) {
        panelToggle.addEventListener('click', () => {
            leftPanel.classList.toggle('collapsed');
            const collapsed = leftPanel.classList.contains('collapsed');
            const animateLine = () => {
                positionVerticalLine();
                requestAnimationFrame(animateLine);
            };
            const frame = requestAnimationFrame(animateLine);
            const onEnd = () => {
                cancelAnimationFrame(frame);
                positionVerticalLine();
                leftPanel.removeEventListener('transitionend', onEnd);
            };
            leftPanel.addEventListener('transitionend', onEnd);
            setTimeout(() => positionVerticalLine(), 50);
        });
    }

    // Category selection
    categoryCards.forEach(card => {
        card.addEventListener('click', function() {
            categoryCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            selectedCategory = this.getAttribute('data-category');
            nextStep1.disabled = false;
        });
    });

    nextStep1.addEventListener('click', () => {
        if (!selectedCategory) {
            showToastError('Please select a complaint category.');
            return;
        }
        currentStep = 2;
        updateSteps();
    });

    descInput.addEventListener('input', () => {
        descCountSpan.textContent = descInput.value.length;
    });

    nextStep2.addEventListener('click', () => {
        const title = titleInput.value.trim();
        const desc = descInput.value.trim();
        const date = dateInput.value;
        if (!title) {
            showToastError('Please enter a title.');
            titleInput.focus();
            return;
        }
        if (!desc) {
            showToastError('Please enter a description.');
            descInput.focus();
            return;
        }
        if (!date) {
            showToastError('Please select the incident date.');
            dateInput.focus();
            return;
        }
        complaintTitle = title;
        complaintDesc = desc;
        complaintLocation = locationInput.value.trim();
        incidentDate = date;
        priority = prioritySelect.value;
        updatePreviewAndFinal();
        currentStep = 3;
        updateSteps();
    });

    prevStep2.addEventListener('click', () => { currentStep = 1; updateSteps(); });

    evidenceFile.addEventListener('change', function() {
        if (this.files.length > 0) {
            selectedFile = this.files[0];
            fileNameDisplay.textContent = `Selected: ${selectedFile.name}`;
        } else {
            selectedFile = null;
            fileNameDisplay.textContent = '';
        }
        updatePreviewAndFinal();
    });

    nextStep3.addEventListener('click', () => { currentStep = 4; updateSteps(); });
    prevStep3.addEventListener('click', () => { currentStep = 2; updateSteps(); });

    function updatePreviewAndFinal() {
        const evidenceName = selectedFile ? selectedFile.name : 'None';
        previewCategory.textContent = selectedCategory || 'Not selected';
        previewTitle.textContent = complaintTitle || '(empty)';
        previewDesc.textContent = complaintDesc || '(empty)';
        previewLocation.textContent = complaintLocation || 'Not provided';
        previewDate.textContent = incidentDate || 'Not set';
        previewPriority.textContent = priority.charAt(0).toUpperCase() + priority.slice(1);
        previewEvidence.innerHTML = evidenceName === 'None' ? 
            '<i class="fas fa-file-alt"></i> None' : 
            `<i class="fas fa-paperclip"></i> ${evidenceName}`;
        finalCategory.textContent = selectedCategory || 'Not selected';
        finalTitle.textContent = complaintTitle || '(empty)';
        finalDesc.textContent = complaintDesc || '(empty)';
        finalLocation.textContent = complaintLocation || 'Not provided';
        finalDate.textContent = incidentDate || 'Not set';
        finalPriority.textContent = priority.charAt(0).toUpperCase() + priority.slice(1);
        finalEvidence.textContent = evidenceName;
    }

    nextStep4.addEventListener('click', () => {
        if (!complaintTitle || !complaintDesc) {
            showToastError('Please complete the complaint details first (Step 2).');
            currentStep = 2;
            updateSteps();
            return;
        }
        currentStep = 5;
        updateSteps();
    });

    prevStep4.addEventListener('click', () => { currentStep = 3; updateSteps(); });
    prevStep5.addEventListener('click', () => { currentStep = 4; updateSteps(); });

    submitBtn.addEventListener('click', async function() {
        if (!selectedCategory || !complaintTitle || !complaintDesc || !incidentDate) {
            showToastError('Please complete all required fields.');
            return;
        }

        const formData = new FormData();
        formData.append('category', selectedCategory);
        formData.append('title', complaintTitle);
        formData.append('description', complaintDesc);
        formData.append('location', complaintLocation);
        formData.append('incident_date', incidentDate);
        formData.append('priority', priority);
        if (selectedFile) formData.append('evidence', selectedFile);

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        try {
            const response = await fetch('submit_complaint.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                showToastSuccess('Complaint submitted successfully! Redirecting...');
                setTimeout(() => { window.location.href = 'student_dashboard.php'; }, 2000);
            } else {
                showToastError('Error: ' + result.message);
                this.disabled = false;
                this.innerHTML = 'Submit Complaint';
            }
        } catch (err) {
            showToastError('Network error. Please try again.');
            this.disabled = false;
            this.innerHTML = 'Submit Complaint';
        }
    });

    window.addEventListener('resize', () => requestAnimationFrame(() => positionVerticalLine()));
    const resizeObserver = new ResizeObserver(() => requestAnimationFrame(() => positionVerticalLine()));
    resizeObserver.observe(stepList);
    stepItems.forEach(item => resizeObserver.observe(item));
    updateSteps();
</script>

</body>
</html>