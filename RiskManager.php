<?php
/**
 * RiskManager - Complex custom logic for risk management
 * 
 * Enforces:
 * 1. Maximum trade size as % of portfolio
 * 2. Maximum position limits (total BTC holding cap)
 * 3. Daily trade volume limits
 * 4. Portfolio risk metrics (exposure %, drawdown tracking)
 * 5. Prevents excessive risk-taking
 */

require_once __DIR__ . '/config.php';

class RiskManager
{
    // --- Configuration ---
    private float $maxTradePercent    = 0.25;  // Max 25% of portfolio per trade
    private float $maxPositionBTC    = 1.0;    // Max 1 BTC total position
    private float $maxPositionETH    = 10.0;   // Max 10 ETH total position
    private float $dailyVolumeLimitUSDT = 50000.0; // Max $50k daily volume
    private float $maxDrawdownPercent = 0.20;  // 20% max drawdown before lockout
    private int   $maxDailyTrades    = 50;     // Max 50 trades per day

    private mysqli $db;
    private int    $userId;

    public function __construct(mysqli $dbConn, int $userId)
    {
        $this->db     = $dbConn;
        $this->userId = $userId;
    }

    // =========================================================
    //  1. VALIDATE TRADE (called before any buy/sell)
    // =========================================================
    public function validateTrade(string $type, string $coin, float $amount, float $price): array
    {
        $errors = [];
        $total  = $amount * $price;

        // --- Get current user balances ---
        $user = $this->getUserBalances();
        if (!$user) {
            return ['allowed' => false, 'errors' => ['User not found']];
        }

        $portfolioValue = $this->calculatePortfolioValue($user);

        // CHECK 1: Max trade size (% of portfolio)
        if ($portfolioValue > 0 && ($total / $portfolioValue) > $this->maxTradePercent) {
            $maxAllowed = $portfolioValue * $this->maxTradePercent;
            $errors[] = "Trade exceeds " . ($this->maxTradePercent * 100) . "% of portfolio. Max allowed: $" . number_format($maxAllowed, 2);
        }

        // CHECK 2: Position limit
        $type = strtoupper($type);
        if ($type === 'BUY' && $coin === 'BTC') {
            $newPosition = (float)$user['btc_balance'] + $amount;
            if ($newPosition > $this->maxPositionBTC) {
                $errors[] = "Position limit exceeded. Max BTC holding: {$this->maxPositionBTC} BTC. Current: {$user['btc_balance']} BTC";
            }
        }
        if ($type === 'BUY' && $coin === 'ETH') {
            $newPosition = (float)$user['eth_balance'] + $amount;
            if ($newPosition > $this->maxPositionETH) {
                $errors[] = "Position limit exceeded. Max ETH holding: {$this->maxPositionETH} ETH. Current: {$user['eth_balance']} ETH";
            }
        }

        // CHECK 3: Daily volume limit
        $dailyVolume = $this->getDailyVolume();
        if (($dailyVolume + $total) > $this->dailyVolumeLimitUSDT) {
            $remaining = $this->dailyVolumeLimitUSDT - $dailyVolume;
            $errors[] = "Daily volume limit reached. Limit: $" . number_format($this->dailyVolumeLimitUSDT, 2) . ". Remaining: $" . number_format(max(0, $remaining), 2);
        }

        // CHECK 4: Daily trade count
        $dailyCount = $this->getDailyTradeCount();
        if ($dailyCount >= $this->maxDailyTrades) {
            $errors[] = "Maximum daily trade count ({$this->maxDailyTrades}) reached.";
        }

        // CHECK 5: Drawdown protection
        $drawdown = $this->calculateDrawdown();
        if ($drawdown !== null && $drawdown >= $this->maxDrawdownPercent) {
            $errors[] = "Portfolio drawdown exceeds " . ($this->maxDrawdownPercent * 100) . "%. Trading paused for risk protection. Current drawdown: " . number_format($drawdown * 100, 1) . "%";
        }

        // CHECK 6: Sufficient balance
        if ($type === 'BUY' && (float)$user['balance'] < $total) {
            $errors[] = "Insufficient USDT balance. Have: $" . number_format($user['balance'], 2) . ", Need: $" . number_format($total, 2);
        }
        if ($type === 'SELL' && $coin === 'BTC' && (float)$user['btc_balance'] < $amount) {
            $errors[] = "Insufficient BTC balance. Have: {$user['btc_balance']} BTC, Need: {$amount} BTC";
        }
        if ($type === 'SELL' && $coin === 'ETH' && (float)$user['eth_balance'] < $amount) {
            $errors[] = "Insufficient ETH balance. Have: {$user['eth_balance']} ETH, Need: {$amount} ETH";
        }

        return [
            'allowed' => empty($errors),
            'errors'  => $errors
        ];
    }

