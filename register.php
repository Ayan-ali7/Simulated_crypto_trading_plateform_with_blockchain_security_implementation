<?php
$page_title = "Register";
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

startSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

$opensslConfigPath = "C:/xampp/php/extras/ssl/openssl.cnf";
if (!file_exists($opensslConfigPath)) {
    die("⚠ OpenSSL config file not found at: $opensslConfigPath");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^()_+=\-\[\]{}|;:,.<>\/\\\\])[A-Za-z\d@$!%*?&#^()_+=\-\[\]{}|;:,.<>\/\\\\]{12,}$/', $password)) {
            $error = 'Password must include uppercase, lowercase, number, and special character.';
        } else {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Email address is already registered.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $keyConfig = [
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                    "config" => $opensslConfigPath
                ];
                $privateKeyRes = openssl_pkey_new($keyConfig);

                if (!$privateKeyRes) {
                    error_log("OpenSSL key generation failed: " . openssl_error_string());
                    $error = "Key generation failed. Please try again.";
                } elseif (!openssl_pkey_export($privateKeyRes, $privateKey, null, $keyConfig)) {
                    error_log("Failed to export private key: " . openssl_error_string());
                    $error = "Key generation failed. Please try again.";
                } else {
                    $keyDetails = openssl_pkey_get_details($privateKeyRes);
                    $publicKey = $keyDetails['key'] ?? null;

                    if (!$publicKey) {
                        error_log("Failed to extract public key.");
                        $error = "Key generation failed. Please try again.";
                    }
                }

                if (empty($error)) {
                    $insertStmt = $db->prepare("INSERT INTO users (email, password_hash, balance, public_key) VALUES (?, ?, 1000.00, ?)");

                if ($insertStmt->execute([$email, $password_hash, $publicKey])) {
                    $userId = $db->lastInsertId();
                    
                    regenerateSessionId();

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['email'] = $email;

                    $keyDir = __DIR__ . '/keys';
                    if (!is_dir($keyDir)) {
                        mkdir($keyDir, 0700, true);
                    }
                    
                    $filename = "$keyDir/private_key_{$userId}.pem";
                    
                    if (file_put_contents($filename, $privateKey)) {
                        $_SESSION['private_key'] = $privateKey;
                        $success = "Registration successful!";
                    } else {
                        $error = " Registration successful, but failed to save the private key file.";
                    }
                }


                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="form-card">
    <h2 class="form-title">Create an Account</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (empty($success)): ?>
    <form method="post" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <small class="form-text">At least 12 characters with uppercase, lowercase, number, and special character</small>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn btn-block">Register</button>
    </form>
    <?php endif; ?>
    
    <p class="text-center mt-3">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>
