<?php
/* ============================================================
   FILE: index.php
   Purpose: Public landing page for SmartEcoTrack
   ============================================================ */
require_once 'db_connect.php';
if (isLoggedIn()) redirect('dashboard.php');

/* ============================================================
   LIVE STATS — pulled from DB for the stats strip
   ============================================================ */
$statTotalReports = 0;
$statResolved     = 0;
$statTotalUsers   = 0;

if ($r = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM incidents")) {
    $statTotalReports = (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
}
if ($r = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM incidents WHERE status = 'resolved'")) {
    $statResolved = (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
}
if ($r = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM users")) {
    $statTotalUsers = (int)(mysqli_fetch_assoc($r)['c'] ?? 0);
}

/* ============================================================
   CARTOON DISASTER ILLUSTRATIONS
   ============================================================ */
$illustrations = [
    /* Water */
    'flood'          => 'https://cdn-icons-png.flaticon.com/512/2909/2909856.png',
    'flooding'       => 'https://cdn-icons-png.flaticon.com/512/2909/2909856.png',
    'flash flood'    => 'https://cdn-icons-png.flaticon.com/512/2909/2909856.png',
    'tsunami'        => 'https://cdn-icons-png.flaticon.com/512/2909/2909856.png',
    'water'          => 'https://cdn-icons-png.flaticon.com/512/2909/2909856.png',

    /* Wind / Storm */
    'typhoon'        => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',
    'storm'          => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',
    'hurricane'      => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',
    'cyclone'        => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',
    'wind'           => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',
    'tornado'        => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',

    /* Earth */
    'earthquake'     => 'https://cdn-icons-png.flaticon.com/512/2933/2933116.png',
    'quake'          => 'https://cdn-icons-png.flaticon.com/512/2933/2933116.png',
    'landslide'      => 'https://cdn-icons-png.flaticon.com/512/2933/2933116.png',
    'mudslide'       => 'https://cdn-icons-png.flaticon.com/512/2933/2933116.png',
    'avalanche'      => 'https://cdn-icons-png.flaticon.com/512/2933/2933116.png',

    /* Fire */
    'fire'           => 'https://cdn-icons-png.flaticon.com/512/785/785116.png',
    'wildfire'       => 'https://cdn-icons-png.flaticon.com/512/785/785116.png',
    'forest fire'    => 'https://cdn-icons-png.flaticon.com/512/785/785116.png',
    'burn'           => 'https://cdn-icons-png.flaticon.com/512/785/785116.png',

    /* Volcano */
    'volcano'        => 'https://cdn-icons-png.flaticon.com/512/2942/2942995.png',
    'volcanic'       => 'https://cdn-icons-png.flaticon.com/512/2942/2942995.png',
    'eruption'       => 'https://cdn-icons-png.flaticon.com/512/2942/2942995.png',
    'lava'           => 'https://cdn-icons-png.flaticon.com/512/2942/2942995.png',

    /* Climate */
    'drought'        => 'https://cdn-icons-png.flaticon.com/512/869/869869.png',
    'heatwave'       => 'https://cdn-icons-png.flaticon.com/512/869/869869.png',
    'heat'           => 'https://cdn-icons-png.flaticon.com/512/869/869869.png',
    'snow'           => 'https://cdn-icons-png.flaticon.com/512/642/642102.png',
    'blizzard'       => 'https://cdn-icons-png.flaticon.com/512/642/642102.png',
    'winter'         => 'https://cdn-icons-png.flaticon.com/512/642/642102.png',

    /* Health */
    'pandemic'       => 'https://cdn-icons-png.flaticon.com/512/2913/2913465.png',
    'epidemic'       => 'https://cdn-icons-png.flaticon.com/512/2913/2913465.png',
    'virus'          => 'https://cdn-icons-png.flaticon.com/512/2913/2913465.png',
    'covid'          => 'https://cdn-icons-png.flaticon.com/512/2913/2913465.png',

    /* Category fallbacks */
    'geological'     => 'https://cdn-icons-png.flaticon.com/512/2933/2933116.png',
    'weather'        => 'https://cdn-icons-png.flaticon.com/512/414/414927.png',
    'hydrological'   => 'https://cdn-icons-png.flaticon.com/512/2909/2909856.png',
    'environmental'  => 'https://cdn-icons-png.flaticon.com/512/785/785116.png',
    'biological'     => 'https://cdn-icons-png.flaticon.com/512/2913/2913465.png',
    'climatological' => 'https://cdn-icons-png.flaticon.com/512/869/869869.png',

    /* Final */
    'default'        => 'https://cdn-icons-png.flaticon.com/512/564/564619.png',
];

/**
 * Find the best cartoon image for a disaster.
 * Priority: exact title → longest keyword in title → keyword in category → default
 */
if (!function_exists('resolve_disaster_image')) {
    function resolve_disaster_image(string $title, string $category, array $map): string {
        $titleLc = strtolower(trim($title));
        $catLc   = strtolower(trim($category));

        if (isset($map[$titleLc])) return $map[$titleLc];

        $bestKey = ''; $bestLen = 0;
        foreach ($map as $key => $url) {
            if ($key === 'default') continue;
            if (strpos($titleLc, $key) !== false && strlen($key) > $bestLen) {
                $bestKey = $key;
                $bestLen = strlen($key);
            }
        }
        if ($bestKey !== '') return $map[$bestKey];

        foreach ($map as $key => $url) {
            if ($key === 'default') continue;
            if (strpos($catLc, $key) !== false) return $url;
        }

        return $map['default'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0A1931">
    <title>SmartEcoTrack · Environmental Intelligence</title>

    <!-- Preload hero background for faster paint -->
    <link rel="preload" as="image"
          href="https://images.unsplash.com/photo-1448375240586-882707db888b?w=1920&q=80">

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
            --navy-400: #4A7DB5;
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

            --crimson: #C62828;
            --crimson-bg: #FDECEA;
            --amber: #E65100;
            --amber-bg: #FFF3E0;
            --status-green: #2E7D32;
            --status-bg: #E8F5E9;

            --white: #FFFFFF;
            --gray-50: #FAFBFC;
            --gray-100: #F4F6F8;
            --gray-200: #E4E8EC;
            --gray-300: #CFD6DC;
            --gray-400: #9AA5AF;
            --gray-500: #6B7680;
            --gray-600: #4A535C;
            --gray-800: #1C2229;

            --text-primary: #1C2229;
            --text-secondary: #4A535C;
            --text-muted: #6B7680;

            --shadow-sm: 0 1px 3px rgba(10,25,49,0.06), 0 1px 2px rgba(10,25,49,0.04);
            --shadow-md: 0 4px 12px rgba(10,25,49,0.08), 0 2px 4px rgba(10,25,49,0.04);
            --shadow-lg: 0 12px 32px rgba(10,25,49,0.10), 0 4px 12px rgba(10,25,49,0.05);
            --shadow-xl: 0 24px 56px rgba(10,25,49,0.12), 0 8px 20px rgba(10,25,49,0.06);

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-pill: 999px;

            --gradient-header: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            --gradient-accent: linear-gradient(135deg, var(--forest-800), var(--forest-600));
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--gray-100);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================
           NAVBAR
           ============================================================ */
        .navbar {
            position: fixed;
            top: 0; left: 0; width: 100%;
            padding: 0.75rem 5vw;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 999;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--navy-800);
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
        }
        .logo-circle {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: var(--gradient-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(64,145,108,0.35);
            flex-shrink: 0;
            overflow: hidden;
        }
        .logo-circle img { width: 100%; height: 100%; object-fit: cover; }
        .logo-text em { font-style: normal; font-weight: 400; color: var(--gray-500); }

        .nav-links { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.55rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.92rem;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-links a:hover { background: var(--navy-50); color: var(--navy-800); }
        .nav-links a.btn-register {
            background: var(--forest-700);
            color: var(--white);
            font-weight: 600;
            padding: 0.55rem 1.15rem;
        }
        .nav-links a.btn-register:hover { background: var(--forest-800); color: var(--white); }

        /* ============================================================
           HERO
           ============================================================ */
        .hero {
            min-height: 92vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 140px 5vw 80px;
            position: relative;
            isolation: isolate;
            background-color: var(--navy-800);
            overflow: hidden;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(rgba(10,25,49,0.82), rgba(17,42,74,0.88)),
                url('https://images.unsplash.com/photo-1448375240586-882707db888b?w=1920&q=80') center/cover no-repeat;
            z-index: -2;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(82,183,136,0.25), transparent 55%),
                radial-gradient(circle at 80% 70%, rgba(82,183,136,0.15), transparent 50%);
            z-index: -1;
            pointer-events: none;
        }
        .hero-content {
            max-width: 900px;
            position: relative;
            z-index: 3;
            animation: floatUp 0.9s ease-out;
        }
        @keyframes floatUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(82,183,136,0.15);
            border: 1px solid rgba(82,183,136,0.35);
            border-radius: var(--radius-pill);
            color: var(--forest-400);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 22px;
        }
        .hero h1 {
            font-size: clamp(2.4rem, 6vw, 4rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--white);
            line-height: 1.12;
            text-shadow: 0 2px 20px rgba(0,0,0,0.35);
            margin-bottom: 20px;
        }
        .hero h1 .highlight {
            background: linear-gradient(135deg, var(--forest-400), var(--forest-200));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }
        .hero p {
            font-size: 1.15rem;
            color: rgba(255,255,255,0.9);
            margin: 0 auto 2.2rem;
            max-width: 620px;
            line-height: 1.7;
        }
        .hero-buttons {
            display: flex;
            gap: 0.85rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.85rem 1.9rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.98rem;
            text-decoration: none;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1.5px solid transparent;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--forest-600);
            color: var(--white);
            box-shadow: 0 6px 20px rgba(82,183,136,0.35);
        }
        .btn-primary:hover {
            background: var(--forest-700);
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(82,183,136,0.45);
            color: var(--white);
        }
        .btn-outline {
            border: 1.5px solid rgba(255,255,255,0.5);
            color: var(--white);
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(6px);
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.18);
            border-color: var(--white);
            transform: translateY(-2px);
            color: var(--white);
        }

        /* ============================================================
           STATS STRIP
           ============================================================ */
        .stats-strip {
            background: var(--navy-900);
            padding: 3rem 5vw;
            color: var(--white);
            position: relative;
            z-index: 2;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .stats-strip .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .stats-strip .stat-item {
            text-align: center;
            padding: 1.5rem 1rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: var(--radius-lg);
            transition: all 0.25s ease;
        }
        .stats-strip .stat-item:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-3px);
            border-color: rgba(82,183,136,0.3);
        }
        .stats-strip .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--forest-400);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .stats-strip .stat-label {
            font-size: 0.85rem;
            opacity: 0.7;
            margin-top: 0.4rem;
            letter-spacing: 0.02em;
        }

        /* ============================================================
           SECTION COMMON
           ============================================================ */
        .section { padding: 5rem 5vw; }
        .section-title {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            color: var(--navy-900);
            text-align: center;
            margin-bottom: 0.6rem;
            letter-spacing: -0.02em;
            font-weight: 700;
        }
        .section-subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 1.05rem;
            margin-bottom: 3rem;
        }

        /* ============================================================
           HOW IT WORKS
           ============================================================ */
        .how-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.75rem;
            max-width: 1100px;
            margin: 0 auto;
        }
        .how-card {
            background: var(--white);
            padding: 2.25rem 1.75rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.25s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .how-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--forest-200);
        }
        .how-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            height: 3px; width: 100%;
            background: var(--gradient-accent);
            opacity: 0;
            transition: opacity 0.25s;
        }
        .how-card:hover::before { opacity: 1; }

        .how-step {
            width: 64px; height: 64px;
            background: var(--gradient-accent);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px -6px rgba(64,145,108,0.5);
        }
        .how-card h3 {
            color: var(--navy-900);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            font-weight: 700;
        }
        .how-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* ============================================================
           DISASTER CARDS
           ============================================================ */
        .disaster-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.75rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .disaster-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--gray-200);
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }
        .disaster-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--forest-200);
        }
        .disaster-thumb {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 10;
            overflow: hidden;
            background: linear-gradient(135deg, var(--navy-50), var(--navy-100));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .disaster-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 18px;
            display: block;
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform;
        }
        .disaster-card:hover .disaster-thumb img { transform: scale(1.12); }

        .thumb-category {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: rgba(255,255,255,0.95);
            color: var(--navy-900);
            border-radius: var(--radius-pill);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            white-space: nowrap;
        }
        .thumb-category i { color: var(--forest-700); font-size: 0.75rem; }

        .disaster-card .card-body {
            padding: 1.25rem 1.35rem 1.5rem;
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 0.55rem;
        }
        .disaster-card .card-body h3 {
            font-size: 1.1rem;
            color: var(--navy-900);
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.35;
            margin: 0;
        }
        .disaster-card .card-body p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.88rem;
            margin: 0;
            flex: 1;
        }
        .disaster-card .card-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: auto;
            padding-top: 0.5rem;
            font-weight: 700;
            color: var(--forest-700);
            font-size: 0.85rem;
            text-decoration: none;
            align-self: flex-start;
            transition: gap 0.15s, color 0.15s;
        }
        .disaster-card .card-link:hover { color: var(--forest-800); gap: 10px; }

        /* ============================================================
           FEATURES
           ============================================================ */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.75rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            background: var(--white);
            padding: 2rem 1.75rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.25s ease;
            text-align: center;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--forest-200);
        }
        .feature-icon {
            font-size: 1.6rem;
            color: var(--white);
            background: var(--navy-700);
            width: 60px; height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            transition: all 0.25s ease;
        }
        .feature-card:hover .feature-icon { background: var(--forest-700); transform: scale(1.05); }
        .feature-card h3 { color: var(--navy-900); margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 700; }
        .feature-card p { color: var(--text-secondary); font-size: 0.9rem; }

        /* ============================================================
           EMERGENCY CONTACTS
           ============================================================ */
        .contact-card {
            background: var(--white);
            padding: 1.75rem 1.75rem 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--navy-700);
            transition: all 0.25s ease;
            box-shadow: var(--shadow-sm);
        }
        .contact-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-left-color: var(--crimson);
        }
        .contact-card h3 { color: var(--navy-900); font-size: 1.05rem; font-weight: 700; margin-bottom: 0.4rem; }
        .contact-phone {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--crimson);
            letter-spacing: -0.02em;
        }
        .contact-type { color: var(--text-secondary); font-weight: 500; font-size: 0.88rem; margin-top: 0.2rem; }
        .contact-region { color: var(--text-muted); font-size: 0.83rem; }
        .btn-call {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.55rem 1.4rem;
            background: var(--forest-700);
            color: var(--white);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
            font-size: 0.88rem;
            transition: all 0.15s;
        }
        .btn-call:hover {
            background: var(--forest-800);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            color: var(--white);
        }

        /* ============================================================
           CTA
           ============================================================ */
        .cta-section {
            background: var(--gradient-header);
            padding: 4.5rem 5vw;
            text-align: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, rgba(82,183,136,0.2), transparent 60%);
            pointer-events: none;
        }
        .cta-section h2 {
            font-size: clamp(1.75rem, 4vw, 2.4rem);
            margin-bottom: 0.6rem;
            color: var(--white);
            font-weight: 700;
            position: relative;
        }
        .cta-section p {
            opacity: 0.88;
            font-size: 1.05rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
        }
        .cta-section .btn {
            position: relative;
            background: var(--white);
            color: var(--navy-800);
        }
        .cta-section .btn:hover {
            background: var(--gray-100);
            color: var(--navy-900);
            transform: translateY(-2px);
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        footer {
            background: var(--navy-900);
            color: rgba(255,255,255,0.75);
            padding: 3.5rem 5vw 2rem;
            border-top: 3px solid var(--forest-700);
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto 2rem;
        }
        footer h4 {
            color: var(--white);
            font-size: 1rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        footer a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            display: block;
            margin: 0.5rem 0;
            transition: color 0.15s;
            font-size: 0.88rem;
        }
        footer a:hover { color: var(--forest-400); }
        footer p { font-size: 0.88rem; line-height: 1.6; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 1.75rem;
            text-align: center;
            font-size: 0.82rem;
            opacity: 0.6;
        }
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
        }
        .footer-logo .logo-circle {
            width: 44px; height: 44px;
            background: var(--forest-700);
            box-shadow: 0 0 0 2px rgba(82,183,136,0.3);
        }
        .footer-logo h4 { margin: 0; color: var(--white); font-size: 1.15rem; font-weight: 700; }

        /* ============================================================
           SCROLL REVEAL
           ============================================================ */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ============================================================
           LOGIN MODAL
           ============================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10,25,49,0.65);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .login-modal {
            display: flex;
            max-width: 900px;
            width: 100%;
            background: var(--white);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            animation: modalPop 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            max-height: 92vh;
            overflow-y: auto;
        }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.94) translateY(20px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .login-modal .login-image {
            width: 42%;
            background: var(--gradient-header);
            color: var(--white);
            padding: 44px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .login-modal .login-image::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(82,183,136,0.25), transparent 55%),
                radial-gradient(circle at 80% 70%, rgba(82,183,136,0.15), transparent 50%);
            pointer-events: none;
        }
        .login-modal .login-image > * { position: relative; z-index: 1; }
        .login-modal .login-image h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .login-modal .login-image h2 i { color: var(--forest-400); margin-right: 8px; }
        .login-modal .login-image p { opacity: 0.88; line-height: 1.65; font-size: 0.95rem; margin-bottom: 24px; }
        .login-modal .login-image .features p {
            padding: 7px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            margin: 0;
        }
        .login-modal .login-image .features p i { color: var(--forest-400); }

        .login-modal .login-form { width: 58%; padding: 44px 40px; }
        .login-modal .login-form h2 {
            color: var(--navy-900);
            margin-bottom: 6px;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .login-modal .login-form h2 i { color: var(--forest-700); margin-right: 6px; }
        .login-modal .subtitle { color: var(--text-secondary); margin-bottom: 22px; font-size: 0.92rem; }
        .login-modal .form-group { margin-bottom: 16px; }
        .login-modal label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.82rem;
        }
        .login-modal label i { margin-right: 6px; color: var(--forest-700); }
        .login-modal .input-group { position: relative; }
        .login-modal .input-group input {
            width: 100%;
            padding: 12px 44px 12px 16px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.15s;
            background: var(--white);
            color: var(--text-primary);
            outline: none;
        }
        .login-modal .input-group input:focus {
            border-color: var(--navy-500);
            box-shadow: 0 0 0 3px rgba(46,95,150,0.18);
        }
        .login-modal .input-group input::placeholder { color: var(--gray-400); }
        .login-modal .toggle-password {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-400);
            background: none;
            border: none;
            font-size: 1rem;
            padding: 5px;
            transition: color 0.15s;
        }
        .login-modal .toggle-password:hover { color: var(--navy-600); }
        .login-modal .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .login-modal .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.88rem;
        }
        .login-modal .remember-me input {
            width: 16px; height: 16px;
            accent-color: var(--forest-700);
            cursor: pointer;
        }
        .login-modal .forgot-password {
            color: var(--navy-700);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
        }
        .login-modal .forgot-password:hover { text-decoration: underline; color: var(--forest-700); }
        .login-modal .alert {
            padding: 11px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-weight: 500;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
            border-left-width: 4px;
        }
        .login-modal .alert-error { background: var(--crimson-bg); color: var(--crimson); border-color: var(--crimson); }
        .login-modal .alert-success { background: var(--status-bg); color: var(--status-green); border-color: var(--status-green); }
        .login-modal .alert-info { background: var(--navy-50); color: var(--navy-700); border-color: var(--navy-300); }
        .login-modal .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--forest-700);
            color: var(--white);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            min-height: 50px;
            transition: all 0.15s;
            font-family: inherit;
        }
        .login-modal .btn-submit:hover {
            background: var(--forest-800);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .login-modal .register-link {
            text-align: center;
            margin-top: 18px;
            color: var(--text-secondary);
            font-size: 0.92rem;
        }
        .login-modal .register-link a {
            color: var(--navy-700);
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .login-modal .register-link a:hover { text-decoration: underline; color: var(--forest-700); }
        .login-modal .login-footer {
            text-align: center;
            margin-top: 16px;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .login-modal .login-footer a {
            color: var(--navy-700);
            text-decoration: none;
        }
        .login-modal .login-footer a:hover { text-decoration: underline; color: var(--forest-700); }
        .close-modal {
            position: absolute;
            top: 16px; right: 16px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 50%;
            width: 38px; height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--gray-500);
            transition: all 0.15s;
            z-index: 10;
        }
        .close-modal:hover {
            background: var(--crimson);
            color: var(--white);
            border-color: var(--crimson);
            transform: rotate(90deg);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .login-modal { flex-direction: column; max-width: 460px; }
            .login-modal .login-image { width: 100%; padding: 28px 24px; text-align: center; }
            .login-modal .login-image .features { display: none; }
            .login-modal .login-form { width: 100%; padding: 28px 24px; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 0.6rem 1.25rem; flex-wrap: wrap; gap: 0.5rem; }
            .nav-links a { padding: 0.4rem 0.8rem; font-size: 0.82rem; }
            .hero { padding: 120px 5vw 60px; min-height: auto; }
            .hero h1 { font-size: 2rem; }
            .hero p { font-size: 1rem; }
            .logo { font-size: 1.1rem; gap: 10px; }
            .logo-circle { width: 40px; height: 40px; font-size: 1.15rem; }
            .logo-text em { display: none; }
            .disaster-grid { grid-template-columns: 1fr; gap: 1.25rem; }
            .section { padding: 3.5rem 5vw; }
            .stats-strip { padding: 2.25rem 5vw; }
            .stats-strip .stat-number { font-size: 2rem; }
        }
        @media (max-width: 480px) {
            .hero-buttons { flex-direction: column; align-items: stretch; }
            .btn { justify-content: center; }
            .login-modal .login-form { padding: 22px 18px; }
            .login-modal .login-image { padding: 22px 18px; }
        }

        :focus-visible { outline: 2px solid var(--navy-600); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            .reveal { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>

<!-- ============ NAVBAR ============ -->
<nav class="navbar">
    <a href="index.php" class="logo">
        <div class="logo-circle">
            <img src="logo.png" alt="Logo"
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-leaf\'></i>';">
        </div>
        <div class="logo-text">Smart<em>EcoTrack</em></div>
    </a>
    <div class="nav-links">
        <a href="#how"><i class="fas fa-list-ol"></i> How It Works</a>
        <a href="#disasters"><i class="fas fa-shield-alt"></i> Disasters</a>
        <a href="#features"><i class="fas fa-star"></i> Features</a>
        <a href="#contacts"><i class="fas fa-phone"></i> Emergency</a>
        <a href="#" onclick="openLoginModal(); return false;"><i class="fas fa-sign-in-alt"></i> Login</a>
        <a href="register.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
    </div>
</nav>

<!-- ============ HERO ============ -->
<section class="hero">
    <div class="hero-content">
        <span class="hero-badge">
            <i class="fas fa-globe-asia"></i> Environmental Intelligence Platform
        </span>
        <h1>
            Protect <span class="highlight">Nature</span>,<br>
            Stay <span class="highlight">Prepared</span>
        </h1>
        <p>
            Report environmental incidents, explore disaster guides, and build resilience
            with real-time intelligence for a safer Philippines.
        </p>
        <div class="hero-buttons">
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-rocket"></i> Get Started Free
            </a>
            <a href="#" class="btn btn-outline" onclick="openLoginModal(); return false;">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </a>
        </div>
    </div>
</section>

<!-- ============ STATS STRIP ============ -->
<section class="stats-strip">
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-number"><?= number_format($statTotalReports) ?>+</div>
            <div class="stat-label">Incidents Reported</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= number_format($statResolved) ?>+</div>
            <div class="stat-label">Successfully Resolved</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= number_format($statTotalUsers) ?>+</div>
            <div class="stat-label">Community Members</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">24/7</div>
            <div class="stat-label">Emergency Coverage</div>
        </div>
    </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section class="section" id="how" style="background: var(--white);">
    <h2 class="section-title">🛠️ How It Works</h2>
    <p class="section-subtitle">Three simple steps to make a difference</p>

    <div class="how-grid">
        <div class="how-card">
            <div class="how-step">1</div>
            <h3>Create Your Account</h3>
            <p>Sign up in seconds — no credit card, no commitments.</p>
        </div>
        <div class="how-card">
            <div class="how-step">2</div>
            <h3>Report Incidents</h3>
            <p>Pin the location on the map and describe what you see.</p>
        </div>
        <div class="how-card">
            <div class="how-step">3</div>
            <h3>Get Help Fast</h3>
            <p>Staff verify and dispatch responders immediately.</p>
        </div>
    </div>
</section>

<!-- ============ DISASTER GUIDES ============ -->
<section class="section" id="disasters" style="background: var(--gray-100);">
    <h2 class="section-title">🌿 Disaster Preparedness Guides</h2>
    <p class="section-subtitle">Essential knowledge for every emergency scenario</p>

    <div class="disaster-grid">
        <?php
        $tips = @mysqli_query($conn, "SELECT * FROM disaster_tips ORDER BY category");

        if ($tips && mysqli_num_rows($tips) > 0):
            while ($tip = mysqli_fetch_assoc($tips)):
                $title = $tip['title']    ?? 'Untitled';
                $cat   = $tip['category'] ?? 'General';
                $body  = $tip['content']  ?? '';
                $icon  = !empty($tip['icon']) ? $tip['icon'] : 'exclamation-triangle';

                $img = trim($tip['image_url'] ?? '');
                if ($img === '') {
                    $img = resolve_disaster_image($title, $cat, $illustrations);
                }

                $titleSafe = htmlspecialchars($title);
                $catSafe   = htmlspecialchars($cat);
                $bodySafe  = htmlspecialchars($body);
                $iconSafe  = htmlspecialchars($icon);
                $safeImg   = htmlspecialchars($img, ENT_QUOTES);
        ?>
        <article class="disaster-card" onclick="openLoginModal()">
            <div class="disaster-thumb">
                <img src="<?= $safeImg ?>"
                     alt="<?= $titleSafe ?>"
                     loading="lazy"
                     decoding="async"
                     referrerpolicy="no-referrer"
                     onerror="this.onerror=null; this.src='<?= $illustrations['default'] ?>';">
                <span class="thumb-category">
                    <i class="fas fa-<?= $iconSafe ?>"></i> <?= $catSafe ?>
                </span>
            </div>
            <div class="card-body">
                <h3><?= $titleSafe ?></h3>
                <p><?= mb_substr($bodySafe, 0, 120) ?>…</p>
                <a href="#" class="card-link" onclick="openLoginModal(); return false;">
                    Read more <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </article>
        <?php
            endwhile;
        else:
            $fallback = [
                ['title' => 'Flood Response',       'category' => 'Hydrological',  'icon' => 'water',    'content' => 'Move to higher ground immediately. Avoid walking or driving through floodwaters. Turn off gas and electricity.'],
                ['title' => 'Typhoon Preparedness', 'category' => 'Weather',       'icon' => 'wind',     'content' => 'Secure loose objects, stock up on food and water, know your evacuation zone. Monitor official weather updates.'],
                ['title' => 'Earthquake Safety',    'category' => 'Geological',    'icon' => 'mountain', 'content' => 'Drop, cover, and hold on. Stay away from windows and heavy furniture. If outdoors, move to an open area.'],
                ['title' => 'Wildfire Awareness',   'category' => 'Environmental', 'icon' => 'fire',     'content' => 'Clear dry vegetation around your home. Have an evacuation plan. Follow evacuation orders promptly.'],
                ['title' => 'Landslide Safety',     'category' => 'Geological',    'icon' => 'mountain', 'content' => 'Watch for cracks in the ground, tilting trees, or unusual sounds. Evacuate immediately if you notice these signs.'],
                ['title' => 'Volcano Preparedness', 'category' => 'Geological',    'icon' => 'fire',     'content' => 'Know your evacuation route. Keep dust masks and goggles handy. Follow local authorities during eruptions.'],
            ];
            foreach ($fallback as $tip):
                $img = resolve_disaster_image($tip['title'], $tip['category'], $illustrations);
                $titleSafe = htmlspecialchars($tip['title']);
                $catSafe   = htmlspecialchars($tip['category']);
                $iconSafe  = htmlspecialchars($tip['icon']);
                $safeImg   = htmlspecialchars($img, ENT_QUOTES);
        ?>
        <article class="disaster-card" onclick="openLoginModal()">
            <div class="disaster-thumb">
                <img src="<?= $safeImg ?>"
                     alt="<?= $titleSafe ?>"
                     loading="lazy"
                     decoding="async"
                     onerror="this.onerror=null; this.src='<?= $illustrations['default'] ?>';">
                <span class="thumb-category">
                    <i class="fas fa-<?= $iconSafe ?>"></i> <?= $catSafe ?>
                </span>
            </div>
            <div class="card-body">
                <h3><?= $titleSafe ?></h3>
                <p><?= mb_substr($tip['content'], 0, 120) ?>…</p>
                <a href="#" class="card-link" onclick="openLoginModal(); return false;">
                    Read more <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </article>
        <?php
            endforeach;
        endif;
        ?>
    </div>
</section>

<!-- ============ FEATURES ============ -->
<section class="section" id="features" style="background: var(--white);">
    <h2 class="section-title">✨ Intelligent Resilience</h2>
    <p class="section-subtitle">Tools that empower communities and protect ecosystems</p>
    <div class="features-grid">
        <div class="feature-card"><div class="feature-icon"><i class="fas fa-map-pin"></i></div><h3>Incident Mapping</h3><p>Pinpoint and report environmental hazards in real time</p></div>
        <div class="feature-card"><div class="feature-icon"><i class="fas fa-phone-volume"></i></div><h3>Emergency Contacts</h3><p>One-tap access to all critical hotlines</p></div>
        <div class="feature-card"><div class="feature-icon"><i class="fas fa-brain"></i></div><h3>Preparedness Quiz</h3><p>Test your knowledge and earn badges</p></div>
        <div class="feature-card"><div class="feature-icon"><i class="fas fa-satellite-dish"></i></div><h3>Live Tracker</h3><p>Real-time monitoring for staff and admins</p></div>
    </div>
</section>

<!-- ============ EMERGENCY CONTACTS ============ -->
<section class="section" id="contacts" style="background: var(--gray-100);">
    <h2 class="section-title">📞 Emergency Contacts</h2>
    <p class="section-subtitle">Save these numbers — they save lives</p>
    <div class="disaster-grid" style="max-width:1200px;">
        <?php
        $contacts = @mysqli_query($conn, "SELECT * FROM emergency_contacts WHERE is_active=1");
        if ($contacts && mysqli_num_rows($contacts) > 0):
            while ($c = mysqli_fetch_assoc($contacts)):
        ?>
        <div class="contact-card">
            <h3><?= htmlspecialchars($c['name']) ?></h3>
            <div class="contact-phone"><?= htmlspecialchars($c['phone']) ?></div>
            <div class="contact-type"><?= htmlspecialchars($c['type']) ?></div>
            <div class="contact-region"><?= htmlspecialchars($c['region']) ?></div>
            <a href="tel:<?= preg_replace('/[^0-9]/', '', $c['phone']) ?>" class="btn-call">
                <i class="fas fa-phone"></i> Call Now
            </a>
        </div>
        <?php
            endwhile;
        else:
            $fallback = [
                ['name' => 'National Emergency',   'phone' => '911',            'type' => 'General Emergency', 'region' => 'Nationwide'],
                ['name' => 'Philippine Red Cross', 'phone' => '143',            'type' => 'Humanitarian Aid',  'region' => 'Nationwide'],
                ['name' => 'NDRRMC',               'phone' => '(02) 8911-5061', 'type' => 'Disaster Response', 'region' => 'Metro Manila'],
                ['name' => 'BFP (Fire)',           'phone' => '117',            'type' => 'Fire & Rescue',     'region' => 'Nationwide'],
            ];
            foreach ($fallback as $c):
        ?>
        <div class="contact-card">
            <h3><?= $c['name'] ?></h3>
            <div class="contact-phone"><?= $c['phone'] ?></div>
            <div class="contact-type"><?= $c['type'] ?></div>
            <div class="contact-region"><?= $c['region'] ?></div>
            <a href="tel:<?= preg_replace('/[^0-9]/', '', $c['phone']) ?>" class="btn-call">
                <i class="fas fa-phone"></i> Call Now
            </a>
        </div>
        <?php endforeach; endif; ?>
    </div>
</section>

<!-- ============ CTA ============ -->
<section class="cta-section">
    <h2>Ready to make an impact?</h2>
    <p>Join a growing community that protects the environment and saves lives.</p>
    <a href="register.php" class="btn">
        <i class="fas fa-user-plus"></i> Create Free Account
    </a>
</section>

<!-- ============ FOOTER ============ -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo">
                <div class="logo-circle">
                    <img src="logo.png" alt="Logo"
                         onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-leaf\'></i>';">
                </div>
                <h4>SmartEcoTrack</h4>
            </div>
            <p style="opacity:0.7; max-width:280px;">Environmental incident reporting & disaster preparedness system.</p>
            <p style="opacity:0.5; font-size:0.82rem; margin-top:0.8rem;">Working with DENR & local communities.</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <a href="#" onclick="openLoginModal(); return false;">Login</a>
            <a href="register.php">Register</a>
            <a href="#disasters">Disaster Guides</a>
            <a href="#contacts">Emergency Contacts</a>
        </div>
        <div>
            <h4>Emergency</h4>
            <a href="tel:911">📞 911 – National</a>
            <a href="tel:143">📞 143 – Red Cross</a>
            <a href="tel:117">📞 117 – Police</a>
            <p style="opacity:0.5; margin-top:0.6rem; font-size:0.82rem;">Available 24/7</p>
        </div>
        <div>
            <h4>Disclaimer</h4>
            <p style="opacity:0.6; font-size:0.82rem;">For information purposes. In life-threatening emergencies, always call 911 immediately.</p>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> SmartEcoTrack · Environmental Intelligence
    </div>
</footer>

<!-- ============ LOGIN MODAL ============ -->
<div class="modal-overlay" id="loginModal">
    <div class="login-modal">
        <button class="close-modal" onclick="closeLoginModal()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <div class="login-image">
            <h2><i class="fas fa-tree"></i> SmartEcoTrack</h2>
            <p>Your partner in environmental protection and disaster preparedness.</p>
            <div class="features">
                <p><i class="fas fa-check-circle"></i> Report environmental incidents</p>
                <p><i class="fas fa-check-circle"></i> Access disaster preparedness guides</p>
                <p><i class="fas fa-check-circle"></i> Emergency contacts at your fingertips</p>
                <p><i class="fas fa-check-circle"></i> Earn points for relief goods</p>
                <p><i class="fas fa-check-circle"></i> Real-time monitoring and alerts</p>
            </div>
        </div>

        <div class="login-form">
            <h2><i class="fas fa-leaf"></i> Welcome Back</h2>
            <p class="subtitle">Sign in to continue protecting our environment</p>

            <div class="alert alert-info" id="modalAlert" style="display:none;">
                <i class="fas fa-info-circle"></i> <span id="modalAlertText"></span>
            </div>

            <form method="POST" action="login.php" id="modalLoginForm">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-group">
                        <input type="email" name="email" id="modalEmail"
                               placeholder="your@email.com" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="modalPassword"
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-password"
                                onclick="toggleModalPassword()" aria-label="Toggle password">
                            <i class="fas fa-eye" id="modalToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-password">
                        <i class="fas fa-question-circle"></i> Forgot?
                    </a>
                </div>

                <button type="submit" class="btn-submit" id="modalSubmitBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>

            <p class="register-link">
                Don't have an account?
                <a href="register.php"><i class="fas fa-arrow-right"></i> Create Account</a>
            </p>

            <div class="login-footer">
                <p>Protected by <i class="fas fa-shield-alt" style="color:var(--forest-700);"></i> SmartEcoTrack Security</p>
            </div>
        </div>
    </div>
</div>

<!-- ============ SCRIPT ============ -->
<script>
    /* ---------- Login modal ---------- */
    function openLoginModal() {
        const modal = document.getElementById('loginModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const email = document.getElementById('modalEmail');
            if (email) email.focus();
        }, 300);
    }
    function closeLoginModal() {
        const modal = document.getElementById('loginModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('loginModal').addEventListener('click', function (e) {
        if (e.target === this) closeLoginModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLoginModal();
    });

    /* ---------- Password toggle ---------- */
    function toggleModalPassword() {
        const pwd  = document.getElementById('modalPassword');
        const icon = document.getElementById('modalToggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            pwd.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    /* ---------- Loading state on submit ---------- */
    document.getElementById('modalLoginForm').addEventListener('submit', function (e) {
        const email = document.getElementById('modalEmail').value.trim();
        const pwd   = document.getElementById('modalPassword').value;
        if (!email || !pwd) { e.preventDefault(); return false; }
        const btn  = document.getElementById('modalSubmitBtn');
        const span = btn.querySelector('span');
        btn.disabled = true;
        span.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
    });

    /* ---------- Handle URL params from login.php ---------- */
    (function () {
        const params    = new URLSearchParams(window.location.search);
        const alertEl   = document.getElementById('modalAlert');
        const alertText = document.getElementById('modalAlertText');

        if (params.has('login_error')) {
            alertEl.className = 'alert alert-error';
            alertEl.style.display = 'flex';
            alertText.textContent = 'Invalid email or password. Please try again.';
            openLoginModal();
        }
        if (params.has('registered')) {
            alertEl.className = 'alert alert-success';
            alertEl.style.display = 'flex';
            alertText.textContent = 'Registration successful! Please login.';
            openLoginModal();
        }
        if (params.has('logout')) {
            alertEl.className = 'alert alert-success';
            alertEl.style.display = 'flex';
            alertText.textContent = 'You have been logged out successfully.';
            openLoginModal();
        }
    })();

    /* ---------- Smooth scroll for anchor links ---------- */
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href === '#' || href === '#loginModal') return;
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    /* ---------- Scroll reveal ---------- */
    (function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const targets = document.querySelectorAll(
            '.disaster-card, .feature-card, .contact-card, .how-card'
        );
        if (!targets.length) return;

        targets.forEach(el => el.classList.add('reveal'));

        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' });

        targets.forEach(el => io.observe(el));
    })();
</script>
</body>
</html>