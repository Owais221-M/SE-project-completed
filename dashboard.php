<?php
session_start();

if (!isset($_SESSION['strategy_enabled'])) {
    $_SESSION['strategy_enabled'] = false;
}

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

require_once "config.php";
require __DIR__ . "/blockchain-transactions/vendor/autoload.php";

use Web3\Web3;
use Web3\Utils;

$user_id = $_SESSION['id'];
$sql = "SELECT username, eth_address, balance, btc_balance FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$username     = $row['username'] ?? 'User';
$eth_address  = $row['eth_address'] ?? null;
$usdt_balance = number_format((float)($row['balance'] ?? 0), 2);
$btc_balance  = number_format((float)($row['btc_balance'] ?? 0), 6);

$eth_balance = "N/A";

if ($eth_address && strlen($eth_address) === 42 && strpos($eth_address, '0x') === 0) {
    try {
        $web3 = new Web3('http://127.0.0.1:8545');
        $balanceReady = false;

        $web3->eth->getBalance($eth_address, 'latest', function ($err, $balance) use (&$eth_balance, &$balanceReady) {
            try {
                $value = null;
                $flatten = function ($input) use (&$flatten) {
                    $flat = [];
                    foreach ((array)$input as $item) {
                        if (is_array($item)) {
                            $flat = array_merge($flat, $flatten($item));
                        } else {
                            $flat[] = $item;
                        }
                    }
                    return $flat;
                };

                $flat = $flatten($balance);

                foreach ($flat as $item) {
                    if ($item instanceof \phpseclib\Math\BigInteger) {
                        $value = Utils::fromWei($item, 'ether')->toString();
                        break;
                    } elseif (is_string($item) && strpos($item, '0x') === 0) {
                        $decimal = hexdec($item);
                        $value = Utils::fromWei((string)$decimal, 'ether');
                        break;
                    }
                }

                $eth_balance = $value ?: 'Unavailable';
            } catch (Exception $e) {
                $eth_balance = 'Error';
            }

            $balanceReady = true;
        });

        $timeout = 0;
        while (!$balanceReady && $timeout < 2000000) {
            usleep(100000);
            $timeout += 100000;
        }

        if (!$balanceReady) {
            $eth_balance = 'Timeout';
        }

    } catch (Exception $e) {
        $eth_balance = "Exception";
    }
} else {
    $eth_balance = "Invalid ETH address";
}

$stmt_tx = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt_tx->bind_param("i", $user_id);
$stmt_tx->execute();
$transactions = $stmt_tx->get_result();
$stmt_tx->close();

$stmt_alerts = $conn->prepare("SELECT * FROM price_alerts WHERE user_id = ? ORDER BY created_at DESC");
$stmt_alerts->bind_param("i", $user_id);
$stmt_alerts->execute();
$alerts = $stmt_alerts->get_result();
$stmt_alerts->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CryptoVault</title>
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2"></script>
</head>
<body>
<div class="g-page">

<!-- ── Navbar ── -->
<nav class="g-navbar">
    <div class="g-navbar-inner">
        <a href="dashboard.php" class="g-navbar-brand">
            <span class="brand-icon">₿</span> CryptoVault
        </a>
        <div class="g-nav-links">
            <a href="dashboard.php" class="g-nav-link active">Dashboard</a>
            <a href="buy_sell_form.html" class="g-nav-link">Trade</a>
            <a href="my_orders.php" class="g-nav-link">Orders</a>
            <a href="order_book.php" class="g-nav-link">Order Book</a>
            <a href="create_alert.php" class="g-nav-link">Alerts</a>
            <a href="logout.php" class="g-btn g-btn-danger" style="padding:6px 14px; font-size:0.8rem;">Logout</a>
        </div>
        <button id="theme-toggle" class="theme-toggle-btn">☀️ Light</button>
    </div>
</nav>

