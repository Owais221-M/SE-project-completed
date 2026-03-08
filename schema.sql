-- =============================================================
--  Crypto Transaction Web – Database Schema
--  Database: crypto_transaction
--  Engine:   InnoDB (MySQL / MariaDB)
-- =============================================================

CREATE DATABASE IF NOT EXISTS crypto_transaction
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE crypto_transaction;

-- -----------------------------------------------------------
--  TABLE: users
--  Stores user accounts, credentials, and wallet balances.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash via password_hash()
    eth_address   VARCHAR(42)  DEFAULT NULL,      -- Ganache Ethereum address
    private_key   VARCHAR(128) DEFAULT NULL,      -- Ganache private key (local dev only)
    balance       DECIMAL(18,2) NOT NULL DEFAULT 10000.00,   -- USDT balance
    btc_balance   DECIMAL(18,8) NOT NULL DEFAULT 0.00000000, -- BTC balance
    eth_balance   DECIMAL(18,6) NOT NULL DEFAULT 0.000000,   -- ETH balance (Ganache)
    otp           VARCHAR(10)  DEFAULT NULL,      -- Email OTP for verification
    otp_expiry    DATETIME     DEFAULT NULL,      -- OTP expiration timestamp
    is_verified   TINYINT(1)   NOT NULL DEFAULT 0,-- Email verified flag
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: transactions
--  Logs every trade (market buy/sell, limit orders, etc.)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    type        VARCHAR(10)   NOT NULL,           -- 'BUY' or 'SELL'
    coin        VARCHAR(10)   NOT NULL,           -- 'BTC' or 'ETH'
    amount      DECIMAL(18,8) NOT NULL,           -- Quantity of coin
    price       DECIMAL(18,2) NOT NULL,           -- Price per unit in USDT
    total       DECIMAL(18,2) DEFAULT NULL,       -- amount * price (NULL for legacy MQTT inserts)
    order_type  VARCHAR(10)   DEFAULT 'market',   -- 'market' or 'limit'
    status      VARCHAR(20)   DEFAULT 'completed',-- 'pending', 'completed', 'cancelled'
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_txn_user     (user_id),
    INDEX idx_txn_status   (status),
    INDEX idx_txn_date     (created_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: price_alerts
--  User-configured alerts that trigger email notifications.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS price_alerts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    coin          VARCHAR(10)   NOT NULL,          -- 'BTC' or 'ETH'
    target_price  DECIMAL(18,2) NOT NULL,          -- Target price in USDT
    notified      TINYINT(1)    NOT NULL DEFAULT 0,-- 0 = pending, 1 = triggered
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_alert_user (user_id),
    INDEX idx_alert_notified (notified)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: price_history
--  Recorded BTC prices for historical analysis & charting.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS price_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    asset       VARCHAR(10)   NOT NULL DEFAULT 'BTC',
    price       DECIMAL(18,2) NOT NULL,
    recorded_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ph_asset_date (asset, recorded_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: bot_control
--  Single-row table controlling the automated trading bot.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS bot_control (
    id        INT NOT NULL DEFAULT 1 PRIMARY KEY,
    is_active TINYINT(1) NOT NULL DEFAULT 0       -- 0 = off, 1 = on
) ENGINE=InnoDB;

-- Insert default row if empty
INSERT IGNORE INTO bot_control (id, is_active) VALUES (1, 0);

-- =============================================================
--  NOTES
-- =============================================================
-- • Default USDT balance for new users: 10,000 USDT (simulated)
-- • ETH balance updated via Ganache RPC (eth_getBalance)
-- • OTP columns (otp, otp_expiry) used for email verification flow
-- • bot_control has exactly 1 row (id=1) toggled by bot_control.php
-- • Risk management thresholds are application-level (RiskManager.php)
--   and do not require additional DB tables
