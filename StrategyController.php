<?php
session_start();


error_log("SESSION ID: " . session_id());

if (empty($_SESSION['strategy_enabled'])) {
    echo json_encode(['status' => 'STRATEGY_DISABLED']);
    exit;
}

require_once 'StrategyEngine.php';
require_once 'TradeExecutor.php';
require_once 'api_helper.php';

$price = fetchBinancePrice('BTCUSDT');

if (!$price || $price <= 0) {
    echo json_encode(['status' => 'INVALID_PRICE']);
    exit;
}

$strategy = new StrategyEngine();
$strategy->addPrice($price);
$result = $strategy->generateSignal();

$executor = new TradeExecutor();
$execution = $executor->execute(
    $result['signal'],
    $_SESSION['id'],
    $price
);

echo json_encode([
    'status'    => 'OK',
    'price'     => $price,
    'signal'    => $result['signal'],
    'sma50'     => $result['sma50'],
    'sma200'    => $result['sma200'],
    'execution' => is_string($execution) ? $execution : 'NO_ACTION'
]);
