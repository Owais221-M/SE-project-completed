<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$_SESSION['strategy_enabled'] = !($_SESSION['strategy_enabled'] ?? false);
header("Location: dashboard.php");
exit;

