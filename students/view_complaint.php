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

// Fetch all responses (including deputy rector, rector, any staff)
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

// Helper function to format role names for display
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        /* ========== SIDEBAR ========== */
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
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            z-index: 100;
        }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header {
            padding: 24px 16px 16px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
        }
        .row-cfms {
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        .brand {
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .toggle-inline {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .toggle-inline:hover { background: rgba(255,255,255,0.3); }
        .row-tagline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }
        .tagline {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.75);
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .toggle-standalone {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .toggle-standalone:hover { background: rgba(255,255,255,0.3); }
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
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
        }
        .menu-item i {
            width: 28px;
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
        }
        .menu-item span {
            font-size: 0.95rem;
            transition: opacity 0.2s;
        }
        .sidebar.collapsed .menu-item span {
            opacity: 0;
            visibility: hidden;
            width: 0;
        }
        .sidebar.collapsed .menu-item { justify-content: center; padding: 14px 0; }
        .menu-item:hover { background: rgba(255, 255, 255, 0.12); color: white; text-decoration: none; }
        .menu-item.active { background: rgba(255, 255, 255, 0.2); color: white; font-weight: 600; }
        .logout-item {
            margin-top: auto;
            margin-bottom: 28px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 20px;
        }
        .logout-item .menu-item { color: rgba(255, 255, 255, 0.9); }
        .logout-item .menu-item:hover {
            background: rgba(255, 100, 100, 0.25);
            color: #ffcaca;
        }
        /* ========== MAIN CONTENT ========== */
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
            gap: 24px;
            border-bottom: 1px solid rgba(0, 71, 171, 0.1);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .create-btn-border {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            border: 2px solid #0047AB;
            border-radius: 40px;
            background: transparent;
            color: #0047AB;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .create-btn-border:hover {
            background: rgba(0, 71, 171, 0.08);
            border-color: #003380;
            color: #003380;
        }
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
            font-size: 1.2rem;
            overflow: hidden;
        }
        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-details {
            text-align: right;
        }
        .profile-details .name {
            font-weight: 700;
            color: #1f2c40;
        }
        .profile-details .reg {
            font-size: 0.75rem;
            color: #6c85a3;
        }
        .dashboard-body {
            padding: 32px 36px;
        }
        .content-area {
            background: white;
            border-radius: 28px;
            padding: 28px 32px;
            min-height: 300px;
            border: 1px solid rgba(0, 71, 171, 0.06);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.02);
        }
        .complaint-detail-grid {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .description-box {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-in-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #d1fae5; color: #065f46; }
        .badge-escalated { background: #fee2e2; color: #991b1b; }
        .download-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2ff;
            padding: 6px 12px;
            border-radius: 40px;
            color: #0047AB;
            text-decoration: none;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .download-link:hover {
            background: #0047AB;
            color: white;
        }
        .conversation {
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 16px;
            background: #fefefe;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 16px;
        }
        .student-message {
            background: #e0f2fe;
            border-left: 4px solid #0047AB;
        }
        .staff-message {
            background: #f1f5f9;
            border-left: 4px solid #2ecc71;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        .message-body {
            line-height: 1.5;
        }
        .followup-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .followup-section textarea {
            width: 100%;
            padding: 12px;
            border-radius: 16px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            margin-bottom: 12px;
        }
        .btn-submit {
            background: #0047AB;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-submit:hover { background: #003380; }
        .feedback-prompt {
            margin-top: 30px;
            padding: 20px;
            background: #e0f2fe;
            border-radius: 20px;
            text-align: center;
        }
        .btn-primary {
            background: #0047AB;
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-block;
            margin-top: 12px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: #e0f2e9;
            color: #1e7b4c;
            border-left: 4px solid #1e7b4c;
        }
        .alert.error {
            background: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #b91c1c;
        }
        .spinner-small {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #e0e7f0;
            border-top-color: #0047AB;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 8px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
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
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
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
            background: rgba(255, 255, 255, 0.9);
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
            .row-tagline { display: none; }
            .row-cfms { flex-direction: column !important; gap: 12px; }
            .toggle-inline { display: flex !important; }
            .menu-item span { display: none; }
            .main-content { margin-left: 80px; }
            .dashboard-body { padding: 20px; }
            .top-bar { padding: 12px 20px; }
            .create-btn-border { padding: 6px 12px; font-size: 0.8rem; }
            .complaint-detail-grid { grid-template-columns: 1fr; }
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
            <span class="tagline">Complaint Management</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="student_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="my_complaints.php" class="menu-item active"><i class="fas fa-file-alt"></i><span>My Complaints</span></a>
        <a href="my_feedback.php" class="menu-item"><i class="fas fa-comment-dots"></i><span>Feedback</span></a>
        <a href="student_dashboard.php?page=profile" class="menu-item"><i class="fas fa-user-circle"></i><span>Profile</span></a>
        <a href="student_dashboard.php?page=change-password" class="menu-item"><i class="fas fa-key"></i><span>Change Password</span></a>
        <a href="#" class="menu-item"><i class="fas fa-bullhorn"></i><span>Announcements</span></a>
    </div>
    <div class="logout-item">
        <div class="menu-item" id="logoutBtn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <a href="new_complaint.php" class="create-btn-border"><i class="fas fa-plus-circle"></i> Create New Complaint</a>
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

    <div class="dashboard-body">
        <div class="content-area">
            <?php if ($success_msg): ?>
                <div class="alert success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <h3>Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?></h3>

            <div class="complaint-detail-grid">
                <div><strong>Title:</strong> <?php echo htmlspecialchars($complaint['title']); ?></div>
                <div><strong>Category:</strong> <?php echo htmlspecialchars($complaint['category_name']); ?></div>
                <div><strong>Status:</strong> <span class="badge <?php echo 'badge-'.str_replace('_','-',$complaint['status']); ?>"><?php echo ucfirst($complaint['status']); ?></span></div>
                <div><strong>Priority:</strong> <?php echo ucfirst($complaint['priority']); ?></div>
                <div><strong>Location:</strong> <?php echo htmlspecialchars($complaint['location'] ?: 'Not provided'); ?></div>
                <div><strong>Incident Date:</strong> <?php echo date('d/m/Y', strtotime($complaint['incident_date'])); ?></div>
                <div><strong>Submitted:</strong> <?php echo date('d/m/Y H:i', strtotime($complaint['created_at'])); ?></div>
                <div><strong>Evidence:</strong>
                    <?php if ($complaint['attachment_path']): ?>
                        <a href="../<?php echo $complaint['attachment_path']; ?>" target="_blank" class="download-link"><i class="fas fa-download"></i> Download Evidence</a>
                    <?php else: ?>
                        <span style="color:#6c85a3;">None</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="description-box">
                <strong>Description:</strong><br>
                <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
            </div>

            <h4>Conversation</h4>
            <div class="conversation" id="conversation">
                <?php foreach ($responses as $resp): ?>
                    <div class="message <?php echo ($resp['role'] == 'student') ? 'student-message' : 'staff-message'; ?>">
                        <div class="message-header">
                            <strong><?php echo htmlspecialchars($resp['full_name']); ?> (<?php echo formatRole($resp['role']); ?>)</strong>
                            <small><?php echo date('d/m/Y H:i', strtotime($resp['created_at'])); ?></small>
                        </div>
                        <div class="message-body"><?php echo nl2br(htmlspecialchars($resp['message'])); ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($responses)): ?>
                    <p>No messages yet. Staff will respond soon.</p>
                <?php endif; ?>
            </div>

            <?php if ($complaint['status'] != 'closed' && $complaint['status'] != 'resolved'): ?>
                <div class="followup-section">
                    <h4>Add a Follow‑up Comment</h4>
                    <div id="followupAlert" class="alert" style="display:none;"></div>
                    <form id="followupForm">
                        <textarea id="followupComment" rows="3" placeholder="Write your follow‑up here..."></textarea>
                        <button type="submit" id="sendFollowupBtn" class="btn-submit">Send Follow‑up</button>
                    </form>
                </div>
            <?php elseif ($complaint['status'] == 'resolved' && !$has_rating): ?>
                <div class="feedback-prompt">
                    <p>This complaint has been resolved. Please rate your experience.</p>
                    <a href="feedback.php?id=<?php echo $complaint_id; ?>" class="btn-primary">Give Feedback</a>
                </div>
            <?php elseif ($complaint['status'] == 'resolved' && $has_rating): ?>
                <div class="feedback-prompt">
                    <p>You have already rated this complaint. Thank you for your feedback.</p>
                </div>
            <?php endif; ?>
        </div>
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
    <div style="text-align: center;">
        <div class="spinner"></div>
        <div class="loading-text">Logging out, please wait...</div>
    </div>
</div>

<script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const toggleInline = document.getElementById('toggleInline');
    const toggleStandalone = document.getElementById('toggleStandalone');
    function updateToggleIcons(collapsed) {
        const arrow = collapsed ? '❯' : '❮';
        toggleInline.innerHTML = arrow;
        toggleStandalone.innerHTML = arrow;
    }
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        updateToggleIcons(true);
    } else {
        updateToggleIcons(false);
    }
    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        const collapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', collapsed);
        updateToggleIcons(collapsed);
    }
    toggleInline.addEventListener('click', toggleSidebar);
    toggleStandalone.addEventListener('click', toggleSidebar);

    // Logout modal
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
        setTimeout(() => { window.location.href = '../logout.php'; }, 500);
    });
    cancelBtn.addEventListener('click', () => {
        modal.classList.remove('active');
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });

    // AJAX follow‑up submission
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
                followupAlert.innerHTML = 'Please enter a comment.';
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
                                <strong><?php echo addslashes($full_name); ?> (Student)</strong>
                                <small>Just now</small>
                            </div>
                            <div class="message-body">${escapeHtml(comment)}</div>
                        </div>
                    `;
                    conversationDiv.insertAdjacentHTML('beforeend', newMessage);
                    conversationDiv.scrollTop = conversationDiv.scrollHeight;
                    followupComment.value = '';
                    followupAlert.style.display = 'block';
                    followupAlert.className = 'alert success';
                    followupAlert.innerHTML = '✓ Follow‑up added successfully!';
                    setTimeout(() => { followupAlert.style.display = 'none'; }, 3000);
                } else {
                    followupAlert.style.display = 'block';
                    followupAlert.className = 'alert error';
                    followupAlert.innerHTML = 'Error: ' + result.message;
                    setTimeout(() => { followupAlert.style.display = 'none'; }, 3000);
                }
            } catch (err) {
                followupAlert.style.display = 'block';
                followupAlert.className = 'alert error';
                followupAlert.innerHTML = 'Network error. Please try again.';
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