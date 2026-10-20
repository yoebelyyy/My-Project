<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Incident ID required']);
    exit();
}

$id = (int)$_GET['id'];

$stmt = mysqli_prepare($conn, 
    "SELECT i.*, u.firstname, u.lastname, u.email, u.phone
     FROM incidents i 
     JOIN users u ON i.user_id = u.id 
     WHERE i.id = ?");

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($incident = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'incident' => [
            'id' => $incident['id'],
            'title' => $incident['title'],
            'description' => $incident['description'],
            'category' => $incident['category'],
            'severity' => $incident['severity'],
            'latitude' => (float)$incident['latitude'],
            'longitude' => (float)$incident['longitude'],
            'location_name' => $incident['location_name'],
            'image_path' => $incident['image_path'],
            'status' => $incident['status'],
            'admin_notes' => $incident['admin_notes'],
            'created_at' => $incident['created_at'],
            'resolved_at' => $incident['resolved_at'],
            'reporter' => [
                'name' => $incident['firstname'] . ' ' . $incident['lastname'],
                'email' => $incident['email'],
                'phone' => $incident['phone']
            ]
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incident not found']);
}
?>