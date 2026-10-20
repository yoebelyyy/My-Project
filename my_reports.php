<?php
/* ============================================================
   FILE: my_reports.php
   Purpose: Reports list + live emergency tracker for staff/admin
   ============================================================ */
require_once 'db_connect.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = (int)$_SESSION['user_id'];

$current_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
if (!$current_user) { session_destroy(); redirect('login.php'); }

$is_admin = (isset($current_user['role']) && strtolower($current_user['role']) === 'admin');
$is_staff = (isset($current_user['role']) && strtolower($current_user['role']) === 'staff');
$can_track = $is_admin || $is_staff;

/* ============================================================
   Fetch reports
   ============================================================ */
if ($is_admin) {
    $reports = mysqli_query($conn,
        "SELECT i.*, u.firstname, u.lastname, u.email, u.phone
         FROM incidents i
         JOIN users u ON i.user_id = u.id
         ORDER BY i.created_at DESC");
} else {
    $reports = mysqli_query($conn,
        "SELECT i.*, u.firstname, u.lastname, u.email, u.phone
         FROM incidents i
         JOIN users u ON i.user_id = u.id
         WHERE i.user_id = $user_id
         ORDER BY i.created_at DESC");
}
$report_count = $reports ? mysqli_num_rows($reports) : 0;

/* ============================================================
   Fetch active emergencies (tracker — admin + staff only)
   ============================================================ */
$emergencies = [];
$emergency_count = 0;
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
        while ($row = mysqli_fetch_assoc($em_result)) {
            $emergencies[] = $row;
        }
    }
    $emergency_count = count($emergencies);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_admin ? 'All Reports' : 'My Reports'; ?> · SmartEcoTrack</title>
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
            line-height: 1.6;
            padding-top: 88px;
            -webkit-font-smoothing: antialiased;
        }

        /* NAVBAR */
        .navbar {
            position: fixed; top: 0; left: 0; width: 100%;
            padding: 0.75rem 5vw;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 999;
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
        }
        .logo {
            display: flex; align-items: center; gap: 14px;
            text-decoration: none; color: var(--navy-800);
            font-size: 1.5rem; font-weight: 700;
            letter-spacing: -0.02em; flex-shrink: 0;
        }
        .logo-circle {
            width: 56px; height: 56px; border-radius: 50%;
            overflow: hidden; display: flex;
            align-items: center; justify-content: center;
            background: var(--navy-50);
            box-shadow: 0 0 0 2.5px var(--navy-500), 0 4px 12px rgba(10,25,49,0.15);
            flex-shrink: 0;
        }
        .logo-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logo-text em { font-style: normal; font-weight: 400; color: var(--gray-500); }

        .nav-links { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
        .nav-links a {
            color: var(--gray-600); text-decoration: none;
            padding: 0.5rem 0.95rem; border-radius: var(--radius-sm);
            font-weight: 500; font-size: 0.88rem;
            transition: all 0.15s ease;
            display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap;
        }
        .nav-links a:hover { background: var(--navy-50); color: var(--navy-800); }
        .nav-links a.active { background: var(--navy-700); color: var(--white); }
        .nav-links a.btn-report {
            background: var(--forest-700); color: var(--white); font-weight: 600;
        }
        .nav-links a.btn-report:hover { background: var(--forest-800); }
        .nav-links a.logout { color: var(--crimson); }
        .nav-links a.logout:hover { background: var(--crimson-bg); }

        .mobile-toggle {
            display: none; background: none; border: none;
            color: var(--navy-800); font-size: 1.4rem;
            cursor: pointer; padding: 0.4rem;
        }

        .container {
            max-width: 1200px; margin: 0 auto;
            padding: 2rem 5vw 3rem;
        }

        /* PAGE HEADER */
        .page-header {
            background: var(--gradient-header); color: var(--white);
            padding: 2.5rem 2rem; border-radius: var(--radius-lg);
            margin-bottom: 2rem; text-align: center;
            position: relative; overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .page-header::before {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(circle at 15% 30%, rgba(82,183,136,0.22), transparent 55%),
                radial-gradient(circle at 85% 70%, rgba(82,183,136,0.12), transparent 50%);
            pointer-events: none;
        }
        .page-header h1 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700; letter-spacing: -0.02em;
            margin-bottom: 0.4rem;
            position: relative; z-index: 1;
        }
        .page-header h1 i { color: var(--forest-400); margin-right: 8px; }
        .page-header p {
            opacity: 0.9; font-size: 0.95rem;
            position: relative; z-index: 1;
        }
        .page-header .count-badge {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 0.9rem;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.20);
            padding: 5px 14px; border-radius: var(--radius-pill);
            font-size: 0.82rem; font-weight: 600;
            position: relative; z-index: 1;
        }
        .page-header .count-badge i { color: var(--forest-400); }

        /* ============================================================
           EMERGENCY TRACKER
           ============================================================ */
        .tracker-header {
            background: linear-gradient(95deg, #A51E1E, #C62828);
            color: var(--white); border-radius: var(--radius-lg);
            padding: 1.5rem 1.75rem; margin-bottom: 1.75rem;
            position: relative; overflow: hidden;
            display: flex; align-items: center;
            justify-content: space-between; gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 12px 32px rgba(198,40,40,0.28);
        }
        .tracker-header::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 70% 50%, rgba(255,255,255,0.15), transparent 60%);
            pointer-events: none;
        }
        .tracker-header .tracker-title {
            display: flex; align-items: center; gap: 12px;
            font-size: 1.15rem; font-weight: 800;
            letter-spacing: -0.01em;
            position: relative; z-index: 1;
        }
        .tracker-header .tracker-title i {
            font-size: 1.5rem;
            animation: pulseIcon 1.6s ease-in-out infinite;
        }
        @keyframes pulseIcon {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.15); }
        }
        .tracker-header .tracker-count {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 16px; border-radius: var(--radius-pill);
            font-weight: 800; font-size: 1rem;
            backdrop-filter: blur(6px);
            position: relative; z-index: 1;
        }

        .tracker-layout {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 1.5rem; margin-bottom: 2.5rem;
        }
        @media (max-width: 1024px) {
            .tracker-layout { grid-template-columns: 1fr; }
        }

        #emergencyMap {
            height: 480px; border-radius: var(--radius-lg);
            border: 2px solid var(--crimson);
            box-shadow: var(--shadow-md);
            background: #eef2f6; z-index: 1;
        }

        .emergency-list {
            display: flex; flex-direction: column; gap: 0.75rem;
            max-height: 480px; overflow-y: auto;
            padding-right: 4px;
        }
        .emergency-list::-webkit-scrollbar { width: 6px; }
        .emergency-list::-webkit-scrollbar-thumb {
            background: var(--gray-300); border-radius: var(--radius-pill);
        }

        .emergency-card {
            background: var(--white); border: 1px solid var(--gray-200);
            border-left: 4px solid var(--crimson);
            border-radius: var(--radius-md);
            padding: 1rem 1.1rem;
            transition: all 0.15s ease; cursor: pointer;
        }
        .emergency-card:hover {
            transform: translateX(3px); box-shadow: var(--shadow-md);
            border-left-width: 6px;
        }
        .emergency-card.sev-critical { border-left-color: #B71C1C; }
        .emergency-card.sev-high     { border-left-color: var(--amber); }

        .emergency-card .em-top {
            display: flex; justify-content: space-between;
            align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem;
        }
        .emergency-card .em-title {
            font-weight: 700; color: var(--navy-900);
            font-size: 0.95rem; line-height: 1.3;
        }
        .emergency-card .em-sev {
            font-size: 0.65rem; font-weight: 800;
            padding: 3px 9px; border-radius: var(--radius-pill);
            text-transform: uppercase; letter-spacing: 0.05em;
            flex-shrink: 0;
        }
        .em-sev.critical { background: var(--crimson-bg); color: var(--crimson); }
        .em-sev.high     { background: var(--amber-bg);   color: var(--amber); }

        .emergency-card .em-citizen {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.6rem 0.75rem; background: var(--gray-50);
            border-radius: var(--radius-sm); margin: 0.5rem 0;
        }
        .emergency-card .em-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--gradient-header); color: var(--white);
            display: flex; align-items: center;
            justify-content: center; font-weight: 700;
            font-size: 0.75rem; flex-shrink: 0;
        }
        .emergency-card .em-cname {
            font-weight: 600; color: var(--navy-900); font-size: 0.85rem;
        }
        .emergency-card .em-cmeta {
            color: var(--gray-500); font-size: 0.73rem;
        }

        .emergency-card .em-actions {
            display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.5rem;
        }
        .em-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 0.42rem 0.85rem; border-radius: var(--radius-sm);
            font-size: 0.78rem; font-weight: 700;
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
            font-size: 0.78rem; color: var(--gray-600);
            display: flex; align-items: flex-start;
            gap: 6px; margin: 0.35rem 0;
        }
        .em-map-loc i { color: var(--crimson); margin-top: 3px; }

        .em-ago {
            font-size: 0.72rem; color: var(--gray-400);
            font-weight: 600; white-space: nowrap;
        }

        .tracker-empty {
            text-align: center; padding: 2.5rem 1rem;
            background: var(--white);
            border: 2px dashed var(--status-green);
            border-radius: var(--radius-lg);
            color: var(--status-green);
        }
        .tracker-empty i {
            font-size: 2.5rem; display: block; margin-bottom: 0.6rem;
        }
        .tracker-empty p { font-weight: 600; font-size: 0.95rem; }
        .tracker-empty small {
            color: var(--gray-500); font-size: 0.82rem;
            display: block; margin-top: 0.35rem;
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

        /* REPORT CARDS */
        .report-card {
            background: var(--white);
            padding: 1.5rem 1.75rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--forest-600);
            transition: all 0.2s ease;
        }
        .report-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
            border-left-color: var(--forest-700);
        }

        .report-card h3 {
            color: var(--navy-900);
            font-size: 1.1rem; font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex; align-items: center;
            gap: 8px; flex-wrap: wrap;
        }

        .report-desc {
            color: var(--gray-600);
            font-size: 0.9rem; line-height: 1.6;
            margin: 0.5rem 0;
        }

        .report-meta {
            display: flex; gap: 0.6rem; flex-wrap: wrap;
            margin: 0.9rem 0; align-items: center;
        }

        .badge {
            padding: 4px 12px; border-radius: var(--radius-pill);
            font-size: 0.72rem; font-weight: 700;
            text-transform: capitalize;
            display: inline-flex; align-items: center;
            gap: 5px; letter-spacing: 0.02em;
        }
        .badge i { font-size: 0.65rem; }

        .status-pending  { background: var(--amber-bg);   color: var(--amber); }
        .status-verified { background: var(--info-bg);    color: var(--info-blue); }
        .status-resolved { background: var(--status-bg);  color: var(--status-green); }
        .status-closed   { background: var(--gray-200);   color: var(--gray-600); }

        .severity-low      { background: var(--status-bg);  color: var(--status-green); }
        .severity-medium   { background: var(--yellow-bg);  color: #B8860B; }
        .severity-high     { background: var(--amber-bg);   color: var(--amber); }
        .severity-critical { background: var(--crimson-bg); color: var(--crimson); }

        .badge-category {
            background: #F3E5F5; color: #7B1FA2;
        }

        .report-date {
            color: var(--gray-500); font-size: 0.82rem;
            display: inline-flex; align-items: center; gap: 5px;
        }

        .reporter-info {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--navy-50);
            border: 1px solid var(--navy-100);
            padding: 5px 12px; border-radius: var(--radius-pill);
            font-size: 0.8rem; color: var(--navy-800); font-weight: 600;
        }
        .reporter-info i { color: var(--navy-600); }
        .reporter-info .email {
            color: var(--gray-500); font-weight: 400;
            font-size: 0.75rem; margin-left: 4px;
        }

        .report-image {
            max-width: 320px; width: 100%;
            border-radius: var(--radius-md);
            margin: 0.75rem 0;
            border: 1px solid var(--gray-200);
            display: block;
            transition: transform 0.2s ease;
        }
        .report-image:hover { transform: scale(1.02); }

        .location-line {
            color: var(--gray-600); font-size: 0.88rem;
            margin: 0.5rem 0;
            display: flex; align-items: center; gap: 6px;
        }
        .location-line i { color: var(--crimson); }

        .admin-response {
            background: var(--info-bg);
            padding: 1rem 1.15rem; border-radius: var(--radius-md);
            margin-top: 1rem;
            border-left: 4px solid var(--info-blue);
            font-size: 0.9rem;
        }
        .admin-response strong {
            color: var(--info-blue);
            display: inline-flex; align-items: center;
            gap: 6px; font-size: 0.85rem;
        }
        .admin-response .response-text {
            margin-top: 0.4rem; color: var(--gray-700); line-height: 1.55;
        }
        .admin-response small {
            color: var(--gray-500); font-size: 0.78rem;
            display: block; margin-top: 0.5rem;
        }

        .card-actions {
            display: flex; gap: 0.6rem;
            margin-top: 1rem; flex-wrap: wrap;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 0.55rem 1.2rem;
            background: var(--forest-700); color: var(--white);
            text-decoration: none; border-radius: var(--radius-sm);
            font-weight: 600; font-size: 0.85rem;
            transition: all 0.15s ease;
            border: none; cursor: pointer;
            font-family: inherit;
        }
        .btn:hover {
            background: var(--forest-800);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            color: var(--white);
        }
        .btn-primary { background: var(--navy-700); }
        .btn-primary:hover { background: var(--navy-800); }

        /* EMPTY STATE */
        .no-reports {
            text-align: center; padding: 3.5rem 2rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .no-reports .empty-icon {
            font-size: 3.5rem; color: var(--forest-600);
            margin-bottom: 1rem; display: block;
        }
        .no-reports h2 {
            color: var(--navy-900); font-size: 1.3rem;
            font-weight: 700; margin-bottom: 0.5rem;
        }
        .no-reports p {
            color: var(--gray-500); font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        /* DIVIDER */
        .section-divider {
            display: flex; align-items: center;
            gap: 1rem; margin: 2.5rem 0 1.75rem;
        }
        .section-divider .line {
            flex: 1; height: 1px;
            background: var(--gray-300);
        }
        .section-divider .label {
            font-weight: 700; color: var(--navy-800);
            font-size: 0.95rem;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .section-divider .label i { color: var(--forest-700); }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body { padding-top: 76px; }
            .navbar { padding: 0.6rem 1.25rem; flex-wrap: wrap; }
            .mobile-toggle { display: block; }
            .nav-links {
                display: none; width: 100%;
                flex-direction: column; align-items: stretch;
                padding: 0.75rem 0 0.25rem;
                border-top: 1px solid var(--gray-200);
                margin-top: 0.5rem;
            }
            .nav-links.active { display: flex; }
            .nav-links a { padding: 0.7rem 1rem; }
            .logo { font-size: 1.2rem; gap: 10px; }
            .logo-circle { width: 48px; height: 48px; }
            .logo-text em { display: none; }
            .container { padding: 1.5rem 1.25rem 2.5rem; }
            .page-header { padding: 1.75rem 1.25rem; }
            .report-card { padding: 1.15rem 1.15rem; }
            .report-image { max-width: 100%; }
            #emergencyMap { height: 320px; }
        }

        :focus-visible { outline: 2px solid var(--navy-600); outline-offset: 2px; }
    </style>
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="navbar">
    <a href="dashboard.php" class="logo">
        <div class="logo-circle">
            <img src="logo.png" alt="Logo"
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-leaf\' style=\'color:var(--forest-700);font-size:1.5rem;\'></i>';">
        </div>
        <div class="logo-text">Smart<em>EcoTrack</em></div>
    </a>

    <button class="mobile-toggle" onclick="toggleMenu()" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links" id="navbarNav">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>

        <?php if (!$is_admin): ?>
            <a href="report_incident.php" class="btn-report">
                <i class="fas fa-plus-circle"></i> Report Incident
            </a>
        <?php endif; ?>

        <a href="my_reports.php" class="active">
            <i class="fas fa-list-check"></i> <?php echo $is_admin ? 'All Reports' : 'My Reports'; ?>
        </a>

        <a href="modules/disaster_tips.php"><i class="fas fa-shield-alt"></i> Tips</a>
        <a href="modules/emergency_contacts.php"><i class="fas fa-phone-alt"></i> Contacts</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- ====== MAIN ====== -->
<div class="container">

    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-<?php echo $is_admin ? 'clipboard-list' : 'file-lines'; ?>"></i>
            <?php echo $is_admin ? 'All Incident Reports' : 'My Reports'; ?>
        </h1>
        <p>
            <?php echo $is_admin
                ? 'Complete list of all reported incidents across the system'
                : 'Track the status of your submitted incidents'; ?>
        </p>
        <div class="count-badge">
            <i class="fas fa-database"></i>
            <?php echo $report_count; ?> report<?php echo $report_count !== 1 ? 's' : ''; ?>
        </div>
    </div>

    <!-- ============================================================
         ✅ EMERGENCY TRACKER — ADMIN + STAFF ONLY
         ============================================================ -->
    <?php if ($can_track): ?>
    <div class="tracker-header">
        <div class="tracker-title">
            <i class="fas fa-satellite-dish"></i>
            <span>
                Live Emergency Tracker
                <div style="font-weight:400;font-size:0.82rem;opacity:0.9;margin-top:2px;">
                    Auto-tracked locations of citizens in critical / high emergencies
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

    <!-- Divider -->
    <div class="section-divider">
        <div class="line"></div>
        <div class="label">
            <i class="fas fa-list-ul"></i>
            <?php echo $is_admin ? 'All Reports' : 'My Reports'; ?> Below
        </div>
        <div class="line"></div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         REPORTS LIST
         ============================================================ -->
    <?php if ($report_count > 0): ?>
        <?php while ($report = mysqli_fetch_assoc($reports)): ?>
            <div class="report-card">

                <h3>
                    <?php
                    $icons = [
                        'flooding' => '🌊', 'landslide' => '⛰️', 'deforestation' => '🌳',
                        'pollution' => '🏭', 'wildfire' => '🔥', 'wildlife' => '🦅',
                        'earthquake' => '🌍', 'typhoon' => '🌀'
                    ];
                    $catIcon = $icons[$report['category']] ?? '📋';
                    echo $catIcon;
                    ?>
                    <?php echo htmlspecialchars($report['title']); ?>
                </h3>

                <?php if (!empty($report['description'])): ?>
                    <p class="report-desc">
                        <?php echo nl2br(htmlspecialchars(mb_substr($report['description'], 0, 220))); ?><?php echo mb_strlen($report['description']) > 220 ? '…' : ''; ?>
                    </p>
                <?php endif; ?>

                <div class="report-meta">
                    <span class="badge status-<?php echo htmlspecialchars($report['status']); ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst(htmlspecialchars($report['status'])); ?>
                    </span>
                    <span class="badge severity-<?php echo htmlspecialchars($report['severity']); ?>">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo ucfirst(htmlspecialchars($report['severity'])); ?>
                    </span>
                    <span class="badge badge-category">
                        <i class="fas fa-tag"></i> <?php echo ucfirst(htmlspecialchars($report['category'])); ?>
                    </span>
                    <span class="report-date">
                        <i class="fas fa-calendar"></i>
                        <?php echo date('M d, Y · h:i A', strtotime($report['created_at'])); ?>
                    </span>

                    <?php if ($can_track): ?>
                        <span class="reporter-info">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($report['firstname'] . ' ' . $report['lastname']); ?>
                            <span class="email">(<?php echo htmlspecialchars($report['email']); ?>)</span>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($report['image_path'])): ?>
                    <img src="uploads/reports/<?php echo htmlspecialchars($report['image_path']); ?>"
                         class="report-image"
                         alt="Incident photo"
                         onerror="this.style.display='none';">
                <?php endif; ?>

                <?php
                $has_coords = !empty($report['latitude']) && !empty($report['longitude']);
                if (!empty($report['location_name']) || $has_coords):
                ?>
                    <p class="location-line">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php
                        if (!empty($report['location_name'])) {
                            echo htmlspecialchars($report['location_name']);
                        } else {
                            echo htmlspecialchars($report['latitude'] . ', ' . $report['longitude']);
                        }
                        ?>
                        <?php if ($has_coords): ?>
                            <button onclick="openLocationOnMap(<?php echo (float)$report['latitude']; ?>, <?php echo (float)$report['longitude']; ?>);"
                                    style="background:none;border:none;color:var(--forest-700);font-weight:600;cursor:pointer;font-size:0.8rem;margin-left:6px;">
                                <i class="fas fa-map"></i> View on Map
                            </button>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($report['admin_notes'])): ?>
                    <div class="admin-response">
                        <strong><i class="fas fa-reply"></i> Staff Response</strong>
                        <div class="response-text">
                            <?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?>
                        </div>
                        <?php if (!empty($report['resolved_at'])): ?>
                            <small>
                                <i class="fas fa-check-circle"></i>
                                Resolved: <?php echo date('M d, Y', strtotime($report['resolved_at'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card-actions">
                    <a href="incident_view.php?id=<?php echo (int)$report['id']; ?>" class="btn">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>

            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-reports">
            <i class="fas fa-clipboard-list empty-icon"></i>
            <h2><?php echo $is_admin ? 'No Reports in the System' : 'No Reports Yet'; ?></h2>
            <p>
                <?php echo $is_admin
                    ? 'No incident reports have been submitted by any user yet.'
                    : "You haven't submitted any incident reports yet."; ?>
            </p>
            <?php if (!$is_admin): ?>
                <a href="report_incident.php" class="btn">
                    <i class="fas fa-plus"></i> Submit Your First Report
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ====== SCRIPT ====== -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    /* ——— Mobile menu ——— */
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

    /* ——— HTML escape ——— */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    <?php if ($can_track): ?>
    /* ============================================================
       EMERGENCY TRACKER
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
            emMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
        }
    }

    function focusEmergency(lat, lng, id) {
        if (!emMap) return;
        emMap.setView([lat, lng], 16, { animate: true });
        const marker = emMarkers[id];
        if (marker) setTimeout(() => marker.openPopup(), 400);
    }

    window.addEventListener('load', () => setTimeout(initEmergencyMap, 400));

    /* ——— Live refresh every 15s ——— */
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
    <?php endif; ?>

    /* ——— Open location on map (for report cards) ——— */
    function openLocationOnMap(lat, lng) {
        <?php if ($can_track): ?>
        if (emMap) {
            emMap.setView([lat, lng], 16, { animate: true });
            // Scroll to the map
            document.getElementById('emergencyMap').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        } else {
            alert('Location: ' + lat + ', ' + lng);
        }
        <?php else: ?>
        // Non-staff users: open in Google Maps
        window.open('https://www.google.com/maps?q=' + lat + ',' + lng, '_blank');
        <?php endif; ?>
    }
</script>
</body>
</html>