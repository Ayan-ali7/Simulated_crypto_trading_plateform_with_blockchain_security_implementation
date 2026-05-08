<?php
$page_title = "Blockscan";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

$pdo = getDbConnection();

$sql = "
    SELECT b.id as block_id, b.block_hash, b.previous_hash, b.created_at,
           bt.transaction_hash, t.id as transaction_id, t.user_id, t.type, t.coin, t.amount, t.price, t.total, t.timestamp as tx_timestamp
    FROM blocks b
    LEFT JOIN blockchain_transactions bt ON b.id = bt.block_id
    LEFT JOIN transactions t ON bt.transaction_id = t.id
    ORDER BY b.id DESC, t.timestamp ASC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$blocks = [];
foreach ($rows as $row) {
    $blockId = $row['block_id'];
    if (!isset($blocks[$blockId])) {
        $blocks[$blockId] = [
            'block_hash' => $row['block_hash'],
            'previous_hash' => $row['previous_hash'],
            'created_at' => $row['created_at'],
            'transactions' => []
        ];
    }
    if ($row['transaction_id']) {
        $blocks[$blockId]['transactions'][] = [
            'transaction_hash' => $row['transaction_hash'],
            'transaction_id' => $row['transaction_id'],
            'user_id' => $row['user_id'],
            'type' => $row['type'],
            'coin' => $row['coin'],
            'amount' => $row['amount'],
            'price' => $row['price'],
            'total' => $row['total'],
            'timestamp' => $row['tx_timestamp'],
        ];
    }
}

include 'includes/header.php'; 
?>

<div class="container">
    <h1 class="card-title" style="margin-bottom: 2rem;">Blockchain Explorer</h1>

    <?php if (empty($blocks)) : ?>
        <div class="alert alert-info">No blocks found.</div>
    <?php else: ?>
        <?php foreach ($blocks as $id => $block): ?>
            <div class="card">
                <div class="block-header">
                    <h2 class="card-title">Block #<?= htmlspecialchars($id) ?></h2>
                    <p><strong>Block Hash:</strong> <span class="hash"><?= htmlspecialchars($block['block_hash']) ?></span></p>
                    <p><strong>Previous Hash:</strong> <span class="hash"><?= htmlspecialchars($block['previous_hash']) ?></span></p>
                    <p><strong>Created At:</strong> <?= htmlspecialchars($block['created_at']) ?></p>
                </div>

                <div class="transactions">
                    <h3 style="margin-top: 1rem; margin-bottom: 0.5rem;">Transactions (<?= count($block['transactions']) ?>)</h3>
                    <?php if (empty($block['transactions'])): ?>
                        <div class="alert alert-info">No transactions in this block.</div>
                    <?php else: ?>
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>Tx ID</th>
                                    <th>Hash</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Coin</th>
                                    <th>Amount</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($block['transactions'] as $tx): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tx['transaction_id']) ?></td>
                                        <td><span class="hash"><?= htmlspecialchars($tx['transaction_hash']) ?></span></td>
                                        <td><?= htmlspecialchars($tx['user_id']) ?></td>
                                        <td class="<?= $tx['type'] === 'buy' ? 'buy' : 'sell' ?>"><?= htmlspecialchars($tx['type']) ?></td>
                                        <td><?= htmlspecialchars($tx['coin']) ?></td>
                                        <td><?= htmlspecialchars($tx['amount']) ?></td>
                                        <td><?= htmlspecialchars($tx['price']) ?></td>
                                        <td><?= htmlspecialchars($tx['total']) ?></td>
                                        <td><?= htmlspecialchars($tx['timestamp']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
