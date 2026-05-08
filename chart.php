<?php
$page_title = "Trading Chart";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

startSession();
requireLogin();

include 'includes/header.php';
?>

<h2>Trading Chart</h2>
<p>Explore charts for various cryptocurrencies and assets.</p>

<div class="tradingview-widget-container">
  <div id="tradingview_full_chart_widget"></div>
  <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
  <script type="text/javascript">
  new TradingView.widget(
  {
  "width": "100%",
  "height": 600, 
  "symbol": "BINANCE:BTCUSDT", 
  "interval": "D",
  "timezone": "Etc/UTC",
  "theme": "dark",
  "style": "1",
  "locale": "en",
  "enable_publishing": false,
  "allow_symbol_change": true, 
  "container_id": "tradingview_full_chart_widget"
}
  );
  </script>
</div>

<?php include 'includes/footer.php'; ?>
