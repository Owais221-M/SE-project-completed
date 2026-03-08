<?php
session_start();

if (!isset($_SESSION['id'])) {
    die("Login required");
}

require 'config.php';
require_once 'RiskManager.php';
require_once 'api_helper.php';

$user_id    = $_SESSION['id'];
$type       = $_POST['type'] ?? '';
$coin       = $_POST['coin'] ?? '';
$amount     = floatval($_POST['amount'] ?? 0);
$price      = floatval($_POST['price'] ?? 0);
$order_type = $_POST['order_type'] ?? 'market';


// NORMALIZATION (CRITICAL)

$type       = strtoupper(trim($type));        
$order_type = strtolower(trim($order_type));  
$coin       = strtoupper(trim($coin));        

  //  BASIC VALIDATION

if (!$type || !$coin || $amount <= 0) {
    die("Invalid input");
}

// Only BTC and ETH trading is supported
if (!in_array($coin, ['BTC', 'ETH'])) {
    die("Only BTC and ETH trading is supported.");
}


 //  MARKET PRICE FETCH
    
if ($order_type === 'market') {
    $symbol = $coin . 'USDT'; // e.g. BTCUSDT or ETHUSDT
    $fetchedPrice = fetchBinancePrice($symbol);
    if ($fetchedPrice === null) {
        die("Failed to fetch market price for $coin after retries. Please try again.");
    }
    $price = $fetchedPrice;
}

$total = $amount * $price;

// === RISK MANAGEMENT VALIDATION ===
$riskManager = new RiskManager($conn, $user_id);
$riskCheck   = $riskManager->validateTrade($type, $coin, $amount, $price);

if (!$riskCheck['allowed']) {
    die("⚠️ Risk check failed: " . implode(" | ", $riskCheck['errors']));
}

$conn->begin_transaction();

try {

    // Determine the balance column based on the coin
    $balance_column = ($coin === 'BTC') ? 'btc_balance' : 'eth_balance';

   
     //  MARKET BUY
     
    if ($order_type === 'market' && $type === 'BUY') {

        $stmt = $conn->prepare(
            "SELECT balance FROM users WHERE id = ? FOR UPDATE"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['balance'] < $total) {
            throw new Exception("Insufficient USDT");
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET balance = balance - ?, $balance_column = $balance_column + ?
             WHERE id = ?"
        );
        $stmt->bind_param("ddi", $total, $amount, $user_id);
        $stmt->execute();

        $status = 'completed';
    }

    
      //  MARKET SELL
      
    elseif ($order_type === 'market' && $type === 'SELL') {

        $stmt = $conn->prepare(
            "SELECT $balance_column FROM users WHERE id = ? FOR UPDATE"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user[$balance_column] < $amount) {
            throw new Exception("Insufficient $coin");
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET $balance_column = $balance_column - ?, balance = balance + ?
             WHERE id = ?"
        );
        $stmt->bind_param("ddi", $amount, $total, $user_id);
        $stmt->execute();

        $status = 'completed';
    }

     //  LIMIT ORDER

    else {
        $status = 'pending';
    }

   
      // SAVE TRANSACTION
      
    $stmt = $conn->prepare(
        "INSERT INTO transactions
         (user_id, type, coin, amount, price, total, order_type, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "issdddss",
        $user_id,
        $type,
        $coin,
        $amount,
        $price,
        $total,
        $order_type,
        $status
    );
    $stmt->execute();

    $conn->commit();
    echo "Order executed successfully";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}