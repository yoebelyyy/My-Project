<?php
require_once '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$message = strtolower(trim($data['message'] ?? ''));

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit();
}

// Save user message if logged in
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO messages (user_id, message, response) VALUES (?, ?, '')");
    mysqli_stmt_bind_param($stmt, "is", $user_id, $message);
    mysqli_stmt_execute($stmt);
}

// AI Chatbot Logic
$response = getChatbotResponse($message, $conn);

// Update response in database
if (isLoggedIn() && isset($stmt)) {
    $message_id = mysqli_insert_id($conn);
    $update_stmt = mysqli_prepare($conn, "UPDATE messages SET response = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "si", $response, $message_id);
    mysqli_stmt_execute($update_stmt);
}

echo json_encode(['success' => true, 'response' => $response]);

function getChatbotResponse($message, $conn) {
    // Greetings
    if (preg_match('/^(hi|hello|hey|greetings)/i', $message)) {
        return "Hello! 👋 I'm EcoBot, your environmental assistant. How can I help you today? You can ask me about:\n\n" .
               "🌿 Environmental incidents\n" .
               "🚨 Disaster preparedness\n" .
               "📋 How to report incidents\n" .
               "📞 Emergency contacts\n" .
               "🌍 Environmental tips";
    }
    
    // Help
    if (preg_match('/(help|what can you do|features)/i', $message)) {
        return "I can help you with:\n\n" .
               "1️⃣ Reporting environmental incidents\n" .
               "2️⃣ Getting disaster preparedness tips\n" .
               "3️⃣ Finding emergency contacts\n" .
               "4️⃣ Learning about environmental protection\n" .
               "5️⃣ Quiz on disaster preparedness\n\n" .
               "Just ask me anything! 🌿";
    }
    
    // Report incident
    if (preg_match('/(report|incident|how to report)/i', $message)) {
        return "To report an environmental incident:\n\n" .
               "1️⃣ Click 'Report Incident' on your dashboard\n" .
               "2️⃣ Fill in the details (title, description, category)\n" .
               "3️⃣ Click on the map to set the location\n" .
               "4️⃣ Upload a photo if available\n" .
               "5️⃣ Submit the report\n\n" .
               "Our team will review and take action! 🌿";
    }
    
    // Emergency contacts
    if (preg_match('/(emergency|contact|phone|hotline|number)/i', $message)) {
        $result = mysqli_query($conn, "SELECT name, phone FROM emergency_contacts WHERE is_active = 1 LIMIT 5");
        $response = "📞 Emergency Contacts:\n\n";
        while ($contact = mysqli_fetch_assoc($result)) {
            $response .= "• {$contact['name']}: {$contact['phone']}\n";
        }
        $response .= "\nFor all emergencies, dial 911 immediately!";
        return $response;
    }
    
    // Disaster tips
    if (preg_match('/(tip|advice|prepare|preparedness|disaster|safety)/i', $message)) {
        // Extract disaster type if mentioned
        $disasters = ['flood', 'earthquake', 'typhoon', 'fire', 'landslide', 'volcano'];
        $found = false;
        
        foreach ($disasters as $disaster) {
            if (stripos($message, $disaster) !== false) {
                $stmt = mysqli_prepare($conn, 
                    "SELECT title, content FROM disaster_tips WHERE category LIKE ? LIMIT 3");
                $search = "%$disaster%";
                mysqli_stmt_bind_param($stmt, "s", $search);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $response = "📚 Tips for $disaster:\n\n";
                    while ($tip = mysqli_fetch_assoc($result)) {
                        $response .= "📌 {$tip['title']}\n{$tip['content']}\n\n";
                    }
                    return $response;
                }
                $found = true;
            }
        }
        
        // General tips if no specific disaster mentioned
        $result = mysqli_query($conn, "SELECT title, content FROM disaster_tips ORDER BY RAND() LIMIT 3");
        $response = "📚 Disaster Preparedness Tips:\n\n";
        while ($tip = mysqli_fetch_assoc($result)) {
            $response .= "📌 {$tip['title']}\n{$tip['content']}\n\n";
        }
        return $response;
    }
    
    // Quiz
    if (preg_match('/(quiz|test|knowledge|learn)/i', $message)) {
        return "🧠 Test your knowledge with our Disaster Preparedness Quiz!\n\n" .
               "• Available on the Dashboard\n" .
               "• Earn points for correct answers\n" .
               "• Compete on the leaderboard\n" .
               "• Learn important safety information\n\n" .
               "Would you like to try a sample question?";
    }
    
    // Environmental facts
    if (preg_match('/(fact|environment|forest|tree|nature|climate)/i', $message)) {
        $facts = [
            "🌳 Forests cover 31% of the world's land area.",
            "💧 A single tree can absorb up to 48 pounds of CO2 per year.",
            "🦋 80% of the world's terrestrial biodiversity lives in forests.",
            "🌍 The Philippines is one of 17 megadiverse countries.",
            "🌿 One large tree can provide a day's supply of oxygen for up to 4 people.",
            "🔥 Forest fires are 90% caused by human activities.",
            "🏔️ The Philippines has over 20 active volcanoes."
        ];
        return $facts[array_rand($facts)] . "\n\nWould you like to know more? 🌿";
    }
    
    // About SmartEcoTrack
    if (preg_match('/(about|smartecotrack|who are you|what is this)/i', $message)) {
        return "🌿 SmartEcoTrack is an Environmental Incident Reporting and Monitoring System\n\n" .
               "Our mission:\n" .
               "• Monitor environmental incidents\n" .
               "• Educate about disaster preparedness\n" .
               "• Connect communities with resources\n" .
               "• Protect Philippine forests and ecosystems\n\n" .
               "We work with DENR and local communities! 🇵🇭";
    }
    
    // Thank you
    if (preg_match('/(thank|thanks|goodbye|bye)/i', $message)) {
        return "You're welcome! 🌿 Stay safe and help protect our environment!\n\n" .
               "Remember: Report incidents, stay prepared, and keep learning!\n\n" .
               "Is there anything else I can help with?";
    }
    
    // Default response
    return "I'm not sure I understand. Can you please rephrase your question? 🤔\n\n" .
           "You can ask me about:\n" .
           "• Reporting environmental incidents\n" .
           "• Disaster preparedness tips\n" .
           "• Emergency contacts\n" .
           "• Environmental facts\n" .
           "• Quiz and learning\n\n" .
           "Or type 'help' to see what I can do!";
}
?>