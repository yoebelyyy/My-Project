<?php
/* ============================================================
   FILE: report_incident.php
   Purpose: Report an incident with automatic GPS location
   ============================================================ */
require_once 'db_connect.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = (int)$_SESSION['user_id'];
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
if (!$user) { session_destroy(); redirect('login.php'); }

$is_admin = (isset($user['role']) && strtolower($user['role']) === 'admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $severity     = trim($_POST['severity'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $location_name= trim($_POST['location_name'] ?? '');
    $latitude     = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
    $longitude    = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $accuracy     = isset($_POST['accuracy'])  && $_POST['accuracy']  !== '' ? (float)$_POST['accuracy']  : null;

    if (empty($title) || empty($category) || empty($severity) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
                $dir = 'uploads/reports/';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $filename = 'report_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename)) {
                    $image_path = $filename;
                }
            }
        }

        // Build reverse-geocoded location_name if only coords were provided
        if (empty($location_name) && $latitude !== null && $longitude !== null) {
            $location_name = sprintf('%.5f, %.5f', $latitude, $longitude);
        }

        // Insert into DB (with lat/lng if columns exist)
        $columns = "user_id, title, category, severity, description, location_name, image_path, status, created_at";
        $placeholders = "?, ?, ?, ?, ?, ?, ?, 'pending', NOW()";
        $types = "issssss";
        $params = [$user_id, $title, $category, $severity, $description, $location_name, $image_path];

        // Check if lat/lng columns exist
        $has_coords = false;
        $cols_result = mysqli_query($conn, "SHOW COLUMNS FROM incidents LIKE 'latitude'");
        if ($cols_result && mysqli_num_rows($cols_result) > 0) {
            $has_coords = true;
            $columns = "user_id, title, category, severity, description, location_name, latitude, longitude, location_accuracy, image_path, status, created_at";
            $placeholders = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()";
            $types = "isssssddds";
            $params = [$user_id, $title, $category, $severity, $description, $location_name,
                       $latitude, $longitude, $accuracy, $image_path];
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO incidents ($columns) VALUES ($placeholders)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $success = '✅ Report submitted successfully! Thank you for helping your community.';
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to save report. Please try again.';
            }
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident · SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet for map -->
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

            --forest-800: #2D6A4F;
            --forest-700: #40916C;
            --forest-600: #52B788;
            --forest-400: #95D5B2;
            --forest-100: #EDF7F0;

            --crimson:    #C62828;
            --crimson-bg: #FDECEA;
            --amber:      #E65100;
            --amber-bg:   #FFF3E0;
            --status-green: #2E7D32;
            --status-bg:    #E8F5E9;

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
        .nav-links a.btn-report { background: var(--forest-700); color: var(--white); font-weight: 600; }
        .nav-links a.btn-report:hover { background: var(--forest-800); }
        .nav-links a.logout { color: var(--crimson); }
        .nav-links a.logout:hover { background: var(--crimson-bg); }

        .mobile-toggle {
            display: none; background: none; border: none;
            color: var(--navy-800); font-size: 1.4rem;
            cursor: pointer; padding: 0.4rem;
        }

        /* CONTAINER */
        .container {
            max-width: 900px; margin: 0 auto;
            padding: 2rem 5vw 3rem;
        }

        /* PAGE HEADER */
        .page-header {
            background: var(--gradient-header);
            color: var(--white);
            padding: 2.5rem 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
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
            font-size: clamp(1.4rem, 3vw, 1.9rem);
            font-weight: 700; letter-spacing: -0.02em;
            position: relative; z-index: 1;
        }
        .page-header h1 i { color: var(--forest-400); margin-right: 8px; }
        .page-header p {
            opacity: 0.9; font-size: 0.95rem;
            position: relative; z-index: 1; margin-top: 0.35rem;
        }

        /* ALERTS */
        .alert {
            padding: 12px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 1.25rem;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px;
            border-left: 4px solid;
        }
        .alert-danger { background: var(--crimson-bg); color: var(--crimson); border-color: var(--crimson); }
        .alert-success { background: var(--status-bg); color: var(--status-green); border-color: var(--status-green); }

        /* CARD */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        /* FORM */
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.4rem;
        }
        .form-label i { color: var(--forest-700); margin-right: 5px; font-size: 0.8rem; }
        .form-label .required { color: var(--crimson); }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.92rem;
            font-family: inherit;
            color: var(--gray-800);
            background: var(--white);
            outline: none;
            transition: all 0.15s ease;
        }
        .form-control:focus {
            border-color: var(--navy-500);
            box-shadow: 0 0 0 3px rgba(46,95,150,0.15);
        }
        textarea.form-control { resize: vertical; min-height: 110px; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* LOCATION BOX */
        .location-box {
            background: var(--forest-100);
            border: 1.5px solid var(--forest-400);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .location-box .loc-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: var(--forest-800);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        .location-box .loc-title i { color: var(--forest-700); }
        .location-status {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.88rem;
            color: var(--gray-600);
            margin-bottom: 0.75rem;
            min-height: 22px;
        }
        .location-status.loading { color: var(--amber); }
        .location-status.success { color: var(--status-green); font-weight: 600; }
        .location-status.error   { color: var(--crimson); font-weight: 600; }

        .loc-details {
            display: none;
            background: var(--white);
            border-radius: var(--radius-sm);
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }
        .loc-details.show { display: block; }
        .loc-details .row {
            display: flex; justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dashed var(--gray-200);
        }
        .loc-details .row:last-child { border-bottom: none; }
        .loc-details .row strong { color: var(--navy-900); font-weight: 600; }

        .btn-loc {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 0.55rem 1.2rem;
            background: var(--forest-700); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-weight: 600; font-size: 0.85rem;
            cursor: pointer; font-family: inherit;
            transition: all 0.15s ease;
        }
        .btn-loc:hover { background: var(--forest-800); transform: translateY(-1px); }
        .btn-loc:disabled { opacity: 0.6; cursor: wait; }
        .btn-loc.secondary {
            background: transparent;
            color: var(--forest-800);
            border: 1.5px solid var(--forest-400);
        }
        .btn-loc.secondary:hover { background: var(--white); }

        .btn-loc-group {
            display: flex; gap: 0.6rem; flex-wrap: wrap;
        }

        /* MAP */
        #map {
            height: 280px;
            border-radius: var(--radius-md);
            margin-top: 0.75rem;
            border: 1.5px solid var(--forest-400);
            display: none;
            z-index: 1;
        }
        #map.show { display: block; }

        /* SUBMIT BUTTON */
        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: var(--forest-700); color: var(--white);
            border: none; border-radius: var(--radius-sm);
            font-size: 1rem; font-weight: 700;
            cursor: pointer; font-family: inherit;
            transition: all 0.15s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: var(--shadow-sm);
        }
        .btn-submit:hover {
            background: var(--forest-800);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .btn-submit:disabled { opacity: 0.7; cursor: wait; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body { padding-top: 76px; }
            .navbar { padding: 0.6rem 1.25rem; flex-wrap: wrap; }
            .mobile-toggle { display: block; }
            .nav-links {
                display: none; width: 100%;
                flex-direction: column;
                align-items: stretch;
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
            .card { padding: 1.35rem; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            #map { height: 220px; }
        }
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
        <a href="report_incident.php" class="btn-report active"><i class="fas fa-plus-circle"></i> Report Incident</a>
        <a href="my_reports.php"><i class="fas fa-list-check"></i> <?php echo $is_admin ? 'All Reports' : 'My Reports'; ?></a>
        <a href="modules/disaster_tips.php"><i class="fas fa-shield-alt"></i> Tips</a>
        <a href="modules/emergency_contacts.php"><i class="fas fa-phone-alt"></i> Emergency</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- MAIN -->
<div class="container">

    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> Report an Incident</h1>
        <p>Your GPS location will be captured automatically to help responders find the exact spot</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <a href="my_reports.php" style="margin-left:auto;color:var(--status-green);font-weight:700;">View My Reports →</a>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data" id="reportForm">

            <!-- TITLE -->
            <div class="form-group">
                <label class="form-label"><i class="fas fa-heading"></i> Incident Title <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" required
                       placeholder="e.g., Flooding on Main Street"
                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>

            <!-- CATEGORY + SEVERITY -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tag"></i> Category <span class="required">*</span></label>
                    <select name="category" class="form-control" required>
                        <option value="">Select category…</option>
                        <?php
                        $cats = ['flooding','landslide','deforestation','pollution','wildfire','wildlife','earthquake','typhoon','other'];
                        foreach ($cats as $c):
                            $sel = (($_POST['category'] ?? '') === $c) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo ucfirst($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-exclamation-triangle"></i> Severity <span class="required">*</span></label>
                    <select name="severity" class="form-control" required>
                        <option value="">Select severity…</option>
                        <?php
                        $sevs = ['low','medium','high','critical'];
                        foreach ($sevs as $s):
                            $sel = (($_POST['severity'] ?? '') === $s) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $s; ?>" <?php echo $sel; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- DESCRIPTION -->
            <div class="form-group">
                <label class="form-label"><i class="fas fa-align-left"></i> Description <span class="required">*</span></label>
                <textarea name="description" class="form-control" required
                          placeholder="Describe what happened, how severe it is, and any immediate dangers…"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <!-- ✅ LOCATION TRACKER -->
            <div class="location-box">
                <div class="loc-title">
                    <i class="fas fa-map-marker-alt"></i> Your Location (Auto-detected)
                </div>
                <div class="location-status" id="locStatus">
                    <i class="fas fa-circle-notch fa-spin"></i> Detecting your GPS location…
                </div>

                <div class="loc-details" id="locDetails">
                    <div class="row"><span>Latitude:</span><strong id="latVal">—</strong></div>
                    <div class="row"><span>Longitude:</span><strong id="lngVal">—</strong></div>
                    <div class="row"><span>Accuracy:</span><strong id="accVal">—</strong></div>
                    <div class="row"><span>Coordinates:</span><strong id="coordVal">—</strong></div>
                </div>

                <div id="map"></div>

                <div class="btn-loc-group" style="margin-top:0.85rem;">
                    <button type="button" class="btn-loc" onclick="detectLocation()" id="btnDetect">
                        <i class="fas fa-crosshairs"></i> Detect My Location
                    </button>
                    <button type="button" class="btn-loc secondary" onclick="toggleMap()" id="btnMap" style="display:none;">
                        <i class="fas fa-map"></i> Show Map
                    </button>
                </div>
            </div>

            <!-- LOCATION NAME (auto-filled but editable) -->
            <div class="form-group">
                <label class="form-label"><i class="fas fa-location-dot"></i> Location Name / Landmark</label>
                <input type="text" name="location_name" id="location_name" class="form-control"
                       placeholder="e.g., Barangay San Isidro, near the bridge"
                       value="<?php echo htmlspecialchars($_POST['location_name'] ?? ''); ?>">
                <small style="color:var(--gray-500);font-size:0.78rem;display:block;margin-top:0.35rem;">
                    <i class="fas fa-info-circle"></i> Auto-filled with coordinates, but you can edit to add a landmark name.
                </small>
            </div>

            <!-- Hidden inputs for coordinates -->
            <input type="hidden" name="latitude"  id="latitude"  value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
            <input type="hidden" name="accuracy"  id="accuracy"  value="<?php echo htmlspecialchars($_POST['accuracy'] ?? ''); ?>">

            <!-- IMAGE -->
            <div class="form-group">
                <label class="form-label"><i class="fas fa-camera"></i> Photo (optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <small style="color:var(--gray-500);font-size:0.78rem;display:block;margin-top:0.35rem;">
                    Max 5MB · JPG, PNG, GIF, WebP
                </small>
            </div>

            <!-- SUBMIT -->
            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Report
            </button>
        </form>
    </div>
</div>

<!-- Leaflet JS -->
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

    /* ——— Location tracking ——— */
    let map = null;
    let marker = null;
    let mapVisible = false;

    function setStatus(msg, type) {
        const el = document.getElementById('locStatus');
        el.className = 'location-status ' + (type || '');
        el.innerHTML = msg;
    }

    function detectLocation() {
        const btn = document.getElementById('btnDetect');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Detecting…';

        if (!navigator.geolocation) {
            setStatus('<i class="fas fa-exclamation-triangle"></i> Geolocation is not supported by your browser.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-crosshairs"></i> Detect My Location';
            return;
        }

        setStatus('<i class="fas fa-circle-notch fa-spin"></i> Getting your GPS coordinates…', 'loading');

        navigator.geolocation.getCurrentPosition(
            onLocationSuccess,
            onLocationError,
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    }

    function onLocationSuccess(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const acc = position.coords.accuracy;

        // Fill hidden inputs
        document.getElementById('latitude').value  = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);
        document.getElementById('accuracy').value  = Math.round(acc);

        // Fill display
        document.getElementById('latVal').textContent = lat.toFixed(6);
        document.getElementById('lngVal').textContent = lng.toFixed(6);
        document.getElementById('accVal').textContent = Math.round(acc) + ' meters';
        document.getElementById('coordVal').textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);

        // Auto-fill location_name if empty
        const locName = document.getElementById('location_name');
        if (!locName.value.trim()) {
            locName.value = lat.toFixed(5) + ', ' + lng.toFixed(5);
        }

        document.getElementById('locDetails').classList.add('show');
        setStatus('<i class="fas fa-check-circle"></i> Location detected successfully!', 'success');

        // Update map if visible
        updateMap(lat, lng);

        const btn = document.getElementById('btnDetect');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-redo"></i> Refresh Location';

        document.getElementById('btnMap').style.display = 'inline-flex';
    }

    function onLocationError(err) {
        let msg = 'Unable to detect location.';
        switch (err.code) {
            case err.PERMISSION_DENIED:   msg = 'Location permission denied. Please allow access and try again.'; break;
            case err.POSITION_UNAVAILABLE: msg = 'Location information is unavailable.'; break;
            case err.TIMEOUT:             msg = 'Location request timed out.'; break;
        }
        setStatus('<i class="fas fa-exclamation-triangle"></i> ' + msg, 'error');

        const btn = document.getElementById('btnDetect');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-crosshairs"></i> Try Again';
    }

    function toggleMap() {
        if (mapVisible) {
            document.getElementById('map').classList.remove('show');
            mapVisible = false;
            document.getElementById('btnMap').innerHTML = '<i class="fas fa-map"></i> Show Map';
        } else {
            document.getElementById('map').classList.add('show');
            mapVisible = true;
            document.getElementById('btnMap').innerHTML = '<i class="fas fa-eye-slash"></i> Hide Map';
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            if (!isNaN(lat) && !isNaN(lng)) {
                setTimeout(() => updateMap(lat, lng), 100);
            }
        }
    }

    function updateMap(lat, lng) {
        const mapEl = document.getElementById('map');
        if (!mapEl.classList.contains('show')) return;

        if (!map) {
            map = L.map('map').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 19
            }).addTo(map);
        } else {
            map.setView([lat, lng], 16);
        }

        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng]).addTo(map)
            .bindPopup('<strong>📍 Incident Location</strong><br>' + lat.toFixed(5) + ', ' + lng.toFixed(5))
            .openPopup();

        // Accuracy circle
        if (window.accCircle) map.removeLayer(window.accCircle);
        const acc = parseFloat(document.getElementById('accuracy').value);
        if (!isNaN(acc)) {
            window.accCircle = L.circle([lat, lng], {
                radius: acc,
                color: '#40916C',
                fillColor: '#52B788',
                fillOpacity: 0.15,
                weight: 1
            }).addTo(map);
        }

        // Force redraw in case hidden initially
        setTimeout(() => map.invalidateSize(), 200);
    }

    /* Auto-detect on page load */
    window.addEventListener('load', () => {
        setTimeout(detectLocation, 800);
    });

    /* Form submit — validate location present */
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if (!lat || !lng) {
            if (!confirm('⚠️ We could not detect your GPS location. Submit anyway?')) {
                e.preventDefault();
                return false;
            }
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Submitting…';
    });
</script>
</body>
</html>