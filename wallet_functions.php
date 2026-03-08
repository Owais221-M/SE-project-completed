<?php

function buyBTC(int $userId, float $price): void {
    require_once __DIR__ . '/config.php';

    $amount = 0.001; // Fixed trade size for bot
    $total = $amount * $price;

    // Check USDT balance
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['balance'] < $total) {
        error_log("USER $userId BUY BTC failed: insufficient USDT (need $total)");
        return;
    }

    // Deduct USDT, add BTC
    $stmt = $conn->prepare("UPDATE users SET balance = balance - ?, btc_balance = btc_balance + ? WHERE id = ?");
    $stmt->bind_param("ddi", $total, $amount, $userId);
    $stmt->execute();
    $stmt->close();

    // Log transaction
    $type = 'BUY';
    $coin = 'BTC';
    $orderType = 'market';
    $status = 'completed';
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdddss", $userId, $type, $coin, $amount, $price, $total, $orderType, $status);
    $stmt->execute();
    $stmt->close();

    error_log("USER $userId BUY BTC at $price - SUCCESS");
}

function sellBTC(int $userId, float $price): void {
    require_once __DIR__ . '/config.php';

    $amount = 0.001; // Fixed trade size for bot
    $total = $amount * $price;

    // Check BTC balance
    $stmt = $conn->prepare("SELECT btc_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['btc_balance'] < $amount) {
        error_log("USER $userId SELL BTC failed: insufficient BTC (need $amount)");
        return;
    }

    // Deduct BTC, add USDT
    $stmt = $conn->prepare("UPDATE users SET btc_balance = btc_balance - ?, balance = balance + ? WHERE id = ?");
    $stmt->bind_param("ddi", $amount, $total, $userId);
    $stmt->execute();
    $stmt->close();

    // Log transaction
    $type = 'SELL';
    $coin = 'BTC';
    $orderType = 'market';
    $status = 'completed';
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdddss", $userId, $type, $coin, $amount, $price, $total, $orderType, $status);
    $stmt->execute();
    $stmt->close();

    error_log("USER $userId SELL BTC at $price - SUCCESS");
}
