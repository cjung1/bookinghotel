<?php
session_start();
include 'includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

try {
    // Kiểm tra session hợp lệ
    if (!isset($_SESSION['verify_email'], $_SESSION['verify_otp'], $_SESSION['verify_otp_expire'])) {
        throw new Exception("Phiên xác thực không hợp lệ");
    }

    // Xử lý OTP đầu vào
    $enteredOtp = preg_replace('/\D/', '', implode('', $_POST['otp'] ?? []));
    if (strlen($enteredOtp) !== 6) {
        throw new Exception("Vui lòng nhập đủ 6 chữ số");
    }

    // Truy vấn database
    $stmt = $pdo->prepare("SELECT verify_otp, verify_otp_expire FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['verify_email']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Tài khoản không tồn tại");
    }

    // Kiểm tra thời hạn
    if (time() > $user['verify_otp_expire']) {
        throw new Exception("Mã OTP đã hết hạn");
    }

    // So sánh OTP
    if ($enteredOtp !== preg_replace('/\D/', '', $user['verify_otp'])) {
        throw new Exception("Mã OTP không chính xác");
    }

    // Cập nhật trạng thái verified
    $stmt = $pdo->prepare("UPDATE users SET verified = 1 WHERE email = ?");
    $stmt->execute([$_SESSION['verify_email']]);

    // Lấy thông tin user
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['verify_email']]);
    $userData = $stmt->fetch();

    // Xóa session OTP
    unset($_SESSION['verify_otp'], $_SESSION['verify_otp_expire'], $_SESSION['verify_email']);

    // Tạo session user
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['name'];

    echo json_encode([
        'status' => 'success',
        'message' => 'Xác thực thành công!',
        'redirect' => 'index.php'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>