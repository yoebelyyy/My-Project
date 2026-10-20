<?php
/* ============================================================
   FILE: db_connect.php
   Purpose: Database connection + helpers
   Hosting: InfinityFree (smarteco.ct.ws)
   ============================================================ */

/* ============================================================
   1. ERROR REPORTING
   ============================================================ */
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])
        || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if ($isLocal) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    /* Hosting: hide errors, log them silently */
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    /* IMPORTANT: only log to a writable path */
    ini_set('error_log', sys_get_temp_dir() . '/php-error.log');
}

/* ============================================================
   2. DATABASE CREDENTIALS
   ============================================================ */
if ($isLocal) {
    $host   = 'localhost';
    $user   = 'root';
    $pass   = '';
    $dbname = 'smartecotrack_db';
    $port   = 3306;
} else {
    /* ---------- HOSTING (InfinityFree / ct.ws) ---------- */
    $host   = 'sql100.infinityfree.com';
    $user   = 'if0_43166310';
    $pass   = 'Secret122709';
    $dbname = 'if0_43166310_smartecotrack_db';
    $port   = 3306;
}

/* ============================================================
   3. CONNECT
   ============================================================ */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    $err = mysqli_connect_error();
    @error_log('DB connect failed: ' . $err);

    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<title>Service Unavailable</title>';
    echo '<style>
            body{font-family:Arial,sans-serif;background:#F4F6F8;
                 display:flex;align-items:center;justify-content:center;
                 min-height:100vh;margin:0;padding:20px;}
            .box{background:#fff;border-radius:16px;padding:40px;
                 max-width:520px;text-align:center;
                 box-shadow:0 12px 32px rgba(10,25,49,0.1);}
            h2{color:#C62828;margin:0 0 12px;font-size:1.5rem;}
            p{color:#4A535C;line-height:1.6;margin:8px 0;}
          </style></head><body><div class="box">';

    if ($isLocal) {
        echo '<h2>⚠️ Database Connection Error</h2>';
        echo '<p>Start <strong>XAMPP</strong> with Apache + MySQL, then reload.</p>';
        echo '<p style="font-size:0.85em;color:#9AA5AF;margin-top:16px;">Error: ' . htmlspecialchars($err) . '</p>';
    } else {
        echo '<h2>🔧 Service Temporarily Unavailable</h2>';
        echo '<p>Please try again in a few minutes.</p>';
    }

    echo '</div></body></html>';
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');

/* ============================================================
   4. ONE-TIME SETUP — LOCAL ONLY
   ============================================================ */
$setupFlagFile = __DIR__ . '/.setup_done';
$needsSetup = $isLocal && !file_exists($setupFlagFile);

if ($needsSetup) {
    /* Create DB (local root has privilege) */
    @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbname`
                          CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    @mysqli_select_db($conn, $dbname);

    /* ---- Tables ---- */
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            firstname VARCHAR(50) NOT NULL,
            lastname VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            profile_image VARCHAR(255) DEFAULT 'default.png',
            role ENUM('user','staff','admin') DEFAULT 'user',
            quiz_score INT DEFAULT 0,
            last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_online TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS incidents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            latitude DECIMAL(10,8) DEFAULT 0,
            longitude DECIMAL(11,8) DEFAULT 0,
            location_name VARCHAR(255),
            image_path VARCHAR(255),
            status VARCHAR(20) DEFAULT 'pending',
            admin_notes TEXT,
            resolved_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS disaster_tips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100),
            title VARCHAR(255),
            content TEXT,
            icon VARCHAR(50) DEFAULT 'shield',
            image_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS quiz_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question TEXT,
            option_a VARCHAR(255),
            option_b VARCHAR(255),
            option_c VARCHAR(255),
            option_d VARCHAR(255),
            correct_answer CHAR(1),
            category VARCHAR(100),
            points INT DEFAULT 10
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS quiz_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            score INT NOT NULL,
            total_questions INT NOT NULL,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS emergency_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            phone VARCHAR(50),
            type VARCHAR(100),
            region VARCHAR(100),
            is_active TINYINT DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS evacuation_routes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            description TEXT,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            type VARCHAR(100),
            capacity INT,
            facilities TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            message TEXT,
            response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(255),
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($tables as $sql) {
        mysqli_query($conn, $sql);
    }

    /* ---- Seed disaster tips ---- */
    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM disaster_tips");
    $row   = $check ? mysqli_fetch_assoc($check) : ['c' => 0];
    if ((int)$row['c'] === 0) {
        mysqli_query($conn, "INSERT INTO disaster_tips (category, title, content, icon, image_url) VALUES
        ('Earthquake', 'Before an Earthquake', 'Secure heavy furniture to walls. Know safe spots in each room - under sturdy tables, against interior walls. Prepare emergency kit with water, food, first aid supplies, flashlight, and batteries. Practice Drop, Cover, and Hold On drills with your family.', 'house', 'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=600'),
        ('Earthquake', 'During an Earthquake', 'DROP to your hands and knees. COVER your head and neck under a sturdy table. HOLD ON until shaking stops. If no shelter nearby, cover head with arms. Stay away from windows and exterior walls. If outdoors, move away from buildings and power lines.', 'person-shelter', 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=600'),
        ('Typhoon', 'Typhoon Preparedness', 'Monitor PAGASA weather updates regularly. Secure roof and windows with storm shutters or plywood. Trim trees near your house. Prepare 3-day supply of food and water. Have battery-powered radio ready. Know your evacuation route and nearest evacuation center.', 'cloud-rain', 'https://images.unsplash.com/photo-1527482797697-8795b05eeb4d?w=600'),
        ('Flood', 'Flood Safety Guide', 'Never walk or drive through flood waters - just 6 inches can knock you down. Move to higher ground immediately. Disconnect electrical appliances. Avoid contact with flood water as it may be contaminated. Keep emergency kit in waterproof container.', 'water', 'https://images.unsplash.com/photo-1547683905-f686c993aae5?w=600'),
        ('Fire', 'Fire Prevention Tips', 'Install smoke alarms on every level of your home. Test them monthly. Create and practice fire escape plan with two ways out. Keep fire extinguisher accessible. Never leave cooking unattended. Clear dry vegetation 30 feet from your home.', 'fire', 'https://images.unsplash.com/photo-1486723312829-f32b4e6e23a4?w=600'),
        ('Landslide', 'Landslide Warning Signs', 'Watch for new cracks in pavement, tilting trees, or sudden water flow changes. Listen for unusual sounds like trees cracking. If you suspect landslide danger, evacuate immediately. Contact local authorities for assessment.', 'mountain', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600'),
        ('Volcanic Eruption', 'Volcano Safety', 'Follow evacuation orders immediately. Wear long-sleeved shirts and pants. Use goggles and wear eyeglasses instead of contact lenses. Use dust mask or damp cloth to help breathing. Stay out of designated restricted zones.', 'activity', 'https://images.unsplash.com/photo-1618410320928-6ea244e228f9?w=600'),
        ('Tsunami', 'Tsunami Preparedness', 'Know your community warning systems. If you feel strong earthquake near coast, move to high ground immediately. Stay away from beach until officials say its safe. Tsunami waves can last for hours.', 'waves', 'https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=600')");
    }

    /* ---- Seed emergency contacts ---- */
    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM emergency_contacts");
    $row   = $check ? mysqli_fetch_assoc($check) : ['c' => 0];
    if ((int)$row['c'] === 0) {
        mysqli_query($conn, "INSERT INTO emergency_contacts (name, phone, type, region) VALUES
        ('National Emergency Hotline', '911', 'General Emergency', 'Nationwide'),
        ('NDRRMC', '(02) 8911-5061', 'Disaster Response', 'Nationwide'),
        ('Philippine Red Cross', '143', 'Medical Emergency', 'Nationwide'),
        ('DENR Hotline', '(02) 8926-3011', 'Environmental', 'Nationwide'),
        ('PHIVOLCS', '(02) 8426-1468', 'Volcano/Earthquake', 'Nationwide'),
        ('PAGASA', '(02) 8284-0800', 'Weather', 'Nationwide'),
        ('BFP Fire', '(02) 8426-0219', 'Fire Emergency', 'Nationwide'),
        ('PNP Hotline', '117', 'Police', 'Nationwide'),
        ('Coast Guard', '(02) 8527-8481', 'Maritime', 'Nationwide'),
        ('MMDA', '136', 'Metro Manila', 'Metro Manila')");
    }

    /* ---- Seed quiz ---- */
    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM quiz_questions");
    $row   = $check ? mysqli_fetch_assoc($check) : ['c' => 0];
    if ((int)$row['c'] === 0) {
        mysqli_query($conn, "INSERT INTO quiz_questions (question, option_a, option_b, option_c, option_d, correct_answer, category, points) VALUES
        ('What is the first thing to do during an earthquake?', 'Run outside', 'Drop, Cover, Hold On', 'Stand in doorway', 'Call for help', 'B', 'Earthquake', 10),
        ('How many inches of moving water can knock down an adult?', '2 inches', '6 inches', '12 inches', '24 inches', 'B', 'Flood', 10),
        ('What is the emergency number in the Philippines?', '117', '911', 'Both 117 and 911', 'Neither', 'C', 'General', 10),
        ('What should NOT be in an emergency kit?', 'Water', 'Food', 'Heavy furniture', 'Flashlight', 'C', 'General', 10),
        ('During typhoon, what protects windows?', 'Curtains', 'Storm shutters', 'Paint', 'Paper', 'B', 'Typhoon', 10),
        ('Where to go during tsunami?', 'Beach', 'High ground', 'Stay home', 'Go boating', 'B', 'Tsunami', 10),
        ('How often test smoke alarms?', 'Yearly', 'Monthly', 'Never', 'Every 5 years', 'B', 'Fire', 10),
        ('What does DENR stand for?', 'Dept of Energy', 'Dept of Environment and Natural Resources', 'Disaster Emergency', 'None', 'B', 'General', 10)");
    }

    /* ---- Seed admin ---- */
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email='admin@smartecotrack.com'");
    if ($check && mysqli_num_rows($check) === 0) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn,
            "INSERT INTO users (firstname, lastname, email, password, role, quiz_score)
             VALUES ('System', 'Admin', 'admin@smartecotrack.com', ?, 'admin', 1000)");
        mysqli_stmt_bind_param($stmt, 's', $hashed);
        mysqli_stmt_execute($stmt);
    }

    /* ---- Seed staff ---- */
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email='staff@smartecotrack.com'");
    if ($check && mysqli_num_rows($check) === 0) {
        $hashed = password_hash('staff123', PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn,
            "INSERT INTO users (firstname, lastname, email, password, role, quiz_score)
             VALUES ('Maria', 'Santos', 'staff@smartecotrack.com', ?, 'staff', 500)");
        mysqli_stmt_bind_param($stmt, 's', $hashed);
        mysqli_stmt_execute($stmt);
    }

    @file_put_contents($setupFlagFile, date('c'));
}

/* ============================================================
   5. SESSION + TIMEZONE
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

/* ============================================================
   6. ONLINE STATUS
   ============================================================ */
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    @mysqli_query($conn, "UPDATE users SET is_online = 1, last_active = NOW() WHERE id = $uid");
    @mysqli_query($conn, "UPDATE users SET is_online = 0
                          WHERE is_online = 1
                            AND last_active < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
}

/* ============================================================
   7. HELPERS
   ============================================================ */

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() { return isset($_SESSION['user_id']); }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
    }
}

if (!function_exists('isStaff')) {
    function isStaff() {
        return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'staff';
    }
}

if (!function_exists('isStaffOrAdmin')) {
    function isStaffOrAdmin() { return isAdmin() || isStaff(); }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn,
            htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

if (!function_exists('clientIp')) {
    function clientIp() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}

if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $description = '') {
        global $conn;
        $ip = clientIp();
        $user_id = (int)$user_id;
        $action = substr($action, 0, 255);
        $description = substr($description, 0, 65535);

        $stmt = @mysqli_prepare($conn,
            "INSERT INTO activity_log (user_id, action, description, ip_address)
             VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $user_id, $action, $description, $ip);
            mysqli_stmt_execute($stmt);
        }
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (!$datetime) return '—';
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60)    . ' mins ago';
        if ($diff < 86400)  return floor($diff / 3600)  . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return date('M d, Y', $time);
    }
}

if (!function_exists('getOnlineUsers')) {
    function getOnlineUsers() {
        global $conn;
        $result = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE is_online = 1");
        $row    = $result ? mysqli_fetch_assoc($result) : ['c' => 0];
        return (int)$row['c'];
    }
}

/* ============================================================
   8. UPLOAD DIRECTORIES (silent, safe permissions)
   ============================================================ */
foreach ([
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/reports',
    __DIR__ . '/uploads/profiles',
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}