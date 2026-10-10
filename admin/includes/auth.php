<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_email'])) {

    header("Location: /park-des-princes/admin/login.php");
    exit;
}