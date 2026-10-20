<?php
/* ============================================================
   FILE: incident_view.php
   Purpose: Detailed view of an incident with status actions
   ============================================================ */

require_once 'db_connect.php';

/* Optional helper — if it doesn't exist, we don't break */
if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

/* ---------- Auth ---------- */
if (!isLoggedIn()) redirect('login.php');

$user_id = (int)$_SESSION['user_id'];

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
if (!$user) { session_destroy(); redirect('login.php'); }

$role         = strtolower(trim($user['role'] ?? 'user'));
$is_admin     = ($role === 'admin');
$is_staff     = ($role === 'staff');
$can_manage   = ($is_admin || $is_staff);

/* ---------- Ensure CSRF token exists (used by status forms) ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Get incident ID ---------- */
$incident_id = (int)($_GET['id'] ?? 0);
if ($incident_id <= 0) {
    $_SESSION['error'] = 'Invalid incident ID.';
    redirect('dashboard.php');
}

/* ---------- Load incident (with fallback if helper missing) ---------- */
$incident = null;
if (function_exists('getIncidentById')) {
    $incident = getIncidentById($incident_id);
}

if (!$incident) {
    // Fallback: direct prepared query — safe join to users
    $stmt = mysqli_prepare($conn,
        "SELECT i.*,
                u.firstname, u.lastname, u.email, u.phone, u.address
         FROM incidents i
         JOIN users u ON i.user_id = u.id
         WHERE i.id = ?
         LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $incident_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $incident = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}

if (!$incident) {
    $_SESSION['error'] = 'Incident not found.';
    redirect('dashboard.php');
}

/* ---------- Access control: managers see all; owners see their own ---------- */
$ownsIt = ((int)$incident['user_id'] === $user_id);
if (!$can_manage && !$ownsIt) {
    http_response_code(403);
    exit('Forbidden — you do not have permission to view this incident.');
}

/* ---------- Load activity log if table exists ---------- */
$activity = [];
$actCheck = mysqli_query($conn, "SHOW TABLES LIKE 'incident_activity'");
if ($actCheck && mysqli_num_rows($actCheck) > 0) {
    $actStmt = mysqli_prepare($conn,
        "SELECT a.*, u.firstname, u.lastname
         FROM incident_activity a
         JOIN users u ON a.user_id = u.id
         WHERE a.incident_id = ?
         ORDER BY a.created_at DESC
         LIMIT 30");
    if ($actStmt) {
        mysqli_stmt_bind_param($actStmt, 'i', $incident_id);
        mysqli_stmt_execute($actStmt);
        $actRes = mysqli_stmt_get_result($actStmt);
        if ($actRes) {
            while ($a = mysqli_fetch_assoc($actRes)) $activity[] = $a;
        }
        mysqli_stmt_close($actStmt);
    }
}

/* ---------- Helpers (in case functions.php doesn't exist) ---------- */
if (!function_exists('badge_class')) {
    function badge_class(string $value): string {
        $map = [
            'pending'  => 'badge-pending',
            'verified' => 'badge-verified',
            'resolved' => 'badge-resolved',
            'closed'   => 'badge-closed',
            'critical' => 'badge-critical',
            'high'     => 'badge-high',
            'medium'   => 'badge-medium',
            'low'      => 'badge-low',
        ];
        return $map[strtolower($value)] ?? 'badge-closed';
    }
}
if (!function_exists('category_icon')) {
    function category_icon(string $cat): string {
        $icons = [
            'flooding'      => '🌊', 'landslide'  => '⛰️',
            'deforestation' => '🌳', 'pollution'  => '🏭',
            'wildfire'      => '🔥', 'wildlife'   => '🦅',
            'earthquake'    => '🌍', 'typhoon'    => '🌀',
            'volcano'       => '🌋', 'tsunami'    => '🌊',
            'fire'          => '🔥', 'drought'    => '☀️',
            'other'         => '📋',
        ];
        return $icons[strtolower($cat)] ?? '📋';
    }
}
if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string {
        if (!$datetime) return '—';
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . ' min ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', strtotime($datetime));
    }
}

