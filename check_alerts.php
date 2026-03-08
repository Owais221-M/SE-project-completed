<?php
require 'config.php'; 
require 'vendor/autoload.php';
require_once 'api_helper.php';

use PHPMailer\PHPMailer\PHPMailer;

// Step 1: Get current BTC and ETH prices (with retry & error handling)
$prices = [];
foreach (['BTC', 'ETH'] as $coin) {
    $fetchedPrice = fetchBinancePrice("{$coin}USDT");
    if ($fetchedPrice === null) {
        error_log("[check_alerts] Failed to fetch {$coin} price after retries. Skipping.");
        continue;
    }
    $prices[$coin] = $fetchedPrice;
}

// Step 2: Get all alerts that are not yet triggered
$alerts = $conn->query("SELECT a.*, u.email FROM price_alerts a JOIN users u ON a.user_id = u.id WHERE a.notified = 0");

// Step 3: Loop through alerts
while ($row = $alerts->fetch_assoc()) {
    $user_email = $row['email'];
    $coin = $row['coin'];
    $target = floatval($row['target_price']);
    $id = $row['id'];

    if (!isset($prices[$coin])) continue; // skip if price unavailable
    $current = $prices[$coin];

    // Trigger alert if current price is above or equal to target
    if ($current >= $target) {
        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ansariowais616@gmail.com';
            $mail->Password = 'iibditzugnczvxte';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('ansariowais616@gmail.com', 'Crypto Alert Bot');
            $mail->addAddress($user_email);
            $mail->Subject = "🔔 {$coin} Alert Triggered!";
            $mail->Body = "The price of {$coin} is now {$current} USDT which has crossed your target of {$target}.";

            if ($mail->send()) {
                $updateStmt = $conn->prepare("UPDATE price_alerts SET notified = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $id);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } catch (Exception $e) {
            // Optional: log or echo error
        }
    }
}
