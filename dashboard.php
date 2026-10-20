<?php
/* ============================================================
   FILE: dashboard.php
   Purpose: Main dashboard — staff & admin can manage reports
            + live clock
            + admin-only user management
            + 4x2 stats grid layout
            + EcoBot AI assistant (CITIZENS ONLY)
   ============================================================ */
require_once 'db_connect.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = (int)$_SESSION['user_id'];

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
if (!$user) { session_destroy(); redirect('login.php'); }

mysqli_query($conn, "UPDATE users SET last_active = NOW() WHERE id = $user_id");

$role         = strtolower($user['role'] ?? 'user');
$is_admin     = ($role === 'admin');
$is_staff     = ($role === 'staff');
$is_regular   = !$is_admin && !$is_staff;
$can_manage   = $is_admin || $is_staff;
$can_track    = $is_admin || $is_staff;
$can_report   = $is_regular;
$can_chatbot  = $is_regular;   // ✅ EcoBot: citizens only

/* STATS */
$my_reports  = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE user_id = $user_id"))['c'];
$my_pending  = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE user_id = $user_id AND status = 'pending'"))['c'];
$my_resolved = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE user_id = $user_id AND status = 'resolved'"))['c'];

$total_reports    = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents"))['c'];
$total_users      = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM users"))['c'];
$pending_all      = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE status = 'pending'"))['c'];
$critical_issues  = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE severity = 'critical' AND status != 'resolved'"))['c'];
$resolved_all     = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE status = 'resolved'"))['c'];
$verified_all     = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM incidents WHERE status = 'verified'"))['c'];

/* ADMIN stats */
$total_admins = 0;
$total_staff  = 0;
$total_regular = 0;
if ($is_admin) {
    $total_admins  = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM users WHERE role = 'admin'"))['c'];
    $total_staff   = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM users WHERE role = 'staff'"))['c'];
    $total_regular = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM users WHERE role NOT IN ('admin','staff')"))['c'];
}

