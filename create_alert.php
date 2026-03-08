<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Alert — CryptoVault</title>
  <link rel="stylesheet" href="global.css" />
  <link rel="stylesheet" href="create_alert.css" />
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
      <a href="order_book.php" class="g-nav-link">Order Book</a>
      <a href="create_alert.php" class="g-nav-link active">Alerts</a>
      <a href="logout.php" class="g-btn g-btn-danger" style="padding:6px 14px; font-size:0.8rem;">Logout</a>
    </div>
  </div>
</nav>

<main>
  <div class="alert-wrap">
    <div class="g-card">

      <div class="alert-icon">🔔</div>
      <h2 style="text-align:center; font-weight:800; margin-bottom:var(--space-xs);">Create Price Alert</h2>
      <p class="alert-hint">Get notified when a coin hits your target price</p>

      <form method="POST" action="save_alert.php" class="alert-form">

        <div>
          <label for="coin" class="g-label">Coin</label>
          <select name="coin" id="coin" class="g-select">
            <option value="BTC">BTC — Bitcoin</option>
            <option value="ETH">ETH — Ethereum</option>
          </select>
        </div>

        <div>
          <label for="target_price" class="g-label">Target Price (USDT)</label>
          <input type="number" step="0.01" name="target_price" id="target_price" required class="g-input" placeholder="e.g. 70000.00">
        </div>

        <button type="submit" class="g-btn g-btn-primary" style="width:100%;">Set Alert</button>
      </form>

    </div>
  </div>
</main>

<footer class="g-footer">
  <p>&copy; 2025 CryptoVault — Built with 🔥</p>
</footer>

</div>
</body>
</html>
