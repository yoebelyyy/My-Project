<?php
/* ============================================================
   FILE: logout.php
   Purpose: Mark user offline, destroy session, redirect
   ============================================================ */
require_once 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    @mysqli_query($conn, "UPDATE users SET is_online = 0 WHERE id = $uid");
}

session_destroy();
header('Location: login.php?logout=1');
exit();