/* Staff online */
$online_staff_result = mysqli_query($conn,
    "SELECT id, firstname, lastname, email, role, last_active
     FROM users
     WHERE is_online = 1
       AND role IN ('staff','admin')
       AND last_active >= NOW() - INTERVAL 5 MINUTE
     ORDER BY last_active DESC");
$online_staff = [];
if ($online_staff_result) {
    while ($s = mysqli_fetch_assoc($online_staff_result)) $online_staff[] = $s;
}
$online_staff_count = count($online_staff);

/* Emergencies */
$emergencies = [];
if ($can_track) {
    $em_result = mysqli_query($conn,
        "SELECT i.id, i.title, i.category, i.severity, i.status,
                i.description, i.location_name,
                i.latitude, i.longitude, i.location_accuracy,
                i.created_at,
                u.id AS user_id, u.firstname, u.lastname, u.email,
                u.phone, u.address
         FROM incidents i
         JOIN users u ON i.user_id = u.id
         WHERE i.latitude IS NOT NULL
           AND i.longitude IS NOT NULL
           AND i.status NOT IN ('resolved','closed')
           AND i.severity IN ('critical','high')
         ORDER BY FIELD(i.severity,'critical','high') ASC, i.created_at DESC
         LIMIT 50");
    if ($em_result) {
        while ($row = mysqli_fetch_assoc($em_result)) $emergencies[] = $row;
    }
}
$emergency_count = count($emergencies);

/* Role-based display */
if ($can_manage) {
    $display_reports  = $total_reports;
    $display_pending  = $pending_all;
    $display_resolved = $resolved_all;
} else {
    $display_reports  = $my_reports;
    $display_pending  = $my_pending;
    $display_resolved = $my_resolved;
}
$stat_total_incidents = $can_manage ? $total_reports : $my_reports;
$stat_total_users     = $total_users;

/* RECENT DATA */
if ($can_manage) {
    $recent_incidents = mysqli_query($conn,
        "SELECT i.*, u.firstname, u.lastname
         FROM incidents i JOIN users u ON i.user_id = u.id
         ORDER BY i.created_at DESC LIMIT 5");
} else {
    $recent_incidents = mysqli_query($conn,
        "SELECT i.*, u.firstname, u.lastname
         FROM incidents i JOIN users u ON i.user_id = u.id
         WHERE i.user_id = $user_id
         ORDER BY i.created_at DESC LIMIT 5");
}

$safety_tips = mysqli_query($conn, "SELECT * FROM disaster_tips ORDER BY RAND() LIMIT 3");

$recent_activities = mysqli_query($conn,
    "SELECT 'report' AS type, i.title AS action, i.created_at, u.firstname, u.lastname, i.id
     FROM incidents i JOIN users u ON i.user_id = u.id
     UNION ALL
     SELECT 'quiz' AS type, CONCAT('Scored ', qr.score, ' points') AS action, qr.completed_at AS created_at,
            u.firstname, u.lastname, NULL AS id
     FROM quiz_results qr JOIN users u ON qr.user_id = u.id
     ORDER BY created_at DESC LIMIT 8");

/* ============================================================
   BOT CONTEXT — only built for citizens
   ============================================================ */
$botContext = $can_chatbot ? [
    'firstname'       => $user['firstname'],
    'role'            => $role,
    'quiz_score'      => (int)$user['quiz_score'],
    'isAdmin'         => $is_admin,
    'isStaff'         => $is_staff,
    'isRegular'       => $is_regular,
    'myReports'       => $my_reports,
    'myPending'       => $my_pending,
    'myResolved'      => $my_resolved,
    'totalReports'    => $total_reports,
    'pendingAll'      => $pending_all,
    'criticalActive'  => $critical_issues,
    'totalUsers'      => $total_users,
    'emergencyCount'  => $emergency_count,
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --navy-900: #0A1931;
            --navy-800: #112A4A;
            --navy-700: #1A3A63;
            --navy-600: #234B7C;
            --navy-500: #2E5F96;
            --navy-300: #7BA4CE;
            --navy-100: #E1EAF4;
            --navy-50:  #F2F6FB;

            --forest-900: #1B4332;
            --forest-800: #2D6A4F;
            --forest-700: #40916C;
            --forest-600: #52B788;
            --forest-400: #95D5B2;
            --forest-100: #EDF7F0;

            --crimson:    #C62828;
            --crimson-bg: #FDECEA;
            --amber:      #E65100;
            --amber-bg:   #FFF3E0;
            --yellow:     #F9A825;
            --yellow-bg:  #FFFDE7;
            --status-green: #2E7D32;
            --status-bg:    #E8F5E9;
            --info-blue:  #1565C0;
            --info-bg:    #E3F2FD;

            --white:    #FFFFFF;
            --gray-50:  #FAFBFC;
            --gray-100: #F4F6F8;
            --gray-200: #E4E8EC;
            --gray-300: #CFD6DC;
            --gray-400: #9AA5AF;
            --gray-500: #6B7680;
            --gray-600: #4A535C;
            --gray-800: #1C2229;

            --shadow-sm: 0 1px 3px rgba(10,25,49,0.06);
            --shadow-md: 0 4px 12px rgba(10,25,49,0.08);
            --shadow-lg: 0 12px 32px rgba(10,25,49,0.10);

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-pill: 999px;

            --gradient-header: linear-gradient(135deg, var(--navy-800), var(--navy-600));
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
            padding-top: 78px;
            -webkit-font-smoothing: antialiased;
        }

        /* NAVBAR */
        .navbar {
            position: fixed; top: 0; left: 0; width: 100%;
            padding: 0.55rem 2.5vw;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 999;
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
        }
        .logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; color: var(--navy-800);
            font-size: 1.3rem; font-weight: 700;
            letter-spacing: -0.02em; flex-shrink: 0;
        }
        .logo-circle {
            width: 42px; height: 42px; border-radius: 50%;
            overflow: hidden; display: flex;
            align-items: center; justify-content: center;
            background: var(--navy-50);
            box-shadow: 0 0 0 2px var(--navy-500), 0 3px 8px rgba(10,25,49,0.15);
            flex-shrink: 0;
        }
        .logo-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logo-text em { font-style: normal; font-weight: 400; color: var(--gray-500); }

        /* LIVE CLOCK */
        .nav-clock {
            display: flex; flex-direction: column; align-items: flex-end;
            padding: 0.25rem 0.7rem;
            background: var(--navy-50);
            border: 1px solid var(--navy-100);
            border-radius: var(--radius-sm);
            font-variant-numeric: tabular-nums;
            margin-right: 0.25rem;
            line-height: 1.15;
            flex-shrink: 0;
        }
        .nav-clock .clock-time {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--navy-800);
            letter-spacing: -0.01em;
        }
        .nav-clock .clock-date {
            font-size: 0.6rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .nav-links { display: flex; gap: 0.25rem; align-items: center; flex-wrap: wrap; }
        .nav-links a {
            color: var(--gray-600); text-decoration: none;
            padding: 0.4rem 0.75rem; border-radius: var(--radius-sm);
            font-weight: 500; font-size: 0.83rem;
            transition: all 0.15s ease;
            display: inline-flex; align-items: center; gap: 5px;
            white-space: nowrap;
        }
        .nav-links a:hover { background: var(--navy-50); color: var(--navy-800); }
        .nav-links a.active { background: var(--navy-700); color: var(--white); }
        .nav-links a.btn-report { background: var(--forest-700); color: var(--white); font-weight: 600; }
        .nav-links a.btn-report:hover { background: var(--forest-800); }
        .nav-links a.btn-manage { background: var(--navy-700); color: var(--white); font-weight: 600; }
        .nav-links a.btn-manage:hover { background: var(--navy-800); }
        .nav-links a.btn-admin { background: linear-gradient(135deg, #FFD700, #FFA000); color: #333; font-weight: 700; }
        .nav-links a.btn-admin:hover { background: linear-gradient(135deg, #FFC700, #E68A00); }
        .nav-links a.logout { color: var(--crimson); }
        .nav-links a.logout:hover { background: var(--crimson-bg); }

        .mobile-toggle {
            display: none; background: none; border: none;
            color: var(--navy-800); font-size: 1.3rem;
            cursor: pointer; padding: 0.3rem;
        }

        .container {
            max-width: 1400px; margin: 0 auto;
            padding: 1rem 2.5vw 1.5rem;
        }

        /* WELCOME */
        .welcome-card {
            background: var(--gradient-header); color: var(--white);
            padding: 1.5rem 1.75rem; border-radius: var(--radius-lg);
            margin-bottom: 1rem; position: relative; overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .welcome-card::before {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(circle at 15% 25%, rgba(82,183,136,0.22), transparent 55%),
                radial-gradient(circle at 85% 75%, rgba(82,183,136,0.12), transparent 50%);
            pointer-events: none;
        }
        .welcome-card::after {
            content: '🌿'; position: absolute;
            right: 20px; bottom: -30px; font-size: 160px;
            opacity: 0.08; pointer-events: none;
        }
        .welcome-content { position: relative; z-index: 1; }
        .welcome-card h1 {
            font-size: clamp(1.3rem, 2.5vw, 1.8rem);
            font-weight: 700; letter-spacing: -0.02em;
            margin-bottom: 0.3rem;
            text-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .welcome-card p.subtitle {
            opacity: 0.9; font-size: 0.9rem; margin-bottom: 0.85rem;
        }

        .user-info {
            display: flex; align-items: center; gap: 0.75rem;
            flex-wrap: wrap; margin-bottom: 1rem;
        }
        .role-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: var(--radius-pill);
            font-weight: 700; font-size: 0.68rem;
            text-transform: uppercase; letter-spacing: 0.06em;
        }
        .role-admin { background: linear-gradient(135deg, #FFD700, #FFA000); color: #333; }
        .role-user  { background: rgba(82,183,136,0.9); color: #1B4332; }
        .role-staff { background: rgba(21,101,192,0.9); color: #fff; }

        .user-info span.info {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.85rem; opacity: 0.9;
        }

        .welcome-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 0.5rem; max-width: 550px;
        }
        .welcome-stat {
            background: rgba(255,255,255,0.12);
            padding: 0.6rem 0.85rem; border-radius: var(--radius-md);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(6px);
        }
        .welcome-stat .stat-value {
            font-size: 1.35rem; font-weight: 800;
            letter-spacing: -0.02em; line-height: 1.1;
        }
        .welcome-stat .stat-label {
            font-size: 0.72rem; opacity: 0.85; letter-spacing: 0.02em;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.5rem;
            margin: 0 0 1rem 0;
            padding: 0;
            align-items: stretch;
            grid-auto-rows: 1fr;
        }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--forest-600);
            transition: box-shadow 0.15s ease;
            display: grid;
            grid-template-columns: auto 1fr;
            grid-template-rows: auto auto;
            grid-template-areas:
                "icon value"
                "icon label";
            align-items: center;
            column-gap: 0.55rem;
            row-gap: 0;
            padding: 0.6rem 0.85rem;
            margin: 0;
            height: 100%;
            min-width: 0;
            overflow: hidden;
            line-height: 1;
        }
        .stat-card:hover { box-shadow: var(--shadow-md); }
        .stat-card.critical { border-left-color: var(--crimson); }
        .stat-card.pending  { border-left-color: var(--amber); }
        .stat-card.resolved { border-left-color: var(--info-blue); }
        .stat-card.users    { border-left-color: var(--navy-600); }
        .stat-card.staff-status-card { border-left-color: var(--status-green); }
        .stat-card.admins   { border-left-color: #B8860B; }
        .stat-card.staff    { border-left-color: var(--info-blue); }

        .stat-card .stat-icon {
            grid-area: icon;
            font-size: 1.5rem;
            color: var(--forest-700);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
            line-height: 1;
            width: 1.5rem;
        }
        .stat-card.critical .stat-icon { color: var(--crimson); }
        .stat-card.pending  .stat-icon { color: var(--amber); }
        .stat-card.resolved .stat-icon { color: var(--info-blue); }
        .stat-card.users    .stat-icon { color: var(--navy-600); }
        .stat-card.staff-status-card .stat-icon { color: var(--status-green); }
        .stat-card.admins   .stat-icon { color: #B8860B; }
        .stat-card.staff    .stat-icon { color: var(--info-blue); }

        .stat-card h3 {
            grid-area: value;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--navy-900);
            letter-spacing: -0.03em;
            line-height: 1;
            margin: 0;
            padding: 0;
            align-self: end;
        }
        .stat-card.critical h3 { color: var(--crimson); }
        .stat-card.pending  h3 { color: var(--amber); }
        .stat-card.resolved h3 { color: var(--info-blue); }
        .stat-card.staff-status-card h3 { color: var(--status-green); }
        .stat-card.admins   h3 { color: #B8860B; }
        .stat-card.staff    h3 { color: var(--info-blue); }

        .stat-card p {
            grid-area: label;
            color: var(--gray-500);
            font-weight: 600;
            font-size: 0.7rem;
            line-height: 1;
            margin: 0;
            padding: 0;
            align-self: start;
        }

        .live-dot {
            display: inline-block; width: 8px; height: 8px;
            background: #4CAF50; border-radius: 50%;
            position: relative; vertical-align: middle; flex-shrink: 0;
        }
        .live-dot::before {
            content: ''; position: absolute; inset: -3px;
            border-radius: 50%; background: #4CAF50;
            opacity: 0.6; animation: livePulse 1.8s ease-out infinite;
        }
        @keyframes livePulse {
            0%   { transform: scale(1);   opacity: 0.6; }
            100% { transform: scale(2.2); opacity: 0; }
        }

        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.6rem; margin-bottom: 1rem;
        }
        .action-btn {
            background: var(--white); padding: 0.9rem 0.75rem;
            border-radius: var(--radius-md); text-align: center;
            text-decoration: none; color: var(--navy-800);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.2s ease;
            font-weight: 600; font-size: 0.85rem;
            display: flex; flex-direction: column;
            align-items: center; gap: 0.4rem;
        }
        .action-btn:hover {
            transform: translateY(-3px); box-shadow: var(--shadow-md);
            border-color: var(--forest-400);
        }
        .action-btn i {
            font-size: 1.35rem; color: var(--forest-700);
            transition: transform 0.2s;
        }
        .action-btn:hover i { transform: scale(1.1); }
        .action-btn.primary {
            background: var(--forest-700); color: var(--white);
            border-color: var(--forest-700);
        }
        .action-btn.primary i { color: var(--white); }
        .action-btn.primary:hover { background: var(--forest-800); }
        .action-btn.manage {
            background: var(--navy-700); color: var(--white);
            border-color: var(--navy-700);
        }
        .action-btn.manage i { color: var(--white); }
        .action-btn.manage:hover { background: var(--navy-800); }
        .action-btn.admin {
            background: linear-gradient(135deg, #FFD700, #FFA000);
            color: #333; border-color: #FFA000;
        }
        .action-btn.admin i { color: #333; }
        .action-btn.admin:hover {
            background: linear-gradient(135deg, #FFC700, #E68A00);
            color: #222;
        }

        /* CARDS */
        .card {
            background: var(--white); border-radius: var(--radius-md);
            padding: 1.1rem 1.15rem; margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .card:last-child { margin-bottom: 0; }
        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 0.7rem;
            flex-wrap: wrap; gap: 0.5rem;
        }
        .card-header h3 {
            color: var(--navy-900); font-size: 0.98rem;
            font-weight: 700; display: flex;
            align-items: center; gap: 8px;
        }
        .card-header h3 i { color: var(--forest-700); }
        .card-header .view-all {
            color: var(--forest-700); text-decoration: none;
            font-weight: 600; font-size: 0.8rem;
            transition: color 0.15s;
        }
        .card-header .view-all:hover { color: var(--forest-800); }

        .incident-item {
            padding: 0.6rem 0.85rem; margin: 0.4rem 0;
            background: var(--gray-50); border-radius: var(--radius-sm);
            border-left: 3px solid var(--forest-600);
            transition: all 0.15s ease;
        }
        .incident-item:hover {
            background: var(--navy-50); transform: translateX(3px);
        }
        .incident-header {
            display: flex; justify-content: space-between;
            align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;
        }
        .incident-title {
            font-weight: 600; color: var(--navy-900);
            font-size: 0.88rem;
        }
        .incident-meta {
            color: var(--gray-500); font-size: 0.75rem; margin-top: 1px;
        }
        .badge-row { display: flex; gap: 5px; flex-wrap: wrap; }

        .badge {
            padding: 2px 9px; border-radius: var(--radius-pill);
            font-size: 0.65rem; font-weight: 700;
            text-transform: capitalize; letter-spacing: 0.02em;
            display: inline-block;
        }
        .badge-pending   { background: var(--amber-bg);   color: var(--amber); }
        .badge-verified  { background: var(--info-bg);    color: var(--info-blue); }
        .badge-resolved  { background: var(--status-bg);  color: var(--status-green); }
        .badge-closed    { background: var(--gray-200);   color: var(--gray-600); }
        .badge-critical  { background: var(--crimson-bg); color: var(--crimson); }
        .badge-high      { background: var(--amber-bg);   color: var(--amber); }
        .badge-medium    { background: var(--yellow-bg);  color: #B8860B; }
        .badge-low       { background: var(--status-bg);  color: var(--status-green); }

        .tip-card {
            background: var(--forest-100);
            padding: 0.75rem 0.9rem; border-radius: var(--radius-sm);
            border-top: 3px solid var(--forest-600);
            transition: all 0.2s; margin-bottom: 0.5rem;
        }
        .tip-card:last-child { margin-bottom: 0; }
        .tip-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .tip-card .tip-category {
            color: var(--forest-800); font-weight: 700;
            text-transform: uppercase; font-size: 0.65rem;
            letter-spacing: 0.06em; margin-bottom: 0.2rem;
        }
        .tip-card h4 {
            color: var(--navy-900); font-size: 0.88rem;
            margin-bottom: 0.2rem;
        }
        .tip-card p {
            color: var(--gray-600); font-size: 0.78rem; line-height: 1.5;
        }

        .activity-item {
            display: flex; align-items: flex-start; gap: 0.7rem;
            padding: 0.55rem 0; border-bottom: 1px solid var(--gray-200);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center;
            justify-content: center; font-size: 0.85rem;
            flex-shrink: 0;
        }
        .activity-report { background: var(--info-bg);  color: var(--info-blue); }
        .activity-quiz   { background: #F3E5F5;         color: #7B1FA2; }
        .activity-content { flex: 1; font-size: 0.83rem; }
        .activity-content strong { color: var(--navy-900); }
        .activity-time {
            font-size: 0.7rem; color: var(--gray-500); margin-top: 1px;
        }

        .staff-list { display: flex; flex-direction: column; gap: 0.4rem; }
        .staff-item {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.5rem 0.7rem; background: var(--gray-50);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--status-green);
            transition: all 0.15s ease;
        }
        .staff-item:hover { background: var(--forest-100); transform: translateX(2px); }
        .staff-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--gradient-header); color: var(--white);
            display: flex; align-items: center;
            justify-content: center; font-weight: 700;
            font-size: 0.75rem; letter-spacing: 0.02em;
            flex-shrink: 0; position: relative;
            box-shadow: 0 2px 5px rgba(10,25,49,0.15);
        }
        .staff-avatar::after {
            content: ''; position: absolute;
            bottom: 0; right: 0; width: 10px; height: 10px;
            background: #4CAF50; border: 2px solid var(--white);
            border-radius: 50%;
        }
        .staff-info { flex: 1; min-width: 0; }
        .staff-name {
            font-weight: 600; color: var(--navy-900);
            font-size: 0.85rem; display: flex;
            align-items: center; gap: 5px; flex-wrap: wrap;
        }
        .staff-role-chip {
            font-size: 0.6rem; text-transform: uppercase;
            letter-spacing: 0.05em; font-weight: 700;
            padding: 1px 7px; border-radius: var(--radius-pill);
        }
        .staff-role-chip.role-staff { background: var(--info-bg);   color: var(--info-blue); }
        .staff-role-chip.role-admin { background: #FFF8E1;          color: #B8860B; }
        .staff-meta {
            color: var(--gray-500); font-size: 0.72rem;
            margin-top: 1px; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
        }
        .staff-last-seen {
            font-size: 0.68rem; color: var(--gray-400);
            font-weight: 600; white-space: nowrap; flex-shrink: 0;
        }

        .tracker-header {
            background: linear-gradient(95deg, #A51E1E, #C62828);
            color: var(--white); border-radius: var(--radius-md);
            padding: 1rem 1.25rem; margin-bottom: 1rem;
            position: relative; overflow: hidden;
            display: flex; align-items: center;
            justify-content: space-between; gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 8px 24px rgba(198,40,40,0.25);
        }
        .tracker-header::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 70% 50%, rgba(255,255,255,0.15), transparent 60%);
            pointer-events: none;
        }
        .tracker-header .tracker-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1.05rem; font-weight: 800;
            letter-spacing: -0.01em;
            position: relative; z-index: 1;
        }
        .tracker-header .tracker-title i {
            font-size: 1.3rem;
            animation: pulseIcon 1.6s ease-in-out infinite;
        }
        @keyframes pulseIcon {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.15); }
        }
        .tracker-header .tracker-count {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            padding: 6px 14px; border-radius: var(--radius-pill);
            font-weight: 800; font-size: 0.9rem;
            backdrop-filter: blur(6px);
            position: relative; z-index: 1;
        }

        .tracker-layout {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 1rem; margin-bottom: 1rem;
        }
        @media (max-width: 1024px) {
            .tracker-layout { grid-template-columns: 1fr; }
        }

        #emergencyMap {
            height: 420px; border-radius: var(--radius-md);
            border: 2px solid var(--crimson);
            box-shadow: var(--shadow-md);
            background: #eef2f6; z-index: 1;
        }

        .emergency-list {
            display: flex; flex-direction: column; gap: 0.55rem;
            max-height: 420px; overflow-y: auto;
            padding-right: 4px;
        }
        .emergency-list::-webkit-scrollbar { width: 6px; }
        .emergency-list::-webkit-scrollbar-thumb {
            background: var(--gray-300); border-radius: var(--radius-pill);
        }

        .emergency-card {
            background: var(--white); border: 1px solid var(--gray-200);
            border-left: 4px solid var(--crimson);
            border-radius: var(--radius-sm);
            padding: 0.75rem 0.85rem;
            transition: all 0.15s ease; cursor: pointer;
        }
        .emergency-card:hover {
            transform: translateX(2px); box-shadow: var(--shadow-sm);
        }
        .emergency-card.sev-critical { border-left-color: #B71C1C; }
        .emergency-card.sev-high     { border-left-color: var(--amber); }

        .emergency-card .em-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 0.4rem; margin-bottom: 0.35rem;
        }
        .emergency-card .em-title {
            font-weight: 700; color: var(--navy-900);
            font-size: 0.88rem; line-height: 1.3;
        }
        .emergency-card .em-sev {
            font-size: 0.6rem; font-weight: 800;
            padding: 2px 7px; border-radius: var(--radius-pill);
            text-transform: uppercase; letter-spacing: 0.05em;
            flex-shrink: 0;
        }
        .em-sev.critical { background: var(--crimson-bg); color: var(--crimson); }
        .em-sev.high     { background: var(--amber-bg);   color: var(--amber); }

        .emergency-card .em-citizen {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.4rem 0.55rem; background: var(--gray-50);
            border-radius: var(--radius-sm); margin: 0.4rem 0;
        }
        .emergency-card .em-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--gradient-header); color: var(--white);
            display: flex; align-items: center;
            justify-content: center; font-weight: 700;
            font-size: 0.68rem; flex-shrink: 0;
        }
        .emergency-card .em-cname {
            font-weight: 600; color: var(--navy-900); font-size: 0.8rem;
        }
        .emergency-card .em-cmeta {
            color: var(--gray-500); font-size: 0.68rem;
        }

        .emergency-card .em-actions {
            display: flex; gap: 0.3rem; flex-wrap: wrap; margin-top: 0.35rem;
        }
        .em-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 0.32rem 0.7rem; border-radius: var(--radius-sm);
            font-size: 0.72rem; font-weight: 700;
            text-decoration: none; cursor: pointer;
            border: none; font-family: inherit;
            transition: all 0.15s ease;
        }
        .em-btn.call { background: var(--crimson); color: var(--white); }
        .em-btn.call:hover { background: #A51E1E; transform: translateY(-1px); }
        .em-btn.view { background: var(--navy-700); color: var(--white); }
        .em-btn.view:hover { background: var(--navy-800); transform: translateY(-1px); }
        .em-btn.locate { background: var(--forest-700); color: var(--white); }
        .em-btn.locate:hover { background: var(--forest-800); transform: translateY(-1px); }
        .em-btn.disabled {
            background: var(--gray-200); color: var(--gray-500);
            cursor: not-allowed;
        }

        .em-map-loc {
            font-size: 0.72rem; color: var(--gray-600);
            display: flex; align-items: flex-start;
            gap: 5px; margin: 0.25rem 0;
        }
        .em-map-loc i { color: var(--crimson); margin-top: 3px; }

        .em-ago {
            font-size: 0.66rem; color: var(--gray-400);
            font-weight: 600; white-space: nowrap;
        }

        .tracker-empty {
            text-align: center; padding: 1.75rem 1rem;
            background: var(--white);
            border: 2px dashed var(--status-green);
            border-radius: var(--radius-md);
            color: var(--status-green);
        }
        .tracker-empty i {
            font-size: 2rem; display: block; margin-bottom: 0.4rem;
        }
        .tracker-empty p { font-weight: 600; font-size: 0.88rem; }
        .tracker-empty small {
            color: var(--gray-500); font-size: 0.75rem;
            display: block; margin-top: 0.25rem;
        }

        .emergency-marker { position: relative; }
        .emergency-marker::before {
            content: ''; position: absolute; inset: -8px;
            border-radius: 50%; background: rgba(198,40,40,0.35);
            animation: emergencyPulse 1.6s ease-out infinite;
        }
        @keyframes emergencyPulse {
            0%   { transform: scale(0.6); opacity: 0.9; }
            100% { transform: scale(2.4); opacity: 0; }
        }

        .empty-state {
            text-align: center; padding: 1.25rem 1rem;
            color: var(--gray-500);
        }
        .empty-state i {
            font-size: 1.8rem; color: var(--gray-300);
            margin-bottom: 0.3rem; display: block;
        }
        .empty-state p { font-size: 0.83rem; }

        .two-column {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 0.85rem; margin-bottom: 1rem;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.75rem;
        }
        .info-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 1.1rem 1rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
        }
        .info-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .info-card .big-icon { font-size: 1.9rem; margin-bottom: 0.5rem; }
        .info-card h3 { font-size: 0.92rem; font-weight: 700; margin-bottom: 0.4rem; }
        .info-card p { font-size: 0.82rem; color: var(--gray-600); line-height: 1.55; }

        footer {
            background: var(--navy-900); color: rgba(255,255,255,0.75);
            text-align: center; padding: 1.1rem 5vw;
            border-top: 3px solid var(--forest-700); margin-top: 1rem;
        }
        footer p { font-size: 0.82rem; }
        footer p.sub { opacity: 0.6; font-size: 0.72rem; margin-top: 0.2rem; }

        /* ============================================================
           ECOBOT — AI CHATBOT (citizens only — CSS still loaded
           but launcher/panel won't be in the DOM for staff/admin)
           ============================================================ */
        .ecobot-launcher {
            position: fixed;
            bottom: 24px; right: 24px;
            width: 60px; height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--forest-700), var(--forest-600));
            color: var(--white);
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 24px rgba(64,145,108,0.45);
            z-index: 1000;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: ecobotFloat 3s ease-in-out infinite;
        }
        .ecobot-launcher:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 30px rgba(64,145,108,0.6);
        }
        .ecobot-launcher:active { transform: scale(0.96); }
        .ecobot-launcher:focus-visible {
            outline: 3px solid var(--forest-400);
            outline-offset: 3px;
        }
        @keyframes ecobotFloat {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-4px); }
        }

        .ecobot-badge {
            position: absolute;
            top: -3px; right: -3px;
            background: var(--crimson);
            color: var(--white);
            font-size: 0.65rem; font-weight: 800;
            min-width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--white);
        }

        .ecobot-panel {
            position: fixed;
            bottom: 96px; right: 24px;
            width: 380px;
            max-width: calc(100vw - 32px);
            height: 540px;
            max-height: calc(100vh - 130px);
            background: var(--white);
            border-radius: 20px;
            box-shadow:
                0 24px 60px rgba(10,25,49,0.28),
                0 0 0 1px rgba(10,25,49,0.06);
            display: flex; flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            opacity: 0;
            transform: translateY(20px) scale(0.96);
            pointer-events: none;
            transition: opacity 0.22s ease, transform 0.22s ease;
        }
        .ecobot-panel.open {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .ecobot-header {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px;
            background: var(--gradient-header);
            color: var(--white);
            flex-shrink: 0;
        }
        .ecobot-avatar {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--forest-700), var(--forest-600));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.25);
            flex-shrink: 0;
        }
        .ecobot-title-block { flex: 1; min-width: 0; }
        .ecobot-title { font-weight: 800; font-size: 1rem; letter-spacing: -0.01em; }
        .ecobot-subtitle {
            font-size: 0.72rem; opacity: 0.85;
            display: flex; align-items: center; gap: 6px;
            margin-top: 1px;
        }
        .ecobot-status-dot {
            display: inline-block;
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76,175,80,0.35);
        }
        .ecobot-close {
            background: rgba(255,255,255,0.12);
            border: none; color: var(--white);
            width: 32px; height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
            flex-shrink: 0;
        }
        .ecobot-close:hover { background: rgba(255,255,255,0.24); }

        .ecobot-messages {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            display: flex; flex-direction: column; gap: 10px;
            background: var(--gray-50);
            scroll-behavior: smooth;
        }
        .ecobot-messages::-webkit-scrollbar { width: 6px; }
        .ecobot-messages::-webkit-scrollbar-thumb {
            background: var(--gray-300); border-radius: 999px;
        }

        .ecobot-msg {
            display: flex;
            animation: ecobotFadeIn 0.25s ease;
        }
        @keyframes ecobotFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .ecobot-msg.ecobot-user { justify-content: flex-end; }
        .ecobot-bubble {
            max-width: 82%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 0.88rem;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .ecobot-bot .ecobot-bubble {
            background: var(--white);
            color: var(--gray-800);
            border: 1px solid var(--gray-200);
            border-bottom-left-radius: 6px;
            box-shadow: 0 1px 2px rgba(10,25,49,0.04);
        }
        .ecobot-user .ecobot-bubble {
            background: var(--forest-700);
            color: var(--white);
            border-bottom-right-radius: 6px;
        }
        .ecobot-bubble strong { font-weight: 700; }
        .ecobot-bubble em { font-style: italic; color: var(--navy-600); }
        .ecobot-user .ecobot-bubble em { color: var(--forest-400); }

        .ecobot-typing .ecobot-bubble {
            display: inline-flex; gap: 4px; align-items: center;
            padding: 12px 16px;
        }
        .ecobot-typing .dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--gray-400);
            animation: ecobotBounce 1.2s infinite ease-in-out;
        }
        .ecobot-typing .dot:nth-child(2) { animation-delay: 0.15s; }
        .ecobot-typing .dot:nth-child(3) { animation-delay: 0.3s; }
        @keyframes ecobotBounce {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30%           { transform: translateY(-5px); opacity: 1; }
        }

        .ecobot-suggestions {
            display: flex; flex-wrap: wrap; gap: 6px;
            margin-top: 4px;
        }
        .ecobot-suggestions button {
            background: var(--white);
            border: 1px solid var(--forest-400);
            color: var(--forest-800);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s;
        }
        .ecobot-suggestions button:hover {
            background: var(--forest-100);
            transform: translateY(-1px);
        }

        .ecobot-input-row {
            display: flex; gap: 8px;
            padding: 12px;
            background: var(--white);
            border-top: 1px solid var(--gray-200);
            flex-shrink: 0;
        }
        .ecobot-input-row input {
            flex: 1;
            padding: 10px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 999px;
            font-size: 0.88rem;
            font-family: inherit;
            background: var(--gray-50);
            transition: border-color 0.15s, background 0.15s;
            min-width: 0;
        }
        .ecobot-input-row input:focus {
            outline: none;
            border-color: var(--forest-600);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(64,145,108,0.12);
        }
        .ecobot-input-row button {
            width: 42px; height: 42px;
            border-radius: 50%;
            border: none;
            background: var(--forest-700);
            color: var(--white);
            cursor: pointer;
            font-size: 0.95rem;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s, transform 0.1s;
            flex-shrink: 0;
        }
        .ecobot-input-row button:hover { background: var(--forest-800); }
        .ecobot-input-row button:active { transform: scale(0.94); }
        .ecobot-input-row button:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }

        @media (max-width: 480px) {
            .ecobot-panel {
                right: 12px; bottom: 84px;
                width: calc(100vw - 24px);
                height: calc(100vh - 110px);
                max-height: none;
            }
            .ecobot-launcher {
                right: 16px; bottom: 16px;
                width: 54px; height: 54px;
                font-size: 1.3rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .ecobot-launcher { animation: none; }
            .ecobot-panel { transition: none; }
            .ecobot-msg { animation: none; }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .two-column { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .navbar { padding: 0.5rem 1rem; flex-wrap: wrap; gap: 0.4rem; }
            .mobile-toggle { display: block; }
            .nav-clock { font-size: 0.72rem; padding: 0.25rem 0.5rem; }
            .nav-clock .clock-time { font-size: 0.78rem; }
            .nav-clock .clock-date { font-size: 0.55rem; }
            .nav-links {
                display: none; width: 100%;
                flex-direction: column; align-items: stretch;
                padding: 0.5rem 0 0.25rem;
                border-top: 1px solid var(--gray-200);
                margin-top: 0.4rem;
            }
            .nav-links.active { display: flex; }
            .nav-links a { padding: 0.6rem 0.85rem; }
            .logo { font-size: 1.05rem; gap: 8px; }
            .logo-circle { width: 38px; height: 38px; }
            .logo-text em { display: none; }
            .container { padding: 0.85rem 1rem 1.5rem; }
            .welcome-card { padding: 1.1rem 1.25rem; }
            .welcome-card h1 { font-size: 1.25rem; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; }
            .stat-card { padding: 0.55rem 0.7rem; }
            .stat-card h3 { font-size: 1.2rem; }
            .stat-card .stat-icon { font-size: 1.25rem; width: 1.25rem; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            .action-btn { padding: 0.75rem 0.6rem; font-size: 0.8rem; }
            .action-btn i { font-size: 1.15rem; }
            .card { padding: 0.9rem 0.95rem; }
            #emergencyMap { height: 300px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
            .welcome-stats { grid-template-columns: 1fr 1fr; }
            .nav-clock .clock-date { display: none; }
            .info-cards { grid-template-columns: 1fr; }
        }

        :focus-visible { outline: 2px solid var(--navy-600); outline-offset: 2px; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="logo">
        <div class="logo-circle">
            <img src="logo.png" alt="Logo"
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-leaf\' style=\'color:var(--forest-700);font-size:1.2rem;\'></i>';">
        </div>
        <div class="logo-text">Smart<em>EcoTrack</em></div>
    </a>

    <div class="nav-clock" id="liveClock" title="Current local time">
        <div class="clock-time" id="clockTime">--:--:--</div>
        <div class="clock-date" id="clockDate">---</div>
    </div>

    <button class="mobile-toggle" onclick="toggleMenu()" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links" id="navbarNav">
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>

        <?php if ($can_report): ?>
            <a href="report_incident.php" class="btn-report">
                <i class="fas fa-plus-circle"></i> Report Incident
            </a>
        <?php endif; ?>

        <?php if ($can_manage): ?>
            <a href="manage_incidents.php" class="btn-manage">
                <i class="fas fa-clipboard-check"></i> Manage Reports
            </a>
        <?php else: ?>
            <a href="my_reports.php"><i class="fas fa-list-check"></i> My Reports</a>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <a href="manage_users.php" class="btn-admin">
                <i class="fas fa-users-cog"></i> Manage Users
            </a>
        <?php endif; ?>

        <a href="modules/disaster_tips.php"><i class="fas fa-shield-alt"></i> Disaster Tips</a>

        <?php if ($can_report): ?>
            <a href="modules/quiz.php"><i class="fas fa-brain"></i> Quiz</a>
        <?php endif; ?>

        <a href="modules/emergency_contacts.php"><i class="fas fa-phone-alt"></i> Emergency</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="container">
    <!-- Welcome -->
    <div class="welcome-card">
        <div class="welcome-content">
            <h1>
                <?php
                $h = (int)date('H');
                if ($h < 12) echo '☀️ Good Morning';
                elseif ($h < 17) echo '🌤️ Good Afternoon';
                else echo '🌙 Good Evening';
                ?>,
                <?php echo htmlspecialchars($user['firstname']); ?>!
            </h1>
            <p class="subtitle">
                <?php
                if ($is_admin) {
                    echo 'System overview — monitor reports, users, and community activity. 🌿';
                } elseif ($is_staff) {
                    echo 'Review and manage citizen incident reports. Your work keeps the community safe. 🛡️';
                } else {
                    echo 'Together, we protect our environment and keep communities safe. 🌿';
                }
                ?>
            </p>

            <div class="user-info">
                <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                    <i class="fas fa-<?php echo $is_admin ? 'crown' : ($is_staff ? 'user-shield' : 'user'); ?>"></i>
                    <?php echo strtoupper($user['role']); ?>
                </span>
                <span class="info"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                <?php if ($is_regular): ?>
                    <span class="info"><i class="fas fa-star"></i> <?php echo (int)$user['quiz_score']; ?> Points</span>
                <?php endif; ?>
                <span class="info"><i class="fas fa-clock"></i> <span id="welcomeClock">--:--:--</span></span>
            </div>

            <div class="welcome-stats">
                <div class="welcome-stat">
                    <div class="stat-value"><?php echo $display_reports; ?></div>
                    <div class="stat-label"><?php echo $can_manage ? 'Total Reports' : 'My Reports'; ?></div>
                </div>
                <div class="welcome-stat">
                    <div class="stat-value"><?php echo $display_pending; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="welcome-stat">
                    <div class="stat-value"><?php echo $display_resolved; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <h3><?php echo $stat_total_incidents; ?></h3>
            <p><?php echo $can_manage ? 'Total Reports' : 'My Reports'; ?></p>
        </div>

        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <h3><?php echo $display_pending; ?></h3>
            <p><?php echo $can_manage ? 'Pending (All)' : 'My Pending'; ?></p>
        </div>

        <?php if ($can_manage): ?>
            <div class="stat-card critical">
                <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                <h3><?php echo $critical_issues; ?></h3>
                <p>Critical (Unresolved)</p>
            </div>
        <?php else: ?>
            <div class="stat-card critical">
                <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                <h3><?php echo $my_pending; ?></h3>
                <p>My Critical</p>
            </div>
        <?php endif; ?>

        <div class="stat-card resolved">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <h3><?php echo $display_resolved; ?></h3>
            <p><?php echo $can_manage ? 'Resolved (All)' : 'My Resolved'; ?></p>
        </div>

        <div class="stat-card users">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <h3><?php echo $stat_total_users; ?></h3>
            <p>Community Members</p>
        </div>

        <div class="stat-card admins">
            <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
            <h3><?php echo $total_admins; ?></h3>
            <p>Administrators</p>
        </div>

        <div class="stat-card staff">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <h3><?php echo $total_staff; ?></h3>
            <p>Staff Members</p>
        </div>

        <div class="stat-card staff-status-card">
            <div class="stat-icon">
                <span class="live-dot"></span>
                <i class="fas fa-user-shield"></i>
            </div>
            <h3 id="onlineStaffCount"><?php echo $online_staff_count; ?></h3>
            <p>Staff Online Now</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <?php if ($can_report): ?>
            <a href="report_incident.php" class="action-btn primary">
                <i class="fas fa-plus-circle"></i> Report Incident
            </a>
        <?php endif; ?>

        <?php if ($can_manage): ?>
            <a href="manage_incidents.php" class="action-btn manage">
                <i class="fas fa-clipboard-check"></i> Manage Citizen Reports
            </a>
        <?php else: ?>
            <a href="my_reports.php" class="action-btn">
                <i class="fas fa-list-check"></i> My Reports
            </a>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <a href="manage_users.php" class="action-btn admin">
                <i class="fas fa-users-cog"></i> Manage Users
            </a>
        <?php endif; ?>

        <a href="modules/disaster_tips.php" class="action-btn">
            <i class="fas fa-book-medical"></i> Disaster Guides
        </a>

        <?php if ($can_report): ?>
            <a href="modules/quiz.php" class="action-btn">
                <i class="fas fa-brain"></i> Take Quiz
            </a>
        <?php endif; ?>

        <a href="modules/emergency_contacts.php" class="action-btn">
            <i class="fas fa-phone-alt"></i> Emergency Contacts
        </a>
    </div>

    <!-- Two Column -->
    <div class="two-column">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> <?php echo $can_manage ? 'Recent Incidents (All)' : 'My Recent Incidents'; ?></h3>
                <a href="<?php echo $can_manage ? 'manage_incidents.php' : 'my_reports.php'; ?>" class="view-all">View All →</a>
            </div>

            <?php if ($recent_incidents && mysqli_num_rows($recent_incidents) > 0): ?>
                <?php while ($inc = mysqli_fetch_assoc($recent_incidents)): ?>
                    <?php
                    $icons = [
                        'flooding' => '🌊', 'landslide' => '⛰️', 'deforestation' => '🌳',
                        'pollution' => '🏭', 'wildfire' => '🔥', 'wildlife' => '🦅',
                        'earthquake' => '🌍', 'typhoon' => '🌀'
                    ];
                    $catIcon = $icons[$inc['category']] ?? '📋';
                    ?>
                    <div class="incident-item">
                        <div class="incident-header">
                            <div>
                                <div class="incident-title">
                                    <?php echo $catIcon . ' ' . htmlspecialchars($inc['title']); ?>
                                </div>
                                <div class="incident-meta">
                                    By <?php echo htmlspecialchars($inc['firstname'] . ' ' . $inc['lastname']); ?>
                                    • <?php echo date('M d, Y', strtotime($inc['created_at'])); ?>
                                </div>
                            </div>
                            <div class="badge-row">
                                <span class="badge badge-<?php echo htmlspecialchars($inc['status']); ?>">
                                    <?php echo ucfirst($inc['status']); ?>
                                </span>
                                <span class="badge badge-<?php echo htmlspecialchars($inc['severity']); ?>">
                                    <?php echo ucfirst($inc['severity']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No incidents reported yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lightbulb"></i> Safety Tips</h3>
                <a href="modules/disaster_tips.php" class="view-all">View All →</a>
            </div>
            <?php if ($safety_tips && mysqli_num_rows($safety_tips) > 0): ?>
                <?php while ($tip = mysqli_fetch_assoc($safety_tips)): ?>
                    <div class="tip-card">
                        <div class="tip-category">
                            <i class="fas fa-<?php echo htmlspecialchars($tip['icon']); ?>"></i>
                            <?php echo htmlspecialchars($tip['category']); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($tip['title']); ?></h4>
                        <p><?php echo htmlspecialchars(mb_substr($tip['content'], 0, 140)) ?>...</p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-lightbulb"></i>
                    <p>No tips available.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Community Activity -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-rss"></i> Community Activity</h3>
        </div>
        <?php if ($recent_activities && mysqli_num_rows($recent_activities) > 0): ?>
            <?php while ($activity = mysqli_fetch_assoc($recent_activities)): ?>
                <div class="activity-item">
                    <div class="activity-icon <?php echo $activity['type'] === 'report' ? 'activity-report' : 'activity-quiz'; ?>">
                        <i class="fas fa-<?php echo $activity['type'] === 'report' ? 'clipboard' : 'brain'; ?>"></i>
                    </div>
                    <div class="activity-content">
                        <strong><?php echo htmlspecialchars($activity['firstname'] . ' ' . $activity['lastname']); ?></strong>
                        <?php echo $activity['type'] === 'report' ? 'reported' : 'completed quiz:'; ?>
                        <?php echo htmlspecialchars($activity['action']); ?>
                        <?php if ($activity['type'] === 'report' && $activity['id']): ?>
                            <a href="incident_view.php?id=<?php echo (int)$activity['id']; ?>"
                               style="color:var(--forest-700); font-size:0.78rem; font-weight:600;">View</a>
                        <?php endif; ?>
                        <div class="activity-time">
                            <?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-rss"></i>
                <p>No recent activity.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Staff Online -->
    <div class="card">
        <div class="card-header">
            <h3>
                <span class="live-dot"></span>
                Staff Online
                <span style="font-weight:400; color:var(--gray-500); font-size:0.78rem; margin-left:4px;">
                    (<span id="staffCountLabel"><?php echo $online_staff_count; ?></span> active now)
                </span>
            </h3>
            <span style="color:var(--gray-400); font-size:0.72rem;">
                <i class="fas fa-sync-alt"></i> Auto-refreshes every 30s
            </span>
        </div>

        <div class="staff-list" id="staffList">
            <?php if (!empty($online_staff)): ?>
                <?php foreach ($online_staff as $s):
                    $initials = strtoupper(substr($s['firstname'], 0, 1) . substr($s['lastname'], 0, 1));
                    $minutes_ago = max(0, (int)((time() - strtotime($s['last_active'])) / 60));
                    if ($minutes_ago < 1)      $seen = 'Active now';
                    elseif ($minutes_ago === 1) $seen = '1 min ago';
                    else                        $seen = $minutes_ago . ' min ago';
                ?>
                    <div class="staff-item">
                        <div class="staff-avatar"><?php echo $initials; ?></div>
                        <div class="staff-info">
                            <div class="staff-name">
                                <?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?>
                                <span class="staff-role-chip role-<?php echo strtolower($s['role']); ?>">
                                    <?php echo htmlspecialchars($s['role']); ?>
                                </span>
                            </div>
                            <div class="staff-meta">
                                <i class="fas fa-envelope" style="font-size:0.65rem;"></i>
                                <?php echo htmlspecialchars($s['email']); ?>
                            </div>
                        </div>
                        <div class="staff-last-seen"><?php echo $seen; ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <p>No staff currently online.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EMERGENCY TRACKER -->
    <?php if ($can_track): ?>
    <div class="tracker-header">
        <div class="tracker-title">
            <i class="fas fa-satellite-dish"></i>
            <span>
                Live Emergency Tracker
                <div style="font-weight:400;font-size:0.75rem;opacity:0.9;margin-top:1px;">
                    Real-time location of citizens in critical / high emergencies
                </div>
            </span>
        </div>
        <div class="tracker-count">
            <i class="fas fa-triangle-exclamation"></i>
            <span id="emergencyCountNum"><?php echo $emergency_count; ?></span> active
        </div>
    </div>

    <div class="tracker-layout">
        <div id="emergencyMap"></div>

        <div class="emergency-list" id="emergencyList">
            <?php if ($emergency_count > 0): ?>
                <?php foreach ($emergencies as $e):
                    $lat = (float)$e['latitude'];
                    $lng = (float)$e['longitude'];
                    $mins = max(0, (int)((time() - strtotime($e['created_at'])) / 60));
                    if ($mins < 1)      $ago = 'Just now';
                    elseif ($mins === 1) $ago = '1 min ago';
                    elseif ($mins < 60)  $ago = $mins . ' min ago';
                    else                 $ago = floor($mins / 60) . 'h ' . ($mins % 60) . 'm ago';

                    $sev = strtolower($e['severity']);
                    $initials = strtoupper(substr($e['firstname'] ?: '?', 0, 1) . substr($e['lastname'] ?: '?', 0, 1));
                    $phone = preg_replace('/[^0-9+]/', '', $e['phone'] ?? '');
                ?>
                    <div class="emergency-card sev-<?php echo $sev; ?>"
                         data-lat="<?php echo $lat; ?>"
                         data-lng="<?php echo $lng; ?>"
                         data-id="<?php echo (int)$e['id']; ?>"
                         onclick="focusEmergency(<?php echo $lat; ?>, <?php echo $lng; ?>, <?php echo (int)$e['id']; ?>)">

                        <div class="em-top">
                            <div class="em-title"><?php echo htmlspecialchars($e['title']); ?></div>
                            <span class="em-sev <?php echo $sev; ?>"><?php echo ucfirst($sev); ?></span>
                        </div>

                        <div class="em-map-loc">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($e['location_name'] ?: ($lat . ', ' . $lng)); ?></span>
                        </div>

                        <div class="em-citizen">
                            <div class="em-avatar"><?php echo $initials; ?></div>
                            <div style="flex:1;min-width:0;">
                                <div class="em-cname"><?php echo htmlspecialchars($e['firstname'] . ' ' . $e['lastname']); ?></div>
                                <div class="em-cmeta">
                                    <?php echo htmlspecialchars($e['email']); ?>
                                    <?php if (!empty($phone)): ?>
                                        · <?php echo htmlspecialchars($e['phone']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="em-ago"><?php echo $ago; ?></div>
                        </div>

                        <div class="em-actions">
                            <?php if (!empty($phone)): ?>
                                <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="em-btn call"
                                   onclick="event.stopPropagation();">
                                    <i class="fas fa-phone"></i> Call
                                </a>
                            <?php else: ?>
                                <span class="em-btn disabled"><i class="fas fa-phone-slash"></i> No phone</span>
                            <?php endif; ?>

                            <a href="incident_view.php?id=<?php echo (int)$e['id']; ?>" class="em-btn view"
                               onclick="event.stopPropagation();">
                                <i class="fas fa-eye"></i> Details
                            </a>

                            <button class="em-btn locate"
                                    onclick="event.stopPropagation(); focusEmergency(<?php echo $lat; ?>, <?php echo $lng; ?>, <?php echo (int)$e['id']; ?>);">
                                <i class="fas fa-crosshairs"></i> Locate
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="tracker-empty">
                    <i class="fas fa-shield-alt"></i>
                    <p>No active emergencies</p>
                    <small>All clear — no critical or high-severity reports with location.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Info Cards -->
    <div class="info-cards">
        <div class="info-card">
            <div class="big-icon" style="color:var(--crimson);">
                <i class="fas fa-phone-alt"></i>
            </div>
            <h3 style="color:var(--crimson);">Emergency Hotline</h3>
            <p style="font-size:1.5rem;font-weight:800;color:var(--navy-900);letter-spacing:-0.02em;">911</p>
            <a href="tel:911" class="action-btn primary" style="display:inline-flex; margin-top:0.5rem; padding:0.4rem 1.1rem;">
                <i class="fas fa-phone"></i> Call Now
            </a>
        </div>

        <div class="info-card">
            <div class="big-icon" style="color:var(--info-blue);">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3 style="color:var(--info-blue);">Did You Know?</h3>
            <p>
                <?php
                $facts = [
                    "Forests cover 31% of the world's land area and are home to 80% of terrestrial biodiversity.",
                    "The Philippines is one of the world's 17 megadiverse countries, hosting over 52,000 described species.",
                    "A single mature tree can absorb up to 48 pounds of CO₂ per year and provide oxygen for 4 people daily.",
                    "Mangrove forests protect coastal communities by reducing storm surge heights by up to 66%.",
                    "Over 90% of disasters in the Philippines are water-related, including floods, typhoons, and landslides."
                ];
                echo $facts[array_rand($facts)];
                ?>
            </p>
        </div>

        <?php if ($can_manage): ?>
            <div class="info-card">
                <div class="big-icon" style="color:var(--forest-700);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 style="color:var(--forest-800);">System Status</h3>
                <p style="font-size:1.6rem;font-weight:800;color:var(--forest-700);">
                    <?php echo $resolved_all + $verified_all; ?>
                </p>
                <p>Reports Addressed</p>
            </div>
        <?php else: ?>
            <div class="info-card">
                <div class="big-icon" style="color:var(--yellow);">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 style="color:var(--amber);">Your Progress</h3>
                <p style="font-size:1.6rem;font-weight:800;color:var(--forest-700);"><?php echo (int)$user['quiz_score']; ?></p>
                <p>Quiz Points Earned</p>
                <a href="modules/quiz.php" style="color:var(--forest-700);font-weight:600;font-size:0.8rem;text-decoration:none;">
                    Take Quiz →
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>&copy; 2026 SmartEcoTrack · Environmental Incident Reporting & Monitoring System</p>
    <p class="sub">Working together for a safer, greener Philippines 🌿</p>
</footer>

<!-- ============================================================
     ECOBOT — rendered ONLY for citizens
     ============================================================ -->
<?php if ($can_chatbot): ?>
<button id="ecobotLauncher" class="ecobot-launcher" aria-label="Open AI assistant">
    <i class="fas fa-robot"></i>
    <span class="ecobot-badge" id="ecobotBadge">1</span>
</button>

<div id="ecobotPanel" class="ecobot-panel" role="dialog" aria-labelledby="ecobotTitle" aria-hidden="true">
    <div class="ecobot-header">
        <div class="ecobot-avatar"><i class="fas fa-leaf"></i></div>
        <div class="ecobot-title-block">
            <div id="ecobotTitle" class="ecobot-title">EcoBot</div>
            <div class="ecobot-subtitle">
                <span class="ecobot-status-dot"></span> Online · AI Assistant
            </div>
        </div>
        <button id="ecobotClose" class="ecobot-close" aria-label="Close chat">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div id="ecobotMessages" class="ecobot-messages">
        <div class="ecobot-msg ecobot-bot">
            <div class="ecobot-bubble">
                Hi <?php echo htmlspecialchars($user['firstname']); ?>! 🌿 I'm <strong>EcoBot</strong>.
                Ask me how to report an incident, get safety tips, or check your report stats.
            </div>
        </div>
        <div class="ecobot-suggestions" id="ecobotSuggestions">
            <button data-q="How do I report an incident?">How do I report an incident?</button>
            <button data-q="What are my report stats?">My report stats</button>
            <button data-q="Flood safety tips">Flood safety</button>
            <button data-q="Emergency contacts">Emergency contacts</button>
        </div>
    </div>

    <form id="ecobotForm" class="ecobot-input-row" autocomplete="off">
        <input type="text" id="ecobotInput" placeholder="Ask EcoBot anything…" maxlength="500" required>
        <button type="submit" id="ecobotSend" aria-label="Send">
            <i class="fas fa-paper-plane"></i>
        </button>
    </form>
</div>
<?php endif; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    /* ============================================================
       LIVE CLOCK
       ============================================================ */
    (function liveClock() {
        const timeEl   = document.getElementById('clockTime');
        const dateEl   = document.getElementById('clockDate');
        const welcomeEl= document.getElementById('welcomeClock');

        function pad(n) { return n < 10 ? '0' + n : n; }

        function tick() {
            const now = new Date();
            let h = now.getHours();
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; if (h === 0) h = 12;
            const timeStr = pad(h) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ampm;

            const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const dateStr = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();

            if (timeEl)    timeEl.textContent    = timeStr;
            if (dateEl)    dateEl.textContent    = dateStr;
            if (welcomeEl) welcomeEl.textContent = timeStr;
        }
        tick();
        setInterval(tick, 1000);
    })();

    /* ============================================================
       MOBILE MENU
       ============================================================ */
    function toggleMenu() {
        document.getElementById('navbarNav').classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
        const nav = document.getElementById('navbarNav');
        const toggle = document.querySelector('.mobile-toggle');
        if (nav.classList.contains('active') &&
            !nav.contains(e.target) && !toggle.contains(e.target)) {
            nav.classList.remove('active');
        }
    });

    /* ============================================================
       ESCAPE HELPER
       ============================================================ */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ============================================================
       STAFF ONLINE — auto-refresh
       ============================================================ */
    function refreshStaffOnline() {
        fetch('get_online_staff.php', { cache: 'no-store' })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                const countEl = document.getElementById('onlineStaffCount');
                const labelEl = document.getElementById('staffCountLabel');
                if (countEl) countEl.textContent = data.count;
                if (labelEl) labelEl.textContent = data.count;

                const list = document.getElementById('staffList');
                if (!list) return;

                if (!data.staff || data.staff.length === 0) {
                    list.innerHTML =
                        '<div class="empty-state">' +
                        '<i class="fas fa-user-slash"></i>' +
                        '<p>No staff currently online.</p>' +
                        '</div>';
                    return;
                }

                list.innerHTML = data.staff.map(s => {
                    const m = s.minutes_ago;
                    let seen = 'Active now';
                    if (m === 1) seen = '1 min ago';
                    else if (m > 1) seen = m + ' min ago';

                    return `
                        <div class="staff-item">
                            <div class="staff-avatar">${s.initials}</div>
                            <div class="staff-info">
                                <div class="staff-name">
                                    ${escapeHtml(s.name)}
                                    <span class="staff-role-chip role-${s.role.toLowerCase()}">
                                        ${escapeHtml(s.role)}
                                    </span>
                                </div>
                                <div class="staff-meta">
                                    <i class="fas fa-envelope" style="font-size:0.65rem;"></i>
                                    ${escapeHtml(s.email)}
                                </div>
                            </div>
                            <div class="staff-last-seen">${seen}</div>
                        </div>
                    `;
                }).join('');
            })
            .catch(() => {});
    }

    setInterval(refreshStaffOnline, 30000);
    setTimeout(refreshStaffOnline, 5000);

    /* ============================================================
       EMERGENCY MAP
       ============================================================ */
    let emMap = null;
    let emMarkers = {};
    let emInitialized = false;

    function initEmergencyMap() {
        if (emInitialized || typeof L === 'undefined') return;
        const el = document.getElementById('emergencyMap');
        if (!el) return;

        emMap = L.map('emergencyMap').setView([12.8797, 121.7740], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19
        }).addTo(emMap);

        emInitialized = true;
        drawEmergencyMarkers();
    }

    function drawEmergencyMarkers() {
        if (!emMap) return;

        const cards = document.querySelectorAll('.emergency-card');
        const bounds = [];

        cards.forEach(card => {
            const id  = card.dataset.id;
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            const title = card.querySelector('.em-title')?.textContent || '';
            const sev   = card.querySelector('.em-sev')?.textContent.toLowerCase() || '';
            const color = sev === 'critical' ? '#B71C1C' : '#E65100';

            const icon = L.divIcon({
                className: 'emergency-marker',
                html: `<div style="
                    width: 20px; height: 20px;
                    background: ${color};
                    border: 3px solid #fff;
                    border-radius: 50%;
                    box-shadow: 0 0 0 2px ${color}55, 0 4px 10px rgba(0,0,0,0.35);
                "></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            const marker = L.marker([lat, lng], { icon }).addTo(emMap);

            const phone = card.querySelector('.em-btn.call')?.getAttribute('href')?.replace('tel:', '') || '';
            const detailUrl = card.querySelector('.em-btn.view')?.getAttribute('href') || '#';

            marker.bindPopup(`
                <div style="font-family:'Inter',sans-serif;min-width:220px;">
                    <div style="font-weight:700;font-size:0.95rem;color:#0A1931;margin-bottom:6px;">
                        ⚠️ ${title}
                    </div>
                    <div style="font-size:0.82rem;color:#4A535C;line-height:1.5;">
                        <div><b>Coordinates:</b> ${lat.toFixed(5)}, ${lng.toFixed(5)}</div>
                        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
                            ${phone ? `<a href="tel:${phone}" style="background:#C62828;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.78rem;">📞 Call</a>` : ''}
                            <a href="${detailUrl}" style="background:#1A3A63;color:#fff;padding:4px 10px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.78rem;">👁 View</a>
                        </div>
                    </div>
                </div>
            `);

            emMarkers[id] = marker;
            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) {
            emMap.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        }
    }

    function focusEmergency(lat, lng, id) {
        if (!emMap) return;
        emMap.setView([lat, lng], 16, { animate: true });
        const marker = emMarkers[id];
        if (marker) setTimeout(() => marker.openPopup(), 400);
    }

    window.addEventListener('load', () => setTimeout(initEmergencyMap, 400));

    function refreshEmergencies() {
        fetch('get_emergencies.php', { cache: 'no-store' })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                const numEl = document.getElementById('emergencyCountNum');
                if (numEl) numEl.textContent = data.count;

                Object.values(emMarkers).forEach(m => emMap && emMap.removeLayer(m));
                emMarkers = {};

                const list = document.getElementById('emergencyList');
                if (!list) return;

                if (!data.emergencies || data.emergencies.length === 0) {
                    list.innerHTML =
                        '<div class="tracker-empty">' +
                        '<i class="fas fa-shield-alt"></i>' +
                        '<p>No active emergencies</p>' +
                        '<small>All clear — no critical or high-severity reports with location.</small>' +
                        '</div>';
                    return;
                }

                list.innerHTML = data.emergencies.map(e => {
                    const ago = e.minutes_ago < 1 ? 'Just now'
                              : e.minutes_ago === 1 ? '1 min ago'
                              : e.minutes_ago < 60 ? e.minutes_ago + ' min ago'
                              : Math.floor(e.minutes_ago / 60) + 'h ' + (e.minutes_ago % 60) + 'm ago';

                    const phone = (e.citizen.phone || '').replace(/[^0-9+]/g, '');
                    const loc = e.location || (e.lat.toFixed(5) + ', ' + e.lng.toFixed(5));

                    return `
                        <div class="emergency-card sev-${e.severity}"
                             data-lat="${e.lat}" data-lng="${e.lng}" data-id="${e.id}"
                             onclick="focusEmergency(${e.lat}, ${e.lng}, ${e.id})">
                            <div class="em-top">
                                <div class="em-title">${escapeHtml(e.title)}</div>
                                <span class="em-sev ${e.severity}">${e.severity}</span>
                            </div>
                            <div class="em-map-loc">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${escapeHtml(loc)}</span>
                            </div>
                            <div class="em-citizen">
                                <div class="em-avatar">${e.citizen.initials}</div>
                                <div style="flex:1;min-width:0;">
                                    <div class="em-cname">${escapeHtml(e.citizen.name)}</div>
                                    <div class="em-cmeta">
                                        ${escapeHtml(e.citizen.email)}
                                        ${phone ? ' · ' + escapeHtml(phone) : ''}
                                    </div>
                                </div>
                                <div class="em-ago">${ago}</div>
                            </div>
                            <div class="em-actions">
                                ${phone
                                    ? `<a href="tel:${phone}" class="em-btn call" onclick="event.stopPropagation();">
                                           <i class="fas fa-phone"></i> Call
                                       </a>`
                                    : `<span class="em-btn disabled"><i class="fas fa-phone-slash"></i> No phone</span>`}
                                <a href="incident_view.php?id=${e.id}" class="em-btn view" onclick="event.stopPropagation();">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                                <button class="em-btn locate"
                                        onclick="event.stopPropagation(); focusEmergency(${e.lat}, ${e.lng}, ${e.id});">
                                    <i class="fas fa-crosshairs"></i> Locate
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');

                drawEmergencyMarkers();
            })
            .catch(() => {});
    }

    setInterval(refreshEmergencies, 15000);
</script>

<?php if ($can_chatbot): ?>
<script>
    /* ============================================================
       ECOBOT — AI CHATBOT (CITIZENS ONLY)
       ============================================================ */
    const ECOBOT_CONTEXT = <?php echo json_encode($botContext, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    (function initEcoBot() {
        const launcher    = document.getElementById('ecobotLauncher');
        const panel       = document.getElementById('ecobotPanel');
        const closeBtn    = document.getElementById('ecobotClose');
        const form        = document.getElementById('ecobotForm');
        const input       = document.getElementById('ecobotInput');
        const sendBtn     = document.getElementById('ecobotSend');
        const messagesEl  = document.getElementById('ecobotMessages');
        const suggestions = document.getElementById('ecobotSuggestions');
        const badge       = document.getElementById('ecobotBadge');

        if (!launcher || !panel) return;

        let isSending = false;

        launcher.addEventListener('click', () => {
            if (badge) badge.style.display = 'none';
        }, { once: true });

        function openPanel() {
            panel.classList.add('open');
            panel.setAttribute('aria-hidden', 'false');
            setTimeout(() => input.focus(), 200);
            scrollToBottom();
        }
        function closePanel() {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
            launcher.focus();
        }

        launcher.addEventListener('click', () => {
            if (panel.classList.contains('open')) closePanel();
            else openPanel();
        });
        closeBtn.addEventListener('click', closePanel);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
        });

        function scrollToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function formatReply(text) {
            const escaped = String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            return escaped
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
                .replace(/\n/g, '<br>');
        }

        function addMessage(role, text) {
            const wrap = document.createElement('div');
            wrap.className = 'ecobot-msg ecobot-' + (role === 'user' ? 'user' : 'bot');
            const bubble = document.createElement('div');
            bubble.className = 'ecobot-bubble';
            bubble.innerHTML = formatReply(text);
            wrap.appendChild(bubble);
            messagesEl.appendChild(wrap);
            scrollToBottom();
            return wrap;
        }

        function showTyping() {
            const wrap = document.createElement('div');
            wrap.className = 'ecobot-msg ecobot-bot ecobot-typing';
            wrap.id = 'ecobotTyping';
            wrap.innerHTML = '<div class="ecobot-bubble"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>';
            messagesEl.appendChild(wrap);
            scrollToBottom();
        }
        function hideTyping() {
            const t = document.getElementById('ecobotTyping');
            if (t) t.remove();
        }

        function hideSuggestions() {
            if (suggestions) suggestions.style.display = 'none';
        }

        /* ---------- Rule-based AI engine (citizen-focused) ---------- */
        function generateReply(message, ctx) {
            const m = message.toLowerCase();
            const has = (...words) => words.some(w => m.includes(w));

            // Emergency first
            if (has('emergency', 'dying', 'accident', 'urgent', 'help now', '911', 'sunog', 'lindol', 'baha na')) {
                return "🚨 For any life-threatening emergency, call **911 immediately**. " +
                       "After calling, you can log the incident on the *Report Incident* page so local staff can respond.";
            }

            // Greetings
            if ((has('hello', 'hi ', 'hey', 'good morning', 'good evening', 'kumusta', 'kamusta') && m.length < 20)
                || m === 'hi' || m === 'hello' || m === 'hey') {
                return `Hi ${ctx.firstname}! 🌿 I'm EcoBot. Ask me anything about reporting incidents, ` +
                       "disaster tips, or how to use SmartEcoTrack.";
            }

            // How to report
            if (has('how to report', 'report incident', 'submit report', 'file report', 'new report', 'paano mag report', 'mag report')) {
                return "To report an incident:\n" +
                       "1️⃣ Click **Report Incident** in the nav bar\n" +
                       "2️⃣ Pick a category (flooding, landslide, pollution…)\n" +
                       "3️⃣ Add a title and description\n" +
                       "4️⃣ Mark the location on the map\n" +
                       "5️⃣ Submit — staff will review it. ✅";
            }

            // My report stats
            if (has('my report', 'my reports', 'status of my', 'my pending', 'how many reports')) {
                return `You currently have **${ctx.myReports} report(s)** — ` +
                       `${ctx.myPending} pending and ${ctx.myResolved} resolved. ` +
                       "View them all on *My Reports*.";
            }

            // Quiz / points
            if (has('quiz', 'points', 'score', 'rank', 'trophy')) {
                return `You have **${ctx.quizScore} quiz points**. 🏆 Take quizzes from the *Quiz* page ` +
                       "to earn more and learn disaster preparedness.";
            }

            // Flood
            if (has('flood', 'baha', 'tubig', 'flash flood')) {
                return "🌊 **Flood safety:**\n" +
                       "• Move to higher ground immediately\n" +
                       "• Avoid walking or driving through flowing water (15 cm can knock you down)\n" +
                       "• Unplug electronics\n" +
                       "• Monitor PAGASA advisories\n" +
                       "Report local flooding via *Report Incident* → Flooding.";
            }

            // Landslide
            if (has('landslide', 'mudslide', 'guhо', 'guhò', 'pagguho')) {
                return "⛰️ **Landslide safety:**\n" +
                       "• Watch for cracks in the ground, tilting trees, unusual rumbling\n" +
                       "• Evacuate **sideways**, not downhill\n" +
                       "• Report suspicious slopes via *Report Incident*.";
            }

            // Earthquake
            if (has('earthquake', 'lindol', 'quake', 'tremor')) {
                return "🌍 **Earthquake:** Duck, Cover, Hold.\n" +
                       "• Stay away from windows\n" +
                       "• After shaking stops, evacuate to open ground\n" +
                       "• Check for injuries\n" +
                       "Report damage via *Report Incident*.";
            }

            // Typhoon
            if (has('typhoon', 'bagyo', 'storm', 'hurricane', 'cyclone')) {
                return "🌀 **Typhoon prep:**\n" +
                       "• Secure loose objects\n" +
                       "• Stock 3 days of water/food\n" +
                       "• Charge devices\n" +
                       "• Know your evacuation route\n" +
                       "• Follow PAGASA updates";
            }

            // Fire
            if (has('fire', 'wildfire', 'sunog', 'burning')) {
                return "🔥 **Fire:**\n" +
                       "• Call **911 immediately**\n" +
                       "• If safe, evacuate upwind\n" +
                       "• Report wildfires via *Report Incident* → Wildfire so responders can be dispatched.";
            }

            // Pollution
            if (has('pollution', 'smoke', 'garbage', 'basura', 'dirty', 'waste')) {
                return "🏭 Report pollution (illegal dumping, smoke, chemical spills) via *Report Incident* → Pollution. " +
                       "Include the location and a photo if possible — it helps staff act faster.";
            }

            // Contacts
            if (has('contact', 'hotline', 'phone number', 'call who', 'numero')) {
                return "📞 **Emergency contacts:**\n" +
                       "• National emergency: **911**\n" +
                       "• Environmental concerns: **DENR 8888**\n" +
                       "• Red Cross: **143**\n\n" +
                       "See *Emergency Contacts* in the nav for local numbers.";
            }

            // Help
            if (has('help', 'what can you do', 'features', 'guide', 'menu', 'commands')) {
                return "I can help with:\n" +
                       "• 📝 How to report an incident\n" +
                       "• 📊 Your report status & quiz points\n" +
                       "• 🌊 Safety tips (flood, earthquake, fire…)\n" +
                       "• 📞 Emergency contacts\n" +
                       "Just ask!";
            }

            // Thanks / bye
            if (has('thank', 'salamat', 'thanks', 'bye', 'paalam')) {
                return `You're welcome, ${ctx.firstname}! Stay safe and keep our communities green. 🌿`;
            }

            // Who are you
            if (has('who are you', 'your name', 'what are you')) {
                return "I'm **EcoBot** 🌿 — SmartEcoTrack's AI assistant. I help citizens with incident reporting, " +
                       "disaster preparedness, and navigating this dashboard. What can I help you with?";
            }

            // Default
            return "I'm not sure I understood that. Try asking:\n" +
                   "• *\"How do I report a flood?\"*\n" +
                   "• *\"What are my report stats?\"*\n" +
                   "• *\"Earthquake safety tips\"*\n" +
                   "Or type **help** for the full menu.";
        }

        function sendMessage(text) {
            text = text.trim();
            if (!text || isSending) return;

            hideSuggestions();
            addMessage('user', text);
            input.value = '';
            isSending = true;
            sendBtn.disabled = true;
            showTyping();

            setTimeout(() => {
                hideTyping();
                const reply = generateReply(text, ECOBOT_CONTEXT);
                addMessage('bot', reply);
                isSending = false;
                sendBtn.disabled = false;
                input.focus();
            }, 450 + Math.random() * 500);
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            sendMessage(input.value);
        });

        if (suggestions) {
            suggestions.addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-q]');
                if (!btn) return;
                sendMessage(btn.dataset.q);
            });
        }
    })();
</script>
<?php endif; ?>

</body>
</html>