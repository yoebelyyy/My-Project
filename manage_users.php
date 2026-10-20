<?php
/* ============================================================
   FILE: manage_users.php
   Purpose: Admin-only user management (create/edit/deactivate)
   ============================================================ */
require_once 'db_connect.php';
if (!isLoggedIn()) redirect('login.php');

$user_id = (int)$_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
if (!$me) { session_destroy(); redirect('login.php'); }

/* ONLY admins can access */
if (strtolower($me['role']) !== 'admin') {
    http_response_code(403);
    exit('Forbidden — only administrators can access this page.');
}

$error   = '';
$success = '';
$show_add_modal = false;
$old = ['firstname'=>'', 'lastname'=>'', 'email'=>'', 'phone'=>'', 'role'=>'user'];

/* ============================================================
   ACTIONS
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- CREATE NEW USER ---- */
    if (isset($_POST['create_user'])) {
        $fn    = trim($_POST['firstname'] ?? '');
        $ln    = trim($_POST['lastname']  ?? '');
        $em    = trim($_POST['email']     ?? '');
        $ph    = trim($_POST['phone']     ?? '');
        $rl    = strtolower(trim($_POST['role'] ?? 'user'));
        $pw    = $_POST['password']         ?? '';
        $pw2   = $_POST['password_confirm'] ?? '';

        $old = [
            'firstname' => $fn,
            'lastname'  => $ln,
            'email'     => $em,
            'phone'     => $ph,
            'role'      => $rl,
        ];

        if ($fn === '' || $ln === '' || $em === '' || $pw === '' || $pw2 === '') {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($rl, ['admin','staff','user'], true)) {
            $error = 'Invalid role selected.';
        } elseif (strlen($pw) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            // Check duplicate email
            $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 's', $em);
            mysqli_stmt_execute($chk);
            mysqli_stmt_store_result($chk);
            $exists = mysqli_stmt_num_rows($chk) > 0;
            mysqli_stmt_close($chk);

            if ($exists) {
                $error = 'A user with that email already exists.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $ins  = mysqli_prepare($conn,
                    "INSERT INTO users (firstname, lastname, email, phone, password, role, is_online, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
                if ($ins) {
                    mysqli_stmt_bind_param($ins, 'ssssss', $fn, $ln, $em, $ph, $hash, $rl);
                    if (mysqli_stmt_execute($ins)) {
                        $success = "User <strong>" . htmlspecialchars($fn . ' ' . $ln) . "</strong> created successfully as " . strtoupper($rl) . ".";
                        $old = ['firstname'=>'', 'lastname'=>'', 'email'=>'', 'phone'=>'', 'role'=>'user'];
                    } else {
                        $error = 'Failed to create user: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($ins);
                } else {
                    $error = 'A server error occurred. Please try again.';
                }
            }
        }

        if ($error !== '') {
            $show_add_modal = true; // reopen modal with errors preserved
        }
    }

    /* ---- Change role ---- */
    if (isset($_POST['change_role'])) {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $new_role  = strtolower(trim($_POST['new_role'] ?? ''));

        if ($target_id === $user_id) {
            $error = 'You cannot change your own role.';
        } elseif (!in_array($new_role, ['admin','staff','user'], true)) {
            $error = 'Invalid role.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_role, $target_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Role updated to " . strtoupper($new_role) . ".";
            } else {
                $error = 'Failed to update role.';
            }
        }
    }

    /* ---- Toggle active ---- */
    if (isset($_POST['toggle_active'])) {
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id === $user_id) {
            $error = 'You cannot deactivate your own account.';
        } else {
            mysqli_query($conn, "UPDATE users SET is_online = 1 - is_online WHERE id = $target_id");
            $success = 'User status toggled.';
        }
    }

    /* ---- Delete user ---- */
    if (isset($_POST['delete_user'])) {
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id === $user_id) {
            $error = 'You cannot delete your own account.';
        } else {
            mysqli_query($conn, "DELETE FROM users WHERE id = $target_id");
            $success = 'User deleted.';
        }
    }
}

