<?php
require_once __DIR__ . '/../db_connect.php';

if (!isLoggedIn()) {
    $_SESSION['error'] = "Please log in to access this page.";
    redirect('../login.php');
}
?>