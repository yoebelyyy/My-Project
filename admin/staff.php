<?php
require_once '../db_connect.php';

// Only admin can access this page
if(!isLoggedIn()) redirect('../login.php');
if(!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Only administrators can manage staff.';
    redirect('../dashboard.php');
}

$success = '';
$error = '';

// Create new staff account
if(isset($_POST['create_staff'])) {
    $firstname = sanitize($_POST['firstname']);
    $lastname = sanitize($_POST['lastname']);
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $phone = sanitize($_POST['phone']);
    
    // Validate
    if(empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif(strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
        if(mysqli_num_rows($check) > 0) {
            $error = 'Email already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (firstname, lastname, email, password, phone, role) 
                    VALUES ('$firstname', '$lastname', '$email', '$hashed', '$phone', 'staff')";
            
            if(mysqli_query($conn, $sql)) {
                $success = "Staff account created successfully!<br>
                           <strong>Email:</strong> $email<br>
                           <strong>Password:</strong> $password";
            } else {
                $error = 'Failed to create staff account: ' . mysqli_error($conn);
            }
        }
    }
}

// Change staff role back to user
if(isset($_GET['demote']) && isAdmin()) {
    $staff_id = (int)$_GET['demote'];
    if($staff_id != $_SESSION['user_id']) {
        mysqli_query($conn, "UPDATE users SET role='user' WHERE id=$staff_id AND role='staff'");
        $success = 'Staff member demoted to regular user.';
    }
}

// Promote user to staff
if(isset($_POST['promote_user'])) {
    $user_id = (int)$_POST['user_id'];
    if($user_id != $_SESSION['user_id']) {
        mysqli_query($conn, "UPDATE users SET role='staff' WHERE id=$user_id AND role='user'");
        $success = 'User promoted to staff successfully.';
    }
}

// Get all staff members
$staff_members = mysqli_query($conn, 
    "SELECT u.*, 
            (SELECT COUNT(*) FROM incidents WHERE user_id = u.id) as report_count,
            (SELECT COUNT(*) FROM incidents WHERE user_id = u.id AND status='pending') as pending_count
     FROM users u 
     WHERE u.role = 'staff' 
     ORDER BY u.created_at DESC");

// Get all regular users (for promotion)
$regular_users = mysqli_query($conn, 
    "SELECT * FROM users WHERE role='user' ORDER BY firstname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Admin Panel | SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --green-dark: #0A2E1F;
            --green-mid: #1B4D3E;
            --green-accent: #4CAF50;
            --staff-color: #9C27B0;
            --danger: #C62828;
            --warning: #FF9800;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #F1F8E9; }
        
        .navbar {
            background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
            padding: 15px 30px; color: white; display: flex; justify-content: space-between;
            align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; font-weight: 500; }
        .navbar a:hover { color: #81C784; }
        .navbar .brand { font-size: 1.3rem; font-weight: 800; }
        .navbar .admin-badge { background: #FFD700; color: #333; padding: 5px 15px; border-radius: 20px; font-weight: 700; }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .header {
            background: linear-gradient(135deg, #6A1B9A, #9C27B0);
            color: white; padding: 30px; border-radius: 20px; margin-bottom: 30px;
        }
        .header h1 { font-size: 1.8rem; }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        
        .card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 25px;
        }
        .card h3 { color: #1B4D3E; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #333; font-weight: 600; font-size: 0.9rem; }
        label .required { color: #C62828; }
        input, select, textarea {
            width: 100%; padding: 10px 15px; border: 2px solid #ddd; border-radius: 10px;
            font-size: 14px; font-family: inherit; transition: 0.3s;
        }
        input:focus, select:focus, textarea:focus { border-color: #9C27B0; outline: none; }
        
        .btn {
            padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 600;
            cursor: pointer; border: none; transition: 0.3s; display: inline-block; font-size: 14px;
        }
        .btn-primary { background: #9C27B0; color: white; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-warning { background: #FF9800; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #F3E5F5; padding: 12px 15px; text-align: left; color: #6A1B9A;
            font-weight: 700; font-size: 0.9rem; border-bottom: 2px solid #E1BEE7;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #E0E0E0; }
        tr:hover { background: #FCE4EC; }
        
        .badge {
            padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
            display: inline-block;
        }
        .badge-staff { background: #E1BEE7; color: #6A1B9A; }
        .badge-admin { background: #FFD700; color: #333; }
        .badge-user { background: #E3F2FD; color: #1565C0; }
        
        .staff-permissions {
            background: #F3E5F5; padding: 15px; border-radius: 10px; margin: 15px 0;
            border-left: 4px solid #9C27B0;
        }
        .staff-permissions h4 { color: #6A1B9A; margin-bottom: 10px; }
        .staff-permissions ul { list-style: none; padding: 0; }
        .staff-permissions li { padding: 5px 0; color: #333; }
        .staff-permissions li i { color: #4CAF50; margin-right: 8px; }
        
        @media (max-width: 768px) {
            .two-column { grid-template-columns: 1fr; }
            .navbar { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div>
            <a href="../dashboard.php" class="brand">🌿 SmartEcoTrack</a>
            <span class="admin-badge"><i class="fas fa-crown"></i> ADMIN PANEL</span>
        </div>
        <div>
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="reports.php"><i class="fas fa-clipboard"></i> Reports</a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
            <a href="staff.php" style="color:#E1BEE7;"><i class="fas fa-user-tie"></i> Staff</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="../logout.php" style="color:#ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-tie"></i> Staff Management</h1>
            <p>Create and manage staff accounts with special permissions</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="two-column">
            <!-- Create New Staff -->
            <div class="card">
                <h3><i class="fas fa-user-plus"></i> Create New Staff Account</h3>
                <div class="staff-permissions">
                    <h4><i class="fas fa-shield-alt"></i> Staff Permissions:</h4>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> View and manage incident reports</li>
                        <li><i class="fas fa-check-circle"></i> Update report status & add responses</li>
                        <li><i class="fas fa-check-circle"></i> View all users (cannot delete)</li>
                        <li><i class="fas fa-times-circle" style="color:#f44336;"></i> Cannot access admin settings</li>
                        <li><i class="fas fa-times-circle" style="color:#f44336;"></i> Cannot create/delete staff accounts</li>
                        <li><i class="fas fa-times-circle" style="color:#f44336;"></i> Cannot change user roles</li>
                    </ul>
                </div>
                <form method="POST">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-group">
                            <label><span class="required">*</span> First Name</label>
                            <input type="text" name="firstname" placeholder="Juan" required>
                        </div>
                        <div class="form-group">
                            <label><span class="required">*</span> Last Name</label>
                            <input type="text" name="lastname" placeholder="Dela Cruz" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><span class="required">*</span> Email Address</label>
                        <input type="email" name="email" placeholder="staff@example.com" required>
                    </div>
                    <div class="form-group">
                        <label><span class="required">*</span> Password (min 6 characters)</label>
                        <input type="password" name="password" placeholder="Create password" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" placeholder="+63 9XX XXX XXXX">
                    </div>
                    <button type="submit" name="create_staff" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-user-plus"></i> Create Staff Account
                    </button>
                </form>
            </div>

            <!-- Promote User to Staff -->
            <div class="card">
                <h3><i class="fas fa-arrow-up"></i> Promote User to Staff</h3>
                <p style="color:#666;margin-bottom:15px;">Promote an existing regular user to staff member</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Select User</label>
                        <select name="user_id" required>
                            <option value="">Choose a user...</option>
                            <?php while($user = mysqli_fetch_assoc($regular_users)): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" name="promote_user" class="btn btn-warning" style="width:100%;">
                        <i class="fas fa-arrow-up"></i> Promote to Staff
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Staff Members -->
        <div class="card">
            <h3>
                <i class="fas fa-users"></i> Current Staff Members 
                <span class="badge badge-staff"><?php echo mysqli_num_rows($staff_members); ?> Staff</span>
            </h3>
            <?php if(mysqli_num_rows($staff_members) > 0): ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Reports Managed</th>
                                <th>Pending</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($staff = mysqli_fetch_assoc($staff_members)): ?>
                            <tr>
                                <td>#<?php echo $staff['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($staff['firstname'] . ' ' . $staff['lastname']); ?></strong>
                                    <br><span class="badge badge-staff">Staff</span>
                                </td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td><?php echo $staff['phone'] ? htmlspecialchars($staff['phone']) : 'N/A'; ?></td>
                                <td><?php echo $staff['report_count']; ?></td>
                                <td><?php echo $staff['pending_count']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($staff['created_at'])); ?></td>
                                <td>
                                    <a href="?demote=<?php echo $staff['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Demote this staff member to regular user?')">
                                        <i class="fas fa-arrow-down"></i> Demote
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px;color:#999;">
                    <i class="fas fa-user-tie" style="font-size:4rem;display:block;margin-bottom:15px;"></i>
                    <p>No staff members yet. Create one above!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Access Level Comparison -->
        <div class="card">
            <h3><i class="fas fa-balance-scale"></i> Access Level Comparison</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th style="text-align:center;">👤 User</th>
                            <th style="text-align:center;">👔 Staff</th>
                            <th style="text-align:center;">👑 Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Report Incidents</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>View Own Reports</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>View Disaster Tips</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>Take Quiz</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>View ALL Reports</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>Update Report Status</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>Add Admin Notes</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>View All Users</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>Delete Reports</td><td style="text-align:center;">❌</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>Manage Staff</td><td style="text-align:center;">❌</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>Change User Roles</td><td style="text-align:center;">❌</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td></tr>
                        <tr><td>System Settings</td><td style="text-align:center;">❌</td><td style="text-align:center;">❌</td><td style="text-align:center;">✅</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>