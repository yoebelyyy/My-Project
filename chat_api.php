<?php
/* ============================================================
   FILE: chat_api.php
   Purpose: AI chatbot backend — proxies to LLM with live context
   ============================================================ */
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

/* ---------- Load config (create this file separately) ---------- */
// config_ai.php should define: AI_PROVIDER, AI_API_KEY, AI_MODEL
$configFile = __DIR__ . '/config_ai.php';
if (file_exists($configFile)) require_once $configFile;

/* ---------- Read user message ---------- */
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];   // [{role: 'user'|'assistant', content: '...'}, ...]

if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty message']);
    exit;
}
if (mb_strlen($message) > 1000) {
    $message = mb_substr($message, 0, 1000);
}

/* ---------- Gather live context for THIS user ---------- */
$user_id = (int)$_SESSION['user_id'];
$user    = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT firstname, lastname, email, role, quiz_score FROM users WHERE id = $user_id LIMIT 1"));
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}
$role = strtolower($user['role']);
$is_admin   = ($role === 'admin');
$is_staff   = ($role === 'staff');
$is_regular = !$is_admin && !$is_staff;

$ctx = [
    'name'          => $user['firstname'],
    'role'          => $role,
    'quiz_score'    => (int)$user['quiz_score'],
];

// Personal stats
$ctx['my_reports']   = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM incidents WHERE user_id=$user_id"))['c'];
$ctx['my_pending']   = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM incidents WHERE user_id=$user_id AND status='pending'"))['c'];
$ctx['my_resolved']  = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) c FROM incidents WHERE user_id=$user_id AND status='resolved'"))['c'];

// Global stats (staff/admin only)
if ($is_admin || $is_staff) {
    $ctx['total_reports']    = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM incidents"))['c'];
    $ctx['pending_all']      = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM incidents WHERE status='pending'"))['c'];
    $ctx['critical_active']  = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM incidents WHERE severity IN ('critical','high') AND status NOT IN ('resolved','closed')"))['c'];
    $ctx['total_users']      = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) c FROM users"))['c'];

    // Recent active emergencies (top 3)
    $em = mysqli_query($conn,
        "SELECT title, severity, location_name, created_at
         FROM incidents
         WHERE severity IN ('critical','high')
           AND status NOT IN ('resolved','closed')
         ORDER BY FIELD(severity,'critical','high'), created_at DESC
         LIMIT 3");
    $ctx['recent_emergencies'] = [];
    if ($em) while ($r = mysqli_fetch_assoc($em)) {
        $ctx['recent_emergencies'][] = [
            'title'    => $r['title'],
            'severity' => $r['severity'],
            'location' => $r['location_name'],
        ];
    }
}

/* ---------- Build system prompt ---------- */
$systemPrompt = <<<SYS
You are "EcoBot", the friendly AI assistant inside SmartEcoTrack — an environmental incident reporting and disaster preparedness platform for the Philippines.

Your responsibilities:
- Help users navigate the app (reporting incidents, viewing reports, taking quizzes, finding emergency contacts).
- Explain environmental issues (flooding, landslides, pollution, deforestation, wildfires, typhoons, earthquakes).
- Give concise, safety-first advice. For any life-threatening emergency, ALWAYS tell them to call 911 first.
- Keep answers under 120 words, friendly, and use plain language.
- You can use emojis sparingly (🌿 🌊 ⚠️).

Rules:
- If asked about personal data of other users, refuse politely.
- If asked to perform actions (like "delete my report"), explain which page they should visit instead of claiming you did it.
- Never invent statistics — use only the CONTEXT below.
- If the question is outside environmental/disaster/app scope, gently steer back.

CURRENT USER CONTEXT:
Name: {$ctx['name']}
Role: {$ctx['role']} (admin = full access, staff = manage reports, user = report + learn)
Quiz points: {$ctx['quiz_score']}
Their own reports: {$ctx['my_reports']} (pending: {$ctx['my_pending']}, resolved: {$ctx['my_resolved']})
SYS;

if ($is_admin || $is_staff) {
    $systemPrompt .= "\nSystem-wide: {$ctx['total_reports']} total reports, {$ctx['pending_all']} pending, "
                  . "{$ctx['critical_active']} critical/high active, {$ctx['total_users']} registered users.";
    if (!empty($ctx['recent_emergencies'])) {
        $systemPrompt .= "\nRecent active emergencies: ";
        $parts = [];
        foreach ($ctx['recent_emergencies'] as $e) {
            $parts[] = "{$e['title']} [{$e['severity']}] at " . ($e['location'] ?: 'unknown location');
        }
        $systemPrompt .= implode('; ', $parts);
    }
}

