<?php
require_once 'api_helper.php';

$api_url = "https://api.binance.com/api/v3/ticker/24hr";
$coins = ['BTCUSDT', 'ETHUSDT'];

$data = [];
foreach ($coins as $symbol) {
    $result = fetchJsonWithRetry("$api_url?symbol=$symbol");
    if ($result !== null) {
        $data[$symbol] = $result;
    } else {
        $data[$symbol] = ['error' => "Failed to fetch data for $symbol after retries"];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
