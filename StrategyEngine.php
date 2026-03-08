<?php
// StrategyEngine class - included by StrategyController.php
// Session is started by the caller
// Strategy enabled check is handled by StrategyController.php

/* STRATEGY ENGINE */
class StrategyEngine
{
    private int $shortPeriod = 50;
    private int $longPeriod  = 200;

    public function __construct()
    {
        // Initialize price window
        if (!isset($_SESSION['price_window']) || !is_array($_SESSION['price_window'])) {
            $_SESSION['price_window'] = [];
        }

        // Initialize previous SMA values
        if (!isset($_SESSION['prev_sma'])) {
            $_SESSION['prev_sma'] = [
                'short' => null,
                'long'  => null
            ];
        }
    }

    /**
      
     * ADD NEW PRICE (CALL THIS EVERY TICK)
      
     */
    public function addPrice(float $price): void
    {
        $_SESSION['price_window'][] = $price;

        // Keep only last 200 prices
        if (count($_SESSION['price_window']) > $this->longPeriod) {
            array_shift($_SESSION['price_window']);
        }
    }

    /**
      
     * SMA CALCULATION
      
     */
    private function calculateSMA(int $period): ?float
    {
        $prices = $_SESSION['price_window'];

        if (count($prices) < $period) {
            return null;
        }

        return array_sum(array_slice($prices, -$period)) / $period;
    }

    /**
      
     * SIGNAL GENERATION
      
     */
    public function generateSignal(): array
    {
        $smaShort = $this->calculateSMA($this->shortPeriod);
        $smaLong  = $this->calculateSMA($this->longPeriod);

        $signal = "HOLD";

        $prevShort = $_SESSION['prev_sma']['short'];
        $prevLong  = $_SESSION['prev_sma']['long'];

        // Crossover detection
        if ($prevShort !== null && $prevLong !== null && $smaShort !== null && $smaLong !== null) {

            // Golden Cross
            if ($prevShort <= $prevLong && $smaShort > $smaLong) {
                $signal = "BUY";
            }
            // Death Cross
            elseif ($prevShort >= $prevLong && $smaShort < $smaLong) {
                $signal = "SELL";
            }
        }

        // Store current SMAs for next tick
        $_SESSION['prev_sma'] = [
            'short' => $smaShort,
            'long'  => $smaLong
        ];

        return [
            'status'        => 'OK',
            'signal'        => $signal,
            'sma50'         => $smaShort,   // null until 50 prices
            'sma200'        => $smaLong,    // null until 200 prices
            'prices_count' => count($_SESSION['price_window']) // DEBUG
        ];
    }
}
// Class is instantiated and used by StrategyController.php
// Do NOT run standalone code here
