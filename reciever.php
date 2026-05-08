<?php
$page_title = "Received Transfers";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

function normalizePem($pem) {
    $lines = explode("\n", trim($pem));
    $base64 = '';
    foreach ($lines as $line) {
        if (strpos($line, 'BEGIN') === false && strpos($line, 'END') === false) {
            $base64 .= trim($line);
        }
    }
    return $base64;
}

function pemToDer($pem) {
    $lines = explode("\n", trim($pem));
    $base64 = '';
    foreach ($lines as $line) {
        if (strpos($line, 'BEGIN') === false && strpos($line, 'END') === false) {
            $base64 .= trim($line);
        }
    }
    return base64_decode($base64);
}

function getPublicKeyFingerprint($pem) {
    $pubKey = openssl_pkey_get_public($pem);
    if (!$pubKey) {
        return false;
    }
    $details = openssl_pkey_get_details($pubKey);
    if (!isset($details['key'])) {
        return false;
    }
    $der = pemToDer($details['key']);
    return hash('sha256', $der);
}

startSession();
requireLogin();

$db = getDbConnection();
$decryptedTransfers = [];
$error = null;

$currentUserId = $_SESSION['user_id'] ?? null;
if ($currentUserId) {
    $stmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && isset($user['public_key'])) {
        $currentUserPublicKey = $user['public_key'];

        $currentUserPrivateKey = null;
        if (isset($_SESSION['private_key'])) {
            $currentUserPrivateKey = openssl_pkey_get_private($_SESSION['private_key']);
        } else {
            $privateKeyPath = __DIR__ . "/keys/private_key_{$currentUserId}.pem";
            if (file_exists($privateKeyPath)) {
                $privateKeyPem = file_get_contents($privateKeyPath);
                $currentUserPrivateKey = openssl_pkey_get_private($privateKeyPem);
            }
        }

        if ($currentUserPrivateKey) {
            $stmt = $db->prepare("SELECT * FROM transactions WHERE claimed = TRUE AND recipient_public_key = ?");
            $stmt->execute([$currentUserPublicKey]);
            $claimedTransfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($claimedTransfers as $tx) {
                $encryptedData = base64_decode($tx['encrypted_data']);
                $signature = base64_decode($tx['sender_signature']);
                $decryptedJson = '';

                if (openssl_private_decrypt($encryptedData, $decryptedJson, $currentUserPrivateKey)) {
                    $transferInfo = json_decode($decryptedJson, true);

                    if ($transferInfo && isset($transferInfo['from_user_id'])) {
                        $senderStmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
                        $senderStmt->execute([$transferInfo['from_user_id']]);
                        $senderData = $senderStmt->fetch();

                        if ($senderData && !empty($senderData['public_key'])) {
                            $senderPublicKey = openssl_pkey_get_public($senderData['public_key']);
                            $isValid = openssl_verify($decryptedJson, $signature, $senderPublicKey, OPENSSL_ALGO_SHA256);

                            if ($isValid === 1) {
                                $decryptedTransfers[] = [
                                    'from_user_id' => $transferInfo['from_user_id'],
                                    'coin' => $transferInfo['coin'],
                                    'amount' => $transferInfo['amount'],
                                    'timestamp' => isset($transferInfo['timestamp']) 
                                        ? date('Y-m-d H:i:s', $transferInfo['timestamp']) 
                                        : 'Unknown'
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token first
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $recipientPrivateKeyPem = trim($_POST['private_key'] ?? '');

        if (empty($recipientPrivateKeyPem)) {
            $error = "You must enter your private key.";
        } else {
            $recipientPrivateKey = openssl_pkey_get_private($recipientPrivateKeyPem);

            if (!$recipientPrivateKey) {
                $error = "Invalid private key.";
            } else {
                $recipientKeyDetails = openssl_pkey_get_details($recipientPrivateKey);
                $recipientPublicKeyPem = $recipientKeyDetails['key'] ?? '';

                if (empty($recipientPublicKeyPem)) {
                    $error = "Unable to derive public key from provided private key.";
                } else {
                    // Process UNCLAIMED transfers
                    $stmt = $db->prepare("SELECT * FROM transactions WHERE type = 'transfer' AND claimed = FALSE");
                    $stmt->execute();
                    $allTransfers = $stmt->fetchAll();

                    $recipientFingerprint = getPublicKeyFingerprint($recipientPublicKeyPem);
                    $claimedCount = 0;

                    foreach ($allTransfers as $tx) {
                        $txFingerprint = getPublicKeyFingerprint($tx['recipient_public_key']);

                        if (!$recipientFingerprint || !$txFingerprint || $recipientFingerprint !== $txFingerprint) {
                            continue;
                        }

                        $encryptedData = base64_decode($tx['encrypted_data']);
                        $signature = base64_decode($tx['sender_signature']);
                        $decryptedJson = '';

                        if (openssl_private_decrypt($encryptedData, $decryptedJson, $recipientPrivateKey)) {
                            $transferInfo = json_decode($decryptedJson, true);

                            if (!$transferInfo || !isset($transferInfo['from_user_id'])) {
                                continue;
                            }

                            $senderStmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
                            $senderStmt->execute([$transferInfo['from_user_id']]);
                            $senderData = $senderStmt->fetch();

                            if (!$senderData || empty($senderData['public_key'])) {
                                continue;
                            }

                            $senderPublicKey = openssl_pkey_get_public($senderData['public_key']);
                            if (!$senderPublicKey) {
                                continue;
                            }

                            $isValid = openssl_verify($decryptedJson, $signature, $senderPublicKey, OPENSSL_ALGO_SHA256);

                            if ($isValid === 1) {
                                $coin = $transferInfo['coin'];
                                $amount = $transferInfo['amount'];
                                $cryptoField = strtolower($coin) . '_balance';

                                $stmt = $db->prepare("SELECT id, $cryptoField FROM users WHERE public_key = ?");
                                $stmt->execute([$recipientPublicKeyPem]);
                                $receiverUser = $stmt->fetch(PDO::FETCH_ASSOC);

                                if ($receiverUser) {
                                    $currentBalance = (float)$receiverUser[$cryptoField];
                                    $newBalance = $currentBalance + $amount;

                                    try {
                                        $db->beginTransaction();

                                        if (!updateUserBalance($receiverUser['id'], $cryptoField, $newBalance)) {
                                            throw new Exception("Failed to update receiver balance");
                                        }

                                        $updateTx = $db->prepare("UPDATE transactions SET claimed = TRUE WHERE id = ?");
                                        $updateTx->execute([$tx['id']]);

                                        $db->commit();

                                        $claimedCount++;

                                        $decryptedTransfers[] = [
                                            'from_user_id' => $transferInfo['from_user_id'],
                                            'coin' => $coin,
                                            'amount' => $amount,
                                            'timestamp' => isset($transferInfo['timestamp']) 
                                                ? date('Y-m-d H:i:s', $transferInfo['timestamp']) 
                                                : 'Unknown',
                                            'is_new' => true
                                        ];

                                    } catch (Exception $e) {
                                        $db->rollBack();
                                    }
                                }
                            }
                        }
                    }
                    $stmt = $db->prepare("SELECT * FROM transactions WHERE type = 'transfer' AND claimed = TRUE");
                    $stmt->execute();
                    $claimedTransfers = $stmt->fetchAll();

                    foreach ($claimedTransfers as $tx) {
                        $txFingerprint = getPublicKeyFingerprint($tx['recipient_public_key']);

                        if (!$recipientFingerprint || !$txFingerprint || $recipientFingerprint !== $txFingerprint) {
                            continue;
                        }

                        $encryptedData = base64_decode($tx['encrypted_data']);
                        $signature = base64_decode($tx['sender_signature']);
                        $decryptedJson = '';

                        if (openssl_private_decrypt($encryptedData, $decryptedJson, $recipientPrivateKey)) {
                            $transferInfo = json_decode($decryptedJson, true);

                            if ($transferInfo && isset($transferInfo['from_user_id'])) {
                                $senderStmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
                                $senderStmt->execute([$transferInfo['from_user_id']]);
                                $senderData = $senderStmt->fetch();

                                if ($senderData && !empty($senderData['public_key'])) {
                                    $senderPublicKey = openssl_pkey_get_public($senderData['public_key']);
                                    $isValid = openssl_verify($decryptedJson, $signature, $senderPublicKey, OPENSSL_ALGO_SHA256);

                                    if ($isValid === 1) {
                                        $decryptedTransfers[] = [
                                            'from_user_id' => $transferInfo['from_user_id'],
                                            'coin' => $transferInfo['coin'],
                                            'amount' => $transferInfo['amount'],
                                            'timestamp' => isset($transferInfo['timestamp']) 
                                                ? date('Y-m-d H:i:s', $transferInfo['timestamp']) 
                                                : 'Unknown'
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    if ($claimedCount > 0) {
                        $error = null;
                    } elseif ($claimedCount === 0 && count($allTransfers) > 0) {
                        $error = "No new transfers found for this private key.";
                    }
                }
            }
        }
    }
}

?>

<?php include 'includes/header.php'; ?>

<h2>Received Transfers</h2>
<p>Paste your private key to decrypt and verify any new transfers made to you.</p>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <textarea name="private_key" rows="10" cols="70" placeholder="-----BEGIN PRIVATE KEY-----..."></textarea><br>
    <button type="submit">Check Transfers</button>
</form>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php
    $newTransfers = [];
    $pastTransfers = [];

    foreach ($decryptedTransfers as $tx) {
        if (isset($tx['is_new']) && $tx['is_new']) {
            $newTransfers[] = $tx;
        } else {
            $pastTransfers[] = $tx;
        }
    }
?>

<?php if (count($newTransfers) > 0): ?>
    <h3>🆕 New Transfers (Just Claimed)</h3>
    <table class="transactions-table">
        <thead>
            <tr>
                <th>From (User ID)</th>
                <th>Coin</th>
                <th>Amount</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($newTransfers as $tx): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tx['from_user_id']); ?></td>
                    <td><?php echo htmlspecialchars($tx['coin']); ?></td>
                    <td><?php echo htmlspecialchars($tx['amount']); ?></td>
                    <td><?php echo htmlspecialchars($tx['timestamp']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (count($pastTransfers) > 0): ?>
    <h3>📦 Past Transfers (Previously Claimed)</h3>
    <table class="transactions-table">
        <thead>
            <tr>
                <th>From (User ID)</th>
                <th>Coin</th>
                <th>Amount</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pastTransfers as $tx): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tx['from_user_id']); ?></td>
                    <td><?php echo htmlspecialchars($tx['coin']); ?></td>
                    <td><?php echo htmlspecialchars($tx['amount']); ?></td>
                    <td><?php echo htmlspecialchars($tx['timestamp']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