<main>
    <div class="dash-grid">

        <!-- ── Row 1: Welcome + Strategy ── -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:var(--space-md);">
            <h1 style="font-size:1.5rem; font-weight:800;">Welcome back, <?php echo htmlspecialchars($username); ?> 👋</h1>
            <form method="POST" action="toggle_strategy.php" class="strategy-toggle-form">
                <button type="submit" class="g-btn <?= $_SESSION['strategy_enabled'] ? 'g-btn-danger' : 'g-btn-primary' ?>" style="font-size:0.8rem;">
                    <?= $_SESSION['strategy_enabled'] ? "🛑 Disable Bot" : "▶️ Enable Bot" ?>
                </button>
            </form>
        </div>

        <!-- ── Row 2: Wallet + Prices ── -->
        <div class="dash-grid-top">

            <!-- Wallet -->
            <div class="dash-card g-animate-in" style="animation-delay:0.1s">
                <div class="dash-card-header">
                    <div class="dash-card-title"><span class="title-icon">💰</span> Portfolio</div>
                </div>
                <div class="wallet-grid">
                    <div class="wallet-item">
                        <span class="coin-icon">💵</span>
                        <div class="coin-label">USDT</div>
                        <div class="coin-value" id="usdt-amount"><?php echo $usdt_balance; ?></div>
                    </div>
                    <div class="wallet-item">
                        <span class="coin-icon">₿</span>
                        <div class="coin-label">BTC</div>
                        <div class="coin-value" id="btc-amount"><?php echo $btc_balance; ?></div>
                    </div>
                    <div class="wallet-item">
                        <span class="coin-icon">⟠</span>
                        <div class="coin-label">ETH</div>
                        <div class="coin-value" id="eth-amount"><?php echo htmlspecialchars($eth_balance); ?></div>
                    </div>
                </div>
                <div class="eth-address-row">
                    <span>ETH Address:</span> <?php echo htmlspecialchars($eth_address); ?>
                </div>
            </div>

            <!-- Live Prices -->
            <div class="dash-card g-animate-in" style="animation-delay:0.2s">
                <div class="dash-card-header">
                    <div class="dash-card-title"><span class="title-icon">📈</span> Live Prices</div>
                </div>
                <div class="price-grid">
                    <a href="#" id="btc-link" class="price-card">
                        <div class="pair-name">BTC / USDT</div>
                        <div class="pair-price" id="btc-price">Loading...</div>
                    </a>
                    <a href="#" id="eth-link" class="price-card">
                        <div class="pair-name">ETH / USDT</div>
                        <div class="pair-price" id="eth-price">Loading...</div>
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Risk Management ── -->
        <div class="dash-card g-animate-in" style="animation-delay:0.3s">
            <div class="dash-card-header">
                <div class="dash-card-title"><span class="title-icon">🛡️</span> Risk Management</div>
                <span id="risk-level" class="g-badge g-badge-green">—</span>
            </div>
            <div class="risk-grid">
                <div class="risk-metric">
                    <label>Portfolio Value</label>
                    <span id="risk-portfolio-value">—</span>
                </div>
                <div class="risk-metric">
                    <label>BTC Exposure</label>
                    <span id="risk-btc-exposure">—</span>
                </div>
                <div class="risk-metric">
                    <label>Daily Volume</label>
                    <span id="risk-daily-volume">—</span>
                </div>
                <div class="risk-metric">
                    <label>Daily Trades</label>
                    <span id="risk-daily-trades">—</span>
                </div>
                <div class="risk-metric">
                    <label>Drawdown</label>
                    <span id="risk-drawdown">—</span>
                </div>
            </div>
        </div>

        <!-- ── Strategy Monitor ── -->
        <div class="dash-card g-animate-in" style="animation-delay:0.35s">
            <div class="dash-card-header">
                <div class="dash-card-title"><span class="title-icon">🤖</span> Strategy Bot</div>
                <span id="strategy-ui-status" class="g-badge <?= $_SESSION['strategy_enabled'] ? 'g-badge-green' : 'g-badge-red' ?>">
                    <?= $_SESSION['strategy_enabled'] ? 'ACTIVE' : 'INACTIVE' ?>
                </span>
            </div>
            <div class="strategy-grid">
                <div class="strategy-stat">
                    <div class="stat-label">Signal</div>
                    <div class="stat-value" id="strategy-signal">—</div>
                </div>
                <div class="strategy-stat">
                    <div class="stat-label">SMA(50)</div>
                    <div class="stat-value" id="strategy-sma50">—</div>
                </div>
                <div class="strategy-stat">
                    <div class="stat-label">SMA(200)</div>
                    <div class="stat-value" id="strategy-sma200">—</div>
                </div>
                <div class="strategy-stat">
                    <div class="stat-label">Last Action</div>
                    <div class="stat-value" id="strategy-action">—</div>
                </div>
            </div>
        </div>

        <!-- ── Market Data Table ── -->
        <div class="dash-card g-animate-in" style="animation-delay:0.4s">
            <div class="dash-card-header">
                <div class="dash-card-title"><span class="title-icon">📊</span> Market Data (24h)</div>
            </div>
            <table class="market-table">
                <thead>
                    <tr><th>Coin</th><th>Price</th><th>24h High</th><th>24h Low</th><th>Volume</th></tr>
                </thead>
                <tbody id="market-data">
                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ── Charts ── -->
        <div class="dash-card g-animate-in" style="animation-delay:0.45s">
            <div class="dash-card-header">
                <div class="dash-card-title"><span class="title-icon">📉</span> Technical Analysis</div>
            </div>

            <div class="chart-controls">
                <label for="chart-interval">Interval</label>
                <select id="chart-interval">
                    <option value="1m">1m</option>
                    <option value="5m">5m</option>
                    <option value="15m">15m</option>
                    <option value="1h" selected>1H</option>
                    <option value="4h">4H</option>
                    <option value="1d">1D</option>
                </select>
                <label><input type="checkbox" id="show-sma" checked> SMA</label>
                <label><input type="checkbox" id="show-rsi" checked> RSI</label>
                <label><input type="checkbox" id="show-macd" checked> MACD</label>
            </div>

            <div id="btc-chart" class="chart-container" style="display:none;">
                <h3>BTC / USDT</h3>
                <canvas id="btcChart"></canvas>
            </div>
            <div id="eth-chart" class="chart-container" style="display:none;">
                <h3>ETH / USDT</h3>
                <canvas id="ethChart"></canvas>
            </div>
            <div id="rsi-chart-container" class="chart-container" style="display:none;">
                <h3>RSI (14)</h3>
                <canvas id="rsiChart" height="100"></canvas>
            </div>
            <div id="macd-chart-container" class="chart-container" style="display:none;">
                <h3>MACD (12, 26, 9)</h3>
                <canvas id="macdChart" height="100"></canvas>
            </div>

            <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; margin-top:var(--space-sm);">Click a price card above to load charts</p>
        </div>

        <!-- ── Recent Transactions ── -->
        <div class="dash-card g-animate-in" style="animation-delay:0.5s">
            <div class="dash-card-header">
                <div class="dash-card-title"><span class="title-icon">🔄</span> Recent Transactions</div>
            </div>
            <table class="data-table">
                <thead>
                    <tr><th>Type</th><th>Coin</th><th>Amount</th><th>Price</th><th>Total</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows === 0): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No transactions yet</td></tr>
                    <?php endif; ?>
                    <?php while ($tx = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="g-badge <?= strtoupper($tx['type']) === 'BUY' ? 'g-badge-green' : 'g-badge-red' ?>">
                                    <?= htmlspecialchars($tx['type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($tx['coin']) ?></td>
                            <td><?= $tx['amount'] ?></td>
                            <td>$<?= number_format((float)$tx['price'], 2) ?></td>
                            <td>$<?= number_format((float)$tx['total'], 2) ?></td>
                            <td style="color:var(--text-muted); font-size:0.78rem;"><?= $tx['created_at'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Price Alerts ── -->
        <div class="dash-card g-animate-in" style="animation-delay:0.55s">
            <div class="dash-card-header">
                <div class="dash-card-title"><span class="title-icon">🔔</span> Price Alerts</div>
                <a href="create_alert.php" class="g-btn g-btn-outline" style="font-size:0.75rem; padding:5px 12px;">+ New Alert</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr><th>Coin</th><th>Target Price</th><th>Status</th><th>Created</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if ($alerts->num_rows === 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No alerts set</td></tr>
                    <?php endif; ?>
                    <?php while ($alert = $alerts->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($alert['coin']) ?></strong></td>
                            <td>$<?= number_format((float)$alert['target_price'], 2) ?></td>
                            <td>
                                <span class="g-badge <?= $alert['notified'] ? 'g-badge-green' : 'g-badge-yellow' ?>">
                                    <?= $alert['notified'] ? 'Triggered' : 'Pending' ?>
                                </span>
                            </td>
                            <td style="color:var(--text-muted); font-size:0.78rem;"><?= $alert['created_at'] ?></td>
                            <td>
                                <?php if (!$alert['notified']): ?>
                                    <form method="POST" action="delete_alert.php" onsubmit="return confirm('Delete this alert?');" style="display:inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <button type="submit" class="delete-alert-btn">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.75rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /dash-grid -->
</main>

<footer class="g-footer">
    <p>&copy; <?php echo date("Y"); ?> CryptoVault — Built with 🔥</p>
</footer>

</div><!-- /g-page -->
<script src="dashboard.js"></script>
</body>
</html>
