# Crypto Transaction Web

A web-based platform for cryptocurrency trading, order management, and blockchain integration. This project includes a PHP backend, smart contract integration (Solidity), and real-time features using MQTT.

## Features
- User registration, login, and OTP verification
- Buy/sell cryptocurrency orders
- Order book and trade matching
- Dashboard with risk metrics and indicators
- Alerts and notifications
- Blockchain integration (Ethereum smart contracts)
- Real-time trade updates via MQTT

## Project Structure
```
├── api_helper.php, api.php, ...           # Core PHP backend files
├── buy_sell_form.html, buy_sell.php, ...  # Trading UI and logic
├── dashboard.php, dashboard.js, ...       # User dashboard
├── blockchain-transactions/               # Smart contract & blockchain integration
│   ├── contracts/TradeContract.sol        # Solidity smart contract
│   ├── migrations/                        # Truffle migrations
│   ├── build/                             # Compiled contracts
│   ├── test/                              # Blockchain tests
│   ├── vendor/                            # PHP blockchain libraries
│   └── truffle-config.js                  # Truffle config
├── mqtt/                                  # MQTT real-time features
│   ├── mqtt_subscriber.py                 # Python MQTT subscriber
│   ├── phpMQTT.php, publish.php, ...      # PHP MQTT scripts
├── vendor/                                # PHP dependencies (Composer)
├── *.css, *.js                            # Frontend assets
```

## Prerequisites
- PHP 7.4+
- Composer (for PHP dependencies)
- Node.js & npm (for Truffle)
- Ganache (for local Ethereum blockchain)
- MetaMask (for browser wallet integration)
- Python 3.x (for MQTT subscriber)
- MQTT broker (e.g., Mosquitto)

## Setup Instructions

### 1. Clone the Repository
```
git clone <repo-url>
cd crypto_transaction_web
```

### 2. Install PHP Dependencies
```
composer install
```

### 3. Blockchain Setup
- Install [Node.js](https://nodejs.org/)
- Install Truffle globally:
  ```
npm install -g truffle
  ```
- Install and run [Ganache](https://trufflesuite.com/ganache/) for a local Ethereum blockchain.
- Deploy the smart contract:
  ```
cd blockchain-transactions
truffle migrate --network development
  ```
- Update contract addresses in your PHP config if needed.

### 4. MQTT Setup
- Install an MQTT broker (e.g., Mosquitto)
- Start the broker:
  ```
mosquitto
  ```
- Configure MQTT connection details in the PHP and Python scripts in the `mqtt/` folder.

### 5. Python MQTT Subscriber
- Install Python dependencies (if any):
  ```
pip install paho-mqtt
  ```
- Run the subscriber:
  ```
python mqtt/mqtt_subscriber.py
  ```

### 6. Configure PHP
- Copy `config.php.example` to `config.php` (if exists) and set your DB, blockchain, and MQTT credentials.
- Ensure XAMPP/Apache is running and the project is in the `htdocs` directory.

### 7. Access the App
- Open your browser and go to: `http://localhost/crypto_transaction_web/`

## Usage
- Register a new user and verify via OTP.
- Log in and access the dashboard.
- Place buy/sell orders, view order book, and manage alerts.
- Trades are matched and executed, with blockchain transactions recorded.
- Real-time updates are pushed via MQTT.

## Troubleshooting
- **Composer errors:** Run `composer install` in the root and `blockchain-transactions/` folders.
- **Truffle/Ganache issues:** Ensure Ganache is running and Truffle is installed.
- **MQTT issues:** Check broker is running and credentials are correct.
- **Database errors:** Verify your MySQL credentials in `config.php`.

## Contributing
1. Fork the repo
2. Create a new branch
3. Commit your changes
4. Open a pull request

## License
Specify your license here (e.g., MIT, GPL-3.0, etc.)

---
**For more details, see the code comments and individual module documentation.**
