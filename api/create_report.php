<?php
require_once '../db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get form data
$title = sanitize($_POST['title'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$category = sanitize($_POST['category'] ?? '');
$severity = sanitize($_POST['severity'] ?? 'low');
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);
$location_name = sanitize($_POST['location_name'] ?? '');

// Validate required fields
if (empty($title) || empty($description) || empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit();
}

// Validate category
$valid_categories = ['flooding', 'landslide', 'deforestation', 'pollution', 'wildfire', 'wildlife', 'other'];
if (!in_array($category, $valid_categories)) {
    echo json_encode(['success' => false, 'message' => 'Invalid category.']);
    exit();
}

// Validate severity
$valid_severities = ['low', 'medium', 'high', 'critical'];
if (!in_array($severity, $valid_severities)) {
    echo json_encode(['success' => false, 'message' => 'Invalid severity level.']);
    exit();
}

// Handle image upload
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $upload = uploadImage($_FILES['image'], 'reports');
    if ($upload['success']) {
        $image_path = $upload['filename'];
    } else {
        echo json_encode(['success' => false, 'message' => $upload['error']]);
        exit();
    }
}

// Insert incident
$stmt = mysqli_prepare($conn, 
    "INSERT INTO incidents (user_id, title, description, category, severity, latitude, longitude, location_name, image_path) 
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

mysqli_stmt_bind_param($stmt, "issssddss", 
    $user_id, $title, $description, $category, $severity, 
    $latitude, $longitude, $location_name, $image_path);

if (mysqli_stmt_execute($stmt)) {
    $incident_id = mysqli_insert_id($conn);
    echo json_encode([
        'success' => true, 
        'message' => 'Report submitted successfully!',
        'incident_id' => $incident_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit report. Please try again.']);
}
?>