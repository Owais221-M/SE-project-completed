<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

require_once "config.php";

$user_id = $_SESSION['id'];

$stmt = $conn->prepare("
    SELECT id, type, coin, amount, price, created_at 
    FROM transactions 
    WHERE user_id = ? 
      AND order_type = 'limit' 
      AND status = 'pending'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — CryptoVault</title>
  <link rel="stylesheet" href="global.css">
  <link rel="stylesheet" href="my_orders.css">
</head>
<body>
<div class="g-page">

<nav class="g-navbar">
  <div class="g-navbar-inner">
    <a href="dashboard.php" class="g-navbar-brand"><span class="brand-icon">₿</span> CryptoVault</a>
    <div class="g-nav-links">
      <a href="dashboard.php" class="g-nav-link">Dashboard</a>
      <a href="buy_sell_form.html" class="g-nav-link">Trade</a>
      <a href="my_orders.php" class="g-nav-link active">Orders</a>
      <a href="order_book.php" class="g-nav-link">Order Book</a>
      <a href="create_alert.php" class="g-nav-link">Alerts</a>
      <a href="logout.php" class="g-btn g-btn-danger" style="padding:6px 14px; font-size:0.8rem;">Logout</a>
    </div>
  </div>
</nav>

<main>
  <div class="orders-wrap">
    <div class="g-card">

      <h2 style="text-align:center; font-weight:800; margin-bottom:var(--space-lg);">
        <span style="font-size:1.5rem;">📝</span> My Pending Limit Orders
      </h2>

      <table class="g-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Coin</th>
            <th>Amount</th>
            <th>Price</th>
            <th>Total (USDT)</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td>
                  <span class="g-badge <?= strtoupper($row['type']) === 'BUY' ? 'g-badge-green' : 'g-badge-red' ?>">
                    <?= htmlspecialchars($row['type']) ?>
                  </span>
                </td>
                <td><strong><?= htmlspecialchars($row['coin']) ?></strong></td>
                <td><?= $row['amount'] ?></td>
                <td>$<?= number_format((float)$row['price'], 2) ?></td>
                <td>$<?= number_format($row['price'] * $row['amount'], 2) ?></td>
                <td style="color:var(--text-muted); font-size:0.78rem;"><?= $row['created_at'] ?></td>
                <td>
                  <form method="POST" action="cancel_order.php" onsubmit="return confirm('Cancel this order?');">
                    <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="cancel-btn">Cancel</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="orders-empty">No pending limit orders</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

    </div>
  </div>
</main>

<footer class="g-footer">
  <p>&copy; <?= date("Y") ?> CryptoVault — Built with 🔥</p>
</footer>

</div>
</body>
</html>
