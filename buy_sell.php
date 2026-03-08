<?php
session_start();
header('Content-Type: text/html');

if (!isset($_SESSION['id'])) {
    die("❌ You must be logged in.");
}

require_once __DIR__ . "/vendor/autoload.php";

use Web3\Web3;
use Web3\Utils;
use Web3p\EthereumTx\Transaction;

// Constants
define('MAX_TRADE_RATIO', 0.25); // 25% risk cap
$platformAddress = "0x90F8bf6A479f320ead074411a4B0e7944Ea8c9C1"; // Platform address

// DB Connection
require_once 'config.php';

// Inputs
$user_id = $_SESSION['id'];
$type = $_POST['type'];
$coin = $_POST['coin'];
$amount = floatval($_POST['amount']);
$price = floatval($_POST['price']);
$total = $amount * $price;

// Validate inputs
if (!in_array($type, ['buy', 'sell']) || !in_array($coin, ['BTC', 'ETH']) || $amount <= 0 || $price <= 0) {
    die("❌ Invalid input.");
}

// Get user data
$stmt = $conn->prepare("SELECT balance, btc_balance, eth_balance, eth_address, private_key FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if (!$result->num_rows) die("❌ User not found.");
$user = $result->fetch_assoc();

$currentUSDT = (float)$user['balance'];
$currentBTC  = (float)$user['btc_balance'];
$currentETH  = (float)$user['eth_balance'];
$eth_address = $user['eth_address'];
$private_key = $user['private_key'];

// === BUY ===
if ($type === "buy") {
    if ($total > $currentUSDT * MAX_TRADE_RATIO) {
        die("❌ Trade exceeds 25% of your balance. Lower the amount.");
    }
    if ($currentUSDT < $total) die("❌ Not enough USDT.");
    $currentUSDT -= $total;
    if ($coin === "BTC") {
        $currentBTC += $amount;
    } else {
        $currentETH += $amount;
    }

// === SELL ===
} elseif ($type === "sell") {
    if ($coin === "BTC") {
        if ($currentBTC < $amount) die("❌ Not enough BTC.");
        $currentBTC -= $amount;
        $currentUSDT += $total;
    } else {
        if ($currentETH < $amount) die("❌ Not enough ETH.");

        $web3 = new Web3('http://127.0.0.1:8545');
        $eth = $web3->eth;

        // Get nonce
        $nonce = null;
        $eth->getTransactionCount($eth_address, 'pending', function ($err, $count) use (&$nonce) {
            if ($err !== null) die("❌ Failed to get nonce: " . $err->getMessage());
            $nonce = $count;
        });

        $wait = 0;
        while ($nonce === null && $wait < 10) {
            usleep(200000); $wait++;
        }
        if ($nonce === null) die("❌ Nonce fetch failed.");

        // Prepare and sign transaction
        $wei = Utils::toWei((string)$amount, 'ether');
        $txData = [
            'nonce' => Utils::toHex($nonce, true),
            'to' => $platformAddress,
            'value' => Utils::toHex($wei, true),
            'gas' => '0x5208',
            'gasPrice' => '0x3B9ACA00',
            'chainId' => 1337
        ];

        $transaction = new Transaction($txData);
        $signedTx = '0x' . $transaction->sign($private_key);

        $txHash = null;
        $eth->sendRawTransaction($signedTx, function ($err, $txHashResult) use (&$txHash) {
            if ($err !== null) die("❌ Failed to send signed tx: " . $err->getMessage());
            $txHash = $txHashResult;
        });

        if (!$txHash) die("❌ Transaction not sent.");
        usleep(3000000); // Wait for mining

        // 🔁 Fetch updated ETH balance using GMP (not base_convert)
        $rpc = json_encode([
            "jsonrpc" => "2.0",
            "method" => "eth_getBalance",
            "params" => [$eth_address, "latest"],
            "id" => 1
        ]);
        $ctx = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json",
                "content" => $rpc
            ]
        ]);
        $res = @file_get_contents("http://127.0.0.1:8545", false, $ctx);
        if ($res === false) {
            error_log('[buy_sell] Failed to fetch ETH balance from Ganache RPC');
            $weiHex = '0x0';
        } else {
            $json = json_decode($res, true);
            $weiHex = $json['result'] ?? '0x0';
        }

        // ✅ FIX: Use GMP to convert hex to decimal
        $weiDec = gmp_strval(gmp_init($weiHex, 16), 10);
        $currentETH = bcdiv($weiDec, bcpow('10', 18), 6); // 6 decimal precision

        $currentUSDT += $total;
    }
}

// === Update DB balances ===
$stmt = $conn->prepare("UPDATE users SET balance = ?, btc_balance = ?, eth_balance = ? WHERE id = ?");
$stmt->bind_param("dddi", $currentUSDT, $currentBTC, $currentETH, $user_id);
$stmt->execute();
$stmt->close();

// === Log trade ===
$stmt = $conn->prepare("INSERT INTO transactions (user_id, type, coin, amount, price, total) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issddd", $user_id, $type, $coin, $amount, $price, $total);
$stmt->execute();
$stmt->close();

$conn->close();
echo "✅ $type of $amount $coin successful!<br><a href='dashboard.php'>Back to Dashboard</a>";