/* ---------- Fallback: no API configured → rule-based reply ---------- */
if (!defined('AI_API_KEY') || AI_API_KEY === '') {
    echo json_encode([
        'reply'  => localReply($message, $ctx, $is_admin, $is_staff),
        'mode'   => 'local',
    ]);
    exit;
}

/* ---------- Call provider ---------- */
$provider = defined('AI_PROVIDER') ? AI_PROVIDER : 'openai';
$model    = defined('AI_MODEL')    ? AI_MODEL    : 'gpt-4o-mini';

$messages = [['role' => 'system', 'content' => $systemPrompt]];
// Trim history to last 6 turns
$history = array_slice($history, -6);
foreach ($history as $h) {
    if (!isset($h['role'], $h['content'])) continue;
    if (!in_array($h['role'], ['user','assistant'], true)) continue;
    $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['content'], 0, 2000)];
}
$messages[] = ['role' => 'user', 'content' => $message];

$payload = [
    'model'       => $model,
    'messages'    => $messages,
    'temperature' => 0.6,
    'max_tokens'  => 350,
];

if ($provider === 'openai') {
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY,
    ];
    $body = json_encode($payload);
} elseif ($provider === 'gemini') {
    // Gemini uses a different schema — convert
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
         . $model . ':generateContent?key=' . AI_API_KEY;
    $contents = [];
    foreach ($messages as $m) {
        if ($m['role'] === 'system') continue; // Gemini uses systemInstruction separately
        $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $sysMsg = '';
    foreach ($messages as $m) if ($m['role'] === 'system') $sysMsg = $m['content'];
    $body = json_encode([
        'systemInstruction' => ['parts' => [['text' => $sysMsg]]],
        'contents'          => $contents,
        'generationConfig'  => ['temperature' => 0.6, 'maxOutputTokens' => 350],
    ]);
    $headers = ['Content-Type: application/json'];
} else {
    // Unknown provider — fallback to local
    echo json_encode(['reply' => localReply($message, $ctx, $is_admin, $is_staff), 'mode' => 'local']);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 25,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    // Graceful fallback
    echo json_encode([
        'reply'  => localReply($message, $ctx, $is_admin, $is_staff),
        'mode'   => 'local',
        'note'   => 'AI service unavailable; used built-in responder.',
    ]);
    exit;
}

$data = json_decode($response, true);
$reply = '';
if ($provider === 'openai') {
    $reply = $data['choices'][0]['message']['content'] ?? '';
} elseif ($provider === 'gemini') {
    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
}
$reply = trim($reply);
if ($reply === '') {
    $reply = localReply($message, $ctx, $is_admin, $is_staff);
    echo json_encode(['reply' => $reply, 'mode' => 'local']);
    exit;
}

echo json_encode(['reply' => $reply, 'mode' => $provider]);


/* ============================================================
   LOCAL RULE-BASED FALLBACK RESPONDER
   ============================================================ */
function localReply(string $msg, array $ctx, bool $isAdmin, bool $isStaff): string {
    $m = mb_strtolower($msg);

    $match = function(array $keywords) use ($m) {
        foreach ($keywords as $k) if (strpos($m, $k) !== false) return true;
        return false;
    };

    // Emergency first
    if ($match(['emergency', 'help now', 'dying', 'accident', 'urgent', '911'])) {
        return "🚨 For any life-threatening emergency, call **911 immediately**. "
             . "After calling, you can log the incident on the *Report Incident* page so local staff can respond.";
    }

    // Greetings
    if ($match(['hello', 'hi ', 'hey', 'good morning', 'good evening', 'kumusta', 'kamusta'])
        && mb_strlen($m) < 20) {
        return "Hi {$ctx['name']}! 🌿 I'm EcoBot. Ask me anything about reporting incidents, "
             . "disaster tips, or how to use SmartEcoTrack.";
    }

    // How to report
    if ($match(['how to report', 'report incident', 'submit report', 'file report', 'new report'])) {
        if ($isAdmin || $isStaff) {
            return "As staff/admin you don't file citizen reports, but you can **manage incoming ones** on the "
                 . "*Manage Reports* page. Citizens file from *Report Incident* on their dashboard.";
        }
        return "To report an incident: click **Report Incident** in the nav bar, "
             . "pick a category (flooding, landslide, pollution…), add a title, description, "
             . "and mark the location on the map. Submit and staff will review it. ✅";
    }

    // Status of their reports
    if ($match(['my report', 'my reports', 'status of my', 'my pending', 'how many reports'])) {
        return "You currently have **{$ctx['my_reports']} report(s)** — "
             . "{$ctx['my_pending']} pending and {$ctx['my_resolved']} resolved. "
             . "View all in *My Reports*.";
    }

    // Points / quiz
    if ($match(['quiz', 'points', 'score', 'rank'])) {
        return "You have **{$ctx['quiz_score']} quiz points**. 🏆 Take quizzes from the *Quiz* page "
             . "to earn more and learn disaster preparedness.";
    }

    // Flood advice
    if ($match(['flood', 'baha', 'tubig'])) {
        return "🌊 **Flood safety:** Move to higher ground, avoid walking/driving through flowing water "
             . "(15 cm can knock you down), unplug electronics, and monitor PAGASA advisories. "
             . "Report local flooding via *Report Incident* → Flooding.";
    }

    // Landslide
    if ($match(['landslide', 'mudslide', 'guhо', 'guhо'])) {
        return "⛰️ **Landslide safety:** Watch for cracks in the ground, tilting trees, or unusual rumbling. "
             . "Evacuate sideways, not downhill. Report suspicious slopes via *Report Incident*.";
    }

    // Earthquake
    if ($match(['earthquake', 'lindol', 'quake'])) {
        return "🌍 **Earthquake:** Duck, Cover, Hold. Stay away from windows. After shaking stops, "
             . "evacuate to open ground and check for injuries. Report damage via *Report Incident*.";
    }

    // Typhoon
    if ($match(['typhoon', 'bagyo', 'storm', 'hurricane'])) {
        return "🌀 **Typhoon prep:** Secure loose objects, stock 3 days of water/food, charge devices, "
             . "know your evacuation route. Follow PAGASA. Report damage after the storm passes.";
    }

    // Wildfire
    if ($match(['fire', 'wildfire', 'sunog'])) {
        return "🔥 **Fire:** Call 911 immediately. If safe, evacuate upwind. Report wildfires via "
             . "*Report Incident* → Wildfire so responders can be dispatched.";
    }

    // Pollution
    if ($match(['pollution', 'smoke', 'garbage', 'basura', 'dirty'])) {
        return "🏭 Report pollution (illegal dumping, smoke, chemical spills) via *Report Incident* → Pollution. "
             . "Include the location and a photo if possible — it helps staff act faster.";
    }

    // Contacts
    if ($match(['contact', 'hotline', 'phone number', 'call who'])) {
        return "📞 National emergency: **911**. For environmental concerns: **DENR 8888**. "
             . "See *Emergency Contacts* in the nav for local numbers.";
    }

    // Staff/admin stats
    if (($isAdmin || $isStaff) && $match(['all report', 'total report', 'overview', 'stats', 'summary'])) {
        return "📊 Overview: **{$ctx['total_reports']} reports** total, "
             . "**{$ctx['pending_all']} pending**, and "
             . ($ctx['critical_active'] ?? 0) . " critical/high still active. "
             . "Head to *Manage Reports* to act on them.";
    }

    // Help / menu
    if ($match(['help', 'what can you do', 'features', 'guide', 'menu'])) {
        return "I can help with:\n"
             . "• 📝 How to report an incident\n"
             . "• 📊 Your report status & quiz points\n"
             . "• 🌊 Safety tips (flood, earthquake, fire…)\n"
             . "• 📞 Emergency contacts\n"
             . "Just ask!";
    }

    // Thanks / bye
    if ($match(['thank', 'salamat', 'thanks'])) {
        return "You're welcome, {$ctx['name']}! Stay safe and keep our communities green. 🌿";
    }

    // Default
    return "I'm not sure I understood that. Try asking things like:\n"
         . "• *\"How do I report a flood?\"*\n"
         . "• *\"What are my report stats?\"*\n"
         . "• *\"Earthquake safety tips\"*\n"
         . "Or type **help** for the full menu.";
}