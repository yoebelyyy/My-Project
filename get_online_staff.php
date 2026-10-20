<?php
/* ============================================================
   FILE: get_online_staff.php
   Purpose: JSON endpoint — returns list of currently online staff
   ============================================================ */
require_once 'db_connect.php';

// Always JSON, never cached
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$staff = [];

try {
    // ——— Safety check: do the required columns exist? ———
    $cols_check = mysqli_query($conn,
        "SHOW COLUMNS FROM users WHERE Field IN ('is_online','last_active','role')");
    $found_cols = [];
    if ($cols_check) {
        while ($c = mysqli_fetch_assoc($cols_check)) {
            $found_cols[] = $c['Field'];
        }
    }

    $required = ['is_online', 'last_active', 'role'];
    $missing  = array_diff($required, $found_cols);

    if (!empty($missing)) {
        // Columns not added yet — return empty list with a hint
        echo json_encode([
            'count'     => 0,
            'staff'     => [],
            'timestamp' => date('c'),
            'warning'   => 'Missing columns: ' . implode(', ', $missing)
        ]);
        exit();
    }

    // ——— Query online staff (active in last 5 minutes) ———
    $query = "SELECT id, firstname, lastname, email, role, last_active
              FROM users
              WHERE is_online = 1
                AND role IN ('staff','admin')
                AND last_active >= NOW() - INTERVAL 5 MINUTE
              ORDER BY last_active DESC";

    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $minutes_ago = max(0, (int)((time() - strtotime($row['last_active'])) / 60));
            $staff[] = [
                'id'          => (int)$row['id'],
                'name'        => trim($row['firstname'] . ' ' . $row['lastname']),
                'initials'    => strtoupper(
                                    substr($row['firstname'] ?: '?', 0, 1) .
                                    substr($row['lastname']  ?: '?', 0, 1)
                                 ),
                'email'       => $row['email'],
                'role'        => strtolower($row['role']),
                'last_active' => $row['last_active'],
                'minutes_ago' => $minutes_ago,
            ];
        }
    }

    echo json_encode([
        'count'     => count($staff),
        'staff'     => $staff,
        'timestamp' => date('c')
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Server error',
        'message' => $e->getMessage(),
        'count'   => 0,
        'staff'   => []
    ]);
}