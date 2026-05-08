<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
function simulate_block_mining(PDO $pdo, $maxTx = 5) {

    $stmt = $pdo->query("SELECT block_hash FROM blocks ORDER BY id DESC LIMIT 1");
    $lastBlockHash = $stmt->fetchColumn() ?: str_repeat("0", 64);

    $sql = "SELECT t.* FROM transactions t
            LEFT JOIN blockchain_transactions bt ON t.id = bt.transaction_id
            WHERE bt.transaction_id IS NULL
            ORDER BY t.timestamp ASC
            LIMIT ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$maxTx]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($transactions) < $maxTx) {
        return false;
    }

    $tx_hashes = [];
    foreach ($transactions as $tx) {
        $tx_data = implode('|', [
            $tx['id'], $tx['user_id'], $tx['type'], $tx['coin'],
            $tx['amount'], $tx['price'], $tx['total'], $tx['timestamp']
        ]);
        $tx_hashes[] = sha256($tx_data);
    }

    $block_content = $lastBlockHash . implode('', $tx_hashes);
    $newBlockHash = sha256($block_content);

    $stmt = $pdo->prepare("INSERT INTO blocks (block_hash, previous_hash) VALUES (?, ?)");
    $stmt->execute([$newBlockHash, $lastBlockHash]);
    $blockId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO blockchain_transactions (block_id, transaction_id, tx_hash) VALUES (?, ?, ?)");
    foreach ($transactions as $i => $tx) {
        $stmt->execute([$blockId, $tx['id'], $tx_hashes[$i]]);
    }

    return $blockId;
}
