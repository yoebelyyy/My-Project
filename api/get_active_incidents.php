<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

try {
    $stmt = mysqli_prepare($conn, 
        "SELECT i.id, i.title, i.category, i.severity, i.latitude, i.longitude, 
                i.status, i.created_at, u.firstname, u.lastname
         FROM incidents i 
         JOIN users u ON i.user_id = u.id 
         WHERE i.status != 'closed' 
         ORDER BY i.created_at DESC 
         LIMIT 100");
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $incidents = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $incidents[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'severity' => $row['severity'],
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'reporter' => $row['firstname'] . ' ' . $row['lastname']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'incidents' => $incidents,
        'count' => count($incidents)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load incidents'
    ]);
}
?>