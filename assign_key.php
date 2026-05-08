<?php
define('WEBSITE_INITIALIZED', true);
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDbConnection();

$email = 'ayanrbk@gmail.com';

$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$config = [
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
];

$privateKeyRes = openssl_pkey_new($config);

if (!$privateKeyRes) {
    die("Key generation failed: " . openssl_error_string());
}

openssl_pkey_export($privateKeyRes, $privateKey);

$keyDetails = openssl_pkey_get_details($privateKeyRes);
$publicKey = $keyDetails['key'] ?? null;

if (!$publicKey) {
    die("Failed to extract public key.");
}

$updateStmt = $db->prepare("UPDATE users SET public_key = ? WHERE id = ?");
$success = $updateStmt->execute([$publicKey, $user['id']]);

if ($success) {
    echo "Public key assigned successfully to user with email: $email<br>";

    $filename = "private_key_{$user['id']}.pem";
    if (file_put_contents($filename, $privateKey)) {
        echo "Private key saved as <strong>$filename</strong><br>";
    } else {
        echo " Failed to save private key to file.<br>";
    }
} else {
    echo " Failed to update public key in the database.<br>";
}
?>
