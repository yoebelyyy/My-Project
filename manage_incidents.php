<?php
/* ============================================================
   FILE: manage_incidents.php
   Purpose: Staff & Admin — manage all citizen incident reports
   ============================================================ */

require_once 'incident_helpers.php';

$user = current_user($conn);
require_manager($user);                 // 403 for plain users

$is_admin = is_admin($user);
$uid      = (int)$user['id'];

/* ---------------- Filters ---------------- */
$status   = trim($_GET['status']   ?? '');
$severity = trim($_GET['severity'] ?? '');
$category = trim($_GET['category'] ?? '');
$q        = trim($_GET['q']        ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$validStatuses   = ['pending','verified','resolved','closed'];
$validSeverities = ['low','medium','high','critical'];

$where  = [];
$params = [];
$types  = '';

if ($status !== '' && in_array($status, $validStatuses, true)) {
    $where[] = "i.status = ?"; $params[] = $status; $types .= 's';
}
if ($severity !== '' && in_array($severity, $validSeverities, true)) {
    $where[] = "i.severity = ?"; $params[] = $severity; $types .= 's';
}
if ($category !== '') {
    $where[] = "i.category = ?"; $params[] = $category; $types .= 's';
}
if ($q !== '') {
    $where[] = "(i.title LIKE ? OR i.description LIKE ? OR i.location_name LIKE ?
                 OR u.firstname LIKE ? OR u.lastname LIKE ?)";
    $like = '%' . $q . '%';
    for ($i = 0; $i < 5; $i++) { $params[] = $like; $types .= 's'; }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ---------------- Count total ---------------- */
$countSql  = "SELECT COUNT(*) AS c
              FROM incidents i
              JOIN users u ON i.user_id = u.id
              $whereSql";
$countStmt = mysqli_prepare($conn, $countSql);
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalRows  = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ---------------- Fetch page ---------------- */
$sql = "SELECT i.*,
               u.firstname, u.lastname, u.email, u.phone,
               a.firstname AS updater_first, a.lastname AS updater_last
        FROM incidents i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN users a ON i.updated_by = a.id
        $whereSql
        ORDER BY
            FIELD(i.status,'pending','verified','resolved','closed'),
            FIELD(i.severity,'critical','high','medium','low'),
            i.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
$bindTypes  = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$incidents = [];
while ($row = mysqli_fetch_assoc($result)) $incidents[] = $row;

/* ---------------- KPIs ---------------- */
$kpis = ['pending'=>0,'verified'=>0,'resolved'=>0,'critical'=>0];
$kpiRes = mysqli_query($conn,
    "SELECT SUM(status='pending')  AS pending,
            SUM(status='verified') AS verified,
            SUM(status='resolved') AS resolved,
            SUM(severity='critical' AND status NOT IN ('resolved','closed')) AS critical
     FROM incidents");
if ($kpiRes) {
    $row = mysqli_fetch_assoc($kpiRes);
    if ($row) $kpis = array_map(fn($v) => (int)($v ?? 0), $row);
}

/* ---------------- Categories ---------------- */
$cats = [];
$catRes = mysqli_query($conn, "SELECT DISTINCT category FROM incidents ORDER BY category");
if ($catRes) while ($c = mysqli_fetch_assoc($catRes)) $cats[] = $c['category'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Reports · SmartEcoTrack</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
.nav-links a.active{background:var(--navy-700);color:var(--white);}
.nav-links a.logout{color:var(--crimson);}
.nav-links a.logout:hover{background:var(--crimson-bg);}
.mobile-toggle{display:none;background:none;border:none;color:var(--navy-800);
               font-size:1.4rem;cursor:pointer;padding:0.4rem;}

.container{max-width:1400px;margin:0 auto;padding:2rem 5vw 3rem;}

.page-head{background:var(--gradient-header);color:var(--white);padding:2rem 2.5rem;
           border-radius:var(--radius-lg);margin-bottom:2rem;position:relative;overflow:hidden;
           box-shadow:var(--shadow-lg);}
.page-head::before{content:'';position:absolute;inset:0;
                   background:radial-gradient(circle at 85% 30%,rgba(82,183,136,0.18),transparent 55%);
                   pointer-events:none;}
.page-head > *{position:relative;z-index:1;}
.page-head h1{font-size:clamp(1.4rem,2.6vw,2rem);font-weight:700;
              letter-spacing:-0.02em;margin-bottom:0.35rem;}
.page-head p{opacity:0.9;font-size:0.95rem;}
.role-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;
           border-radius:var(--radius-pill);background:rgba(255,255,255,0.15);
           border:1px solid rgba(255,255,255,0.25);font-size:0.72rem;font-weight:700;
           text-transform:uppercase;letter-spacing:0.06em;margin-left:10px;backdrop-filter:blur(6px);}

.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
          gap:1rem;margin-bottom:1.75rem;}
.kpi{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);
     padding:1.25rem 1.35rem;box-shadow:var(--shadow-sm);border-left:4px solid var(--forest-600);
     transition:all 0.2s;}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
