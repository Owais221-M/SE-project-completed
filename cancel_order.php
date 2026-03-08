<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['id']) || !isset($_POST['order_id'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['id'];
$order_id = (int)$_POST['order_id'];

// Fetch the order first to check it's pending and get details for refund
$stmt = $conn->prepare("SELECT type, coin, amount, price, total, status FROM transactions WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || $order['status'] !== 'pending') {
    header("Location: my_orders.php");
    exit;
}

$conn->begin_transaction();
try {
    // Mark as cancelled
    $stmt = $conn->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Refund: BUY orders had USDT locked, SELL orders had crypto locked
    $type = strtoupper($order['type']);
    $coin = strtoupper($order['coin']);
    $amount = (float)$order['amount'];
    $total = (float)$order['total'];

    if ($type === 'BUY') {
        // Refund USDT that was reserved
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $total, $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($type === 'SELL') {
        // Refund the crypto that was reserved
        if ($coin === 'BTC') {
            $stmt = $conn->prepare("UPDATE users SET btc_balance = btc_balance + ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($coin === 'ETH') {
            $stmt = $conn->prepare("UPDATE users SET eth_balance = eth_balance + ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Cancel order failed: " . $e->getMessage());
}

header("Location: my_orders.php");
exit;
