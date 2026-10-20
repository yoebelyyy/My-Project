<?php
/* ============================================================
   FILE: register.php
   Purpose: User registration with validation
   ============================================================ */
require_once 'db_connect.php';
if (isLoggedIn()) redirect('dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = sanitize($_POST['firstname'] ?? '');
    $lastname  = sanitize($_POST['lastname']  ?? '');
    $email     = sanitize($_POST['email']     ?? '');
    $password  = $_POST['password']         ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $emailEsc = mysqli_real_escape_string($conn, $email);
        $check    = mysqli_query($conn, "SELECT id FROM users WHERE email='$emailEsc'");

        if ($check && mysqli_num_rows($check) > 0) {
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql  = "INSERT INTO users (firstname, lastname, email, password)
                     VALUES ('$firstname', '$lastname', '$email', '$hash')";
            if (mysqli_query($conn, $sql)) {
                $success = 'Registration successful! Redirecting to login…';
                header("refresh:2;url=login.php?registered=1");
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A1931">
    <title>Create Account · SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --forest-500: #74C69D;
            --forest-400: #95D5B2;
            --forest-200: #D8F3DC;
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

            --shadow-sm: 0 1px 3px rgba(10,25,49,0.06), 0 1px 2px rgba(10,25,49,0.04);
            --shadow-md: 0 4px 12px rgba(10,25,49,0.08), 0 2px 4px rgba(10,25,49,0.04);
            --shadow-lg: 0 12px 32px rgba(10,25,49,0.10), 0 4px 12px rgba(10,25,49,0.05);
            --shadow-xl: 0 24px 56px rgba(10,25,49,0.14), 0 8px 20px rgba(10,25,49,0.06);

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-pill: 999px;

            --gradient-header: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            --gradient-accent: linear-gradient(135deg, var(--forest-800), var(--forest-600));
        }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
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

        /* Layered background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(circle at 15% 20%, rgba(82,183,136,0.18), transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(46,95,150,0.30), transparent 50%),
                linear-gradient(135deg, #0A1931 0%, #112A4A 50%, #1A3A63 100%);
            z-index: 0;
        }

        /* Floating particles */
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

        /* Back to home button */
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
        .back-home i { transition: transform 0.25s ease; }
        .back-home:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .back-home:hover i { transform: translateX(-3px); }
        .back-home:focus {
            outline: 2px solid var(--forest-400);
            outline-offset: 3px;
        }

        /* Main container */
        .register-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1080px;
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
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

        /* ============================================================
           LEFT — BRAND PANEL
           ============================================================ */
        .brand-panel {
            background:
                linear-gradient(155deg, rgba(10,25,49,0.92), rgba(26,58,99,0.88)),
                url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1200&q=80') center/cover;
            color: var(--white);
            padding: 48px 42px;
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
            background: var(--gradient-accent);
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
            font-size: 0.92rem;
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
            margin-bottom: 28px;
        }

        .brand-features {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
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
            width: 32px; height: 32px;
            border-radius: 9px;
            background: var(--gradient-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .feature-icon i { font-size: 0.85rem; color: var(--white); }
        .feature-text {
            font-size: 0.86rem;
            font-weight: 500;
            color: rgba(255,255,255,0.92);
            line-height: 1.35;
        }

        .brand-footer {
            position: relative;
            z-index: 1;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.5);
            margin-top: 28px;
        }

        /* ============================================================
           RIGHT — REGISTER FORM
           ============================================================ */
        .form-panel {
            padding: 44px 42px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
        }

        .form-panel h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--navy-900);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .form-panel .form-subtitle {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 24px;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.86rem;
            font-weight: 500;
            margin-bottom: 18px;
            animation: slideDown 0.35s ease;
            border-left: 4px solid transparent;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert i { flex-shrink: 0; font-size: 0.95rem; }
        .alert-error {
            background: var(--crimson-bg);
            color: var(--crimson);
            border-left-color: var(--crimson);
        }
        .alert-success {
            background: var(--status-bg);
            color: var(--status-green);
            border-left-color: var(--status-green);
        }

        /* Rows */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--navy-800);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        /* Inputs */
        .input-wrap {
            position: relative;
        }
        .input-wrap > i.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
            transition: color 0.2s;
            pointer-events: none;
            z-index: 1;
        }
        .input-wrap input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.92rem;
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
        .input-wrap input:focus ~ i.field-icon {
            color: var(--navy-500);
        }

        /* Password input — extra right padding for toggle */
        .input-wrap.password input { padding-right: 44px; }
        .toggle-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 0.95rem;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .toggle-eye:hover { color: var(--navy-600); background: var(--gray-100); }
        .toggle-eye:focus { outline: 2px solid var(--navy-500); outline-offset: 2px; }

        /* Password strength meter */
        .strength-meter {
            margin-top: 8px;
            display: flex;
            gap: 4px;
        }
        .strength-meter .bar {
            flex: 1;
            height: 4px;
            background: var(--gray-200);
            border-radius: var(--radius-pill);
            transition: background 0.3s ease;
        }
        .strength-label {
            font-size: 0.72rem;
            font-weight: 600;
            margin-top: 6px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .strength-label.weak   { color: var(--crimson); }
        .strength-label.fair   { color: var(--amber); }
        .strength-label.good   { color: #1976D2; }
        .strength-label.strong { color: var(--status-green); }

        /* Password match indicator */
        .match-hint {
            font-size: 0.72rem;
            font-weight: 600;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--gray-500);
        }
        .match-hint.ok    { color: var(--status-green); }
        .match-hint.error { color: var(--crimson); }

        /* Submit */
        .btn-submit {
            width: 100%;
            padding: 13px 20px;
            margin-top: 8px;
            background: var(--gradient-accent);
            color: var(--white);
            border: none;
            border-radius: 10px;
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
            box-shadow: 0 8px 20px -8px rgba(64,145,108,0.55);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px -8px rgba(64,145,108,0.65);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:focus {
            outline: 2px solid var(--forest-700);
            outline-offset: 3px;
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            pointer-events: none;
        }
        .btn-submit.loading i {
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 18px;
            color: var(--gray-500);
            font-size: 0.88rem;
        }
        .login-link a {
            color: var(--forest-700);
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
            transition: color 0.2s;
        }
        .login-link a:hover {
            color: var(--forest-800);
            text-decoration: underline;
        }

        /* Terms note */
        .terms-note {
            margin-top: 16px;
            padding: 10px 14px;
            background: var(--navy-50);
            border-radius: 10px;
            font-size: 0.75rem;
            color: var(--gray-500);
            line-height: 1.55;
            text-align: center;
        }
        .terms-note i { color: var(--navy-600); margin-right: 4px; }
        .terms-note a { color: var(--navy-700); font-weight: 600; text-decoration: none; }
        .terms-note a:hover { text-decoration: underline; }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .register-wrapper {
                grid-template-columns: 1fr;
                max-width: 520px;
            }
            .brand-panel {
                padding: 32px 28px;
                min-height: auto;
            }
            .brand-features { display: none; }
            .brand-desc { margin-bottom: 0; }
            .brand-footer { display: none; }
            .form-panel { padding: 32px 28px; }
        }
        @media (max-width: 520px) {
            body { padding: 12px; padding-top: 72px; }
            .register-wrapper { border-radius: 18px; }
            .brand-panel { padding: 24px 22px; }
            .brand-logo-icon { width: 48px; height: 48px; border-radius: 13px; }
            .brand-logo-icon i { font-size: 1.35rem; }
            .brand-title { font-size: 1.3rem; }
            .form-panel { padding: 26px 22px; }
            .form-panel h2 { font-size: 1.35rem; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
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

<!-- Floating particles -->
<div class="particles" id="particles" aria-hidden="true"></div>

<!-- Back to home -->
<a href="index.php" class="back-home" aria-label="Back to home">
    <i class="fas fa-arrow-left" aria-hidden="true"></i>
    <span>Back to Home</span>
</a>

<div class="register-wrapper">

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
                Join a community<br>
                that protects <span>communities</span>.
            </h1>
            <p class="brand-desc">
                Create your free account to report environmental incidents,
                access disaster guides, and stay prepared — all in one place.
            </p>
        </div>

        <div class="brand-features">
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="feature-text">Report environmental incidents with location</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="feature-text">Access disaster preparedness guides</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="feature-text">Emergency contacts at your fingertips</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-brain"></i></div>
                <div class="feature-text">Interactive quiz to test your knowledge</div>
            </div>
        </div>

        <div class="brand-footer">
            © <?php echo date('Y'); ?> SmartEcoTrack · Safer, greener Philippines 🌿
        </div>
    </aside>

    <!-- ============================================================
         RIGHT — REGISTER FORM
         ============================================================ -->
    <main class="form-panel">
        <h2>Create your account</h2>
        <p class="form-subtitle">It only takes a minute — start making an impact.</p>

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

        <form method="POST" id="registerForm" novalidate autocomplete="on">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstname">First name</label>
                    <div class="input-wrap">
                        <i class="fas fa-user field-icon" aria-hidden="true"></i>
                        <input type="text"
                               id="firstname"
                               name="firstname"
                               placeholder="Juan"
                               value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>"
                               autocomplete="given-name"
                               required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="lastname">Last name</label>
                    <div class="input-wrap">
                        <i class="fas fa-user field-icon" aria-hidden="true"></i>
                        <input type="text"
                               id="lastname"
                               name="lastname"
                               placeholder="Dela Cruz"
                               value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>"
                               autocomplete="family-name"
                               required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope field-icon" aria-hidden="true"></i>
                    <input type="email"
                           id="email"
                           name="email"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           autocomplete="email"
                           required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap password">
                        <i class="fas fa-lock field-icon" aria-hidden="true"></i>
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="Min. 6 characters"
                               autocomplete="new-password"
                               minlength="6"
                               required>
                        <button type="button"
                                class="toggle-eye"
                                id="togglePassword"
                                aria-label="Show password">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <!-- Strength meter -->
                    <div class="strength-meter" id="strengthMeter" aria-hidden="true">
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel">At least 6 characters</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <div class="input-wrap password">
                        <i class="fas fa-lock field-icon" aria-hidden="true"></i>
                        <input type="password"
                               id="confirm_password"
                               name="confirm_password"
                               placeholder="Repeat password"
                               autocomplete="new-password"
                               required>
                        <button type="button"
                                class="toggle-eye"
                                id="toggleConfirm"
                                aria-label="Show confirm password">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="match-hint" id="matchHint"></div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="registerBtn">
                <i class="fas fa-user-plus" aria-hidden="true"></i>
                <span>Create Account</span>
            </button>
        </form>

        <p class="login-link">
            Already have an account?
            <a href="login.php">Sign in <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </p>

        <div class="terms-note">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            By creating an account you agree to our
            <a href="terms.php">Terms</a> and <a href="privacy.php">Privacy Policy</a>.
        </div>
    </main>
</div>

<script>
    /* ---------- Password visibility toggles ---------- */
    (function () {
        const pwd     = document.getElementById('password');
        const conf    = document.getElementById('confirm_password');
        const tPwd    = document.getElementById('togglePassword');
        const tConf   = document.getElementById('toggleConfirm');

        function bindToggle(btn, input) {
            if (!btn || !input) return;
            btn.addEventListener('click', function () {
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                const icon = btn.querySelector('i');
                icon.classList.toggle('fa-eye', showing);
                icon.classList.toggle('fa-eye-slash', !showing);
                btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        }

        bindToggle(tPwd,  pwd);
        bindToggle(tConf, conf);
    })();

    /* ---------- Password strength meter ---------- */
    (function () {
        const pwd    = document.getElementById('password');
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
                    if (score <= 1) b.style.background = '#C62828';       // weak
                    else if (score === 2) b.style.background = '#E65100'; // fair
                    else if (score === 3) b.style.background = '#1976D2'; // good
                    else b.style.background = '#2E7D32';                  // strong
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

    /* ---------- Password match indicator ---------- */
    (function () {
        const pwd   = document.getElementById('password');
        const conf  = document.getElementById('confirm_password');
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

    /* ---------- Loading state on submit ---------- */
    document.getElementById('registerForm').addEventListener('submit', function (e) {
        const firstname = document.getElementById('firstname').value.trim();
        const lastname  = document.getElementById('lastname').value.trim();
        const email     = document.getElementById('email').value.trim();
        const password  = document.getElementById('password').value;
        const confirm   = document.getElementById('confirm_password').value;

        if (!firstname || !lastname || !email || !password || !confirm) {
            e.preventDefault();
            return;
        }
        if (password !== confirm) {
            e.preventDefault();
            return;
        }

        const btn  = document.getElementById('registerBtn');
        const icon = btn.querySelector('i');
        const span = btn.querySelector('span');

        btn.disabled = true;
        span.textContent = 'Creating account…';
        icon.classList.remove('fa-user-plus');
        icon.classList.add('fa-spinner', 'fa-spin');
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