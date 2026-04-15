<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("ДОСТУП ЗАПРЕЩЕН. <a href='login.php'>Войти</a>");
}
?>