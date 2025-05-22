<?php
session_start();
include 'includes/config.php';
$pageTitle = isset($_GET['tab']) ? ucfirst($_GET['tab']) : 'Đăng nhập/Đăng ký';
include 'includes/header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        // Xử lý đăng ký
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $errors[] = "Mật khẩu không trùng khớp!";
        } else {
            try {
                // Kiểm tra email tồn tại
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $errors[] = "Email đã tồn tại!";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $otp = rand(100000, 999999);
                    $otp_expire = time() + 300;

                    // Thêm dữ liệu người dùng
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, password, verify_otp, verify_otp_expire, verified)
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([$name, $email, $hashed_password, $otp, $otp_expire]);

                    // Gửi email và set session
                    $_SESSION['verify_email'] = $email;
                    $_SESSION['verify_otp'] = $otp;
                    $_SESSION['verify_otp_expire'] = $otp_expire;

                    // Gửi OTP qua email (sử dụng API Node.js)
                    $postData = json_encode(['toEmail' => $email, 'otp' => $otp]);
                    $apiUrl = 'http://localhost:3001/send-otp-email'; // **<-- CHÚ Ý: ĐẢM BẢO BẠN ĐÃ THAY THẾ URL NÀY NẾU CẦN**

                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Thêm thời gian chờ (ví dụ: 30 giây)

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);

                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 300) {
                        header("Location: verify.php");
                        exit();
                    } else {
                        $errorMessage = "Lỗi khi gửi mã OTP qua email. Vui lòng thử lại sau.";
                        if (!empty($curlError)) {
                            $errorMessage .= " (cURL error: " . $curlError . ")";
                        }
                        if (!empty($response)) {
                            $errorMessage .= " (Response from API: " . $response . ")";
                            error_log("Email API Response (Error): " . $response); // Ghi log response khi có lỗi
                        }
                        $errors[] = $errorMessage;
                        // Tùy chọn: Xóa người dùng nếu gửi email thất bại
                        // $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
                        // $stmt->execute([$email]);
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Lỗi đăng ký: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['login'])) {
        // Xử lý đăng nhập
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['verified'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['name'];
                    header("Location: index.php");
                    exit();
                } else {
                    // Yêu cầu xác thực OTP
                    $_SESSION['verify_email'] = $user['email'];
                    $_SESSION['verify_otp'] = $user['verify_otp'];
                    $_SESSION['verify_otp_expire'] = $user['verify_otp_expire'];
                    header("Location: verify.php");
                    exit();
                }
            } else {
                $errors[] = "Thông tin đăng nhập không chính xác!";
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi đăng nhập: " . $e->getMessage();
        }
    }
}
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h2>Chào mừng đến với CONANDOYCLEHOTELBOOKING</h2>
        </div>

        <div class="auth-tabs">
            <div class="auth-tab <?= (isset($_GET['tab']) && $_GET['tab'] === 'register') ? '' : 'active' ?>" data-tab="login">Đăng nhập</div>
            <div class="auth-tab <?= (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'active' : '' ?>" data-tab="register">Đăng ký</div>
        </div>

        <div class="auth-body">
            <?php if(!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach($errors as $error): ?>
                        <p><?= $error ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form <?= (isset($_GET['tab']) && $_GET['tab'] === 'register') ? '' : 'active' ?>" id="loginForm" method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Nhập email" required>
                </div>

                <div class="form-group">
                    <input type="password" name="password" placeholder="Mật khẩu" required>
                </div>

                <button type="submit" name="login" class="auth-btn">Đăng nhập</button>

                <div class="auth-footer">
                    <p>Quên mật khẩu? <a href="forgot_password.php">Nhấn vào đây</a></p>
                </div>
            </form>

            <form class="auth-form <?= (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'active' : '' ?>" id="registerForm" method="POST">
                <div class="form-group">
                    <input type="text" name="name" placeholder="Họ và tên" required>
                </div>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>

                <div class="form-group">
                    <input type="password" name="password" placeholder="Mật khẩu (ít nhất 6 ký tự)" required minlength="6">
                </div>

                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                </div>

                <button type="submit" name="register" class="auth-btn">Đăng ký</button>

                <div class="terms">
                    <p>Bằng cách đăng ký, bạn đồng ý với <a href="#">Điều khoản sử dụng</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabName = this.dataset.tab;

        // Xóa lớp 'active' khỏi tất cả các tab và form
        document.querySelectorAll('.auth-tab, .auth-form').forEach(el => {
            el.classList.remove('active');
        });

        // Thêm lớp 'active' vào tab vừa được click và form tương ứng
        this.classList.add('active');
        document.getElementById(`${tabName}Form`).classList.add('active');

        // Update URL
        history.replaceState(null, null, `?tab=${tabName}`);
    });
});

// Tự động focus vào input đầu tiên của form đang hiển thị ('active')
document.querySelector('.auth-form.active input')?.focus();
</script>

<?php include 'includes/footer.php'; ?>