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

// Fetch complaints for this student
$sql = "SELECT c.id, c.complaint_number, c.title, cat.name AS category, 
               c.status, c.created_at
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.student_id = ?
        ORDER BY c.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$complaints = [];
while ($row = mysqli_fetch_assoc($result)) {
    $complaints[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Complaints - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Same styles as student dashboard (copy exactly) */
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
        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 14px 0;
        }
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            text-decoration: none;
        }
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
        }
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
        .complaints-table {
            width: 100%;
            border-collapse: collapse;
        }
        .complaints-table th,
        .complaints-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e7f0;
        }
        .complaints-table th {
            background: #f4f7fc;
            font-weight: 600;
            color: #1f2c40;
        }
        .complaints-table tr:hover {
            background: #f9fafb;
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
        .badge-closed { background: #e2e3e5; color: #383d41; }
        .btn-view {
            background: #0047AB;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: 0.2s;
        }
        .btn-view:hover {
            background: #003380;
        }
        .empty-row td {
            text-align: center;
            padding: 40px;
            color: #6c85a3;
        }
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
            .complaints-table th, .complaints-table td { padding: 8px; font-size: 0.8rem; }
        }
        /* Modal & loading (same as dashboard) */
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
        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }
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
        .modal-btn.confirm {
            background: #0047AB;
            color: white;
        }
        .modal-btn.confirm:hover {
            background: #003380;
        }
        .modal-btn.cancel {
            background: #e0e7f0;
            color: #1f2c40;
        }
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
        .loading-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e0e7f0;
            border-top-color: #0047AB;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text {
            margin-top: 16px;
            font-weight: 500;
            color: #0047AB;
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
        <a href="student_dashboard.php?page=dashboard" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="my_complaints.php" class="menu-item active">
            <i class="fas fa-file-alt"></i><span>My Complaints</span>
        </a>
        <a href="my_feedback.php" class="menu-item" data-page="feedback">
            <i class="fas fa-comment-dots"></i><span>Feedback</span>
        </a>
        <a href="student_dashboard.php?page=profile" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="student_dashboard.php?page=change-password" class="menu-item">
            <i class="fas fa-key"></i><span>Change Password</span>
        </a>
        <a href="#" class="menu-item" data-page="announcements">
            <i class="fas fa-bullhorn"></i><span>Announcements</span>
        </a>
    </div>
    <div class="logout-item">
        <div class="menu-item" id="logoutBtn">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <a href="new_complaint.php" class="create-btn-border">
            <i class="fas fa-plus-circle"></i> Create New Complaint
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

    <div class="dashboard-body">
        <div class="content-area">
            <h3 style="margin-bottom: 20px;">My Complaints</h3>
            <table class="complaints-table">
                <thead>
                    <tr><th>Complaint #</th><th>Title</th><th>Category</th><th>Status</th><th>Date Submitted</th><th>Action</th></tr>
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
                                <td><?php echo htmlspecialchars($c['complaint_number']); ?></td>
                                <td><?php echo htmlspecialchars($c['title']); ?></td>
                                <td><?php echo htmlspecialchars($c['category']); ?></td>
                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                                <td><a href="view_complaint.php?id=<?php echo $c['id']; ?>" class="btn-view">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row"><td colspan="6">You have not submitted any complaints yet. Click "Create New Complaint" to get started.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal (same as dashboard) -->
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
    // Sidebar toggle logic
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