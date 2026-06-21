<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/connection.php';

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];
$admin_email = $_SESSION['email'];

$active_page = 'manage_users';

// Handle user deletion
if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    if ($delete_id != $admin_id) {
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $delete_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
        $_SESSION['flash_message'] = "User deleted successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "You cannot delete your own account!";
        $_SESSION['flash_type'] = "error";
    }
    header("Location: manage_users.php");
    exit();
}

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Get all users
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';

$users_where = "1=1";
if ($search) {
    $users_where .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR reg_number LIKE '%$search%')";
}
if ($role_filter) {
    $users_where .= " AND role = '$role_filter'";
}

$users_sql = "SELECT id, full_name, email, role, is_active, phone_number, created_at, reg_number FROM users WHERE $users_where ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_sql);

// Get departments for dropdown
$departments = [];
$dept_sql = "SELECT id, name FROM departments ORDER BY name";
$dept_result = mysqli_query($conn, $dept_sql);
while ($dept = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $dept;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Users - Admin Panel</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f4f7fc; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 280px; background: #0047AB; color: white; display: flex; flex-direction: column; height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; transition: width 0.3s ease; box-shadow: 4px 0 20px rgba(0,0,0,0.08); z-index: 100; }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 24px 16px 16px 16px; border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 20px; }
        .row-cfms { display: flex; justify-content: space-between; align-items: center; }
        .brand { font-size: 1.6rem; font-weight: 700; color: white; white-space: nowrap; }
        .toggle-inline { background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .row-tagline { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
        .tagline { font-size: 0.7rem; color: rgba(255,255,255,0.75); white-space: nowrap; }
        .toggle-standalone { background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .sidebar:not(.collapsed) .toggle-inline { display: none; }
        .sidebar.collapsed .row-tagline { display: none; }
        .sidebar.collapsed .row-cfms { flex-direction: column; justify-content: center; gap: 12px; }
        .sidebar.collapsed .brand { font-size: 1rem; }
        .sidebar.collapsed .toggle-inline { display: flex; }
        .sidebar-menu { flex: 1; padding: 0 12px; }
        .menu-item { display: flex; align-items: center; gap: 16px; padding: 14px 12px; margin: 10px 0; border-radius: 14px; cursor: pointer; transition: all 0.2s; color: rgba(255,255,255,0.85); font-weight: 500; white-space: nowrap; text-decoration: none; }
        .menu-item i { width: 28px; font-size: 1.3rem; text-align: center; }
        .menu-item span { font-size: 0.95rem; }
        .sidebar.collapsed .menu-item span { opacity: 0; visibility: hidden; width: 0; }
        .sidebar.collapsed .menu-item { justify-content: center; padding: 14px 0; }
        .menu-item:hover { background: rgba(255,255,255,0.12); color: white; text-decoration: none; }
        .menu-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .logout-item { margin-top: auto; margin-bottom: 28px; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 20px; }
        .main-content { flex: 1; margin-left: 280px; transition: margin-left 0.3s ease; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        .sidebar.collapsed ~ .main-content { margin-left: 80px; }
        .top-bar { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,71,171,0.1); position: sticky; top: 0; z-index: 10; }
        .welcome-message { font-size: 1.2rem; font-weight: 500; color: #0047AB; }
        .profile-info { display: flex; align-items: center; gap: 16px; }
        .profile-pic { width: 44px; height: 44px; background: #0047AB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; overflow: hidden; }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-details { text-align: right; }
        .profile-details .name { font-weight: 700; color: #1f2c40; }
        .dashboard-body { padding: 32px 36px; }
        .content-area { background: white; border-radius: 28px; padding: 28px 32px; min-height: 300px; border: 1px solid rgba(0,71,171,0.06); }
        .filters-bar { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; align-items: flex-end; background: #f8fafc; padding: 20px; border-radius: 20px; }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 5px; color: #2c3e66; }
        .filter-group input, .filter-group select { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 0.9rem; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #0047AB; }
        .filter-group button { background: #0047AB; color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer; font-weight: 500; }
        .filter-group button:hover { background: #003380; }
        .btn-sm { padding: 6px 14px; border-radius: 30px; background: #0047AB; color: white; text-decoration: none; font-size: 0.8rem; display: inline-block; margin: 2px; border: none; cursor: pointer; }
        .btn-sm:hover { background: #003380; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-edit { background: #f59e0b; }
        .btn-edit:hover { background: #d97706; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th { padding: 16px 20px; text-align: left; background: #f8fafc; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
        .users-table td { padding: 14px 20px; border-bottom: 1px solid #edf2f7; }
        .badge { padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
        .role-student { background: #dbeafe; color: #1e40af; }
        .role-hod { background: #cffafe; color: #0e7490; }
        .role-dean { background: #e0e7ff; color: #4338ca; }
        .role-accountant { background: #fef3c7; color: #b45309; }
        .role-director { background: #f1f5f9; color: #475569; }
        .role-admin { background: #fce7f3; color: #be185d; }
        .role-it_officer { background: #dcfce7; color: #166534; }
        .role-examination_officer { background: #fef9c3; color: #854d0e; }
        .role-president { background: #e0f2fe; color: #0369a1; }
        .role-deputy_rector { background: #f3e8ff; color: #6b21a5; }
        .role-rector { background: #fef08a; color: #713f12; }
        .table-responsive { overflow-x: auto; }
        
        /* Modal Styles */
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
        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        .modal-container {
            background: white;
            border-radius: 28px;
            max-width: 500px;
            width: 90%;
            padding: 28px 24px;
            text-align: center;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-container h3 {
            color: #0047AB;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2c40;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            font-size: 0.95rem;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #0047AB;
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
        .modal-btn.cancel:hover {
            background: #cbd5e1;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 24px;
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
        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .brand { font-size: 1rem !important; }
            .row-tagline, .tagline, .menu-item span { display: none; }
            .row-cfms { flex-direction: column !important; gap: 12px; }
            .toggle-inline { display: flex !important; }
            .main-content { margin-left: 80px; }
            .dashboard-body { padding: 20px; }
            .content-area { padding: 20px; }
            .filters-bar { flex-direction: column; }
            .users-table { font-size: 0.8rem; }
            .users-table th, .users-table td { padding: 10px 8px; }
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
            <span class="tagline">Admin Portal</span>
            <button class="toggle-standalone" id="toggleStandalone">❮</button>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="admin_dashboard.php" class="menu-item">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="manage_users.php" class="menu-item active">
            <i class="fas fa-users"></i><span>Manage Users</span>
        </a>
        <a href="all_complaints.php" class="menu-item">
            <i class="fas fa-file-alt"></i><span>All Complaints</span>
        </a>
        <a href="add_user.php" class="menu-item">
            <i class="fas fa-user-plus"></i><span>Add User</span>
        </a>
        <a href="profile.php" class="menu-item">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
        <a href="change_password.php" class="menu-item">
            <i class="fas fa-key"></i><span>Change Password</span>
        </a>
    </div>
    <div class="logout-item">
        <div class="menu-item" id="logoutBtn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
    </div>
</div>

<div class="main-content">
    <div class="top-bar">
        <div class="welcome-message">Welcome, <span><?php echo htmlspecialchars($admin_name); ?></span><br><small style="font-size:0.8rem;">System Administrator</small></div>
        <div class="profile-info">
            <div class="profile-pic">
                <?php if (!empty($_SESSION['profile_picture']) && file_exists('../' . $_SESSION['profile_picture'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="reg">Admin</div>
            </div>
        </div>
    </div>

    <div class="dashboard-body">
        <?php if ($flash_message): ?>
            <div class="alert <?php echo $flash_type; ?>"><?php echo $flash_message; ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email or reg number">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Filter by Role</label>
                    <select name="role_filter">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter=='student'?'selected':''; ?>>Student</option>
                        <option value="hod" <?php echo $role_filter=='hod'?'selected':''; ?>>HOD</option>
                        <option value="dean" <?php echo $role_filter=='dean'?'selected':''; ?>>Dean</option>
                        <option value="accountant" <?php echo $role_filter=='accountant'?'selected':''; ?>>Accountant</option>
                        <option value="director" <?php echo $role_filter=='director'?'selected':''; ?>>Director</option>
                        <option value="examination_officer" <?php echo $role_filter=='examination_officer'?'selected':''; ?>>Examination Officer</option>
                        <option value="president" <?php echo $role_filter=='president'?'selected':''; ?>>President</option>
                        <option value="deputy_rector" <?php echo $role_filter=='deputy_rector'?'selected':''; ?>>Deputy Rector</option>
                        <option value="rector" <?php echo $role_filter=='rector'?'selected':''; ?>>Rector</option>
                        <option value="it_officer" <?php echo $role_filter=='it_officer'?'selected':''; ?>>IT Officer</option>
                        <option value="admin" <?php echo $role_filter=='admin'?'selected':''; ?>>Admin</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit"><i class="fas fa-search"></i> Apply Filters</button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="manage_users.php" class="btn-sm" style="background:#6c757d; display: inline-block; text-align: center; line-height: 38px;"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="content-area">
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Reg Number</th><th>Phone</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($users_result) == 0): ?>
                            <tr><td colspan="9" style="text-align:center; padding:40px;">No users found. </div>--> ?>
                        <?php else: ?>
                            <?php while ($user = mysqli_fetch_assoc($users_result)):
                                $role_class = match($user['role']) {
                                    'student' => 'role-student',
                                    'hod' => 'role-hod',
                                    'dean' => 'role-dean',
                                    'accountant' => 'role-accountant',
                                    'director' => 'role-director',
                                    'admin' => 'role-admin',
                                    'it_officer' => 'role-it_officer',
                                    'examination_officer' => 'role-examination_officer',
                                    'president' => 'role-president',
                                    'deputy_rector' => 'role-deputy_rector',
                                    'rector' => 'role-rector',
                                    default => 'role-staff'
                                };
                            ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['reg_number'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone_number'] ?: '-'); ?></td>
                                <td><span class="role-badge <?php echo $role_class; ?>"><?php echo str_replace('_', ' ', ucfirst($user['role'])); ?></span></td>
                                <td><span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button onclick="openEditModal(<?php echo $user['id']; ?>)" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</button>
                                    <?php if ($user['id'] != $admin_id): ?>
                                        <a href="manage_users.php?delete_user=<?php echo $user['id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i> Delete</a>
                                    <?php endif; ?>
                                 </div>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <i class="fas fa-edit" style="font-size: 2rem; color: #0047AB;"></i>
        <h3>Edit User</h3>
        <form id="editUserForm">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="edit_full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="edit_phone" name="phone">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="edit_role" name="role">
                    <option value="student">Student</option>
                    <option value="hod">HOD</option>
                    <option value="dean">Dean</option>
                    <option value="accountant">Accountant</option>
                    <option value="director">Director</option>
                    <option value="examination_officer">Examination Officer</option>
                    <option value="president">President</option>
                    <option value="deputy_rector">Deputy Rector</option>
                    <option value="rector">Rector</option>
                    <option value="it_officer">IT Officer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" id="edit_password" name="password" placeholder="Enter new password to change">
            </div>
            <div class="modal-buttons">
                <button type="button" class="modal-btn confirm" id="saveUserBtn">Save Changes</button>
                <button type="button" class="modal-btn cancel" id="closeEditModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-container">
        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc2626;"></i>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
        <div class="modal-buttons">
            <button class="modal-btn confirm" id="confirmDeleteBtn">Yes, Delete</button>
            <button class="modal-btn cancel" id="cancelDeleteBtn">Cancel</button>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-container">
        <i class="fas fa-sign-out-alt"></i><h3>Confirm Logout</h3><p>Are you sure you want to logout?</p>
        <div class="modal-buttons">
            <button class="modal-btn confirm" id="confirmLogout">Yes, Logout</button>
            <button class="modal-btn cancel" id="cancelLogout">No, Cancel</button>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="loading-overlay">
    <div style="text-align:center;">
        <div class="spinner"></div>
        <div style="margin-top:16px; font-weight:500; color:#0047AB;">Processing...</div>
    </div>
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
    
    // ========== EDIT USER MODAL FUNCTIONS ==========
    function openEditModal(userId) {
        // Show loading overlay
        document.getElementById('loadingOverlay').classList.add('active');
        
        // Fetch user data
        fetch('get_user.php?id=' + userId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');
                if (data.success) {
                    document.getElementById('edit_user_id').value = data.user.id;
                    document.getElementById('edit_full_name').value = data.user.full_name;
                    document.getElementById('edit_email').value = data.user.email;
                    document.getElementById('edit_phone').value = data.user.phone_number || '';
                    document.getElementById('edit_role').value = data.user.role;
                    document.getElementById('edit_password').value = '';
                    document.getElementById('editModal').classList.add('active');
                } else {
                    alert('Error loading user data: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').classList.remove('active');
                console.error('Error:', error);
                alert('Error loading user data. Please try again.');
            });
    }
    
    // Save user changes
    document.getElementById('saveUserBtn').addEventListener('click', function() {
        const formData = new FormData(document.getElementById('editUserForm'));
        formData.append('update_user_ajax', '1');
        
        document.getElementById('loadingOverlay').classList.add('active');
        document.getElementById('editModal').classList.remove('active');
        
        fetch('manage_users_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loadingOverlay').classList.remove('active');
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').classList.remove('active');
            console.error('Error:', error);
            alert('Error updating user. Please try again.');
        });
    });
    
    // Close edit modal
    document.getElementById('closeEditModal').addEventListener('click', function() {
        document.getElementById('editModal').classList.remove('active');
    });
    
    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === document.getElementById('editModal')) {
            document.getElementById('editModal').classList.remove('active');
        }
    });
    
    // ========== DELETE MODAL FUNCTIONS ==========
    let deleteUserId = null;
    function confirmDelete(userId) {
        deleteUserId = userId;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (deleteUserId) {
            window.location.href = 'manage_users.php?delete_user=' + deleteUserId;
        }
    });
    
    document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
        document.getElementById('deleteModal').classList.remove('active');
        deleteUserId = null;
    });
    
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === document.getElementById('deleteModal')) {
            document.getElementById('deleteModal').classList.remove('active');
            deleteUserId = null;
        }
    });
    
    // ========== LOGOUT FUNCTIONS ==========
    const logoutBtn = document.getElementById('logoutBtn');
    const modal = document.getElementById('logoutModal');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');
    
    logoutBtn.addEventListener('click', (e) => { 
        e.preventDefault(); 
        modal.classList.add('active'); 
    });
    
    confirmLogout.addEventListener('click', () => { 
        modal.classList.remove('active'); 
        loadingOverlay.classList.add('active'); 
        setTimeout(() => { 
            window.location.href = '../logout.php'; 
        }, 500); 
    });
    
    cancelLogout.addEventListener('click', () => { 
        modal.classList.remove('active'); 
    });
    
    modal.addEventListener('click', (e) => { 
        if (e.target === modal) {
            modal.classList.remove('active'); 
        }
    });
</script>
</body>
</html>