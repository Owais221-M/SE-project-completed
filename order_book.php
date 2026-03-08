<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

require_once "config.php";

// Using UPPER() for case-insensitive matching to handle both 'buy'/'BUY' formats
$buy_orders = $conn->query("SELECT * FROM transactions WHERE UPPER(type) = 'BUY' AND status = 'pending' ORDER BY price DESC LIMIT 10");
$sell_orders = $conn->query("SELECT * FROM transactions WHERE UPPER(type) = 'SELL' AND status = 'pending' ORDER BY price ASC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Book — CryptoVault</title>
  <link rel="stylesheet" href="global.css" />
  <link rel="stylesheet" href="order_book.css" />
</head>
<body>
<div class="g-page">

<nav class="g-navbar">
  <div class="g-navbar-inner">
    <a href="dashboard.php" class="g-navbar-brand"><span class="brand-icon">₿</span> CryptoVault</a>
    <div class="g-nav-links">
      <a href="dashboard.php" class="g-nav-link">Dashboard</a>
      <a href="buy_sell_form.html" class="g-nav-link">Trade</a>
      <a href="my_orders.php" class="g-nav-link">Orders</a>
      <a href="order_book.php" class="g-nav-link active">Order Book</a>
      <a href="create_alert.php" class="g-nav-link">Alerts</a>
      <a href="logout.php" class="g-btn g-btn-danger" style="padding:6px 14px; font-size:0.8rem;">Logout</a>
    </div>
  </div>
</nav>

<main>
  <div class="ob-wrap">
    <div class="g-card">

      <h2 style="text-align:center; font-weight:800; margin-bottom:var(--space-lg);">
        <span style="font-size:1.5rem;">📕</span> Order Book
      </h2>

      <div class="ob-columns">

        <!-- Sell Orders -->
        <div>
          <div class="ob-section-title sell">🔴 Sell Orders (Asks)</div>
          <?php if ($sell_orders->num_rows === 0): ?>
            <div class="ob-empty">No sell orders</div>
          <?php else: ?>
          <table class="ob-table">
            <thead><tr><th>Price</th><th>Amount</th><th>Total</th></tr></thead>
            <tbody>
              <?php while ($order = $sell_orders->fetch_assoc()): ?>
                <tr>
                  <td class="ob-price-sell"><?= htmlspecialchars($order['price']) ?></td>
                  <td><?= htmlspecialchars($order['amount']) ?></td>
                  <td>$<?= number_format($order['price'] * $order['amount'], 2) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <!-- Buy Orders -->
        <div>
          <div class="ob-section-title buy">🟢 Buy Orders (Bids)</div>
          <?php if ($buy_orders->num_rows === 0): ?>
            <div class="ob-empty">No buy orders</div>
          <?php else: ?>
          <table class="ob-table">
            <thead><tr><th>Price</th><th>Amount</th><th>Total</th></tr></thead>
            <tbody>
              <?php while ($order = $buy_orders->fetch_assoc()): ?>
                <tr>
                  <td class="ob-price-buy"><?= htmlspecialchars($order['price']) ?></td>
                  <td><?= htmlspecialchars($order['amount']) ?></td>
                  <td>$<?= number_format($order['price'] * $order['amount'], 2) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

      </div><!-- /ob-columns -->
    </div>
  </div>
</main>

<footer class="g-footer">
  <p>&copy; <?= date("Y") ?> CryptoVault — Built with 🔥</p>
</footer>

</div>
</body>
</html>