.kpi.pending{border-left-color:var(--amber);}
.kpi.verified{border-left-color:var(--info-blue);}
.kpi.resolved{border-left-color:var(--status-green);}
.kpi.critical{border-left-color:var(--crimson);}
.kpi .kpi-icon{font-size:1.3rem;margin-bottom:0.35rem;}
.kpi.pending .kpi-icon{color:var(--amber);}
.kpi.verified .kpi-icon{color:var(--info-blue);}
.kpi.resolved .kpi-icon{color:var(--status-green);}
.kpi.critical .kpi-icon{color:var(--crimson);}
.kpi .kpi-val{font-size:1.9rem;font-weight:800;color:var(--navy-900);
              letter-spacing:-0.03em;line-height:1.1;}
.kpi .kpi-label{color:var(--gray-500);font-size:0.82rem;font-weight:600;margin-top:0.25rem;}

.filters{background:var(--white);border:1px solid var(--gray-200);
         border-radius:var(--radius-lg);padding:1.25rem 1.5rem;
         margin-bottom:1.75rem;box-shadow:var(--shadow-sm);}
.filters form{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:0.75rem;align-items:end;}
.filters .field label{display:block;font-size:0.72rem;font-weight:700;color:var(--gray-500);
                      text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.35rem;}
.filters input,.filters select{width:100%;padding:0.65rem 0.9rem;
                                border:2px solid var(--gray-200);border-radius:var(--radius-sm);
                                font-family:inherit;font-size:0.9rem;background:var(--white);
                                color:var(--gray-800);transition:border-color 0.15s;}
.filters input:focus,.filters select:focus{outline:none;border-color:var(--navy-500);
                                            box-shadow:0 0 0 3px rgba(46,95,150,0.1);}

.btn{display:inline-flex;align-items:center;gap:6px;padding:0.65rem 1.25rem;
     border-radius:var(--radius-sm);font-family:inherit;font-size:0.88rem;font-weight:600;
     cursor:pointer;border:none;text-decoration:none;transition:all 0.15s;}
.btn-primary{background:var(--navy-700);color:var(--white);}
.btn-primary:hover{background:var(--navy-800);}
.btn-ghost{background:var(--gray-100);color:var(--gray-600);}
.btn-ghost:hover{background:var(--gray-200);}
.btn-sm{padding:0.42rem 0.8rem;font-size:0.78rem;}

.table-card{background:var(--white);border:1px solid var(--gray-200);
            border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:0.88rem;}
thead{background:var(--navy-50);border-bottom:2px solid var(--gray-200);}
thead th{text-align:left;padding:0.85rem 1rem;font-size:0.72rem;font-weight:800;
         text-transform:uppercase;letter-spacing:0.06em;color:var(--navy-800);white-space:nowrap;}
tbody tr{border-bottom:1px solid var(--gray-200);transition:background 0.12s;}
tbody tr:hover{background:var(--navy-50);}
tbody tr:last-child{border-bottom:none;}
tbody td{padding:1rem;vertical-align:top;color:var(--gray-800);}

