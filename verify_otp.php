<?php
$page_title = "Verify OTP";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

startSession();

$error = '';

if (!isset($_SESSION['pending_user_id'], $_SESSION['otp'], $_SESSION['otp_expiry'])) {
    header('Location: login.php');
    exit;
}

if (time() > $_SESSION['otp_expiry']) {
    $error = 'Your OTP has expired. Please log in again.';

    unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email'], $_SESSION['otp'], $_SESSION['otp_time'], $_SESSION['otp_expiry']);

    include 'includes/header.php';
    echo "<div class='form-card'><div class='alert alert-danger'>{$error}</div><a class='btn btn-block' href='login.php'>Return to Login</a></div>";
    include 'includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = sanitizeInput($_POST['otp'] ?? '');

    if ($enteredOtp == $_SESSION['otp']) {
        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['email'] = $_SESSION['pending_user_email'];

        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email'], $_SESSION['otp'], $_SESSION['otp_time'], $_SESSION['otp_expiry']);

        $db = getDbConnection();
        $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$_SESSION['user_id']]);

        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid OTP. Please try again.';
    }
}

include 'includes/header.php';
?>

<div class="form-card">
    <h2 class="form-title">Enter OTP</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" action="verify_otp.php">
        <div class="form-group">
            <label for="otp">One-Time Password (OTP)</label>
            <input type="text" id="otp" name="otp" required pattern="\d{6}" maxlength="6" placeholder="Enter 6-digit OTP">
        </div>
        <button type="submit" class="btn btn-block">Verify</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
