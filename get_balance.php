<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

require_once 'config.php';

// ✅ Hex to Decimal with BCMath
function hexToDecimalBcmath($hex) {
    $hex = strtolower(ltrim($hex, "0x"));
    $dec = '0';
    $len = strlen($hex);
    for ($i = 0; $i < $len; $i++) {
        $current = strpos('0123456789abcdef', $hex[$i]);
        $power = bcpow('16', (string)($len - $i - 1));
        $dec = bcadd($dec, bcmul((string)$current, $power));
    }
    return $dec;
}

$user_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT balance, btc_balance, eth_address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$usdt = round((float)$user['balance'], 2);
$btc = round((float)$user['btc_balance'], 6);
$eth_address = $user['eth_address'];
$eth_balance = "Unavailable";

// ✅ Get ETH balance from Ganache
if ($eth_address && strlen($eth_address) === 42 && strpos($eth_address, '0x') === 0) {
    $url = 'http://127.0.0.1:8545';
    $data = [
        "jsonrpc" => "2.0",
        "method" => "eth_getBalance",
        "params" => [$eth_address, "latest"],
        "id" => 1
    ];

    $options = [
        "http" => [
            "method"  => "POST",
            "header"  => "Content-Type: application/json",
            "content" => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response !== false) {
        $res = json_decode($response, true);
        if (isset($res['result']) && strpos($res['result'], '0x') === 0) {
            $wei = hexToDecimalBcmath($res['result']);
            $eth_balance = bcdiv($wei, bcpow('10', 18), 6); // to ETH

            // ✅ Update user's ETH balance in DB
            $update = $conn->prepare("UPDATE users SET eth_balance = ? WHERE id = ?");
            $update->bind_param("di", $eth_balance, $user_id);
            $update->execute();
            $update->close();
        }
    } else {
        error_log("❌ Ganache RPC call failed.");
    }
}

// ✅ Return all balances
echo json_encode([
    'balance' => number_format($usdt, 2, '.', ''),
    'btc_balance' => number_format($btc, 6, '.', ''),
    'eth_balance' => number_format($eth_balance, 6, '.', '')
]);

$conn->close();
