<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

try {
    // Get random questions
    $stmt = mysqli_prepare($conn, 
        "SELECT id, question, option_a, option_b, option_c, option_d, 
                correct_answer, category, points 
         FROM quiz_questions 
         ORDER BY RAND() 
         LIMIT 5");
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $questions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = [
            'id' => $row['id'],
            'question' => $row['question'],
            'option_a' => $row['option_a'],
            'option_b' => $row['option_b'],
            'option_c' => $row['option_c'],
            'option_d' => $row['option_d'],
            'correct_answer' => $row['correct_answer'],
            'category' => $row['category'],
            'points' => (int)$row['points']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'count' => count($questions)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load questions'
    ]);
}
?>