<?php
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';

startSession();

logout();

header('Location: login.php');
exit;
?>
