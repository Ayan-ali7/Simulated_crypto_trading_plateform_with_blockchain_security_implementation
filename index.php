<?php
$page_title = "Portfolio Dashboard";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';
startSession();
requireLogin();
$user = getUserInfo($_SESSION['user_id']);
$db = getDbConnection();
$stmt = $db->prepare("SELECT public_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$publicKey = $stmt->fetchColumn();
$btcPrice = getCryptoPrice('BTCUSDT');
$ethPrice = getCryptoPrice('ETHUSDT');
$btcValue = $user['btc_balance'] * $btcPrice;
$ethValue = $user['eth_balance'] * $ethPrice;
$totalValue = $user['balance'] + $btcValue + $ethValue;
$btcAvgBuyPrice = calculateAverageBuyPrice($_SESSION['user_id'], 'BTC');
$ethAvgBuyPrice = calculateAverageBuyPrice($_SESSION['user_id'], 'ETH');
$btcPnL = calculatePnL($_SESSION['user_id'], 'BTC', $btcPrice);
$ethPnL = calculatePnL($_SESSION['user_id'], 'ETH', $ethPrice);
include 'includes/header.php';
?>

<div class="dashboard-header">
    <h2>Portfolio Dashboard</h2>
    <p class="dashboard-subtitle">Monitor your crypto assets in real-time</p>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="crypto-icon usdt-icon">₮</div>
        <h3 class="card-title">USDT Balance</h3>
        <div class="balance-value"><?php echo formatCrypto($user['balance'], 'USDT'); ?></div>
    </div>
    
    <div class="card">
        <div class="crypto-icon btc-icon">₿</div>
        <h3 class="card-title">Bitcoin</h3>
        <div class="balance-value"><?php echo formatCrypto($user['btc_balance'], 'BTC'); ?></div>
        <div class="balance-converted">
            <span>Value:</span>
            <strong><?php echo formatCrypto($btcValue, 'USDT'); ?></strong>
        </div>
        <div class="balance-converted">
            <span>Avg. Buy:</span>
            <strong><?php echo formatCrypto($btcAvgBuyPrice, 'USDT'); ?></strong>
        </div>
        <div class="balance-converted">
            <span>PnL:</span>
            <strong class="<?php echo $btcPnL >= 0 ? 'pnl-positive' : 'pnl-negative'; ?>">
                <?php echo formatCrypto($btcPnL, 'USDT'); ?>
            </strong>
        </div>
    </div>
    
    <div class="card">
        <div class="crypto-icon eth-icon">Ξ</div>
        <h3 class="card-title">Ethereum</h3>
        <div class="balance-value"><?php echo formatCrypto($user['eth_balance'], 'ETH'); ?></div>
        <div class="balance-converted">
            <span>Value:</span>
            <strong><?php echo formatCrypto($ethValue, 'USDT'); ?></strong>
        </div>
        <div class="balance-converted">
            <span>Avg. Buy:</span>
            <strong><?php echo formatCrypto($ethAvgBuyPrice, 'USDT'); ?></strong>
        </div>
        <div class="balance-converted">
            <span>PnL:</span>
            <strong class="<?php echo $ethPnL >= 0 ? 'pnl-positive' : 'pnl-negative'; ?>">
                <?php echo formatCrypto($ethPnL, 'USDT'); ?>
            </strong>
        </div>
    </div>
    
    <div class="card">
        <div class="crypto-icon total-icon">💎</div>
        <h3 class="card-title">Total Portfolio</h3>
        <div class="balance-value"><?php echo formatCrypto($totalValue, 'USDT'); ?></div>
    </div>
</div>

<div class="actions-container">
    <button class="btn-public-key" onclick="openPublicKeyModal()">
        🔑 View Public Key
    </button>
</div>

<div id="publicKeyModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closePublicKeyModal()">&times;</span>
        <h3>🔐 Your Public Key</h3>
        <div class="public-key-display" id="publicKeyText"><?php echo htmlspecialchars($publicKey); ?></div>
        <button class="btn-copy" onclick="copyPublicKey()">📋 Copy to Clipboard</button>
        <span class="copy-success" id="copySuccess">✓ Copied!</span>
    </div>
</div>

<script>
    function openPublicKeyModal() {
        document.getElementById('publicKeyModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function closePublicKeyModal() {
        document.getElementById('publicKeyModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function copyPublicKey() {
        const publicKeyText = document.getElementById('publicKeyText').textContent;
        navigator.clipboard.writeText(publicKeyText).then(function() {
            const successMsg = document.getElementById('copySuccess');
            successMsg.classList.add('show');
            setTimeout(function() {
                successMsg.classList.remove('show');
            }, 2000);
        });
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('publicKeyModal');
        if (event.target == modal) {
            closePublicKeyModal();
        }
    }
    
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePublicKeyModal();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>