    // =========================================================
    //  2. PORTFOLIO RISK METRICS
    // =========================================================
    public function getPortfolioMetrics(): array
    {
        $user = $this->getUserBalances();
        if (!$user) {
            return ['error' => 'User not found'];
        }

        $portfolioValue = $this->calculatePortfolioValue($user);
        $dailyVolume    = $this->getDailyVolume();
        $dailyCount     = $this->getDailyTradeCount();
        $drawdown       = $this->calculateDrawdown();
        $btcExposure    = $this->calculateBTCExposure($user);

        return [
            'portfolio_value'       => round($portfolioValue, 2),
            'usdt_balance'          => round((float)$user['balance'], 2),
            'btc_balance'           => round((float)$user['btc_balance'], 6),
            'btc_exposure_percent'  => round($btcExposure * 100, 1),
            'daily_volume_used'     => round($dailyVolume, 2),
            'daily_volume_limit'    => $this->dailyVolumeLimitUSDT,
            'daily_volume_percent'  => round(($dailyVolume / $this->dailyVolumeLimitUSDT) * 100, 1),
            'daily_trades'          => $dailyCount,
            'daily_trades_limit'    => $this->maxDailyTrades,
            'max_trade_percent'     => $this->maxTradePercent * 100,
            'max_position_btc'      => $this->maxPositionBTC,
            'drawdown_percent'      => $drawdown !== null ? round($drawdown * 100, 1) : 0,
            'max_drawdown_percent'  => $this->maxDrawdownPercent * 100,
            'risk_level'            => $this->calculateRiskLevel($btcExposure, $drawdown, $dailyVolume)
        ];
    }

    // =========================================================
    //  INTERNAL HELPERS
    // =========================================================

    private function getUserBalances(): ?array
    {
        $stmt = $this->db->prepare("SELECT balance, btc_balance, eth_balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    private function calculatePortfolioValue(array $user): float
    {
        $btcPrice = $this->fetchBTCPrice();
        $ethPrice = $this->fetchETHPrice();
        $usdtBal  = (float)$user['balance'];
        $btcBal   = (float)$user['btc_balance'];
        $ethBal   = (float)($user['eth_balance'] ?? 0);
        return $usdtBal + ($btcBal * $btcPrice) + ($ethBal * $ethPrice);
    }

    private function calculateBTCExposure(array $user): float
    {
        $btcPrice       = $this->fetchBTCPrice();
        $btcValueUSDT   = (float)$user['btc_balance'] * $btcPrice;
        $portfolioValue = $this->calculatePortfolioValue($user);
        return $portfolioValue > 0 ? $btcValueUSDT / $portfolioValue : 0;
    }

    private function getDailyVolume(): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(total), 0) as daily_vol 
             FROM transactions 
             WHERE user_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float)$row['daily_vol'];
    }

    private function getDailyTradeCount(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt 
             FROM transactions 
             WHERE user_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)$row['cnt'];
    }

    /**
     * Calculate portfolio drawdown from peak balance.
     * Tracks peak portfolio value over the user's history.
     */
    private function calculateDrawdown(): ?float
    {
        // Get initial deposit value (first balance after registration)
        $stmt = $this->db->prepare(
            "SELECT balance, btc_balance FROM users WHERE id = ?"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) return null;

        $currentValue = $this->calculatePortfolioValue($user);

        // Get the peak portfolio value from trade history
        // We approximate by summing: initial balance + all profits
        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(running_total), ?) as peak FROM (
                SELECT @running := @running + 
                    CASE WHEN UPPER(type) = 'SELL' THEN total ELSE -total END as running_total
                FROM transactions, (SELECT @running := ?) r
                WHERE user_id = ? AND status = 'completed'
                ORDER BY created_at ASC
            ) sub"
        );
        $initialBalance = 10000.0; // Default starting balance
        $stmt->bind_param("ddi", $initialBalance, $initialBalance, $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $peak = max((float)($row['peak'] ?? $initialBalance), $currentValue);

        if ($peak <= 0) return 0;

        $drawdown = ($peak - $currentValue) / $peak;
        return max(0, $drawdown);
    }

    private function calculateRiskLevel(float $exposure, ?float $drawdown, float $dailyVolume): string
    {
        $dd = $drawdown ?? 0;
        $volRatio = $dailyVolume / $this->dailyVolumeLimitUSDT;

        if ($dd >= 0.15 || $exposure >= 0.8 || $volRatio >= 0.9) {
            return 'HIGH';
        } elseif ($dd >= 0.08 || $exposure >= 0.5 || $volRatio >= 0.5) {
            return 'MEDIUM';
        }
        return 'LOW';
    }

    private function fetchBTCPrice(): float
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        require_once __DIR__ . '/api_helper.php';
        $price = fetchBinancePrice('BTCUSDT');
        $cached = $price ?? 0;
        return $cached;
    }

    private function fetchETHPrice(): float
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        require_once __DIR__ . '/api_helper.php';
        $price = fetchBinancePrice('ETHUSDT');
        $cached = $price ?? 0;
        return $cached;
    }
}
