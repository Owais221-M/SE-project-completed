<?php
session_start();
require 'config.php';

// Fetch pending buy and sell limit orders from transactions table
$buy_orders = $conn->query("SELECT * FROM transactions WHERE UPPER(type)='BUY' AND status='pending' AND order_type='limit' ORDER BY price DESC, created_at ASC");
$sell_orders = $conn->query("SELECT * FROM transactions WHERE UPPER(type)='SELL' AND status='pending' AND order_type='limit' ORDER BY price ASC, created_at ASC");

if (!$buy_orders || !$sell_orders) {
    die("Failed to fetch orders");
}

// Prepared statements for updates — BTC
$updateBuyerBTC  = $conn->prepare("UPDATE users SET balance = balance - ?, btc_balance = btc_balance + ? WHERE id = ?");
$updateSellerBTC = $conn->prepare("UPDATE users SET balance = balance + ?, btc_balance = btc_balance - ? WHERE id = ?");

// Prepared statements for updates — ETH
$updateBuyerETH  = $conn->prepare("UPDATE users SET balance = balance - ?, eth_balance = eth_balance + ? WHERE id = ?");
$updateSellerETH = $conn->prepare("UPDATE users SET balance = balance + ?, eth_balance = eth_balance - ? WHERE id = ?");

$updateOrderAmount = $conn->prepare("UPDATE transactions SET amount = amount - ? WHERE id = ?");
$fulfillOrder = $conn->prepare("UPDATE transactions SET status='completed' WHERE id = ?");

while ($buy = $buy_orders->fetch_assoc()) {
    $conn->begin_transaction();
    try {
        $sell_orders->data_seek(0); // reset pointer
        while ($sell = $sell_orders->fetch_assoc()) {
            // Orders must be for the same coin to match
            if (strtoupper($buy['coin']) !== strtoupper($sell['coin'])) {
                continue;
            }

            if ($buy['price'] >= $sell['price'] && $buy['amount'] > 0 && $sell['amount'] > 0) {
                $trade_amount = min((float)$buy['amount'], (float)$sell['amount']);
                $execution_price = (float)$sell['price'];
                $coin = strtoupper($buy['coin']);

                $buyer_id = (int)$buy['user_id'];
                $seller_id = (int)$sell['user_id'];
                $total_cost = $trade_amount * $execution_price;

                // Select the right prepared statements based on coin
                if ($coin === 'ETH') {
                    $updateBuyer  = $updateBuyerETH;
                    $updateSeller = $updateSellerETH;
                } else {
                    $updateBuyer  = $updateBuyerBTC;
                    $updateSeller = $updateSellerBTC;
                }

                // Deduct USDT from buyer, add coin
                $updateBuyer->bind_param("ddi", $total_cost, $trade_amount, $buyer_id);
                $updateBuyer->execute();

                // Add USDT to seller, deduct coin
                $updateSeller->bind_param("ddi", $total_cost, $trade_amount, $seller_id);
                $updateSeller->execute();

                // Update remaining amounts
                $buy_id = (int)$buy['id'];
                $sell_id = (int)$sell['id'];
                $updateOrderAmount->bind_param("di", $trade_amount, $buy_id);
                $updateOrderAmount->execute();
                $updateOrderAmount->bind_param("di", $trade_amount, $sell_id);
                $updateOrderAmount->execute();

                // If order is fulfilled, mark as completed
                if ($trade_amount >= (float)$buy['amount']) {
                    $fulfillOrder->bind_param("i", $buy_id);
                    $fulfillOrder->execute();
                }

                if ($trade_amount >= (float)$sell['amount']) {
                    $fulfillOrder->bind_param("i", $sell_id);
                    $fulfillOrder->execute();
                }

                // Update in-memory amounts for next iteration
                $buy['amount'] -= $trade_amount;
                $sell['amount'] -= $trade_amount;

                break; // match only one sell per buy per iteration
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Matching failed: " . $e->getMessage());
    }
}

$updateBuyerBTC->close();
$updateSellerBTC->close();
$updateBuyerETH->close();
$updateSellerETH->close();
$updateOrderAmount->close();
$fulfillOrder->close();
