<?php
/* ============================================================
   FILE: profile.php
   Purpose: User profile — edit info, change password, view reports
   ============================================================ */
require_once 'db_connect.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = (int)$_SESSION['user_id'];

/* Optional helper — safe if file doesn't exist */
if (file_exists('includes/functions.php')) require_once 'includes/functions.php';

/* ---------- Load user ---------- */
$user = null;
if (function_exists('getUserById')) {
    $user = getUserById($user_id);
}
if (!$user) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}
if (!$user) { session_destroy(); redirect('login.php'); }

/* ---------- Load stats ---------- */
$stats = ['quiz_score' => 0, 'reports' => 0, 'resolved' => 0, 'pending' => 0];
if (function_exists('getUserStats')) {
    $stats = array_merge($stats, getUserStats($user_id));
} else {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM incidents WHERE user_id = $user_id");
    if ($r) $stats['reports'] = (int)mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM incidents WHERE user_id = $user_id AND status = 'resolved'");
    if ($r) $stats['resolved'] = (int)mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM incidents WHERE user_id = $user_id AND status = 'pending'");
    if ($r) $stats['pending'] = (int)mysqli_fetch_assoc($r)['c'];

    $stats['quiz_score'] = (int)($user['quiz_score'] ?? 0);
}

/* ---------- Flash messages from session ---------- */
$error   = '';
$success = '';

/* ---------- Handle form submissions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- Update profile ---- */
    if (isset($_POST['update_profile'])) {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname']  ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $address   = trim($_POST['address']   ?? '');

        $profile_image = $user['profile_image'] ?? '';

        if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === 0) {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed, true)) {
                $error = 'Invalid image format. Use JPG, PNG, GIF, or WebP.';
            } elseif ($_FILES['profile_image']['size'] > 3 * 1024 * 1024) {
                $error = 'Image too large. Max 3 MB.';
            } else {
                $dir = 'uploads/profiles/';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $filename)) {
                    $profile_image = $filename;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if (empty($error)) {
            $stmt = mysqli_prepare($conn,
                "UPDATE users SET firstname=?, lastname=?, phone=?, address=?, profile_image=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssssi',
                $firstname, $lastname, $phone, $address, $profile_image, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['firstname'] = $firstname;
                $_SESSION['lastname']  = $lastname;
                $success = 'Profile updated successfully!';
                $user['firstname'] = $firstname;
                $user['lastname']  = $lastname;
                $user['phone']     = $phone;
                $user['address']   = $address;
                $user['profile_image'] = $profile_image;
            } else {
                $error = 'Failed to update profile.';
            }
        }
    }

    /* ---- Change password ---- */
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'si', $hashed, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password.';
            }
        }
    }
}

