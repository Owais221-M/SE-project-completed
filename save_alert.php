<?php
session_start();
if (!isset($_SESSION['id'])) die("Login required");

require_once 'config.php';

$coin = $_POST['coin'] ?? '';
if (!in_array($coin, ['BTC', 'ETH'])) die("Invalid coin");
$price = floatval($_POST['target_price'] ?? 0);
if ($price <= 0) die("Invalid target price");
$user_id = $_SESSION['id'];

$stmt = $conn->prepare("INSERT INTO price_alerts (user_id, coin, target_price) VALUES (?, ?, ?)");
$stmt->bind_param("isd", $user_id, $coin, $price);
$stmt->execute();

echo "✅ Alert set successfully!";
