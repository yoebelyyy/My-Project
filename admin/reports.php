<?php
require_once '../db_connect.php';

// Check access - Staff OR Admin can access
if(!isLoggedIn()) {
    redirect('../login.php');
}
if(!isStaffOrAdmin()) {
    $_SESSION['error'] = 'Access denied. Staff or Admin privileges required.';
    redirect('../dashboard.php');
}

$success = '';
$error = '';

// Handle status update (Staff & Admin)
if(isset($_POST['update_status'])) {
    $id = (int)$_POST['incident_id'];
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['admin_notes']);
    $resolved = ($status == 'resolved') ? "'" . date('Y-m-d H:i:s') . "'" : 'NULL';
    
    mysqli_query($conn, "UPDATE incidents SET status='$status', admin_notes='$notes', resolved_at=$resolved WHERE id=$id");
    logActivity($_SESSION['user_id'], 'update_report', "Updated report #$id to $status");
    $success = 'Report updated successfully!';
}

// Handle delete (Admin only)
if(isset($_GET['delete']) && isAdmin()) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM incidents WHERE id=$id");
    logActivity($_SESSION['user_id'], 'delete_report', "Deleted report #$id");
    $success = 'Report deleted successfully!';
}

// Get all reports
$filter = $_GET['status'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$query = "SELECT i.*, u.firstname, u.lastname, u.email, u.phone 
          FROM incidents i JOIN users u ON i.user_id=u.id WHERE 1=1";
if($filter) {
    $filter = sanitize($filter);
    $query .= " AND i.status='$filter'";
}
if($severity_filter) {
    $severity_filter = sanitize($severity_filter);
    $query .= " AND i.severity='$severity_filter'";
}
$query .= " ORDER BY 
    CASE i.severity 
        WHEN 'critical' THEN 1 
        WHEN 'high' THEN 2 
        WHEN 'medium' THEN 3 
        WHEN 'low' THEN 4 
    END, i.created_at DESC";
$reports = mysqli_query($conn, $query);

// Stats
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM incidents"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM incidents WHERE status='pending'"))['c'];
$verified = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM incidents WHERE status='verified'"))['c'];
$resolved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM incidents WHERE status='resolved'"))['c'];
$critical = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM incidents WHERE severity='critical' AND status!='resolved'"))['c'];

