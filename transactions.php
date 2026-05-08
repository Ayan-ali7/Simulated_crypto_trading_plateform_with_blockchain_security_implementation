<?php
$page_title = "Transaction History";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

$db = getDbConnection();
$stmt = $db->prepare("
    SELECT id, type, coin, amount, price, total, timestamp 
    FROM transactions 
    WHERE user_id = ? 
    ORDER BY timestamp DESC
");

$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

include 'includes/header.php';
?>

<h2>Your Transaction History</h2>
<p>View all your buy and sell transactions.</p>

<?php if (count($transactions) > 0): ?>
    <table class="transactions-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Type</th>
                <th>Coin</th>
                <th>Amount</th>
                <th>Price (USDT)</th>
                <th>Total (USDT)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($tx['timestamp'])); ?></td>
                    <td class="<?php echo $tx['type']; ?>"><?php echo ucfirst($tx['type']); ?></td>
                    <td><?php echo $tx['coin']; ?></td>
                    <td><?php echo formatCrypto($tx['amount'], $tx['coin']); ?></td>
                    <td><?php echo formatCrypto($tx['price'], 'USDT'); ?></td>
                    <td><?php echo formatCrypto($tx['total'], 'USDT'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="alert alert-info">You don't have any transactions yet.</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
