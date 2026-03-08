<?php
// Test file — uses default session save path
session_start();

$_SESSION['counter'] = ($_SESSION['counter'] ?? 0) + 1;

echo "Session ID: " . session_id() . "<br>";
echo "Counter: " . $_SESSION['counter'];