$hasCoords = (!empty($incident['latitude']) && !empty($incident['longitude']));
$catIcon   = category_icon($incident['category'] ?? 'other');
$statusLc  = strtolower($incident['status'] ?? 'pending');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($incident['title']) ?> · SmartEcoTrack</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
    --navy-900:#0A1931;--navy-800:#112A4A;--navy-700:#1A3A63;--navy-600:#234B7C;
    --navy-500:#2E5F96;--navy-300:#7BA4CE;--navy-100:#E1EAF4;--navy-50:#F2F6FB;
    --forest-900:#1B4332;--forest-800:#2D6A4F;--forest-700:#40916C;--forest-600:#52B788;
    --forest-400:#95D5B2;--forest-100:#EDF7F0;
    --crimson:#C62828;--crimson-bg:#FDECEA;
    --amber:#E65100;--amber-bg:#FFF3E0;
    --yellow:#F9A825;--yellow-bg:#FFFDE7;
    --status-green:#2E7D32;--status-bg:#E8F5E9;
    --info-blue:#1565C0;--info-bg:#E3F2FD;
    --white:#FFFFFF;--gray-50:#FAFBFC;--gray-100:#F4F6F8;--gray-200:#E4E8EC;
    --gray-300:#CFD6DC;--gray-400:#9AA5AF;--gray-500:#6B7680;--gray-600:#4A535C;--gray-800:#1C2229;
    --shadow-sm:0 1px 3px rgba(10,25,49,0.06);
    --shadow-md:0 4px 12px rgba(10,25,49,0.08);
    --shadow-lg:0 12px 32px rgba(10,25,49,0.10);
    --radius-sm:6px;--radius-md:10px;--radius-lg:16px;--radius-pill:999px;
    --gradient-header:linear-gradient(135deg,var(--navy-800),var(--navy-600));
}
body{font-family:'Inter',system-ui,sans-serif;background:var(--gray-100);color:var(--gray-800);
     line-height:1.6;padding-top:88px;-webkit-font-smoothing:antialiased;}

/* NAVBAR */
.navbar{position:fixed;top:0;left:0;width:100%;padding:0.75rem 5vw;
        display:flex;justify-content:space-between;align-items:center;z-index:999;
        background:var(--white);border-bottom:1px solid var(--gray-200);box-shadow:var(--shadow-sm);}
.logo{display:flex;align-items:center;gap:14px;text-decoration:none;color:var(--navy-800);
      font-size:1.5rem;font-weight:700;letter-spacing:-0.02em;flex-shrink:0;}
.logo-circle{width:56px;height:56px;border-radius:50%;overflow:hidden;display:flex;
             align-items:center;justify-content:center;background:var(--navy-50);
             box-shadow:0 0 0 2.5px var(--navy-500),0 4px 12px rgba(10,25,49,0.15);flex-shrink:0;}
.logo-circle img{width:100%;height:100%;object-fit:cover;}
.logo-text em{font-style:normal;font-weight:400;color:var(--gray-500);}
.nav-links{display:flex;gap:0.35rem;align-items:center;flex-wrap:wrap;}
.nav-links a{color:var(--gray-600);text-decoration:none;padding:0.5rem 0.95rem;
             border-radius:var(--radius-sm);font-weight:500;font-size:0.88rem;
             transition:all 0.15s;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.nav-links a:hover{background:var(--navy-50);color:var(--navy-800);}
.nav-links a.logout{color:var(--crimson);}
.nav-links a.logout:hover{background:var(--crimson-bg);}
.mobile-toggle{display:none;background:none;border:none;color:var(--navy-800);
               font-size:1.4rem;cursor:pointer;padding:0.4rem;}

.container{max-width:1200px;margin:0 auto;padding:2rem 5vw 3rem;}

.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--navy-600);
           text-decoration:none;font-weight:600;font-size:0.88rem;margin-bottom:1rem;}
.back-link:hover{color:var(--navy-800);}

/* LAYOUT */
.detail-layout{display:grid;grid-template-columns:1.55fr 1fr;gap:1.5rem;}
@media (max-width:1000px){ .detail-layout{grid-template-columns:1fr;} }

/* CARDS */
.card{background:var(--white);border:1px solid var(--gray-200);
      border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);
      padding:1.75rem;margin-bottom:1.5rem;}
.card-header-bar{display:flex;justify-content:space-between;align-items:flex-start;
                 flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;}
