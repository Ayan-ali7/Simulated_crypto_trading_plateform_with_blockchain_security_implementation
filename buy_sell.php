<?php
$page_title = "Buy/Sell Crypto";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'blockchain.php';


startSession();
requireLogin();


$user = getUserInfo($_SESSION['user_id']);


$btcPrice = getCryptoPrice('BTCUSDT');
$ethPrice = getCryptoPrice('ETHUSDT');


$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $coin = sanitizeInput($_POST['coin'] ?? '');
        $type = sanitizeInput($_POST['type'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $total = floatval($_POST['total'] ?? 0);
        
        if (!in_array($coin, ['BTC', 'ETH'])) {
            $error = 'Invalid cryptocurrency selected.';
        } elseif (!in_array($type, ['buy', 'sell', 'transfer'])) {
            $error = 'Invalid transaction type.';
        } elseif ($amount <= 0) {
            $error = 'Amount must be greater than zero.';
        } elseif ($price <= 0) {
            $error = 'Price must be greater than zero.';
        } else {
            $db = getDbConnection();
            
            $currentPrice = $coin === 'BTC' ? $btcPrice : $ethPrice;
            $total = $amount * $currentPrice;
            
            if ($type === 'buy') {


                if ($user['balance'] < $total) {
                    $error = 'Insufficient USDT balance for this purchase.';
                } else {
                    $newBalance = $user['balance'] - $total;
                    $cryptoField = strtolower($coin) . '_balance';
                    $newCryptoBalance = $user[$cryptoField] + $amount;
                    
                    $db->beginTransaction();
                    
                    try {
                        updateUserBalance($_SESSION['user_id'], 'balance', $newBalance);
                        
                        updateUserBalance($_SESSION['user_id'], $cryptoField, $newCryptoBalance);
                        
                        recordTransaction($_SESSION['user_id'], 'buy', $coin, $amount, $currentPrice, $total);
                        
                        $db->commit();
                        
                        $success = "Successfully purchased $amount $coin at $currentPrice USDT per coin.";
                        
                        $user = getUserInfo($_SESSION['user_id']);


                        $stmt = $db->query("SELECT COUNT(*) FROM transactions t
                                            LEFT JOIN blockchain_transactions bt ON t.id = bt.transaction_id
                                            WHERE bt.transaction_id IS NULL");
                        $pendingCount = $stmt->fetchColumn();


                        if ($pendingCount >= 5) {
                            createBlockIfNeeded($db);
                        }


                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Transaction failed: ' . $e->getMessage();
                    }
                }
            }
            else if ($type === 'sell') {
                $cryptoField = strtolower($coin) . '_balance';
                
                if ($user[$cryptoField] < $amount) {
                    $error = "Insufficient $coin balance for this sale.";
                } else {
                    $newBalance = $user['balance'] + $total;
                    $newCryptoBalance = $user[$cryptoField] - $amount;
                    
                    $db->beginTransaction();
                    
                    try {
                        updateUserBalance($_SESSION['user_id'], 'balance', $newBalance);
                        
                        updateUserBalance($_SESSION['user_id'], $cryptoField, $newCryptoBalance);
                        
                        recordTransaction($_SESSION['user_id'], 'sell', $coin, $amount, $currentPrice, $total);
                        
                        $db->commit();
                        
                        $success = "Successfully sold $amount $coin at $currentPrice USDT per coin.";
                        


                        $user = getUserInfo($_SESSION['user_id']);


                        $stmt = $db->query("SELECT COUNT(*) FROM transactions t
                                            LEFT JOIN blockchain_transactions bt ON t.id = bt.transaction_id
                                            WHERE bt.transaction_id IS NULL");
                        $pendingCount = $stmt->fetchColumn();


                        if ($pendingCount >= 5) {
                            createBlockIfNeeded($db);
                        }


                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Transaction failed: ' . $e->getMessage();
                    }
                }
            }
           else if ($type === 'transfer') {
    $recipientPublicKeyPem = trim($_POST['recipient_public_key'] ?? '');
    $senderPrivateKeyPem = trim($_POST['sender_private_key'] ?? '');

    if (empty($recipientPublicKeyPem) || empty($senderPrivateKeyPem)) {
        $error = 'Both recipient public key and your private key are required.';
    } else {
        $cryptoField = strtolower($coin) . '_balance';

        if ($user[$cryptoField] < $amount) {
            $error = "Insufficient $coin balance for this transfer.";
        } else {
            $recipientPublicKey = openssl_pkey_get_public($recipientPublicKeyPem);
            $senderPrivateKey = openssl_pkey_get_private($senderPrivateKeyPem);

            if (!$recipientPublicKey) {
                $error = 'Invalid recipient public key.';
            } else if (!$senderPrivateKey) {
                $error = 'Invalid sender private key.';
            } else {
                // Fetch sender's public key from database (FIX FOR LINE 162 ERROR)
                $stmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $senderData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$senderData || empty($senderData['public_key'])) {
                    $error = 'Unable to retrieve your public key from database.';
                } else {
                    // VALIDATE: Check if sender's private key matches their registered public key
                    $keyDetails = openssl_pkey_get_details($senderPrivateKey);
                    $derivedPublicKey = $keyDetails['key'] ?? '';
                    
                    // Normalize both keys for comparison
                    function normalizeKey($pem) {
                        $lines = explode("\n", trim($pem));
                        $base64 = '';
                        foreach ($lines as $line) {
                            if (strpos($line, 'BEGIN') === false && strpos($line, 'END') === false) {
                                $base64 .= trim($line);
                            }
                        }
                        return $base64;
                    }
                    
                    $dbKeyNormalized = normalizeKey($senderData['public_key']);
                    $derivedKeyNormalized = normalizeKey($derivedPublicKey);
                    
                    if ($dbKeyNormalized !== $derivedKeyNormalized) {
                        $error = 'The private key you provided does not match your registered public key. Please use the private key from keys/private_key_' . $_SESSION['user_id'] . '.pem';
                    } else {
                        // Private key is valid - proceed with transfer
                        $newSenderBalance = $user[$cryptoField] - $amount;

                        $transferData = json_encode([
                            'from_user_id' => $_SESSION['user_id'],
                            'coin' => $coin,
                            'amount' => $amount,
                            'timestamp' => time()
                        ]);

                        $encryptedData = '';
                        if (!openssl_public_encrypt($transferData, $encryptedData, $recipientPublicKey)) {
                            $error = 'Encryption failed.';
                        } else {
                            $signature = '';
                            if (!openssl_sign($transferData, $signature, $senderPrivateKey, OPENSSL_ALGO_SHA256)) {
                                $error = 'Failed to sign the transaction.';
                            } else {
                                try {
                                    $db->beginTransaction();

                                    updateUserBalance($_SESSION['user_id'], $cryptoField, $newSenderBalance);

                                    recordTransaction(
                                        $_SESSION['user_id'],
                                        'transfer',
                                        $coin,
                                        $amount,
                                        $currentPrice,  
                                        $total,  
                                        $recipientPublicKeyPem,
                                        base64_encode($signature),
                                        base64_encode($encryptedData)
                                    );

                                    $db->commit();
                                    $success = "Successfully transferred $amount $coin securely.";

                                    $user = getUserInfo($_SESSION['user_id']);
                                } catch (Exception $e) {
                                    $db->rollBack();
                                    $error = 'Transaction failed: ' . $e->getMessage();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}



        }
    }
}


include 'includes/header.php';
?>


<h2>Buy/Sell Cryptocurrency</h2>
<p>Current Balances: 
   USDT: <?php echo formatCrypto($user['balance'], 'USDT'); ?> | 
   BTC: <?php echo formatCrypto($user['btc_balance'], 'BTC'); ?> | 
   ETH: <?php echo formatCrypto($user['eth_balance'], 'ETH'); ?>
</p>


<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>


<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>


<div class="form-card">
    <h3 class="form-title">Trade Cryptocurrency</h3>
    
    <form method="post" action="buy_sell.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        
        <div class="form-group">
            <label for="type">Transaction Type</label>
            <select id="type" name="type" required>
                <option value="buy">Buy</option>
                <option value="sell">Sell</option>
                <option value="transfer">Transfer</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="coin">Cryptocurrency</label>
            <select id="coin" name="coin" required>
                <option value="BTC">Bitcoin (BTC)</option>
                <option value="ETH">Ethereum (ETH)</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="price">Current Price (USDT)</label>
            <input type="number" id="price" name="price" value="<?php echo $btcPrice; ?>" readonly>
        </div>
        
        <div class="form-group">
            <label for="amount">Amount</label>
            <input type="number" id="amount" name="amount" step="0.00000001" required>
            <small class="form-text">Enter the amount of cryptocurrency to buy/sell</small>
        </div>


        <div class="form-group" id="recipient-group" style="display: none;">
            <label for="recipient_public_key">Recipient Public Key (PEM format)</label>
            <textarea id="recipient_public_key" name="recipient_public_key" rows="6" placeholder="Paste recipient's public key here..."></textarea>
        </div>


        <div class="form-group" id="private-key-group" style="display: none;">
            <label for="sender_private_key">Your Private Key (PEM format)</label>
            <textarea id="sender_private_key" name="sender_private_key" rows="6" placeholder="Paste your private key here..."></textarea>
        </div>


        <script>
            const typeSelect = document.getElementById("type");
            typeSelect.addEventListener("change", function () {
                const isTransfer = this.value === "transfer";
                document.getElementById("recipient-group").style.display = isTransfer ? "block" : "none";
                document.getElementById("private-key-group").style.display = isTransfer ? "block" : "none";
            });
        </script>
        
        <div class="form-group">
            <label for="total">Total Cost (USDT)</label>
            <input type="number" id="total" name="total" readonly>
        </div>
        
        <button type="submit" class="btn btn-block">Execute Trade</button>
    </form>
</div>


<?php include 'includes/footer.php'; ?>
