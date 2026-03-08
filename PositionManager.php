<?php

class PositionManager {

    public function __construct() {
        if (!isset($_SESSION['position'])) {
            $_SESSION['position'] = 'NONE'; // NONE | LONG
        }
    }

    public function canBuy(): bool {
        return $_SESSION['position'] === 'NONE';
    }

    public function canSell(): bool {
        return $_SESSION['position'] === 'LONG';
    }

    public function enterLong(): void {
        $_SESSION['position'] = 'LONG';
    }

    public function exitLong(): void {
        $_SESSION['position'] = 'NONE';
    }

    public function getPosition(): string {
        return $_SESSION['position'];
    }
}
