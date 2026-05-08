<?php
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

header('Content-Type: application/json');


$coin = $_GET['coin'] ?? 'BTC';
if (!in_array($coin, ['BTC', 'ETH'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid coin']);
    exit;
}

$symbol = $coin . 'USDT';
$price = getCryptoPrice($symbol);

if ($price !== false) {
    echo json_encode([
        'success' => true,
        'coin' => $coin,
        'price' => $price
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch coin price'
    ]);
}
?>
