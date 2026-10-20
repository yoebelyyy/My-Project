<?php
require_once '../db_connect.php';

// Only admin can access
if(!isLoggedIn()) redirect('../login.php');
if(!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Only administrators can manage users.';
    redirect('../dashboard.php');
}

$success = '';
$error = '';

// Delete user
if(isset($_GET['delete']) && isAdmin()) {
    $delete_id = (int)$_GET['delete'];
    if($delete_id == $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        mysqli_query($conn, "DELETE FROM incidents WHERE user_id=$delete_id");
        mysqli_query($conn, "DELETE FROM quiz_results WHERE user_id=$delete_id");
        mysqli_query($conn, "DELETE FROM activity_log WHERE user_id=$delete_id");
        mysqli_query($conn, "DELETE FROM users WHERE id=$delete_id");
        logActivity($_SESSION['user_id'], 'delete_user', "Deleted user #$delete_id");
        $success = 'User deleted successfully.';
    }
}

// Change role
if(isset($_POST['change_role']) && isAdmin()) {
    $user_id = (int)$_POST['user_id'];
    $new_role = sanitize($_POST['role']);
    if($user_id != $_SESSION['user_id']) {
        mysqli_query($conn, "UPDATE users SET role='$new_role' WHERE id=$user_id");
        logActivity($_SESSION['user_id'], 'change_role', "Changed user #$user_id role to $new_role");
        $success = 'User role updated successfully.';
    } else {
        $error = 'You cannot change your own role.';
    }
}

