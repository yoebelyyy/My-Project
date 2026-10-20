<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

try {
    $stmt = mysqli_prepare($conn, 
        "SELECT id, firstname, lastname, quiz_score, 
                (SELECT COUNT(*) FROM incidents WHERE user_id = users.id) as reports_count
         FROM users 
         WHERE quiz_score > 0 
         ORDER BY quiz_score DESC 
         LIMIT 20");
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $leaderboard = [];
    $rank = 1;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $leaderboard[] = [
            'rank' => $rank,
            'id' => $row['id'],
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'score' => (int)$row['quiz_score'],
            'reports' => (int)$row['reports_count']
        ];
        $rank++;
    }
    
    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load leaderboard'
    ]);
}
?>