/* ---------- Load user's reports ---------- */
$reports = null;
$stmt = mysqli_prepare($conn,
    "SELECT * FROM incidents WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$reports = mysqli_stmt_get_result($stmt);

/* ---------- Helpers ---------- */
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
if (!function_exists('badge_class')) {
    function badge_class(string $value): string {
        $map = [
            'pending'=>'badge-pending','verified'=>'badge-verified',
            'resolved'=>'badge-resolved','closed'=>'badge-closed',
            'critical'=>'badge-critical','high'=>'badge-high',
            'medium'=>'badge-medium','low'=>'badge-low',
        ];
        return $map[strtolower($value)] ?? 'badge-closed';
    }
}

$initials = strtoupper(substr($user['firstname'] ?? '?', 0, 1) . substr($user['lastname'] ?? '?', 0, 1));
$role     = strtolower($user['role'] ?? 'user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0A1931">
<title>My Profile · SmartEcoTrack</title>
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
    --gradient-accent:linear-gradient(135deg,var(--forest-800),var(--forest-600));
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

.container{max-width:1200px;margin:0 auto;padding:2rem 5vw 3rem;}

/* ============================================================
   HERO PROFILE HEADER
   ============================================================ */
.profile-hero{
    background:var(--gradient-header);color:var(--white);
    border-radius:var(--radius-lg);padding:2.25rem 2rem;
    margin-bottom:2rem;position:relative;overflow:hidden;
    box-shadow:var(--shadow-lg);
}
.profile-hero::before{
    content:'';position:absolute;inset:0;
    background:
        radial-gradient(circle at 15% 25%, rgba(82,183,136,0.25), transparent 55%),
        radial-gradient(circle at 85% 75%, rgba(82,183,136,0.15), transparent 50%);
    pointer-events:none;
}
.profile-hero-inner{
    position:relative;z-index:1;
    display:flex;align-items:center;gap:1.75rem;flex-wrap:wrap;
}

/* Avatar block */
.hero-avatar-wrap{position:relative;flex-shrink:0;}
.hero-avatar{
    width:110px;height:110px;border-radius:50%;
    object-fit:cover;display:block;
    border:4px solid rgba(255,255,255,0.9);
    box-shadow:
        0 0 0 3px rgba(255,255,255,0.15),
        0 12px 32px rgba(10,25,49,0.35);
}
.hero-avatar-badge{
    position:absolute;bottom:2px;right:2px;
    width:32px;height:32px;border-radius:50%;
    background:var(--forest-600);
    display:flex;align-items:center;justify-content:center;
    color:var(--white);font-size:0.85rem;
    border:3px solid var(--navy-800);
    box-shadow:0 4px 12px rgba(82,183,136,0.5);
}

/* Info block */
.hero-info{flex:1;min-width:220px;}
.hero-info h1{
    font-size:clamp(1.4rem,3vw,2rem);
    font-weight:800;letter-spacing:-0.02em;
    margin-bottom:6px;
    text-shadow:0 2px 8px rgba(0,0,0,0.25);
}
.hero-info .email{
    display:inline-flex;align-items:center;gap:8px;
    font-size:0.9rem;opacity:0.85;
    margin-bottom:14px;
}

/* Hero chips row */
.hero-chips{display:flex;gap:8px;flex-wrap:wrap;}
.hero-chip{
    display:inline-flex;align-items:center;gap:7px;
    padding:5px 14px;border-radius:var(--radius-pill);
    font-size:0.75rem;font-weight:700;
    text-transform:uppercase;letter-spacing:0.05em;
    backdrop-filter:blur(6px);
}
.hero-chip.role{
    background:rgba(255,255,255,0.15);
    border:1px solid rgba(255,255,255,0.25);
    color:var(--white);
}
.hero-chip.role.admin { background:rgba(255,215,0,0.2); border-color:rgba(255,215,0,0.4); }
.hero-chip.role.staff { background:rgba(21,101,192,0.35); border-color:rgba(21,101,192,0.5); }
.hero-chip.points{
    background:var(--forest-600);
    color:var(--white);
    border:1px solid rgba(255,255,255,0.2);
}
.hero-chip.verified{
    background:rgba(255,255,255,0.15);
    border:1px solid rgba(255,255,255,0.25);
    color:var(--white);
}
.hero-chip i{font-size:0.78rem;}

/* Hero stats on the right */
.hero-stats{
    display:flex;gap:1.25rem;flex-wrap:wrap;
    padding-left:1.75rem;border-left:1px solid rgba(255,255,255,0.15);
}
.hero-stat{text-align:center;}
.hero-stat .num{
    font-size:1.9rem;font-weight:800;letter-spacing:-0.03em;
    line-height:1.1;color:var(--forest-400);
}
.hero-stat .lbl{
    font-size:0.68rem;text-transform:uppercase;
    letter-spacing:0.06em;opacity:0.75;
    font-weight:700;margin-top:4px;
}

/* ============================================================
   TABS
   ============================================================ */
.tabs-bar{
    background:var(--white);border:1px solid var(--gray-200);
    border-radius:var(--radius-lg);padding:6px;
    display:flex;gap:4px;margin-bottom:1.5rem;
    box-shadow:var(--shadow-sm);flex-wrap:wrap;
}
.tab-btn{
    flex:1;min-width:140px;
    padding:0.75rem 1rem;border:none;background:transparent;
    border-radius:var(--radius-md);
    font-family:inherit;font-size:0.88rem;font-weight:600;
    color:var(--gray-600);cursor:pointer;
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    transition:all 0.15s ease;white-space:nowrap;
}
.tab-btn:hover{background:var(--navy-50);color:var(--navy-800);}
.tab-btn.active{background:var(--navy-700);color:var(--white);box-shadow:var(--shadow-sm);}
.tab-btn i{font-size:0.9rem;}
.tab-btn .count{
    background:var(--gray-200);color:var(--gray-600);
    padding:1px 8px;border-radius:var(--radius-pill);
    font-size:0.7rem;font-weight:800;
}
.tab-btn.active .count{background:rgba(255,255,255,0.25);color:var(--white);}

.tab-content{display:none;}
.tab-content.active{display:block;animation:fadeInUp 0.3s ease;}
@keyframes fadeInUp{
    from{opacity:0;transform:translateY(8px);}
    to{opacity:1;transform:translateY(0);}
}

/* ============================================================
   CARDS
   ============================================================ */
.card{
    background:var(--white);border:1px solid var(--gray-200);
    border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);
    overflow:hidden;margin-bottom:1.5rem;
}
.card:last-child{margin-bottom:0;}
.card-header{
    padding:1rem 1.5rem;
    background:var(--gray-50);
    border-bottom:1px solid var(--gray-200);
    font-weight:700;font-size:0.92rem;
    color:var(--navy-800);
    display:flex;align-items:center;gap:10px;
}
.card-header i{
    width:30px;height:30px;
    background:var(--navy-700);color:var(--white);
    border-radius:var(--radius-sm);
    display:inline-flex;align-items:center;justify-content:center;
    font-size:0.82rem;
}
.card-header.green i{background:var(--forest-700);}
.card-header.amber i{background:var(--amber);}
.card-body{padding:1.75rem 1.5rem;}

/* ============================================================
   FORMS
   ============================================================ */
.form-row{
    display:grid;grid-template-columns:1fr 1fr;
    gap:1rem;margin-bottom:1rem;
}
.form-group{margin-bottom:1.1rem;}
.form-label{
    display:block;font-size:0.82rem;font-weight:600;
    color:var(--gray-800);margin-bottom:0.4rem;
}
.form-label i{color:var(--forest-700);margin-right:5px;font-size:0.78rem;}
.form-label .required{color:var(--crimson);}

.form-control{
    width:100%;padding:0.75rem 1rem;
    border:1.5px solid var(--gray-300);
    border-radius:var(--radius-sm);
    font-size:0.92rem;font-family:inherit;
    color:var(--gray-800);background:var(--white);
    outline:none;transition:all 0.15s ease;
}
.form-control:focus{
    border-color:var(--navy-500);
    box-shadow:0 0 0 3px rgba(46,95,150,0.15);
}
.form-control:disabled{background:var(--gray-100);color:var(--gray-500);cursor:not-allowed;}
textarea.form-control{resize:vertical;min-height:80px;}

/* Character counter */
.char-counter{
    font-size:0.72rem;color:var(--gray-500);
    text-align:right;margin-top:3px;
}
.char-counter.warn{color:var(--amber);font-weight:700;}
.char-counter.over{color:var(--crimson);font-weight:700;}

/* File input */
input[type="file"].form-control{
    padding:0.55rem 0.9rem;cursor:pointer;font-size:0.85rem;
}
input[type="file"].form-control::file-selector-button{
    background:var(--navy-700);color:var(--white);
    border:none;padding:0.42rem 0.9rem;
    border-radius:var(--radius-sm);
    font-weight:600;font-size:0.8rem;
    cursor:pointer;margin-right:0.75rem;font-family:inherit;
}

/* Password strength meter */
.strength-meter{
    margin-top:8px;display:flex;gap:4px;
}
.strength-meter .bar{
    flex:1;height:4px;background:var(--gray-200);
    border-radius:var(--radius-pill);transition:background 0.3s;
}
.strength-label{
    font-size:0.72rem;font-weight:600;margin-top:6px;
    color:var(--gray-500);
    display:flex;align-items:center;gap:4px;
}
.strength-label.weak{color:var(--crimson);}
.strength-label.fair{color:var(--amber);}
.strength-label.good{color:#1976D2;}
.strength-label.strong{color:var(--status-green);}

/* Password match hint */
.match-hint{
    font-size:0.72rem;font-weight:600;
    margin-top:6px;display:flex;align-items:center;gap:4px;
    color:var(--gray-500);
}
.match-hint.ok{color:var(--status-green);}
.match-hint.error{color:var(--crimson);}

/* Toggle eye */
.input-wrap{position:relative;}
.toggle-eye{
    position:absolute;right:12px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:var(--gray-400);
    cursor:pointer;font-size:0.95rem;padding:6px;
    border-radius:6px;transition:all 0.15s;
}
.toggle-eye:hover{color:var(--navy-600);background:var(--gray-100);}

/* Buttons */
.btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:0.75rem 1.5rem;border-radius:var(--radius-sm);
    font-weight:600;font-size:0.9rem;font-family:inherit;
    text-decoration:none;border:none;cursor:pointer;
    transition:all 0.15s ease;
}
.btn-success{
    background:var(--gradient-accent);color:var(--white);
    box-shadow:0 4px 12px rgba(64,145,108,0.35);
}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(64,145,108,0.45);}
.btn-warning{
    background:var(--amber);color:var(--white);
    box-shadow:0 4px 12px rgba(230,81,0,0.35);
}
.btn-warning:hover{background:#C54A00;transform:translateY(-2px);}
.btn-sm{padding:0.42rem 0.9rem;font-size:0.78rem;}

/* ============================================================
   REPORTS LIST
   ============================================================ */
.report-list{
    display:flex;flex-direction:column;gap:0.85rem;
}
.report-item{
    display:flex;align-items:center;gap:1rem;
    padding:1rem 1.15rem;
    background:var(--gray-50);
    border-radius:var(--radius-md);
    border-left:4px solid var(--forest-600);
    transition:all 0.15s ease;
    text-decoration:none;color:inherit;
    flex-wrap:wrap;
}
.report-item:hover{
    background:var(--navy-50);
    transform:translateX(4px);
    box-shadow:var(--shadow-sm);
}
.report-item.sev-critical{border-left-color:#B71C1C;}
.report-item.sev-high{border-left-color:var(--amber);}
.report-item.sev-medium{border-left-color:var(--yellow);}
.report-item.sev-low{border-left-color:var(--status-green);}

.report-icon{
    width:44px;height:44px;border-radius:50%;
    background:var(--white);border:1px solid var(--gray-200);
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;flex-shrink:0;
}
.report-body{flex:1;min-width:180px;}
.report-title{
    font-weight:700;color:var(--navy-900);
    font-size:0.95rem;line-height:1.3;margin-bottom:3px;
}
.report-meta{
    font-size:0.78rem;color:var(--gray-500);
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.report-meta i{font-size:0.72rem;}
.report-badges{display:flex;gap:6px;flex-wrap:wrap;}

.badge{
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 11px;border-radius:var(--radius-pill);
    font-size:0.7rem;font-weight:700;
    text-transform:capitalize;letter-spacing:0.02em;
}
.badge-pending{background:var(--amber-bg);color:var(--amber);}
.badge-verified{background:var(--info-bg);color:var(--info-blue);}
.badge-resolved{background:var(--status-bg);color:var(--status-green);}
.badge-closed{background:var(--gray-200);color:var(--gray-600);}
.badge-critical{background:var(--crimson-bg);color:var(--crimson);}
.badge-high{background:var(--amber-bg);color:var(--amber);}
.badge-medium{background:var(--yellow-bg);color:#B8860B;}
.badge-low{background:var(--status-bg);color:var(--status-green);}

/* Empty */
.empty-state{
    text-align:center;padding:3rem 1rem;color:var(--gray-500);
}
.empty-state i{
    font-size:2.5rem;color:var(--gray-300);
    margin-bottom:0.6rem;display:block;
}
.empty-state p{font-size:0.92rem;font-weight:500;}

/* Alerts */
.alert{
    padding:12px 18px;border-radius:var(--radius-sm);
    margin-bottom:1.25rem;font-weight:500;font-size:0.9rem;
    display:flex;align-items:center;gap:10px;
    border-left:4px solid;animation:slideDown 0.3s ease;
}
@keyframes slideDown{
    from{opacity:0;transform:translateY(-8px);}
    to{opacity:1;transform:translateY(0);}
}
.alert-danger{background:var(--crimson-bg);color:var(--crimson);border-color:var(--crimson);}
.alert-success{background:var(--status-bg);color:var(--status-green);border-color:var(--status-green);}

/* FOOTER */
footer{
    background:var(--navy-900);color:rgba(255,255,255,0.75);
    text-align:center;padding:1.75rem 5vw;
    border-top:3px solid var(--forest-700);margin-top:2rem;
}
footer p{font-size:0.88rem;}
footer p.sub{opacity:0.6;font-size:0.8rem;margin-top:0.3rem;}

/* RESPONSIVE */
@media (max-width:900px){
    .hero-stats{padding-left:0;border-left:none;width:100%;justify-content:space-around;
                 padding-top:1.25rem;border-top:1px solid rgba(255,255,255,0.15);}
}
@media (max-width:768px){
    body{padding-top:76px;}
    .navbar{padding:0.6rem 1.25rem;flex-wrap:wrap;}
    .mobile-toggle{display:block;}
    .nav-links{display:none;width:100%;flex-direction:column;align-items:stretch;
               padding:0.75rem 0 0.25rem;border-top:1px solid var(--gray-200);margin-top:0.5rem;}
    .nav-links.active{display:flex;}
    .nav-links a{padding:0.7rem 1rem;}
    .logo{font-size:1.2rem;gap:10px;}
    .logo-circle{width:48px;height:48px;}
    .logo-text em{display:none;}
    .container{padding:1.5rem 1.25rem 2.5rem;}
    .profile-hero{padding:1.75rem 1.25rem;}
    .profile-hero-inner{gap:1rem;}
    .hero-avatar{width:80px;height:80px;}
    .hero-avatar-badge{width:26px;height:26px;font-size:0.7rem;}
    .hero-info h1{font-size:1.35rem;}
    .hero-stat .num{font-size:1.5rem;}
    .form-row{grid-template-columns:1fr;gap:0;}
    .card-body{padding:1.25rem 1.1rem;}
    .tab-btn{min-width:auto;font-size:0.82rem;padding:0.6rem 0.75rem;}
    .tab-btn span{display:none;}
    .tab-btn i{font-size:1.05rem;margin:0;}
}
@media (max-width:480px){
    .hero-avatar{width:70px;height:70px;}
    .hero-stats{gap:0.75rem;}
    .hero-stat .num{font-size:1.25rem;}
    .hero-stat .lbl{font-size:0.6rem;}
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
        <a href="profile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="container">

    <!-- ============================================================
         HERO PROFILE HEADER
         ============================================================ -->
    <div class="profile-hero">
        <div class="profile-hero-inner">

            <!-- Avatar -->
            <div class="hero-avatar-wrap">
                <img src="uploads/profiles/<?= htmlspecialchars($user['profile_image'] ?: 'default.jpg') ?>"
                     alt="<?= htmlspecialchars($user['firstname'] ?? 'User') ?>"
                     class="hero-avatar"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($initials) ?>&background=40916C&color=fff&size=220&bold=true'">
                <div class="hero-avatar-badge">
                    <i class="fas fa-check"></i>
                </div>
            </div>

            <!-- Info -->
            <div class="hero-info">
                <h1><?= htmlspecialchars(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?></h1>
                <div class="email">
                    <i class="fas fa-envelope"></i>
                    <?= htmlspecialchars($user['email'] ?? '') ?>
                </div>

                <div class="hero-chips">
                    <span class="hero-chip role <?= $role ?>">
                        <i class="fas fa-<?= $role === 'admin' ? 'crown' : ($role === 'staff' ? 'user-shield' : 'user') ?>"></i>
                        <?= strtoupper($role) ?>
                    </span>
                    <span class="hero-chip points">
                        <i class="fas fa-star"></i>
                        <?= number_format((int)($stats['quiz_score'] ?? 0)) ?> pts
                    </span>
                    <?php if (($stats['verified'] ?? 0) > 0): ?>
                        <span class="hero-chip verified">
                            <i class="fas fa-circle-check"></i>
                            <?= (int)$stats['verified'] ?> verified
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="num"><?= number_format((int)($stats['reports'] ?? 0)) ?></div>
                    <div class="lbl">Reports</div>
                </div>
                <div class="hero-stat">
                    <div class="num"><?= number_format((int)($stats['resolved'] ?? 0)) ?></div>
                    <div class="lbl">Resolved</div>
                </div>
                <div class="hero-stat">
                    <div class="num"><?= number_format((int)($stats['pending'] ?? 0)) ?></div>
                    <div class="lbl">Pending</div>
                </div>
            </div>

        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         TABS
         ============================================================ -->
    <div class="tabs-bar">
        <button type="button" class="tab-btn active" data-tab="edit" onclick="switchTab('edit')">
            <i class="fas fa-user-edit"></i>
            <span>Edit Profile</span>
        </button>
        <button type="button" class="tab-btn" data-tab="password" onclick="switchTab('password')">
            <i class="fas fa-lock"></i>
            <span>Password</span>
        </button>
        <button type="button" class="tab-btn" data-tab="reports" onclick="switchTab('reports')">
            <i class="fas fa-clipboard-list"></i>
            <span>My Reports</span>
            <span class="count"><?= (int)($stats['reports'] ?? 0) ?></span>
        </button>
    </div>

    <!-- ============================================================
         TAB 1 — EDIT PROFILE
         ============================================================ -->
    <div class="tab-content active" id="tab-edit">
        <div class="card">
            <div class="card-header green">
                <i class="fas fa-user-edit"></i>
                Personal Information
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="form-row">
                        <div>
                            <label class="form-label">
                                <i class="fas fa-user"></i> First Name <span class="required">*</span>
                            </label>
                            <input type="text" name="firstname" class="form-control"
                                   value="<?= htmlspecialchars($user['firstname'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label class="form-label">
                                <i class="fas fa-user"></i> Last Name <span class="required">*</span>
                            </label>
                            <input type="text" name="lastname" class="form-control"
                                   value="<?= htmlspecialchars($user['lastname'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" class="form-control"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="e.g. +63 912 345 6789"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Address
                        </label>
                        <textarea name="address" class="form-control" id="addressInput"
                                  rows="3" maxlength="250"
                                  placeholder="Enter your full address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        <div class="char-counter" id="addrCounter">0 / 250</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-image"></i> Profile Image
                        </label>
                        <input type="file" name="profile_image" class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="char-counter">Max 3 MB · JPG, PNG, GIF, WebP</div>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 2 — CHANGE PASSWORD
         ============================================================ -->
    <div class="tab-content" id="tab-password">
        <div class="card">
            <div class="card-header amber">
                <i class="fas fa-lock"></i>
                Change Password
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Current Password <span class="required">*</span>
                        </label>
                        <div class="input-wrap">
                            <input type="password" name="current_password" id="currentPwd"
                                   class="form-control" required>
                            <button type="button" class="toggle-eye"
                                    onclick="togglePwd('currentPwd', this)" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label class="form-label">
                                <i class="fas fa-lock"></i> New Password <span class="required">*</span>
                            </label>
                            <div class="input-wrap">
                                <input type="password" name="new_password" id="newPwd"
                                       class="form-control" required minlength="6">
                                <button type="button" class="toggle-eye"
                                        onclick="togglePwd('newPwd', this)" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-meter" id="strengthMeter" aria-hidden="true">
                                <div class="bar"></div>
                                <div class="bar"></div>
                                <div class="bar"></div>
                                <div class="bar"></div>
                            </div>
                            <div class="strength-label" id="strengthLabel">At least 6 characters</div>
                        </div>
                        <div>
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Confirm Password <span class="required">*</span>
                            </label>
                            <div class="input-wrap">
                                <input type="password" name="confirm_password" id="confirmPwd"
                                       class="form-control" required>
                                <button type="button" class="toggle-eye"
                                        onclick="togglePwd('confirmPwd', this)" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="match-hint" id="matchHint"></div>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 3 — MY REPORTS
         ============================================================ -->
    <div class="tab-content" id="tab-reports">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-list"></i>
                My Reports
                <span style="margin-left:auto;font-weight:500;color:var(--gray-500);font-size:0.82rem;">
                    <?= (int)($stats['reports'] ?? 0) ?> total
                </span>
            </div>
            <div class="card-body">
                <?php if ($reports && mysqli_num_rows($reports) > 0): ?>
                    <div class="report-list">
                        <?php while ($r = mysqli_fetch_assoc($reports)):
                            $sev  = strtolower($r['severity'] ?? 'low');
                            $stat = strtolower($r['status']   ?? 'pending');
                            $cat  = $r['category'] ?? 'other';
                        ?>
                            <a href="incident_view.php?id=<?= (int)$r['id'] ?>"
                               class="report-item sev-<?= htmlspecialchars($sev) ?>">

                                <div class="report-icon"><?= category_icon($cat) ?></div>

                                <div class="report-body">
                                    <div class="report-title">
                                        <?= htmlspecialchars($r['title'] ?? 'Untitled') ?>
                                    </div>
                                    <div class="report-meta">
                                        <span>
                                            <i class="fas fa-tag"></i>
                                            <?= htmlspecialchars(ucfirst($cat)) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            <?= date('M d, Y', strtotime($r['created_at'] ?? 'now')) ?>
                                        </span>
                                        <?php if (!empty($r['location_name'])): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars(mb_substr($r['location_name'], 0, 40)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="report-badges">
                                    <span class="badge <?= badge_class($stat) ?>">
                                        <?= htmlspecialchars($stat) ?>
                                    </span>
                                    <span class="badge <?= badge_class($sev) ?>">
                                        <?= htmlspecialchars($sev) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>You haven't submitted any reports yet.</p>
                        <p style="font-size:0.82rem;margin-top:0.35rem;">
                            <a href="report_incident.php" style="color:var(--forest-700);font-weight:600;text-decoration:none;">
                                Report an incident →
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<footer>
    <p>&copy; <?= date('Y') ?> SmartEcoTrack · Environmental Incident Reporting & Monitoring System</p>
    <p class="sub">Working together for a safer, greener Philippines 🌿</p>
</footer>

<script>
    /* ---------- Mobile menu ---------- */
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

    /* ---------- Tabs ---------- */
    function switchTab(name) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        const content = document.getElementById('tab-' + name);
        const btn     = document.querySelector('.tab-btn[data-tab="' + name + '"]');
        if (content) content.classList.add('active');
        if (btn)     btn.classList.add('active');

        /* Sync hash */
        if (history.replaceState) {
            history.replaceState(null, '', '#' + name);
        }

        /* Scroll to top of tabs */
        const bar = document.querySelector('.tabs-bar');
        if (bar) bar.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* Auto-open tab from URL hash */
    (function () {
        const hash = (location.hash || '').replace('#', '');
        if (['edit', 'password', 'reports'].includes(hash)) {
            switchTab(hash);
        }
    })();

    /* ---------- Password toggle ---------- */
    function togglePwd(id, btn) {
        const input = document.getElementById(id);
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        const icon = btn.querySelector('i');
        icon.classList.toggle('fa-eye', showing);
        icon.classList.toggle('fa-eye-slash', !showing);
    }

    /* ---------- Password strength meter ---------- */
    (function () {
        const pwd    = document.getElementById('newPwd');
        const meter  = document.getElementById('strengthMeter');
        const label  = document.getElementById('strengthLabel');
        if (!pwd || !meter || !label) return;

        const bars = meter.querySelectorAll('.bar');

        pwd.addEventListener('input', function () {
            const val = pwd.value;
            let score = 0;
            if (val.length >= 6)  score++;
            if (val.length >= 10) score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/\d/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;

            bars.forEach((b, i) => {
                b.style.background = '';
                if (i < score) {
                    if (score <= 1)       b.style.background = '#C62828';
                    else if (score === 2) b.style.background = '#E65100';
                    else if (score === 3) b.style.background = '#1976D2';
                    else                  b.style.background = '#2E7D32';
                }
            });

            label.className = 'strength-label';
            if (!val) {
                label.textContent = 'At least 6 characters';
            } else if (score <= 1) {
                label.classList.add('weak');
                label.innerHTML = '<i class="fas fa-circle-exclamation"></i> Weak password';
            } else if (score === 2) {
                label.classList.add('fair');
                label.innerHTML = '<i class="fas fa-circle-info"></i> Fair password';
            } else if (score === 3) {
                label.classList.add('good');
                label.innerHTML = '<i class="fas fa-circle-check"></i> Good password';
            } else {
                label.classList.add('strong');
                label.innerHTML = '<i class="fas fa-shield-halved"></i> Strong password';
            }
        });
    })();

    /* ---------- Password match ---------- */
    (function () {
        const pwd   = document.getElementById('newPwd');
        const conf  = document.getElementById('confirmPwd');
        const hint  = document.getElementById('matchHint');
        if (!pwd || !conf || !hint) return;

        function checkMatch() {
            const a = pwd.value;
            const b = conf.value;
            hint.className = 'match-hint';
            if (!b) { hint.textContent = ''; return; }
            if (a === b) {
                hint.classList.add('ok');
                hint.innerHTML = '<i class="fas fa-circle-check"></i> Passwords match';
            } else {
                hint.classList.add('error');
                hint.innerHTML = '<i class="fas fa-circle-xmark"></i> Passwords do not match';
            }
        }

        pwd.addEventListener('input', checkMatch);
        conf.addEventListener('input', checkMatch);
    })();

    /* ---------- Address character counter ---------- */
    (function () {
        const input = document.getElementById('addressInput');
        const counter = document.getElementById('addrCounter');
        if (!input || !counter) return;

        function update() {
            const len = input.value.length;
            counter.textContent = len + ' / 250';
            counter.className = 'char-counter';
            if (len > 250) counter.classList.add('over');
            else if (len > 220) counter.classList.add('warn');
        }

        input.addEventListener('input', update);
        update();
    })();

    /* ---------- Loading state on submit ---------- */
    ['profileForm', 'passwordForm'].forEach(id => {
        const form = document.getElementById(id);
        if (!form) return;
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving…';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            }, 4000);
        });
    });
</script>
</body>
</html>