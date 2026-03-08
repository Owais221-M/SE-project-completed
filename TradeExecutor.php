<?php

require_once 'PositionManager.php';
require_once 'wallet_functions.php';

class TradeExecutor {

    private PositionManager $positionManager;

    public function __construct() {
        $this->positionManager = new PositionManager();
    }

    public function execute(
        string $signal,
        int $userId,
        float $price
    ): string {

        if ($signal === "HOLD") {
            return "HOLD";
        }

        if ($signal === "BUY" && $this->positionManager->canBuy()) {
            buyBTC($userId, $price);
            $this->positionManager->enterLong();
            return "BUY_EXECUTED";
        }

        if ($signal === "SELL" && $this->positionManager->canSell()) {
            sellBTC($userId, $price);
            $this->positionManager->exitLong();
            return "SELL_EXECUTED";
        }

        return "NO_ACTION";
    }
}
