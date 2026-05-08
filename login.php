<?php
$page_title = "Login";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';


startSession();


if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}


$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $otp = rand(100000, 999999); 
                
                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_user_email'] = $user['email'];
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_time'] = time();
                $_SESSION['otp_expiry'] = time() + 50;


                require_once 'includes/send_otp.php';
                if (!sendOtpEmail($user['email'], $otp)) {
                    $error = 'Failed to send OTP email. Please try again.';
                } else {
                    header('Location: verify_otp.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}


include 'includes/header.php';
?>


<div class="form-card">
    <h2 class="form-title">Login to Your Account</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn btn-block">Login</button>
    </form>
    
    <p class="text-center mt-3">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>


<?php include 'includes/footer.php'; ?>