/* ============================================================
   LOAD USERS
   ============================================================ */
$filter_role = $_GET['role'] ?? '';
$search      = trim($_GET['q'] ?? '');

$where = [];
if (in_array($filter_role, ['admin','staff','user'], true)) {
    if ($filter_role === 'user') {
        $where[] = "role NOT IN ('admin','staff')";
    } else {
        $where[] = "role = '" . mysqli_real_escape_string($conn, $filter_role) . "'";
    }
}
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where[] = "(firstname LIKE '%$s%' OR lastname LIKE '%$s%' OR email LIKE '%$s%')";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = mysqli_query($conn,
    "SELECT id, firstname, lastname, email, phone, role, is_online, last_active, created_at
     FROM users $whereSql
     ORDER BY FIELD(role,'admin','staff','user'), firstname ASC");

/* Stats */
$statAdmins  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='admin'"))['c'];
$statStaff   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='staff'"))['c'];
$statUsers   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role NOT IN ('admin','staff')"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users · SmartEcoTrack</title>
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
    --shadow-xl:0 24px 48px rgba(10,25,49,0.20);
    --radius-sm:6px;--radius-md:10px;--radius-lg:16px;--radius-pill:999px;
    --gradient-header:linear-gradient(135deg,var(--navy-800),var(--navy-600));
    --gradient-gold:linear-gradient(135deg,#FFD700,#FFA000);
}
body{font-family:'Inter',system-ui,sans-serif;background:var(--gray-100);color:var(--gray-800);
     line-height:1.6;padding-top:88px;-webkit-font-smoothing:antialiased;}

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

.nav-clock{display:flex;flex-direction:column;align-items:flex-end;
           padding:0.35rem 0.85rem;background:var(--navy-50);border:1px solid var(--navy-100);
           border-radius:var(--radius-sm);font-variant-numeric:tabular-nums;margin-right:0.35rem;
           line-height:1.15;flex-shrink:0;}
.nav-clock .clock-time{font-size:0.95rem;font-weight:800;color:var(--navy-800);letter-spacing:-0.01em;}
.nav-clock .clock-date{font-size:0.65rem;color:var(--gray-500);font-weight:600;
                       text-transform:uppercase;letter-spacing:0.04em;}

.nav-links{display:flex;gap:0.35rem;align-items:center;flex-wrap:wrap;}
.nav-links a{color:var(--gray-600);text-decoration:none;padding:0.5rem 0.95rem;
             border-radius:var(--radius-sm);font-weight:500;font-size:0.88rem;
             transition:all 0.15s;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.nav-links a:hover{background:var(--navy-50);color:var(--navy-800);}
.nav-links a.active{background:var(--gradient-gold);color:#333;font-weight:700;}
.nav-links a.logout{color:var(--crimson);}
.nav-links a.logout:hover{background:var(--crimson-bg);}
.mobile-toggle{display:none;background:none;border:none;color:var(--navy-800);
               font-size:1.4rem;cursor:pointer;padding:0.4rem;}

.container{max-width:1400px;margin:0 auto;padding:2rem 5vw 3rem;}

/* HERO HEADER */
.page-hero{background:var(--gradient-gold);color:#333;border-radius:var(--radius-lg);
           padding:2rem 2.25rem;margin-bottom:2rem;box-shadow:0 12px 32px rgba(255,165,0,0.25);
           display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;
           position:relative;overflow:hidden;}
.page-hero::before{content:'';position:absolute;inset:0;
    background:radial-gradient(circle at 85% 30%, rgba(255,255,255,0.4), transparent 55%);
    pointer-events:none;}
.page-hero-left{display:flex;align-items:center;gap:1.25rem;position:relative;z-index:1;
                flex:1;min-width:260px;}
.page-hero-icon{width:60px;height:60px;background:rgba(255,255,255,0.5);
                border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;
                font-size:1.6rem;color:#333;flex-shrink:0;
                box-shadow:0 6px 16px rgba(0,0,0,0.08);}
.page-hero h1{font-size:1.35rem;font-weight:800;letter-spacing:-0.02em;margin-bottom:2px;}
.page-hero p{font-size:0.88rem;color:#555;}

.page-hero-stats{display:flex;gap:1.25rem;flex-wrap:wrap;position:relative;z-index:1;align-items:center;}
.hero-stat{text-align:center;padding:0 1rem;border-left:1px solid rgba(0,0,0,0.1);}
.hero-stat:first-child{border-left:none;padding-left:0;}
.hero-stat .num{font-size:1.75rem;font-weight:800;letter-spacing:-0.02em;line-height:1.1;color:#222;}
.hero-stat .lbl{font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;
                margin-top:2px;font-weight:700;color:#555;}

/* Add User CTA button (in hero) */
.hero-add-btn{
    background:#222;color:#FFD700;border:none;cursor:pointer;
    padding:0.85rem 1.4rem;border-radius:var(--radius-md);
    font-family:inherit;font-size:0.92rem;font-weight:800;
    display:inline-flex;align-items:center;gap:10px;
    box-shadow:0 6px 16px rgba(0,0,0,0.25);
    transition:all 0.2s;position:relative;z-index:1;white-space:nowrap;
}
.hero-add-btn:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,0.35);background:#111;}
.hero-add-btn i{font-size:1rem;}

/* FLASH */
.flash{padding:12px 18px;border-radius:var(--radius-sm);margin-bottom:1.5rem;
       font-weight:500;font-size:0.9rem;display:flex;align-items:center;gap:10px;
       border-left:4px solid;}
.flash-error{background:var(--crimson-bg);color:var(--crimson);border-color:var(--crimson);}
.flash-success{background:var(--status-bg);color:var(--status-green);border-color:var(--status-green);}

/* FILTERS */
.filters{background:var(--white);border:1px solid var(--gray-200);
         border-radius:var(--radius-lg);padding:1.25rem 1.5rem;
         margin-bottom:1.5rem;box-shadow:var(--shadow-sm);}
.filters form{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:0.75rem;align-items:end;}
.filters .field label{display:block;font-size:0.72rem;font-weight:700;color:var(--gray-500);
                      text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.35rem;}
.filters input,.filters select{width:100%;padding:0.65rem 0.9rem;
    border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);
    font-family:inherit;font-size:0.9rem;background:var(--white);}
.filters input:focus,.filters select:focus{outline:none;border-color:var(--navy-500);
    box-shadow:0 0 0 3px rgba(46,95,150,0.15);}

.btn{display:inline-flex;align-items:center;gap:6px;padding:0.65rem 1.25rem;
     border-radius:var(--radius-sm);font-family:inherit;font-size:0.88rem;
     font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all 0.15s;}
.btn-primary{background:var(--navy-700);color:var(--white);}
.btn-primary:hover{background:var(--navy-800);}
.btn-gold{background:var(--gradient-gold);color:#333;}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(255,165,0,0.4);}
.btn-sm{padding:0.42rem 0.85rem;font-size:0.78rem;}

/* TABLE */
.table-card{background:var(--white);border:1px solid var(--gray-200);
            border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:0.88rem;}
thead{background:var(--gray-50);border-bottom:2px solid var(--gray-200);}
thead th{text-align:left;padding:0.85rem 1rem;font-size:0.72rem;font-weight:800;
         text-transform:uppercase;letter-spacing:0.06em;color:var(--navy-800);white-space:nowrap;}
tbody tr{border-bottom:1px solid var(--gray-200);transition:background 0.12s;}
tbody tr:hover{background:var(--navy-50);}
tbody tr:last-child{border-bottom:none;}
tbody td{padding:1rem;vertical-align:middle;}

.user-cell{display:flex;align-items:center;gap:0.85rem;}
.user-avatar{width:40px;height:40px;border-radius:50%;background:var(--gradient-header);
             color:var(--white);display:flex;align-items:center;justify-content:center;
             font-weight:700;font-size:0.85rem;flex-shrink:0;
             box-shadow:0 2px 6px rgba(10,25,49,0.15);}
.user-avatar.gold{background:var(--gradient-gold);color:#333;}
.user-avatar.blue{background:linear-gradient(135deg,#1565C0,#0D47A1);}
.user-name{font-weight:600;color:var(--navy-900);font-size:0.92rem;}
.user-email{font-size:0.78rem;color:var(--gray-500);}

.role-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;
           border-radius:var(--radius-pill);font-size:0.68rem;font-weight:800;
           text-transform:uppercase;letter-spacing:0.05em;}
.role-chip.admin{background:var(--gradient-gold);color:#333;}
.role-chip.staff{background:var(--info-bg);color:var(--info-blue);}
.role-chip.user{background:var(--forest-100);color:var(--forest-800);}

.status-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;
             border-radius:var(--radius-pill);font-size:0.7rem;font-weight:700;}
.status-chip.active{background:var(--status-bg);color:var(--status-green);}
.status-chip.inactive{background:var(--gray-200);color:var(--gray-600);}
.status-chip .dot{width:6px;height:6px;border-radius:50%;background:currentColor;}

.row-actions{display:flex;gap:0.4rem;flex-wrap:wrap;}
.row-actions form{display:inline;}
.act-btn{display:inline-flex;align-items:center;gap:5px;padding:0.35rem 0.75rem;
         border-radius:var(--radius-sm);font-size:0.72rem;font-weight:700;
         cursor:pointer;border:none;text-decoration:none;font-family:inherit;
         transition:all 0.15s;white-space:nowrap;}
.act-make-staff{background:var(--info-bg);color:var(--info-blue);}
.act-make-staff:hover{background:#BBDEFB;}
.act-make-admin{background:var(--yellow-bg);color:#B8860B;}
.act-make-admin:hover{background:#FFF3C4;}
.act-make-user{background:var(--forest-100);color:var(--forest-800);}
.act-make-user:hover{background:#D8F3DC;}
.act-deactivate{background:var(--gray-200);color:var(--gray-600);}
.act-deactivate:hover{background:var(--gray-300);}
.act-delete{background:var(--crimson-bg);color:var(--crimson);}
.act-delete:hover{background:#F8BBD0;}

.empty{text-align:center;padding:3rem 1rem;color:var(--gray-500);}
.empty i{font-size:2.5rem;color:var(--gray-300);display:block;margin-bottom:0.75rem;}

footer{background:var(--navy-900);color:rgba(255,255,255,0.75);text-align:center;
       padding:1.75rem 5vw;border-top:3px solid var(--forest-700);margin-top:2rem;}
footer p{font-size:0.88rem;}
footer p.sub{opacity:0.6;font-size:0.8rem;margin-top:0.3rem;}

/* ============================================================
   ADD USER MODAL
   ============================================================ */
.modal-overlay{
    display:none;position:fixed;inset:0;background:rgba(10,25,49,0.7);
    backdrop-filter:blur(8px);z-index:2000;
    align-items:center;justify-content:center;padding:20px;
    overflow-y:auto;
}
.modal-overlay.active{display:flex;}
.modal-card{
    background:var(--white);border-radius:var(--radius-lg);
    width:100%;max-width:560px;max-height:92vh;overflow-y:auto;
    box-shadow:var(--shadow-xl);
    animation:modalPop 0.35s cubic-bezier(0.16,1,0.3,1);
    position:relative;
}
@keyframes modalPop{
    from{opacity:0;transform:scale(0.94) translateY(20px);}
    to{opacity:1;transform:scale(1) translateY(0);}
}
.modal-header{
    padding:1.5rem 1.75rem 1rem;
    border-bottom:1px solid var(--gray-200);
    display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;
    background:var(--gradient-gold);border-radius:var(--radius-lg) var(--radius-lg) 0 0;
}
.modal-header h2{
    font-size:1.15rem;font-weight:800;color:#333;
    display:flex;align-items:center;gap:10px;line-height:1.3;
}
.modal-header p{font-size:0.82rem;color:#555;margin-top:2px;}
.modal-close{
    background:rgba(0,0,0,0.15);border:none;color:#333;
    width:36px;height:36px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:1rem;flex-shrink:0;
    transition:all 0.15s;
}
.modal-close:hover{background:rgba(0,0,0,0.3);color:#fff;transform:rotate(90deg);}

.modal-body{padding:1.5rem 1.75rem;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;margin-bottom:0.85rem;}
.form-group{margin-bottom:0.85rem;}
.form-group label{
    display:block;font-size:0.72rem;font-weight:800;
    color:var(--navy-800);text-transform:uppercase;letter-spacing:0.06em;
    margin-bottom:0.4rem;
}
.form-group label .req{color:var(--crimson);margin-left:2px;}
.form-group input,.form-group select{
    width:100%;padding:0.7rem 0.9rem;
    border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);
    font-family:inherit;font-size:0.9rem;background:var(--white);
    color:var(--navy-900);transition:all 0.15s;
}
.form-group input:focus,.form-group select:focus{
    outline:none;border-color:var(--navy-500);
    box-shadow:0 0 0 3px rgba(46,95,150,0.15);
}
.form-group input::placeholder{color:var(--gray-400);}

.role-picker{display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-top:0.4rem;}
.role-option{
    position:relative;cursor:pointer;
}
.role-option input{position:absolute;opacity:0;pointer-events:none;}
.role-option .role-box{
    display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:0.7rem 0.4rem;border:2px solid var(--gray-200);
    border-radius:var(--radius-sm);transition:all 0.15s;
    background:var(--gray-50);text-align:center;
}
.role-option .role-box i{font-size:1.1rem;color:var(--gray-500);}
.role-option .role-box span{font-size:0.72rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.04em;}
.role-option:hover .role-box{border-color:var(--gray-400);}
.role-option input:checked + .role-box{
    border-color:var(--forest-700);background:var(--forest-100);
}
.role-option input:checked + .role-box i{color:var(--forest-700);}
.role-option input:checked + .role-box span{color:var(--forest-800);}
.role-option input[value="admin"]:checked + .role-box{
    border-color:#B8860B;background:var(--yellow-bg);
}
.role-option input[value="admin"]:checked + .role-box i{color:#B8860B;}
.role-option input[value="admin"]:checked + .role-box span{color:#8B6508;}
.role-option input[value="staff"]:checked + .role-box{
    border-color:var(--info-blue);background:var(--info-bg);
}
.role-option input[value="staff"]:checked + .role-box i{color:var(--info-blue);}
.role-option input[value="staff"]:checked + .role-box span{color:var(--info-blue);}

.pw-hint{font-size:0.7rem;color:var(--gray-500);margin-top:0.3rem;}

.modal-error{
    background:var(--crimson-bg);color:var(--crimson);
    padding:10px 14px;border-radius:var(--radius-sm);
    border-left:3px solid var(--crimson);
    font-size:0.83rem;margin-bottom:1rem;
    display:flex;align-items:center;gap:8px;
}

.modal-footer{
    padding:1rem 1.75rem 1.5rem;
    display:flex;gap:0.6rem;justify-content:flex-end;flex-wrap:wrap;
    border-top:1px solid var(--gray-200);
    background:var(--gray-50);
    border-radius:0 0 var(--radius-lg) var(--radius-lg);
}
.btn-cancel{
    background:var(--white);color:var(--gray-600);
    border:1.5px solid var(--gray-300);
    padding:0.7rem 1.4rem;border-radius:var(--radius-sm);
    font-family:inherit;font-size:0.88rem;font-weight:600;
    cursor:pointer;transition:all 0.15s;
}
.btn-cancel:hover{background:var(--gray-100);border-color:var(--gray-400);}

.btn-create{
    background:var(--forest-700);color:var(--white);
    border:none;padding:0.7rem 1.6rem;border-radius:var(--radius-sm);
    font-family:inherit;font-size:0.88rem;font-weight:700;
    cursor:pointer;transition:all 0.15s;
    display:inline-flex;align-items:center;gap:8px;
    box-shadow:0 6px 16px rgba(64,145,108,0.3);
}
.btn-create:hover{background:var(--forest-800);transform:translateY(-1px);box-shadow:0 8px 20px rgba(64,145,108,0.4);}
.btn-create:disabled{opacity:0.7;cursor:not-allowed;transform:none;}

@media (max-width:900px){
    .filters form{grid-template-columns:1fr 1fr;}
    .filters form .field-full{grid-column:1 / -1;}
    .filters form .btn{grid-column:1 / -1;}
}
@media (max-width:768px){
    body{padding-top:76px;}
    .navbar{padding:0.6rem 1.25rem;flex-wrap:wrap;gap:0.5rem;}
    .mobile-toggle{display:block;}
    .nav-clock{font-size:0.8rem;padding:0.3rem 0.6rem;}
    .nav-clock .clock-time{font-size:0.82rem;}
    .nav-clock .clock-date{font-size:0.58rem;}
    .nav-links{display:none;width:100%;flex-direction:column;align-items:stretch;
               padding:0.75rem 0 0.25rem;border-top:1px solid var(--gray-200);margin-top:0.5rem;}
    .nav-links.active{display:flex;}
    .nav-links a{padding:0.7rem 1rem;}
    .logo{font-size:1.2rem;gap:10px;}
    .logo-circle{width:48px;height:48px;}
    .logo-text em{display:none;}
    .container{padding:1.5rem 1.25rem 2.5rem;}
    .page-hero{padding:1.5rem;flex-direction:column;align-items:stretch;}
    .page-hero-stats{justify-content:space-around;}
    .hero-add-btn{justify-content:center;width:100%;margin-top:0.5rem;}
    thead{display:none;}
    tbody tr{display:block;padding:1rem;border-bottom:1px solid var(--gray-200);}
    tbody td{display:block;padding:0.35rem 0;border:none;}
    tbody td::before{content:attr(data-label);display:block;font-size:0.68rem;
                     font-weight:800;color:var(--gray-500);text-transform:uppercase;
                     letter-spacing:0.06em;margin-bottom:2px;}
    .row-actions{margin-top:0.5rem;}
    .form-row{grid-template-columns:1fr;}
    .role-picker{grid-template-columns:1fr 1fr 1fr;}
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

    <!-- LIVE CLOCK -->
    <div class="nav-clock" id="liveClock">
        <div class="clock-time" id="clockTime">--:--:--</div>
        <div class="clock-date" id="clockDate">---</div>
    </div>

    <button class="mobile-toggle" onclick="toggleMenu()" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links" id="navbarNav">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage_incidents.php"><i class="fas fa-clipboard-check"></i> Reports</a>
        <a href="manage_users.php" class="active"><i class="fas fa-users-cog"></i> Users</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="container">

    <!-- PAGE HERO -->
    <div class="page-hero">
        <div class="page-hero-left">
            <div class="page-hero-icon"><i class="fas fa-users-cog"></i></div>
            <div>
                <h1>User &amp; Staff Management</h1>
                <p>Manage roles, activate/deactivate accounts, and oversee the community</p>
            </div>
        </div>

        <div class="page-hero-stats">
            <div class="hero-stat">
                <div class="num"><?= $statAdmins ?></div>
                <div class="lbl">Admins</div>
            </div>
            <div class="hero-stat">
                <div class="num"><?= $statStaff ?></div>
                <div class="lbl">Staff</div>
            </div>
            <div class="hero-stat">
                <div class="num"><?= $statUsers ?></div>
                <div class="lbl">Users</div>
            </div>

            <button type="button" class="hero-add-btn" onclick="openAddModal()">
                <i class="fas fa-user-plus"></i> Add User / Staff
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="flash flash-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="flash flash-success">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="filters">
        <form method="GET">
            <div class="field field-full">
                <label for="q">Search</label>
                <input type="text" id="q" name="q"
                       placeholder="Name or email…"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="">All roles</option>
                    <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="staff" <?= $filter_role === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="user"  <?= $filter_role === 'user'  ? 'selected' : '' ?>>User</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
    </div>

    <!-- USERS TABLE -->
    <div class="table-card">
        <?php if ($users && mysqli_num_rows($users) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($u = mysqli_fetch_assoc($users)):
                        $role = strtolower($u['role'] ?? 'user');
                        $initials = strtoupper(substr($u['firstname'] ?? '?', 0, 1) . substr($u['lastname'] ?? '?', 0, 1));
                        $isMe = ((int)$u['id'] === $user_id);
                        $isOnline = ((int)$u['is_online'] === 1);

                        $avatarCls = $role === 'admin' ? 'gold' : ($role === 'staff' ? 'blue' : '');
                    ?>
                        <tr>
                            <td data-label="User">
                                <div class="user-cell">
                                    <div class="user-avatar <?= $avatarCls ?>"><?= $initials ?></div>
                                    <div>
                                        <div class="user-name">
                                            <?= htmlspecialchars(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? '')) ?>
                                            <?php if ($isMe): ?>
                                                <span style="color:var(--forest-700);font-size:0.75rem;">(You)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Role">
                                <span class="role-chip <?= $role ?>">
                                    <i class="fas fa-<?= $role === 'admin' ? 'crown' : ($role === 'staff' ? 'user-shield' : 'user') ?>"></i>
                                    <?= $role ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="status-chip <?= $isOnline ? 'active' : 'inactive' ?>">
                                    <span class="dot"></span>
                                    <?= $isOnline ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td data-label="Last Active">
                                <?php
                                if (empty($u['last_active'])) {
                                    echo '<span style="color:var(--gray-400);">Never</span>';
                                } else {
                                    $mins = max(0, (int)((time() - strtotime($u['last_active'])) / 60));
                                    if ($mins < 1) echo 'Just now';
                                    elseif ($mins < 60) echo $mins . 'm ago';
                                    elseif ($mins < 1440) echo floor($mins/60) . 'h ago';
                                    else echo floor($mins/1440) . 'd ago';
                                }
                                ?>
                            </td>
                            <td data-label="Actions">
                                <div class="row-actions">
                                    <?php if (!$isMe): ?>
                                        <?php if ($role !== 'staff'): ?>
                                            <form method="POST" onsubmit="return confirm('Promote to STAFF?');">
                                                <input type="hidden" name="change_role" value="1">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="new_role" value="staff">
                                                <button type="submit" class="act-btn act-make-staff">
                                                    <i class="fas fa-user-shield"></i> Make Staff
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($role !== 'admin'): ?>
                                            <form method="POST" onsubmit="return confirm('Promote to ADMIN?');">
                                                <input type="hidden" name="change_role" value="1">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="new_role" value="admin">
                                                <button type="submit" class="act-btn act-make-admin">
                                                    <i class="fas fa-crown"></i> Make Admin
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($role !== 'user'): ?>
                                            <form method="POST" onsubmit="return confirm('Demote to USER?');">
                                                <input type="hidden" name="change_role" value="1">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="new_role" value="user">
                                                <button type="submit" class="act-btn act-make-user">
                                                    <i class="fas fa-user"></i> Make User
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" onsubmit="return confirm('Delete this user permanently?');">
                                            <input type="hidden" name="delete_user" value="1">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="act-btn act-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);font-size:0.78rem;">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                <i class="fas fa-users-slash"></i>
                <p>No users match your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================
     ADD USER MODAL
     ============================================================ -->
<div class="modal-overlay <?= $show_add_modal ? 'active' : '' ?>" id="addUserModal">
    <div class="modal-card" role="dialog" aria-labelledby="addUserTitle" aria-modal="true">
        <div class="modal-header">
            <div>
                <h2 id="addUserTitle">
                    <i class="fas fa-user-plus"></i> Create New Account
                </h2>
                <p>Add a new user, staff member, or administrator</p>
            </div>
            <button type="button" class="modal-close" onclick="closeAddModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="addUserForm" autocomplete="off">
            <div class="modal-body">
                <?php if ($show_add_modal && $error): ?>
                    <div class="modal-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="create_user" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="firstname">First Name <span class="req">*</span></label>
                        <input type="text" id="firstname" name="firstname"
                               placeholder="Juan"
                               value="<?= htmlspecialchars($old['firstname']) ?>"
                               required maxlength="80">
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name <span class="req">*</span></label>
                        <input type="text" id="lastname" name="lastname"
                               placeholder="Dela Cruz"
                               value="<?= htmlspecialchars($old['lastname']) ?>"
                               required maxlength="80">
                    </div>
                </div>

                <div class="form-group">
                    <label for="add_email">Email Address <span class="req">*</span></label>
                    <input type="email" id="add_email" name="email"
                           placeholder="juan.delacruz@example.com"
                           value="<?= htmlspecialchars($old['email']) ?>"
                           required maxlength="180" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="add_phone">Phone Number <small style="text-transform:none;font-weight:400;color:var(--gray-400);">(optional)</small></label>
                    <input type="tel" id="add_phone" name="phone"
                           placeholder="+63 912 345 6789"
                           value="<?= htmlspecialchars($old['phone']) ?>"
                           maxlength="30">
                </div>

                <div class="form-group">
                    <label>Role <span class="req">*</span></label>
                    <div class="role-picker">
                        <label class="role-option">
                            <input type="radio" name="role" value="user"
                                <?= $old['role'] === 'user' ? 'checked' : '' ?>>
                            <div class="role-box">
                                <i class="fas fa-user"></i>
                                <span>User</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="staff"
                                <?= $old['role'] === 'staff' ? 'checked' : '' ?>>
                            <div class="role-box">
                                <i class="fas fa-user-shield"></i>
                                <span>Staff</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin"
                                <?= $old['role'] === 'admin' ? 'checked' : '' ?>>
                            <div class="role-box">
                                <i class="fas fa-crown"></i>
                                <span>Admin</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_password">Password <span class="req">*</span></label>
                        <input type="password" id="add_password" name="password"
                               placeholder="Min 6 characters"
                               required minlength="6" autocomplete="new-password">
                        <div class="pw-hint">At least 6 characters</div>
                    </div>
                    <div class="form-group">
                        <label for="add_password_confirm">Confirm Password <span class="req">*</span></label>
                        <input type="password" id="add_password_confirm" name="password_confirm"
                               placeholder="Re-enter password"
                               required minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">
                    Cancel
                </button>
                <button type="submit" class="btn-create" id="createUserBtn">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> SmartEcoTrack · Environmental Incident Reporting & Monitoring System</p>
    <p class="sub">Working together for a safer, greener Philippines 🌿</p>
</footer>

<script>
    /* LIVE CLOCK */
    (function liveClock() {
        const timeEl = document.getElementById('clockTime');
        const dateEl = document.getElementById('clockDate');
        function pad(n) { return n < 10 ? '0' + n : n; }
        function tick() {
            const now = new Date();
            let h = now.getHours();
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; if (h === 0) h = 12;
            timeEl.textContent = pad(h) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ampm;
            const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            dateEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
        }
        tick();
        setInterval(tick, 1000);
    })();

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

    /* ---------- ADD USER MODAL ---------- */
    const addModal = document.getElementById('addUserModal');

    function openAddModal() {
        addModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const fn = document.getElementById('firstname');
            if (fn) fn.focus();
        }, 250);
    }
    function closeAddModal() {
        addModal.classList.remove('active');
        document.body.style.overflow = '';
    }
    addModal.addEventListener('click', function (e) {
        if (e.target === this) closeAddModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && addModal.classList.contains('active')) closeAddModal();
    });

    /* Loading state */
    document.getElementById('addUserForm').addEventListener('submit', function (e) {
        const pw  = document.getElementById('add_password');
        const pw2 = document.getElementById('add_password_confirm');

        if (pw.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters.');
            pw.focus();
            return;
        }
        if (pw.value !== pw2.value) {
            e.preventDefault();
            alert('Passwords do not match. Please try again.');
            pw2.focus();
            return;
        }

        const btn = document.getElementById('createUserBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating…';
    });
</script>
</body>
</html>