// Get all users
$users = mysqli_query($conn, 
    "SELECT u.*, 
            (SELECT COUNT(*) FROM incidents WHERE user_id=u.id) as report_count,
            (SELECT COUNT(*) FROM incidents WHERE user_id=u.id AND status='pending') as pending_count
     FROM users u 
     ORDER BY FIELD(u.role,'admin','staff','user'), u.created_at DESC");

// Stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='admin'"))['c'];
$total_staff = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='staff'"))['c'];
$total_regular = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user'"))['c'];
$online_now = getOnlineUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel | SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green-dark:#0A2E1F; --green-mid:#1B4D3E; --green-accent:#4CAF50; --green-lighter:#81C784; --danger:#C62828; --warning:#FF9800; --info:#2196F3; --staff:#9C27B0; --gold:#FFD700; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#F1F8E9; }
        
        .top-bar {
            background:var(--green-dark);
            color:white;
            padding:8px 30px;
            font-size:0.85rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:10px;
        }
        .top-bar .clock {
            font-weight:700;
            color:var(--green-lighter);
            font-family:'Courier New',monospace;
            font-size:1.1rem;
        }
        
        .navbar {
            background:linear-gradient(135deg,#1B4D3E,#2D6A4F);
            padding:12px 30px;
            color:white;
            display:flex;
            justify-content:space-between;
            align-items:center;
            box-shadow:0 4px 20px rgba(0,0,0,0.2);
            flex-wrap:wrap;
            gap:10px;
        }
        .navbar a { color:white; text-decoration:none; margin:0 8px; font-weight:500; font-size:0.9rem; }
        .navbar a:hover { color:#81C784; }
        .navbar .brand { font-size:1.2rem; font-weight:800; }
        .badge-admin { background:var(--gold); color:#333; padding:4px 12px; border-radius:20px; font-weight:700; font-size:0.8rem; }
        
        .container { max-width:1400px; margin:25px auto; padding:0 20px; }
        
        .header {
            background:linear-gradient(135deg,#1B4D3E,#2D6A4F);
            color:white;
            padding:25px 30px;
            border-radius:15px;
            margin-bottom:20px;
        }
        
        .alert { padding:15px; border-radius:10px; margin-bottom:20px; font-weight:600; }
        .alert-success { background:#e8f5e9; color:#2e7d32; }
        .alert-error { background:#ffebee; color:#c62828; }
        
        .stats-mini {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
            gap:10px;
            margin-bottom:20px;
        }
        .stat-mini {
            background:white;
            padding:15px;
            border-radius:10px;
            text-align:center;
            box-shadow:0 3px 10px rgba(0,0,0,0.05);
        }
        .stat-mini .num { font-size:1.8rem; font-weight:800; }
        .stat-mini .lbl { font-size:0.8rem; color:#666; }
        
        .card {
            background:white;
            border-radius:15px;
            padding:25px;
            box-shadow:0 5px 20px rgba(0,0,0,0.08);
        }
        .card h3 { color:#1B4D3E; margin-bottom:20px; }
        
        table { width:100%; border-collapse:collapse; }
        th {
            background:#F1F8E9;
            padding:12px 15px;
            text-align:left;
            color:#1B4D3E;
            font-weight:700;
            font-size:0.85rem;
            border-bottom:2px solid #C8E6C9;
        }
        td { padding:10px 15px; border-bottom:1px solid #E0E0E0; font-size:0.9rem; }
        tr:hover { background:#F9FBE7; }
        
        .badge {
            padding:3px 10px;
            border-radius:20px;
            font-size:0.75rem;
            font-weight:600;
            display:inline-block;
        }
        .badge-admin { background:#FFD700; color:#333; }
        .badge-staff { background:#E1BEE7; color:#6A1B9A; }
        .badge-user { background:#E3F2FD; color:#1565C0; }
        .badge-online { background:#E8F5E9; color:#2E7D32; }
        .badge-offline { background:#ECEFF1; color:#546E7A; }
        
        .btn {
            padding:6px 14px;
            border-radius:25px;
            text-decoration:none;
            font-weight:600;
            font-size:0.8rem;
            cursor:pointer;
            border:none;
            transition:0.3s;
            display:inline-block;
            margin:2px;
        }
        .btn-warning { background:#FF9800; color:white; }
        .btn-danger { background:#f44336; color:white; }
        .btn-info { background:#2196F3; color:white; }
        .btn:hover { opacity:0.9; transform:translateY(-2px); }
        
        .modal {
            display:none;
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.5);
            z-index:1000;
            justify-content:center;
            align-items:center;
        }
        .modal.active { display:flex; }
        .modal-content {
            background:white;
            padding:25px;
            border-radius:15px;
            max-width:400px;
            width:90%;
        }
        
        @media (max-width:768px) {
            .navbar { flex-direction:column; text-align:center; }
            table { font-size:0.8rem; }
            th, td { padding:8px; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span>📅 <strong><?php echo date('l, F j, Y'); ?></strong></span>
        <span class="clock" id="liveClock"><?php echo date('h:i:s A'); ?></span>
        <span>🟢 <?php echo $online_now; ?> online</span>
    </div>

    <nav class="navbar">
        <div>
            <a href="../dashboard.php" class="brand">🌿 SmartEcoTrack</a>
            <span class="badge-admin"><i class="fas fa-crown"></i> ADMIN PANEL</span>
        </div>
        <div>
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="reports.php"><i class="fas fa-clipboard"></i> Reports</a>
            <a href="users.php" style="color:#FFD700;"><i class="fas fa-users"></i> Users</a>
            <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="../logout.php" style="color:#ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users-cog"></i> User Management</h1>
            <p>Admin: <?php echo $_SESSION['firstname'].' '.$_SESSION['lastname']; ?> | Date: <?php echo date('F j, Y h:i A'); ?></p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="stats-mini">
            <div class="stat-mini"><div class="num"><?php echo $total_users; ?></div><div class="lbl">Total Users</div></div>
            <div class="stat-mini"><div class="num" style="color:#FFD700;"><?php echo $total_admins; ?></div><div class="lbl">Admins</div></div>
            <div class="stat-mini"><div class="num" style="color:#9C27B0;"><?php echo $total_staff; ?></div><div class="lbl">Staff</div></div>
            <div class="stat-mini"><div class="num" style="color:#2196F3;"><?php echo $total_regular; ?></div><div class="lbl">Users</div></div>
            <div class="stat-mini"><div class="num" style="color:#4CAF50;"><?php echo $online_now; ?></div><div class="lbl">Online Now</div></div>
        </div>

        <div class="card">
            <h3><i class="fas fa-list"></i> All Users</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Points</th>
                            <th>Reports</th>
                            <th>Pending</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($u['firstname'].' '.$u['lastname']); ?></strong>
                                <?php if($u['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge badge-admin">YOU</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $u['role']; ?>">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $u['is_online'] ? 'badge-online' : 'badge-offline'; ?>">
                                    <?php echo $u['is_online'] ? '🟢 Online' : '⚫ Offline'; ?>
                                </span>
                            </td>
                            <td><?php echo $u['quiz_score']; ?></td>
                            <td><?php echo $u['report_count']; ?></td>
                            <td><?php echo $u['pending_count']; ?></td>
                            <td><?php echo timeAgo($u['last_active']); ?></td>
                            <td>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-warning" onclick="openRoleModal(<?php echo $u['id']; ?>,'<?php echo $u['role']; ?>','<?php echo addslashes($u['firstname']); ?>')">
                                        <i class="fas fa-edit"></i> Role
                                    </button>
                                    <?php if(isAdmin()): ?>
                                        <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete user: <?php echo addslashes($u['firstname']); ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#999;">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="roleModal">
        <div class="modal-content">
            <h3>Change User Role</h3>
            <form method="POST">
                <input type="hidden" name="user_id" id="modal_user_id">
                <p id="modal_user_name" style="margin-bottom:15px;font-weight:600;"></p>
                <label>New Role:</label>
                <select name="role" id="modal_role" style="width:100%;padding:10px;border:2px solid #ddd;border-radius:8px;margin-bottom:15px;">
                    <option value="user">👤 User (Regular)</option>
                    <option value="staff">👔 Staff (Report Manager)</option>
                    <option value="admin">👑 Admin (Full Access)</option>
                </select>
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="change_role" class="btn btn-info">Save Changes</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateClock() {
            document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US',{timeZone:'Asia/Manila',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
        }
        setInterval(updateClock,1000);
        updateClock();
        
        function openRoleModal(id,role,name) {
            document.getElementById('modal_user_id').value=id;
            document.getElementById('modal_role').value=role;
            document.getElementById('modal_user_name').textContent='User: '+name;
            document.getElementById('roleModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('roleModal').classList.remove('active');
        }
        window.onclick=function(e){if(e.target.classList.contains('modal'))closeModal();}
    </script>
</body>
</html>