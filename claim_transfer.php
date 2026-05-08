<?php
$page_title = "Claim Transfers";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';


startSession();
requireLogin();


$db = getDbConnection();


$user = getUserInfo($_SESSION['user_id']);
$success = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_transfer'])) {
    // Validate CSRF token first
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $transferId = intval($_POST['transfer_id'] ?? 0);
        $privateKeyPem = trim($_POST['recipient_private_key'] ?? '');


        if (empty($transferId) || empty($privateKeyPem)) {
            $error = "Transfer ID and private key are required.";
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND claimed = FALSE");
                $stmt->execute([$transferId]);
                $transfer = $stmt->fetch(PDO::FETCH_ASSOC);


                if (!$transfer) {
                    $error = "Transfer not found or already claimed.";
                } else if (trim($transfer['recipient_public_key']) !== trim($user['public_key'])) {
                    $error = "This transfer is not addressed to your public key.";
                } else {
                    $encryptedData = base64_decode($transfer['encrypted_data']);
                    $signature = base64_decode($transfer['sender_signature']);


                    $recipientPrivateKey = openssl_pkey_get_private($privateKeyPem);
                    if (!$recipientPrivateKey) {
                        $error = "Invalid private key.";
                    } else {
                        $decrypted = '';
                        if (!openssl_private_decrypt($encryptedData, $decrypted, $recipientPrivateKey)) {
                            $error = "Failed to decrypt the data with the provided private key.";
                        } else {
                            $data = json_decode($decrypted, true);
                            $stmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
                            $stmt->execute([$data['from_user_id']]);
                            $senderUser = $stmt->fetch(PDO::FETCH_ASSOC);


                            if (!$senderUser) {
                                $error = "Sender not found.";
                            } else {
                                $senderPublicKey = openssl_pkey_get_public($senderUser['public_key']);
                                if (!$senderPublicKey || !openssl_verify($decrypted, $signature, $senderPublicKey, OPENSSL_ALGO_SHA256)) {
                                    $error = "Signature verification failed.";
                                } else {
                                    $cryptoField = strtolower($data['coin']) . '_balance';
                                    $amount = floatval($data['amount']);


                                    $stmt = $db->prepare("UPDATE users SET $cryptoField = $cryptoField + ? WHERE id = ?");
                                    $stmt->execute([$amount, $user['id']]);


                                    $stmt = $db->prepare("UPDATE transactions SET claimed = TRUE, user_id = ? WHERE id = ?");
                                    $stmt->execute([$user['id'], $transferId]);


                                    $success = "Successfully claimed $amount {$data['coin']}.";
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = "Error claiming transfer: " . $e->getMessage();
            }
        }
    }
}


$stmt = $db->prepare("SELECT * FROM transactions WHERE claimed = FALSE AND recipient_public_key = ?");
$stmt->execute([trim($user['public_key'])]);
$unclaimedTransfers = $stmt->fetchAll(PDO::FETCH_ASSOC);


include 'includes/header.php';
?>


<h2>Claim Your Transfers</h2>
<p>Use your private key to claim encrypted transfers sent to your public key.</p>


<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php elseif ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>


<div class="dashboard-grid">
    <div class="card">
        <h3 class="card-title">Unclaimed Transfers</h3>
        <?php if (count($unclaimedTransfers) === 0): ?>
            <p>No unclaimed transfers found for your account.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($unclaimedTransfers as $t): ?>
                    <li>
                        <strong>ID:</strong> <?= htmlspecialchars($t['id']) ?> |
                        <strong>Coin:</strong> <?= htmlspecialchars($t['coin']) ?> |
                        <strong>Amount:</strong> <?= htmlspecialchars($t['amount']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>


    <div class="card">
        <h3 class="card-title">Claim Transfer</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            
            <label for="transfer_id">Transfer ID</label><br>
            <input type="text" name="transfer_id" required><br><br>


            <label for="recipient_private_key">Your Private Key</label><br>
            <textarea name="recipient_private_key" rows="8" cols="60" required></textarea><br><br>


            <button type="submit" name="claim_transfer">Claim Transfer</button>
        </form>
    </div>
</div>


<?php include 'includes/footer.php'; ?>
