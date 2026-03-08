<?php
require_once 'config.php';
require_once 'api_helper.php';

$btc_price = fetchBinancePrice('BTCUSDT');
if ($btc_price === null) {
    error_log('[record_price] Failed to fetch BTC price after retries.');
    die("Failed to fetch price");
}

$stmt = $conn->prepare("INSERT INTO price_history (asset, price) VALUES ('BTC', ?)");
$stmt->bind_param("d", $btc_price);
$stmt->execute();
$stmt->close();
?>
