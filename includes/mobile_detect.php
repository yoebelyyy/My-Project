<?php
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileAgents = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'webOS',
        'BlackBerry', 'Windows Phone', 'Opera Mini', 'IEMobile'
    ];
    
    foreach ($mobileAgents as $agent) {
        if (stripos($userAgent, $agent) !== false) {
            return true;
        }
    }
    return false;
}
?>