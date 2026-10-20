<?php
require_once '../db_connect.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$score = (int)($data['score'] ?? 0);
$total_questions = (int)($data['total_questions'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($total_questions <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quiz data']);
    exit();
}

// Insert quiz result
$stmt = mysqli_prepare($conn, 
    "INSERT INTO quiz_results (user_id, score, total_questions) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iii", $user_id, $score, $total_questions);

if (mysqli_stmt_execute($stmt)) {
    // Update user's total quiz score
    $update_stmt = mysqli_prepare($conn, 
        "UPDATE users SET quiz_score = quiz_score + ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "ii", $score, $user_id);
    mysqli_stmt_execute($update_stmt);
    
    // Get updated score
    $score_stmt = mysqli_prepare($conn, "SELECT quiz_score FROM users WHERE id = ?");
    mysqli_stmt_bind_param($score_stmt, "i", $user_id);
    mysqli_stmt_execute($score_stmt);
    $result = mysqli_stmt_get_result($score_stmt);
    $total_score = mysqli_fetch_assoc($result)['quiz_score'];
    
    // Get rank
    $rank_stmt = mysqli_prepare($conn, 
        "SELECT COUNT(*) + 1 as rank FROM users WHERE quiz_score > ?");
    mysqli_stmt_bind_param($rank_stmt, "i", $total_score);
    mysqli_stmt_execute($rank_stmt);
    $rank_result = mysqli_stmt_get_result($rank_stmt);
    $rank = mysqli_fetch_assoc($rank_result)['rank'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz submitted successfully!',
        'score' => $score,
        'total_score' => $total_score,
        'rank' => $rank
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit quiz']);
}
?>