function getCategoryIcon($cat) {
    $icons = ['flooding'=>'🌊','landslide'=>'⛰️','deforestation'=>'🌳','pollution'=>'🏭','wildfire'=>'🔥','wildlife'=>'🦅','earthquake'=>'🌍','typhoon'=>'🌀'];
    return $icons[$cat] ?? '📋';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports - <?php echo isAdmin() ? 'Admin' : 'Staff'; ?> Panel | SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green-dark: #0A2E1F; --green-mid: #1B4D3E; --green-accent: #4CAF50; --danger: #C62828; --warning: #FF9800; --info: #2196F3; --staff: #9C27B0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #F1F8E9; }
        
        .top-bar {
            background: var(--green-dark);
            color: white;
            padding: 8px 30px;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .top-bar .clock {
            font-weight: 700;
            color: var(--green-lighter);
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
            padding: 12px 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            flex-wrap: wrap;
            gap: 10px;
        }
        .navbar a { color: white; text-decoration: none; margin: 0 8px; font-weight: 500; font-size: 0.9rem; }
        .navbar a:hover { color: #81C784; }
        .navbar .brand { font-size: 1.2rem; font-weight: 800; }
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .badge-admin { background: #FFD700; color: #333; }
        .badge-staff { background: #E1BEE7; color: #6A1B9A; }
        
        .container { max-width: 1400px; margin: 25px auto; padding: 0 20px; }
        
        .header {
            background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-mini {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .stat-mini .num { font-size: 1.8rem; font-weight: 800; }
        .stat-mini .lbl { font-size: 0.8rem; color: #666; }
        
        .filters { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn {
            padding: 8px 18px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            background: white;
            color: #1B4D3E;
            transition: 0.3s;
        }
        .filter-btn.active { background: #4CAF50; color: white; }
        .filter-btn:hover { transform: translateY(-2px); }
        
        .report-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            border-left: 5px solid #4CAF50;
        }
        .report-card.critical { border-left-color: #C62828; }
        .report-card.high { border-left-color: #FF5722; }
        
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pending { background: #FFF3E0; color: #E65100; }
        .badge-verified { background: #E3F2FD; color: #1565C0; }
        .badge-resolved { background: #E8F5E9; color: #2E7D32; }
        .badge-critical { background: #FFEBEE; color: #C62828; }
        
        .btn {
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: 0.3s;
            display: inline-block;
        }
        .btn-primary { background: #2196F3; color: white; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 15px;
            max-width: 450px;
            width: 90%;
        }
        .modal-content h3 { margin-bottom: 15px; color: #1B4D3E; }
        .modal-content select, .modal-content textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin-bottom: 12px;
            font-family: inherit;
        }
        
        @media (max-width: 768px) {
            .navbar { flex-direction: column; text-align: center; }
            .top-bar { font-size: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <span>📅 <strong><?php echo date('l, F j, Y'); ?></strong></span>
        <span class="clock" id="liveClock"><?php echo date('h:i:s A'); ?></span>
        <span>🕐 Asia/Manila (PHT)</span>
    </div>

    <nav class="navbar">
        <div>
            <a href="../dashboard.php" class="brand">🌿 SmartEcoTrack</a>
            <span class="role-badge <?php echo isAdmin() ? 'badge-admin' : 'badge-staff'; ?>">
                <i class="fas fa-<?php echo isAdmin() ? 'crown' : 'user-tie'; ?>"></i>
                <?php echo isAdmin() ? 'ADMIN' : 'STAFF'; ?> PANEL
            </span>
        </div>
        <div>
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="reports.php" style="color:#FFD700;"><i class="fas fa-clipboard"></i> Reports</a>
            <?php if(isAdmin()): ?>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <?php endif; ?>
            <a href="../logout.php" style="color:#ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-clipboard-check"></i> Incident Report Management</h1>
            <p>Logged in as: <strong><?php echo $_SESSION['firstname'] . ' ' . $_SESSION['lastname']; ?></strong> (<?php echo ucfirst($_SESSION['role']); ?>) | Date: <?php echo date('F j, Y'); ?></p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <div class="stats-mini">
            <div class="stat-mini">
                <div class="num"><?php echo $total; ?></div>
                <div class="lbl">Total Reports</div>
            </div>
            <div class="stat-mini">
                <div class="num" style="color:#E65100;"><?php echo $pending; ?></div>
                <div class="lbl">⏳ Pending</div>
            </div>
            <div class="stat-mini">
                <div class="num" style="color:#1565C0;"><?php echo $verified; ?></div>
                <div class="lbl">✅ Verified</div>
            </div>
            <div class="stat-mini">
                <div class="num" style="color:#2E7D32;"><?php echo $resolved; ?></div>
                <div class="lbl">🎉 Resolved</div>
            </div>
            <div class="stat-mini">
                <div class="num" style="color:#C62828;"><?php echo $critical; ?></div>
                <div class="lbl">🚨 Critical</div>
            </div>
        </div>

        <div class="filters">
            <a href="reports.php" class="filter-btn <?php echo !$filter ? 'active' : ''; ?>">📋 All</a>
            <a href="?status=pending" class="filter-btn <?php echo $filter=='pending' ? 'active' : ''; ?>">⏳ Pending</a>
            <a href="?status=verified" class="filter-btn <?php echo $filter=='verified' ? 'active' : ''; ?>">✅ Verified</a>
            <a href="?status=resolved" class="filter-btn <?php echo $filter=='resolved' ? 'active' : ''; ?>">🎉 Resolved</a>
            <a href="?severity=critical" class="filter-btn <?php echo $severity_filter=='critical' ? 'active' : ''; ?>">🚨 Critical</a>
        </div>

        <?php while($r = mysqli_fetch_assoc($reports)): ?>
        <div class="report-card <?php echo $r['severity']; ?>">
            <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;">
                <div>
                    <h3><?php echo getCategoryIcon($r['category']); ?> <?php echo htmlspecialchars($r['title']); ?></h3>
                    <p style="color:#666;font-size:0.85rem;">
                        <strong><?php echo htmlspecialchars($r['firstname'].' '.$r['lastname']); ?></strong>
                        | 📧 <?php echo $r['email']; ?>
                        <?php echo $r['phone'] ? '| 📞 '.$r['phone'] : ''; ?>
                        | 📅 <?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?>
                    </p>
                </div>
                <div>
                    <span class="badge badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
                    <span class="badge badge-<?php echo $r['severity']; ?>"><?php echo ucfirst($r['severity']); ?></span>
                </div>
            </div>
            
            <p style="margin:10px 0;"><?php echo nl2br(htmlspecialchars(substr($r['description'], 0, 200))); ?>...</p>
            
            <?php if($r['admin_notes']): ?>
                <div style="background:#E3F2FD;padding:10px;border-radius:8px;margin:10px 0;font-size:0.9rem;">
                    <strong>📝 Response:</strong> <?php echo htmlspecialchars($r['admin_notes']); ?>
                </div>
            <?php endif; ?>
            
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                <button class="btn btn-primary" onclick="openModal(<?php echo $r['id']; ?>, '<?php echo $r['status']; ?>', '<?php echo addslashes($r['admin_notes']); ?>')">
                    <i class="fas fa-edit"></i> Update
                </button>
                <?php if(isAdmin()): ?>
                    <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this report?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Update Modal -->
    <div class="modal" id="updateModal">
        <div class="modal-content">
            <h3>Update Report Status</h3>
            <form method="POST">
                <input type="hidden" name="incident_id" id="modal_id">
                <label>Status:</label>
                <select name="status" id="modal_status">
                    <option value="pending">⏳ Pending</option>
                    <option value="verified">✅ Verified</option>
                    <option value="resolved">🎉 Resolved</option>
                    <option value="closed">🔒 Closed</option>
                </select>
                <label>Response Notes:</label>
                <textarea name="admin_notes" id="modal_notes" rows="3" placeholder="Write response to citizen..."></textarea>
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="update_status" class="btn btn-success">Save</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-US', {timeZone:'Asia/Manila',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        function openModal(id, status, notes) {
            document.getElementById('modal_id').value = id;
            document.getElementById('modal_status').value = status;
            document.getElementById('modal_notes').value = notes || '';
            document.getElementById('updateModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('updateModal').classList.remove('active');
        }
        window.onclick = function(e) { if(e.target.classList.contains('modal')) closeModal(); }
    </script>
</body>
</html>