<?php
require_once __DIR__ . '/../db_connect.php';

// Function to get user by ID
function getUserById($user_id) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// Function to get recent incidents
function getRecentIncidents($limit = 5) {
    global $conn;
    $stmt = mysqli_prepare($conn, 
        "SELECT i.*, u.firstname, u.lastname 
         FROM incidents i 
         JOIN users u ON i.user_id = u.id 
         ORDER BY i.created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Function to get incident by ID
function getIncidentById($id) {
    global $conn;
    $stmt = mysqli_prepare($conn, 
        "SELECT i.*, u.firstname, u.lastname, u.email, u.phone 
         FROM incidents i 
         JOIN users u ON i.user_id = u.id 
         WHERE i.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// Function to get user statistics
function getUserStats($user_id) {
    global $conn;
    
    // Count reports
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM incidents WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $reports = mysqli_fetch_assoc($result)['count'];
    
    // Get quiz score
    $stmt = mysqli_prepare($conn, "SELECT quiz_score FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $quiz_score = mysqli_fetch_assoc($result)['quiz_score'];
    
    return [
        'reports' => $reports,
        'quiz_score' => $quiz_score
    ];
}

// Function to check if email exists
function emailExists($email) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    return mysqli_stmt_num_rows($stmt) > 0;
}

// Function to generate reset token
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Function to format date
function formatDate($date) {
    return date('F j, Y g:i A', strtotime($date));
}

// Function to get severity badge
function getSeverityBadge($severity) {
    $badges = [
        'low' => '<span class="badge bg-success">Low</span>',
        'medium' => '<span class="badge bg-warning">Medium</span>',
        'high' => '<span class="badge bg-danger">High</span>',
        'critical' => '<span class="badge bg-dark">Critical</span>'
    ];
    return $badges[$severity] ?? $badges['low'];
}

// Function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'flooding' => '🌊',
        'landslide' => '⛰️',
        'deforestation' => '🪓',
        'pollution' => '🏭',
        'wildfire' => '🔥',
        'wildlife' => '🦅',
        'other' => '📋'
    ];
    return $icons[$category] ?? '📋';
}

// Function to validate image upload
function validateImage($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $file['name'];
    $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($filetype, $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG & GIF allowed.'];
    }
    
    if ($file['size'] > 5000000) { // 5MB max
        return ['success' => false, 'error' => 'File too large. Maximum 5MB allowed.'];
    }
    
    return ['success' => true, 'extension' => $filetype];
}

// Function to upload image
function uploadImage($file, $directory) {
    $validation = validateImage($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    $upload_dir = "../uploads/$directory/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = uniqid() . '.' . $validation['extension'];
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}
?>