.cell-title{font-weight:600;color:var(--navy-900);font-size:0.92rem;line-height:1.35;margin-bottom:2px;}
.cell-sub{font-size:0.78rem;color:var(--gray-500);}
.cell-cat{display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;
          color:var(--gray-600);text-transform:capitalize;}

.badge{display:inline-block;padding:3px 11px;border-radius:var(--radius-pill);
       font-size:0.7rem;font-weight:700;text-transform:capitalize;letter-spacing:0.02em;white-space:nowrap;}
.badge-pending{background:var(--amber-bg);color:var(--amber);}
.badge-verified{background:var(--info-bg);color:var(--info-blue);}
.badge-resolved{background:var(--status-bg);color:var(--status-green);}
.badge-closed{background:var(--gray-200);color:var(--gray-600);}
.badge-critical{background:var(--crimson-bg);color:var(--crimson);}
.badge-high{background:var(--amber-bg);color:var(--amber);}
.badge-medium{background:var(--yellow-bg);color:#B8860B;}
.badge-low{background:var(--status-bg);color:var(--status-green);}

.row-actions{display:flex;gap:0.4rem;flex-wrap:wrap;}
.row-actions form{display:inline;}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:0.4rem 0.75rem;
         border-radius:var(--radius-sm);font-size:0.75rem;font-weight:700;
         cursor:pointer;border:none;text-decoration:none;font-family:inherit;
         transition:all 0.15s;white-space:nowrap;}
