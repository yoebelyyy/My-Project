<?php
/* ============================================================
   FILE: incident_helpers.php
   Shared auth, permissions, CSRF, activity logging, UI helpers
   ============================================================ */

require_once 'db_connect.php';

/* ---------------- Auth ---------------- */

if (!function_exists('current_user')) {
    function current_user(mysqli $conn): array {
        if (!isLoggedIn()) {
            redirect('login.php');
        }
        $uid = (int)$_SESSION['user_id'];
        $res = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid");
        $user = $res ? mysqli_fetch_assoc($res) : null;

        if (!$user) {
            session_destroy();
            redirect('login.php?timeout=1');
        }

        mysqli_query($conn, "UPDATE users SET last_active = NOW() WHERE id = $uid");
        return $user;
    }
}

if (!function_exists('user_role')) {
    function user_role(array $user): string {
        return strtolower(trim($user['role'] ?? ''));
    }
}

if (!function_exists('is_admin')) {
    function is_admin(array $user): bool {
        return user_role($user) === 'admin';
    }
}

if (!function_exists('is_staff')) {
    function is_staff(array $user): bool {
        return user_role($user) === 'staff';
    }
}

if (!function_exists('can_manage_incidents')) {
    function can_manage_incidents(array $user): bool {
        return in_array(user_role($user), ['admin', 'staff'], true);
    }
}

if (!function_exists('require_manager')) {
    function require_manager(array $user): void {
        if (!can_manage_incidents($user)) {
            http_response_code(403);
            exit('Forbidden — you do not have permission to manage incidents.');
        }
    }
}

/* ---------------- CSRF ---------------- */

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        $sent = $_POST['csrf_token'] ?? '';
        if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
            http_response_code(419);
            exit('Invalid CSRF token. Please go back and try again.');
        }
    }
}

/* ---------------- Activity Log ---------------- */

if (!function_exists('log_activity')) {
    function log_activity(mysqli $conn, int $incidentId, int $userId,
                          string $action, ?string $old = null,
                          ?string $new = null, ?string $note = null): void {
        // Check table exists
        $t = mysqli_query($conn, "SHOW TABLES LIKE 'incident_activity'");
        if (!$t || mysqli_num_rows($t) === 0) return;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO incident_activity
                (incident_id, user_id, action, old_value, new_value, note)
             VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return;

        mysqli_stmt_bind_param($stmt, 'iissss',
            $incidentId, $userId, $action, $old, $new, $note);
        mysqli_stmt_execute($stmt);
    }
}

/* ---------------- UI Helpers ---------------- */

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

if (!function_exists('category_icon')) {
    function category_icon(string $cat): string {
        $icons = [
            'flooding'      => '🌊',
            'landslide'     => '⛰️',
            'deforestation' => '🌳',
            'pollution'     => '🏭',
            'wildfire'      => '🔥',
            'wildlife'      => '🦅',
            'earthquake'    => '🌍',
            'typhoon'       => '🌀',
            'other'         => '📋',
        ];
        return $icons[strtolower($cat)] ?? '📋';
    }
}