.card-header-bar h2{font-size:1.3rem;font-weight:700;color:var(--navy-900);
                    letter-spacing:-0.02em;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.card-header-bar .cat-icon{font-size:1.5rem;}
.card-header-bar h3{font-size:1rem;font-weight:700;color:var(--navy-900);
                    display:flex;align-items:center;gap:8px;margin-bottom:1rem;}
.card-header-bar h3 i{color:var(--forest-700);}

/* BADGES */
.badge{display:inline-block;padding:4px 12px;border-radius:var(--radius-pill);
       font-size:0.72rem;font-weight:700;text-transform:capitalize;letter-spacing:0.02em;}
.badge-pending{background:var(--amber-bg);color:var(--amber);}
.badge-verified{background:var(--info-bg);color:var(--info-blue);}
.badge-resolved{background:var(--status-bg);color:var(--status-green);}
.badge-closed{background:var(--gray-200);color:var(--gray-600);}
.badge-critical{background:var(--crimson-bg);color:var(--crimson);}
.badge-high{background:var(--amber-bg);color:var(--amber);}
.badge-medium{background:var(--yellow-bg);color:#B8860B;}
.badge-low{background:var(--status-bg);color:var(--status-green);}
.badge-row{display:flex;gap:6px;flex-wrap:wrap;}

/* META GRID */
.meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
           gap:1rem;margin-top:1.25rem;}
.meta-item{padding:0.85rem 1rem;background:var(--gray-50);
           border-radius:var(--radius-md);border-left:3px solid var(--forest-600);}
.meta-item .label{font-size:0.7rem;font-weight:700;color:var(--gray-500);
                  text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;}
.meta-item .value{font-size:0.92rem;color:var(--navy-900);font-weight:600;
                  word-break:break-word;}
.meta-item .value small{display:block;color:var(--gray-500);
                        font-weight:400;font-size:0.8rem;margin-top:2px;}

/* IMAGE */
.incident-image{width:100%;max-height:440px;object-fit:cover;
                border-radius:var(--radius-md);margin-bottom:1.25rem;
                border:1px solid var(--gray-200);}

/* DESCRIPTION */
.description{background:var(--gray-50);padding:1.1rem 1.2rem;
             border-radius:var(--radius-md);font-size:0.95rem;color:var(--gray-800);
             line-height:1.7;white-space:pre-wrap;margin-bottom:1.25rem;}

/* MAP */
#incidentMap{height:300px;border-radius:var(--radius-md);
             border:1px solid var(--gray-200);background:#eef2f6;}

/* ACTIONS */
.actions-bar{display:flex;flex-wrap:wrap;gap:0.6rem;padding:1.25rem 1.5rem;
             background:var(--navy-50);border:1px solid var(--navy-100);
             border-radius:var(--radius-lg);align-items:center;margin-bottom:1.5rem;}
.actions-bar .title{font-weight:700;color:var(--navy-800);font-size:0.88rem;
                    margin-right:auto;display:flex;align-items:center;gap:8px;}
.actions-bar form{display:inline;}

.btn{display:inline-flex;align-items:center;gap:6px;padding:0.6rem 1.1rem;
     border-radius:var(--radius-sm);font-family:inherit;font-size:0.85rem;
     font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.15s;}
.btn-primary{background:var(--navy-700);color:var(--white);}
.btn-primary:hover{background:var(--navy-800);}
.btn-verify{background:var(--info-blue);color:var(--white);}
.btn-verify:hover{background:#0D47A1;}
.btn-resolve{background:var(--status-green);color:var(--white);}
.btn-resolve:hover{background:#1B5E20;}
.btn-close{background:var(--gray-600);color:var(--white);}
.btn-close:hover{background:var(--gray-800);}
.btn-ghost{background:var(--white);color:var(--gray-600);border:1px solid var(--gray-300);}
.btn-ghost:hover{background:var(--gray-100);}
.btn:disabled{opacity:0.5;cursor:not-allowed;pointer-events:none;}

/* REPORTER CARD */
.reporter-avatar{width:56px;height:56px;border-radius:50%;
                 background:var(--gradient-header);color:var(--white);
                 display:flex;align-items:center;justify-content:center;
                 font-weight:700;font-size:1.1rem;flex-shrink:0;
                 box-shadow:0 4px 12px rgba(10,25,49,0.15);}
.reporter-row{display:flex;align-items:center;gap:1rem;margin-bottom:1rem;}
.reporter-name{font-weight:700;color:var(--navy-900);font-size:1rem;}
.reporter-meta{font-size:0.82rem;color:var(--gray-500);}
.reporter-detail{display:flex;align-items:center;gap:10px;padding:0.5rem 0;
                 font-size:0.88rem;color:var(--gray-600);border-bottom:1px solid var(--gray-200);}
.reporter-detail:last-child{border-bottom:none;}
.reporter-detail i{width:16px;color:var(--forest-700);}
.reporter-detail strong{color:var(--navy-900);font-weight:600;}

/* TIMELINE */
.timeline{display:flex;flex-direction:column;}
.timeline-item{display:flex;gap:0.9rem;padding-bottom:1.1rem;position:relative;}
.timeline-item:last-child{padding-bottom:0;}
.timeline-item:not(:last-child)::before{content:'';position:absolute;
    left:15px;top:32px;bottom:0;width:2px;background:var(--gray-200);}
.timeline-dot{width:32px;height:32px;border-radius:50%;display:flex;
              align-items:center;justify-content:center;font-size:0.75rem;
              flex-shrink:0;z-index:1;color:var(--white);background:var(--navy-700);
              box-shadow:0 2px 6px rgba(10,25,49,0.15);}
.timeline-dot.status_change{background:var(--info-blue);}
.timeline-dot.note_added{background:var(--forest-700);}
.timeline-content{flex:1;font-size:0.88rem;padding-top:2px;}
.timeline-content strong{color:var(--navy-900);}
.timeline-content .change{display:inline-block;padding:1px 8px;
    border-radius:6px;background:var(--gray-100);
    font-family:ui-monospace,Consolas,monospace;font-size:0.75rem;margin:0 4px;}
.timeline-content .when{font-size:0.75rem;color:var(--gray-500);margin-top:3px;}
.timeline-content .note{margin-top:6px;padding:0.6rem 0.9rem;
    background:var(--gray-50);border-radius:var(--radius-sm);font-size:0.85rem;
    color:var(--gray-800);border-left:3px solid var(--forest-600);white-space:pre-wrap;}

/* RESOLUTION NOTE */
.resolution-note{background:var(--status-bg);border-left:4px solid var(--status-green);
                 padding:1rem 1.2rem;border-radius:var(--radius-md);margin-bottom:1rem;}
.resolution-note .label{font-size:0.72rem;font-weight:700;color:var(--status-green);
                        text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;
                        display:flex;align-items:center;gap:6px;}
.resolution-note p{font-size:0.9rem;color:var(--gray-800);
                   white-space:pre-wrap;line-height:1.65;}

/* FLASH */
.flash{padding:0.85rem 1.1rem;border-radius:var(--radius-md);margin-bottom:1.25rem;
       display:flex;align-items:center;gap:10px;font-size:0.9rem;}
.flash-success{background:var(--status-bg);color:var(--status-green);border-left:4px solid var(--status-green);}
.flash-error{background:var(--crimson-bg);color:var(--crimson);border-left:4px solid var(--crimson);}

/* EMPTY */
.empty-state{text-align:center;padding:2rem 1rem;color:var(--gray-500);}
.empty-state i{font-size:2rem;color:var(--gray-300);display:block;margin-bottom:0.5rem;}

/* FOOTER */
footer{background:var(--navy-900);color:rgba(255,255,255,0.75);text-align:center;
       padding:1.75rem 5vw;border-top:3px solid var(--forest-700);margin-top:2rem;}
footer p{font-size:0.88rem;}
footer p.sub{opacity:0.6;font-size:0.8rem;margin-top:0.3rem;}

/* RESPONSIVE */
@media (max-width:768px){
    body{padding-top:76px;}
    .navbar{padding:0.6rem 1.25rem;flex-wrap:wrap;}
    .mobile-toggle{display:block;}
    .nav-links{display:none;width:100%;flex-direction:column;align-items:stretch;
               padding:0.75rem 0 0.25rem;border-top:1px solid var(--gray-200);margin-top:0.5rem;}
    .nav-links.active{display:flex;}
    .logo{font-size:1.2rem;gap:10px;}
    .logo-circle{width:48px;height:48px;}
    .logo-text em{display:none;}
    .container{padding:1.5rem 1.25rem 2.5rem;}
    .card{padding:1.25rem;}
    .actions-bar{flex-direction:column;align-items:stretch;}
    .actions-bar .title{margin-right:0;}
    .actions-bar .btn, .actions-bar form, .actions-bar form .btn{width:100%;justify-content:center;}
    #incidentMap{height:240px;}
}
:focus-visible{outline:2px solid var(--navy-600);outline-offset:2px;}
</style>
</head>
<body>

<!-- NAVBAR -->
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
        <?php if ($can_manage): ?>
            <a href="manage_incidents.php"><i class="fas fa-clipboard-check"></i> Manage Reports</a>
        <?php else: ?>
            <a href="my_reports.php"><i class="fas fa-list-check"></i> My Reports</a>
        <?php endif; ?>
        <a href="report_incident.php"><i class="fas fa-plus-circle"></i> Report</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="container">

    <a href="<?= $can_manage ? 'manage_incidents.php' : 'my_reports.php' ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to <?= $can_manage ? 'Reports' : 'My Reports' ?>
    </a>

    <?php if (isset($_GET['updated'])): ?>
        <div class="flash flash-success">
            <i class="fas fa-check-circle"></i>
            <span>Incident updated successfully.</span>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="flash flash-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($_GET['error']) ?></span>
        </div>
    <?php endif; ?>

    <div class="detail-layout">

        <!-- ============================================================
             LEFT COLUMN — MAIN DETAILS
             ============================================================ -->
        <div>
            <div class="card">
                <div class="card-header-bar">
                    <h2>
                        <span class="cat-icon"><?= $catIcon ?></span>
                        <?= htmlspecialchars($incident['title']) ?>
                    </h2>
                    <div class="badge-row">
                        <span class="badge <?= badge_class($incident['status']) ?>">
                            <?= htmlspecialchars($incident['status']) ?>
                        </span>
                        <span class="badge <?= badge_class($incident['severity']) ?>">
                            <?= htmlspecialchars($incident['severity']) ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($incident['image_path']) && file_exists('uploads/reports/' . $incident['image_path'])): ?>
                    <img src="uploads/reports/<?= htmlspecialchars($incident['image_path']) ?>"
                         alt="<?= htmlspecialchars($incident['title']) ?>"
                         class="incident-image"
                         onerror="this.style.display='none';">
                <?php endif; ?>

                <h3 style="font-size:0.95rem;font-weight:700;color:var(--navy-900);
                           margin-bottom:0.6rem;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-align-left" style="color:var(--forest-700);"></i> Description
                </h3>
                <div class="description"><?= nl2br(htmlspecialchars($incident['description'])) ?></div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <div class="label">Category</div>
                        <div class="value"><?= $catIcon ?> <?= ucfirst(htmlspecialchars($incident['category'])) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="label">Severity</div>
                        <div class="value" style="text-transform:capitalize;">
                            <?= htmlspecialchars($incident['severity']) ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="label">Reported</div>
                        <div class="value">
                            <?= date('M d, Y · h:i A', strtotime($incident['created_at'])) ?>
                            <small><?= time_ago($incident['created_at']) ?></small>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="label">Location</div>
                        <div class="value">
                            <?= htmlspecialchars($incident['location_name'] ?: 'Not specified') ?>
                            <?php if ($hasCoords): ?>
                                <small><?= number_format((float)$incident['latitude'], 5) ?>, <?= number_format((float)$incident['longitude'], 5) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($hasCoords): ?>
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;color:var(--navy-900);
                           margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-map-marker-alt" style="color:var(--forest-700);"></i> Location Map
                </h3>
                <div id="incidentMap"></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($incident['resolution_note'])): ?>
            <div class="card">
                <div class="resolution-note">
                    <div class="label">
                        <i class="fas fa-check-circle"></i> Resolution Note
                    </div>
                    <p><?= nl2br(htmlspecialchars($incident['resolution_note'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================
             RIGHT COLUMN — SIDEBAR
             ============================================================ -->
        <div>

            <!-- Staff/Admin actions -->
            <?php if ($can_manage): ?>
            <div class="actions-bar">
                <span class="title"><i class="fas fa-tools"></i> Manage Status</span>

                <?php if ($statusLc === 'pending'): ?>
                    <form method="POST" action="update_incident.php"
                          onsubmit="return confirm('Mark as VERIFIED?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="status" value="verified">
                        <button type="submit" class="btn btn-verify">
                            <i class="fas fa-check"></i> Verify
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($statusLc, ['pending','verified'], true)): ?>
                    <form method="POST" action="update_incident.php"
                          onsubmit="return confirm('Mark as RESOLVED?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit" class="btn btn-resolve">
                            <i class="fas fa-circle-check"></i> Resolve
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($statusLc !== 'closed'): ?>
                    <form method="POST" action="update_incident.php"
                          onsubmit="return confirm('Close this incident?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="status" value="closed">
                        <button type="submit" class="btn btn-close">
                            <i class="fas fa-xmark"></i> Close
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Reporter -->
            <div class="card">
                <h3><i class="fas fa-user"></i> Reporter</h3>
                <div class="reporter-row">
                    <div class="reporter-avatar">
                        <?= strtoupper(substr($incident['firstname'] ?? '?', 0, 1) . substr($incident['lastname'] ?? '?', 0, 1)) ?>
                    </div>
                    <div>
                        <div class="reporter-name">
                            <?= htmlspecialchars(($incident['firstname'] ?? '') . ' ' . ($incident['lastname'] ?? '')) ?>
                        </div>
                        <div class="reporter-meta">Citizen reporter</div>
                    </div>
                </div>
                <?php if (!empty($incident['email'])): ?>
                    <div class="reporter-detail">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($incident['email']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($incident['phone'])): ?>
                    <div class="reporter-detail">
                        <i class="fas fa-phone"></i>
                        <strong><?= htmlspecialchars($incident['phone']) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($incident['address'])): ?>
                    <div class="reporter-detail">
                        <i class="fas fa-home"></i>
                        <span><?= htmlspecialchars($incident['address']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity Timeline -->
            <?php if (!empty($activity)): ?>
            <div class="card">
                <h3><i class="fas fa-history"></i> Activity Timeline</h3>
                <div class="timeline">
                    <?php foreach ($activity as $act):
                        $actionType = strtolower($act['action'] ?? 'note');
                        $who = trim(($act['firstname'] ?? '') . ' ' . ($act['lastname'] ?? ''));
                        if ($who === '') $who = 'System';
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-dot <?= htmlspecialchars($actionType) ?>">
                                <?php if ($actionType === 'status_change'): ?>
                                    <i class="fas fa-arrows-rotate"></i>
                                <?php elseif ($actionType === 'note_added'): ?>
                                    <i class="fas fa-note-sticky"></i>
                                <?php else: ?>
                                    <i class="fas fa-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <strong><?= htmlspecialchars($who) ?></strong>

                                <?php if ($actionType === 'status_change'): ?>
                                    changed status
                                    <?php if (!empty($act['old_value'])): ?>
                                        <span class="change"><?= htmlspecialchars($act['old_value']) ?></span>
                                        →
                                    <?php endif; ?>
                                    <?php if (!empty($act['new_value'])): ?>
                                        <span class="change"><?= htmlspecialchars($act['new_value']) ?></span>
                                    <?php endif; ?>

                                <?php elseif ($actionType === 'note_added'): ?>
                                    added a note
                                    <?php if (!empty($act['note'])): ?>
                                        <div class="note"><?= nl2br(htmlspecialchars($act['note'])) ?></div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <?= htmlspecialchars($act['description'] ?? 'performed an action') ?>
                                    <?php if (!empty($act['note'])): ?>
                                        <div class="note"><?= nl2br(htmlspecialchars($act['note'])) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="when"><?= time_ago($act['created_at'] ?? null) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> SmartEcoTrack · Environmental Incident Reporting & Monitoring System</p>
    <p class="sub">Working together for a safer, greener Philippines 🌿</p>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    /* Mobile menu */
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

    <?php if ($hasCoords): ?>
    /* Incident map */
    (function initIncidentMap() {
        const el = document.getElementById('incidentMap');
        if (!el || typeof L === 'undefined') return;

        const lat = <?= json_encode((float)$incident['latitude']) ?>;
        const lng = <?= json_encode((float)$incident['longitude']) ?>;
        const title = <?= json_encode($incident['title']) ?>;

        const map = L.map('incidentMap').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        const marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup(
            '<div style="font-family:Inter,sans-serif;font-weight:600;color:#0A1931;">' +
            title.replace(/[<>&"]/g, function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];}) +
            '</div>'
        ).openPopup();
    })();
    <?php endif; ?>
</script>
</body>
</html>