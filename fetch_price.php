<?php
require_once 'config.php';
require_once 'api_helper.php';

// Record BTC price
$btcPrice = fetchBinancePrice('BTCUSDT');
if ($btcPrice !== null) {
    $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('BTC', ?, NOW())");
    $stmt->bind_param("d", $btcPrice);
    $stmt->execute();
    $stmt->close();
}

// Record ETH price
$ethPrice = fetchBinancePrice('ETHUSDT');
if ($ethPrice !== null) {
    $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('ETH', ?, NOW())");
    $stmt->bind_param("d", $ethPrice);
    $stmt->execute();
    $stmt->close();
}
?>
