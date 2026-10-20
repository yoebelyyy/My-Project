<?php
/* ============================================================
   FILE: login.php
   Purpose: Secure login page for SmartEcoTrack
   Version: 2.1 (demo credentials removed)
   ============================================================ */

require_once 'db_connect.php';

/* ---------- Ensure session is started ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Redirect if already logged in ---------- */
if (isLoggedIn()) {
    redirect('dashboard.php');
}

/* ---------- Initialize flash messages ---------- */
$error   = '';
$success = '';

if (isset($_GET['registered']))    $success = 'Registration successful! Please login with your credentials.';
if (isset($_GET['logout']))        $success = 'You have been logged out successfully.';
if (isset($_GET['expired']))       $error   = 'Your session has expired. Please login again.';
if (isset($_GET['reset_success'])) $success = 'Password reset successful! Please login.';

/* ---------- CSRF token (per session) ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Login attempt throttle ---------- */
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts']   = 0;
    $_SESSION['login_lock_until'] = 0;
}

/* ---------- Handle POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) CSRF check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = 'Invalid form submission. Please refresh and try again.';
    }

    // 2) Lockout check
    elseif ($_SESSION['login_lock_until'] > time()) {
        $wait  = (int)($_SESSION['login_lock_until'] - time());
        $error = "Too many failed attempts. Please try again in {$wait} seconds.";
    }

    else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // 3) Lookup user with a prepared statement
            $user = null;
            $stmt = mysqli_prepare($conn,
                "SELECT id, firstname, lastname, email, password, role
                 FROM users WHERE email = ? LIMIT 1");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    $user = mysqli_fetch_assoc($result);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'A server error occurred. Please try again.';
            }

            // 4) Verify credentials
            if ($user && password_verify($password, $user['password'])) {

                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['firstname'] = $user['firstname'];
                $_SESSION['lastname']  = $user['lastname'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];

                // Reset throttle
                $_SESSION['login_attempts']   = 0;
                $_SESSION['login_lock_until'] = 0;

                // 5) Optional "remember me" token (30 days)
                if (!empty($_POST['remember'])) {
                    $rawToken = bin2hex(random_bytes(32));
                    $hash     = hash('sha256', $rawToken);

                    $ins = mysqli_prepare($conn,
                        "INSERT INTO user_tokens (user_id, token_hash, expires_at)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
                    if ($ins) {
                        mysqli_stmt_bind_param($ins, 'is', $user['id'], $hash);
                        mysqli_stmt_execute($ins);
                        mysqli_stmt_close($ins);

                        setcookie('remember_token', $rawToken, [
                            'expires'  => time() + 60 * 60 * 24 * 30,
                            'path'     => '/',
                            'secure'   => !empty($_SERVER['HTTPS']),
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                }

                // 6) Update presence (best-effort)
                $upd = mysqli_prepare($conn,
                    "UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'i', $user['id']);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }

                redirect('dashboard.php');
            } else {
                // Failed login — increment throttle
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_lock_until'] = time() + 60; // 60s lockout
                    $_SESSION['login_attempts']   = 0;
                    $error = 'Too many failed attempts. Please try again in 60 seconds.';
                } else {
                    $error = 'Invalid email or password. Please try again.';
                }
            }
        }
    }

    // Rotate CSRF token after each POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A1931">
    <title>Login · SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           CSS RESET & VARIABLES (matches dashboard theme)
           ============================================================ */
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
            --shadow-xl: 0 24px 48px rgba(10,25,49,0.18);

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-pill: 999px;

            --gradient-header: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            --gradient-brand:  linear-gradient(135deg, var(--forest-700), var(--forest-500, #52B788));
        }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--navy-900);
            color: var(--gray-800);
            line-height: 1.55;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(circle at 15% 20%, rgba(82,183,136,0.15), transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(46,95,150,0.25), transparent 50%),
                linear-gradient(135deg, #0A1931 0%, #112A4A 50%, #1A3A63 100%);
            z-index: 0;
        }

        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            color: rgba(82,183,136,0.12);
            font-size: 1.5rem;
            animation: floatParticle 22s infinite linear;
            will-change: transform, opacity;
        }
        @keyframes floatParticle {
            0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }

        .back-home {
            position: fixed;
            top: 24px;
            left: 24px;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            background: rgba(255,255,255,0.08);
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: var(--radius-pill);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.25s ease;
            box-shadow: 0 6px 20px -6px rgba(0,0,0,0.35);
        }
        .back-home i { font-size: 0.9rem; transition: transform 0.25s ease; }
        .back-home:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 10px 26px -8px rgba(0,0,0,0.5);
        }
        .back-home:hover i { transform: translateX(-3px); }
        .back-home:focus { outline: 2px solid var(--forest-400); outline-offset: 3px; }

        .back-inline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px dashed var(--gray-200);
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        .back-inline a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--navy-600);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-inline a:hover { color: var(--navy-800); text-decoration: underline; }
        .back-inline a i { transition: transform 0.2s; }
        .back-inline a:hover i { transform: translateX(-3px); }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1050px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 30px 60px -20px rgba(0,0,0,0.4),
                0 0 0 1px rgba(255,255,255,0.05);
            animation: slideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand-panel {
            background:
                linear-gradient(155deg, rgba(10,25,49,0.92), rgba(26,58,99,0.88)),
                url('https://images.unsplash.com/photo-1448375240586-882707db888b?w=1200&q=80') center/cover;
            color: var(--white);
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .brand-panel::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(82,183,136,0.25), transparent 70%);
            pointer-events: none;
        }
        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -100px; left: -60px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(46,95,150,0.35), transparent 70%);
            pointer-events: none;
        }

        .brand-header { position: relative; z-index: 1; }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }
        .brand-logo-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: var(--gradient-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px -8px rgba(82,183,136,0.6);
            flex-shrink: 0;
        }
        .brand-logo-icon i { font-size: 1.6rem; color: var(--white); }
        .brand-logo-text {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .brand-logo-text em {
            font-style: normal;
            font-weight: 400;
            color: rgba(255,255,255,0.65);
            display: block;
            font-size: 0.95rem;
            letter-spacing: 0;
        }

        .brand-title {
            font-size: clamp(1.5rem, 2.4vw, 2rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin-bottom: 14px;
        }
        .brand-title span {
            background: linear-gradient(135deg, #95D5B2, #52B788);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .brand-desc {
            font-size: 0.95rem;
            color: rgba(255,255,255,0.78);
            line-height: 1.65;
            max-width: 380px;
            margin-bottom: 32px;
        }

        .brand-features {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            transition: all 0.25s ease;
        }
        .feature-item:hover {
            background: rgba(255,255,255,0.11);
            transform: translateX(4px);
        }
        .feature-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: var(--gradient-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .feature-icon i { font-size: 0.95rem; color: var(--white); }
        .feature-text {
            font-size: 0.88rem;
            font-weight: 500;
            color: rgba(255,255,255,0.92);
            line-height: 1.35;
        }

        .brand-footer {
            position: relative;
            z-index: 1;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.5);
            margin-top: 32px;
        }

        .form-panel {
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
        }

        .form-panel h2 {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--navy-900);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .form-panel .form-subtitle {
            color: var(--gray-500);
            font-size: 0.92rem;
            margin-bottom: 28px;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 20px;
            animation: slideDown 0.35s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert i { flex-shrink: 0; font-size: 1rem; }
        .alert-error {
            background: var(--crimson-bg);
            color: var(--crimson);
            border-left: 4px solid var(--crimson);
        }
        .alert-success {
            background: var(--status-bg);
            color: var(--status-green);
            border-left: 4px solid var(--status-green);
        }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--navy-800);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }
        .input-wrap { position: relative; }
        .input-wrap > i.field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1rem;
            transition: color 0.2s;
            pointer-events: none;
            z-index: 1;
        }
        .input-wrap input {
            width: 100%;
            padding: 14px 46px 14px 46px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--navy-900);
            background: var(--gray-50);
            transition: all 0.2s ease;
        }
        .input-wrap input::placeholder { color: var(--gray-400); }
        .input-wrap input:hover { border-color: var(--gray-300); }
        .input-wrap input:focus {
            outline: none;
            border-color: var(--navy-500);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(46,95,150,0.12);
        }
        .input-wrap input:focus ~ i.field-icon { color: var(--navy-500); }

        .input-wrap input.input-error {
            border-color: var(--crimson);
            background: var(--crimson-bg);
        }

        .toggle-eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 1rem;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .toggle-eye:hover { color: var(--navy-600); background: var(--gray-100); }
        .toggle-eye:focus { outline: 2px solid var(--navy-500); outline-offset: 2px; }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 10px;
        }
        .remember-me {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
            cursor: pointer;
            user-select: none;
        }
        .remember-me input {
            width: 16px; height: 16px;
            accent-color: var(--forest-700);
            cursor: pointer;
        }
        .forgot-link {
            color: var(--navy-600);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link:hover { color: var(--navy-800); text-decoration: underline; }

        .btn-submit {
            width: 100%;
            padding: 14px 20px;
            background: var(--gradient-brand);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 8px 20px -8px rgba(64,145,108,0.5);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px -8px rgba(64,145,108,0.6);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:focus { outline: 2px solid var(--forest-700); outline-offset: 3px; }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            pointer-events: none;
        }
        .btn-submit.loading i { animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: var(--gray-500);
            font-size: 0.88rem;
        }
        .register-link a {
            color: var(--forest-700);
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
            transition: color 0.2s;
        }
        .register-link a:hover { color: var(--forest-800); text-decoration: underline; }

        @media (max-width: 900px) {
            .login-wrapper { grid-template-columns: 1fr; max-width: 520px; }
            .brand-panel { padding: 32px 28px; min-height: auto; }
            .brand-features { display: none; }
            .brand-desc { margin-bottom: 0; }
            .brand-footer { display: none; }
            .form-panel { padding: 36px 28px; }
        }
        @media (max-width: 480px) {
            body { padding: 12px; padding-top: 72px; }
            .login-wrapper { border-radius: 18px; }
            .brand-panel { padding: 24px 22px; }
            .brand-logo-icon { width: 48px; height: 48px; border-radius: 13px; }
            .brand-logo-icon i { font-size: 1.35rem; }
            .brand-title { font-size: 1.3rem; }
            .form-panel { padding: 28px 22px; }
            .form-panel h2 { font-size: 1.4rem; }
            .back-home { top: 12px; left: 12px; padding: 8px 14px; font-size: 0.8rem; }
            .back-home span { display: none; }
        }

        :focus-visible { outline: 2px solid var(--navy-600); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            .particle { display: none; }
        }
    </style>
</head>
<body>

<a href="index.php" class="back-home" aria-label="Back to home">
    <i class="fas fa-arrow-left" aria-hidden="true"></i>
    <span>Back to Home</span>
</a>

<div class="particles" aria-hidden="true" id="particles"></div>

<div class="login-wrapper">

    <!-- ============================================================
         LEFT — BRAND PANEL
         ============================================================ -->
    <aside class="brand-panel">
        <div class="brand-header">
            <div class="brand-logo">
                <div class="brand-logo-icon">
                    <i class="fas fa-leaf" aria-hidden="true"></i>
                </div>
                <div class="brand-logo-text">
                    SmartEcoTrack
                    <em>Environmental Monitoring System</em>
                </div>
            </div>

            <h1 class="brand-title">
                Protecting <span>communities</span><br>
                through real-time reporting.
            </h1>
            <p class="brand-desc">
                Report environmental incidents, access disaster preparedness guides,
                and help keep your community safe — all in one place.
            </p>
        </div>

        <div class="brand-features">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="feature-text">Report environmental incidents with location</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="feature-text">Disaster preparedness guides & tips</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-satellite-dish"></i></div>
                <div class="feature-text">Live emergency tracker for staff & admin</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="feature-text">Emergency contacts at your fingertips</div>
            </div>
        </div>

        <div class="brand-footer">
            © <?php echo date('Y'); ?> SmartEcoTrack · Safer, greener Philippines 🌿
        </div>
    </aside>

    <!-- ============================================================
         RIGHT — LOGIN FORM
         ============================================================ -->
    <main class="form-panel">
        <h2>Welcome back</h2>
        <p class="form-subtitle">Sign in to continue to your dashboard.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" autocomplete="on" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope field-icon" aria-hidden="true"></i>
                    <input type="email"
                           id="email"
                           name="email"
                           placeholder="you@example.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                           autocomplete="email"
                           required
                           autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock field-icon" aria-hidden="true"></i>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           required>
                    <button type="button"
                            class="toggle-eye"
                            id="togglePassword"
                            aria-label="Show password">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" value="1"
                        <?php echo !empty($_POST['remember']) ? 'checked' : ''; ?>>
                    Remember me
                </label>
                <a href="forgot_password.php" class="forgot-link">
                    <i class="fas fa-key" aria-hidden="true"></i> Forgot password?
                </a>
            </div>

            <button type="submit" class="btn-submit" id="loginBtn">
                <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                <span>Sign In</span>
            </button>
        </form>

        <p class="register-link">
            Don't have an account?
            <a href="register.php">Create one <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </p>

        <div class="back-inline">
            <a href="index.php">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                Back to Home
            </a>
        </div>
    </main>
</div>

<script>
    /* ---------- Password visibility toggle ---------- */
    (function () {
        const toggleBtn = document.getElementById('togglePassword');
        const pwd       = document.getElementById('password');
        if (!toggleBtn || !pwd) return;

        toggleBtn.addEventListener('click', function () {
            const showing = pwd.type === 'text';
            pwd.type = showing ? 'password' : 'text';
            const icon = toggleBtn.querySelector('i');
            icon.classList.toggle('fa-eye', showing);
            icon.classList.toggle('fa-eye-slash', !showing);
            toggleBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    })();

    /* ---------- Loading state on submit (with safe focus) ---------- */
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        const emailEl = document.getElementById('email');
        const pwdEl   = document.getElementById('password');

        if (!emailEl.value.trim() || !pwdEl.value) {
            e.preventDefault();
            emailEl.classList.toggle('input-error', !emailEl.value.trim());
            pwdEl.classList.toggle('input-error', !pwdEl.value);
            (!emailEl.value.trim() ? emailEl : pwdEl).focus();
            return;
        }

        emailEl.classList.remove('input-error');
        pwdEl.classList.remove('input-error');

        const btn  = document.getElementById('loginBtn');
        const icon = btn.querySelector('i');
        const span = btn.querySelector('span');

        btn.classList.add('loading');
        btn.disabled = true;
        span.textContent = 'Signing in…';
        icon.classList.replace('fa-sign-in-alt', 'fa-spinner');
    });

    /* ---------- Clear error style as user types ---------- */
    ['email', 'password'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function () {
                el.classList.remove('input-error');
            });
        }
    });

    /* ---------- Floating leaf particles ---------- */
    (function createParticles() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const container = document.getElementById('particles');
        if (!container) return;

        const glyphs = ['🌿', '🍃', '🌱', '🌾', '🍂'];
        const count  = window.innerWidth < 768 ? 10 : 18;
        const frag   = document.createDocumentFragment();

        for (let i = 0; i < count; i++) {
            const p = document.createElement('span');
            p.className = 'particle';
            p.textContent = glyphs[Math.floor(Math.random() * glyphs.length)];
            p.style.left = Math.random() * 100 + '%';
            p.style.fontSize = (Math.random() * 1.2 + 0.9) + 'rem';
            p.style.animationDuration = (Math.random() * 15 + 15) + 's';
            p.style.animationDelay = (Math.random() * -25) + 's';
            frag.appendChild(p);
        }
        container.appendChild(frag);
    })();
</script>
</body>
</html>