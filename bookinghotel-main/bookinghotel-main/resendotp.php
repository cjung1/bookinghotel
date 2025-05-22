<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['verify_email'])) {
    header('Location: loginregister.php');
    exit();
}

$newOtp = rand(100000, 999999);
$newExpire = time() + 300;

try {
    // Cập nhật database
    $stmt = $pdo->prepare("UPDATE users SET verify_otp = ?, verify_otp_expire = ? WHERE email = ?");
    $stmt->execute([$newOtp, $newExpire, $_SESSION['verify_email']]);

    // Cập nhật session
    $_SESSION['verify_otp'] = $newOtp;
    $_SESSION['verify_otp_expire'] = $newExpire;

    // Gửi email qua API
    $nodeApiUrl = 'http://localhost:3001/send-otp-email';
    $postData = json_encode([
        'toEmail' => $_SESSION['verify_email'],
        'otp' => $newOtp
    ]);

    $ch = curl_init($nodeApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200 || !json_decode($response)->success) {
        throw new Exception("Gửi email thất bại");
    }

    $_SESSION['verify_success'] = 'Đã gửi lại mã OTP mới!';

} catch (Exception $e) {
    $_SESSION['verify_error'] = $e->getMessage();
} finally {
    curl_close($ch);
}

header('Location: verify.php');
exit();
?>