# Crypto Transaction & Blockchain Simulation System

A comprehensive web-based platform for managing cryptocurrency transactions with a simulated blockchain backend. This project demonstrates core blockchain concepts, real-time market data integration, and secure user authentication.

## 🚀 Features

### 1. User Authentication & Security
- **Registration & Login**: Secure user management system.
- **OTP Verification**: Two-factor authentication using PHPMailer (Gmail SMTP).
- **Public/Private Keys**: RSA-based key management for secure transactions and identities.

### 2. Portfolio Dashboard
- **Real-time Asset Tracking**: Monitor balances for USDT, Bitcoin (BTC), and Ethereum (ETH).
- **Live Market Data**: Integrated with the Binance API for up-to-the-minute price updates.
- **PnL Analytics**: Automatically calculates Profit and Loss (PnL) and Average Buy Price for your holdings.

### 3. Cryptocurrency Exchange
- **Buy/Sell Logic**: Execute trades at current market prices.
- **Transaction History**: Comprehensive log of all user activities.

### 4. Blockchain Simulation
- **Block Mining**: Transactions are periodically grouped and mined into blocks using SHA-256 hashing.
- **Immutable Ledger**: Each block contains the hash of the previous block, ensuring data integrity.
- **Blockchain Explorer**: A dedicated interface to inspect blocks, their hashes, and the transactions contained within.

### 5. Peer-to-Peer Transfers
- **Claim & Transfer**: Send and receive crypto assets between users using their public keys.

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3 (Modern UI with glassmorphism effects), JavaScript.
- **Backend**: PHP 8.x.
- **Database**: PostgreSQL.
- **APIs**: Binance Market Data API.
- **Libraries**: PHPMailer.

## ⚙️ Setup Instructions

### Prerequisites
- **Apache Server** (e.g., via XAMPP).
- **PostgreSQL** installed and running.
- **Composer** (optional, for PHPMailer management).

### 1. Database Configuration
1. Create a database named `crypto_transaction` in PostgreSQL.
2. Configure your connection details in `config/database.php`:
   ```php
   $host = 'localhost';
   $port = '5432';
   $dbname = 'crypto_transaction';
   $db_user = 'your_username';
   $db_pass = 'your_password';
   ```

### 2. PHPMailer Setup (OTP)
To enable OTP emails, update the SMTP settings in `includes/send_otp.php`:
- Update `Username` with your Gmail address.
- Update `Password` with a Gmail **App Password**.

### 3. Running the Project
1. Move the project folder to your server directory (e.g., `C:\xampp\htdocs\`).
2. Start Apache and ensure PostgreSQL is accessible.
3. Access the project via `http://localhost/Web_programming_project_2025_test/`.

## 📂 Project Structure
- `assets/`: UI styling (CSS) and client-side logic (JS).
- `config/`: System configurations (DB, API, Sessions).
- `includes/`: Reusable components (Header, Footer, Functions) and PHPMailer.
- `keys/`: Storage for generated RSA PEM files.
- `blockchain_explorer.php`: Interface for viewing the ledger.
- `buy_sell.php`: Trading interface.

## 📝 License
This project was developed as part of a Web Programming course (2025). 
