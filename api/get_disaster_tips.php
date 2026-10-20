<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

$category = $_GET['category'] ?? '';

try {
    if (!empty($category)) {
        $stmt = mysqli_prepare($conn, 
            "SELECT * FROM disaster_tips WHERE category LIKE ? ORDER BY created_at DESC");
        $search = "%$category%";
        mysqli_stmt_bind_param($stmt, "s", $search);
    } else {
        $stmt = mysqli_prepare($conn, 
            "SELECT * FROM disaster_tips ORDER BY created_at DESC");
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $tips = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tips[] = [
            'id' => $row['id'],
            'category' => $row['category'],
            'title' => $row['title'],
            'content' => $row['content'],
            'icon' => $row['icon']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tips' => $tips,
        'count' => count($tips)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load tips'
    ]);
}
?>