.act-view{background:var(--navy-700);color:var(--white);}
.act-view:hover{background:var(--navy-800);}
.act-verify{background:var(--info-bg);color:var(--info-blue);}
.act-verify:hover{background:#BBDEFB;}
.act-resolve{background:var(--status-bg);color:var(--status-green);}
.act-resolve:hover{background:#C8E6C9;}
.act-close{background:var(--gray-200);color:var(--gray-600);}
.act-close:hover{background:var(--gray-300);}

.empty{text-align:center;padding:3rem 1rem;color:var(--gray-500);}
.empty i{font-size:2.5rem;color:var(--gray-300);display:block;margin-bottom:0.75rem;}
.empty p{font-size:0.95rem;font-weight:500;}

.pager{display:flex;justify-content:space-between;align-items:center;
       padding:1rem 1.5rem;border-top:1px solid var(--gray-200);
       flex-wrap:wrap;gap:0.75rem;background:var(--gray-50);}
.pager .info{font-size:0.82rem;color:var(--gray-500);}
.pager .pages{display:flex;gap:4px;flex-wrap:wrap;}
.pager .pages a,.pager .pages span{display:inline-flex;align-items:center;justify-content:center;
    min-width:34px;height:34px;padding:0 10px;border-radius:var(--radius-sm);
    font-size:0.82rem;font-weight:600;text-decoration:none;color:var(--gray-600);
    background:var(--white);border:1px solid var(--gray-200);transition:all 0.15s;}
.pager .pages a:hover{background:var(--navy-50);color:var(--navy-800);border-color:var(--navy-300);}
.pager .pages span.current{background:var(--navy-700);color:var(--white);border-color:var(--navy-700);}
.pager .pages span.dots{border:none;background:transparent;}

.flash{padding:0.85rem 1.1rem;border-radius:var(--radius-md);margin-bottom:1.25rem;
       display:flex;align-items:center;gap:10px;font-size:0.9rem;animation:fadeIn 0.35s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
.flash-success{background:var(--status-bg);color:var(--status-green);border-left:4px solid var(--status-green);}
.flash-error{background:var(--crimson-bg);color:var(--crimson);border-left:4px solid var(--crimson);}

footer{background:var(--navy-900);color:rgba(255,255,255,0.75);
       text-align:center;padding:1.75rem 5vw;border-top:3px solid var(--forest-700);margin-top:2rem;}
footer p{font-size:0.88rem;}
footer p.sub{opacity:0.6;font-size:0.8rem;margin-top:0.3rem;}

@media (max-width:900px){
    .filters form{grid-template-columns:1fr 1fr;}
    .filters form .field-full{grid-column:1 / -1;}
    .filters form .btn{grid-column:1 / -1;}
}
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
    .page-head{padding:1.5rem;}
    thead{display:none;}
    tbody tr{display:block;padding:1rem;border-bottom:1px solid var(--gray-200);}
    tbody td{display:block;padding:0.35rem 0;border:none;}
    tbody td::before{content:attr(data-label);display:block;font-size:0.68rem;
                     font-weight:800;color:var(--gray-500);text-transform:uppercase;
                     letter-spacing:0.06em;margin-bottom:2px;}
    .row-actions{margin-top:0.5rem;}
}
:focus-visible{outline:2px solid var(--navy-600);outline-offset:2px;}
</style>
</head>
<body>

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
        <a href="manage_incidents.php" class="active">
            <i class="fas fa-clipboard-check"></i> Manage Reports
        </a>
        <a href="modules/disaster_tips.php"><i class="fas fa-shield-alt"></i> Disaster Tips</a>
        <a href="modules/emergency_contacts.php"><i class="fas fa-phone-alt"></i> Emergency</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="container">

    <div class="page-head">
        <h1>
            <i class="fas fa-clipboard-check"></i>
            Manage Citizen Reports
            <span class="role-chip">
                <i class="fas fa-<?php echo $is_admin ? 'crown' : 'user-shield'; ?>"></i>
                <?php echo strtoupper(user_role($user)); ?>
            </span>
        </h1>
        <p>Review, verify, and resolve incident reports submitted by citizens.</p>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="flash flash-success"><i class="fas fa-check-circle"></i>
            <span>Incident updated successfully.</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_GET['error']); ?></span></div>
    <?php endif; ?>

    <!-- KPI strip -->
    <div class="kpi-grid">
        <div class="kpi pending">
            <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="kpi-val"><?php echo $kpis['pending']; ?></div>
            <div class="kpi-label">Pending Review</div>
        </div>
        <div class="kpi verified">
            <div class="kpi-icon"><i class="fas fa-circle-check"></i></div>
            <div class="kpi-val"><?php echo $kpis['verified']; ?></div>
            <div class="kpi-label">Verified</div>
        </div>
        <div class="kpi resolved">
            <div class="kpi-icon"><i class="fas fa-flag-checkered"></i></div>
            <div class="kpi-val"><?php echo $kpis['resolved']; ?></div>
            <div class="kpi-label">Resolved</div>
        </div>
        <div class="kpi critical">
            <div class="kpi-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="kpi-val"><?php echo $kpis['critical']; ?></div>
            <div class="kpi-label">Critical Active</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <form method="GET" action="manage_incidents.php">
            <div class="field field-full">
                <label for="q">Search</label>
                <input type="text" id="q" name="q"
                       placeholder="Title, description, location, citizen name…"
                       value="<?php echo htmlspecialchars($q); ?>">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                            <?php echo ucfirst($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="severity">Severity</label>
                <select id="severity" name="severity">
                    <option value="">All</option>
                    <?php foreach ($validSeverities as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $severity === $s ? 'selected' : ''; ?>>
                            <?php echo ucfirst($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"
                            <?php echo $category === $c ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($c)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
        <?php if ($status || $severity || $category || $q): ?>
            <div style="margin-top:0.75rem;">
                <a href="manage_incidents.php" class="btn btn-ghost btn-sm">
                    <i class="fas fa-times"></i> Clear filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="table-card">
        <?php if (empty($incidents)): ?>
            <div class="empty">
                <i class="fas fa-inbox"></i>
                <p>No incidents match your filters.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Incident</th>
                            <th>Citizen</th>
                            <th>Category</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($incidents as $inc): ?>
                        <?php $id = (int)$inc['id']; ?>
                        <tr>
                            <td data-label="Incident">
                                <div class="cell-title">
                                    <?php echo category_icon($inc['category']); ?>
                                    <?php echo htmlspecialchars($inc['title']); ?>
                                </div>
                                <?php if (!empty($inc['location_name'])): ?>
                                    <div class="cell-sub">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($inc['location_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Citizen">
                                <div class="cell-title" style="font-size:0.88rem;">
                                    <?php echo htmlspecialchars($inc['firstname'] . ' ' . $inc['lastname']); ?>
                                </div>
                                <div class="cell-sub">
                                    <?php echo htmlspecialchars($inc['email']); ?>
                                </div>
                            </td>
                            <td data-label="Category">
                                <span class="cell-cat"><?php echo htmlspecialchars($inc['category']); ?></span>
                            </td>
                            <td data-label="Severity">
                                <span class="badge <?php echo badge_class($inc['severity']); ?>">
                                    <?php echo htmlspecialchars($inc['severity']); ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="badge <?php echo badge_class($inc['status']); ?>">
                                    <?php echo htmlspecialchars($inc['status']); ?>
                                </span>
                                <?php if (!empty($inc['updater_first'])): ?>
                                    <div class="cell-sub" style="margin-top:4px;">
                                        by <?php echo htmlspecialchars($inc['updater_first'] . ' ' . $inc['updater_last']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Submitted">
                                <div class="cell-sub"><?php echo time_ago($inc['created_at']); ?></div>
                                <div class="cell-sub" style="font-size:0.72rem;">
                                    <?php echo date('M d, Y', strtotime($inc['created_at'])); ?>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="row-actions">
                                    <a href="incident_view.php?id=<?php echo $id; ?>"
                                       class="act-btn act-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>

                                    <?php if (strtolower($inc['status']) === 'pending'): ?>
                                        <form method="POST" action="update_incident.php"
                                              onsubmit="return confirm('Mark this report as VERIFIED?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="status" value="verified">
                                            <button type="submit" class="act-btn act-verify">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array(strtolower($inc['status']), ['pending','verified'], true)): ?>
                                        <form method="POST" action="update_incident.php"
                                              onsubmit="return confirm('Mark this report as RESOLVED?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="status" value="resolved">
                                            <button type="submit" class="act-btn act-resolve">
                                                <i class="fas fa-circle-check"></i> Resolve
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (strtolower($inc['status']) !== 'closed'): ?>
                                        <form method="POST" action="update_incident.php"
                                              onsubmit="return confirm('Close this report? This cannot be undone.');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="status" value="closed">
                                            <button type="submit" class="act-btn act-close">
                                                <i class="fas fa-xmark"></i> Close
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pager -->
            <?php
            $qs = $_GET;
            $buildPageUrl = function ($p) use ($qs) {
                $qs['page'] = $p;
                return 'manage_incidents.php?' . http_build_query($qs);
            };
            ?>
            <div class="pager">
                <div class="info">
                    Showing <strong><?php echo count($incidents); ?></strong>
                    of <strong><?php echo $totalRows; ?></strong> report<?php echo $totalRows === 1 ? '' : 's'; ?>
                    — Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="pages">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($page - 1)); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1) {
                        echo '<a href="' . htmlspecialchars($buildPageUrl(1)) . '">1</a>';
                        if ($start > 2) echo '<span class="dots">…</span>';
                    }
                    for ($p = $start; $p <= $end; $p++) {
                        if ($p === $page) {
                            echo '<span class="current">' . $p . '</span>';
                        } else {
                            echo '<a href="' . htmlspecialchars($buildPageUrl($p)) . '">' . $p . '</a>';
                        }
                    }
                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1) echo '<span class="dots">…</span>';
                        echo '<a href="' . htmlspecialchars($buildPageUrl($totalPages)) . '">'
                           . $totalPages . '</a>';
                    }
                    ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($page + 1)); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> SmartEcoTrack · Environmental Incident Reporting & Monitoring System</p>
    <p class="sub">Working together for a safer, greener Philippines 🌿</p>
</footer>

<script>
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
</script>
</body>
</html>