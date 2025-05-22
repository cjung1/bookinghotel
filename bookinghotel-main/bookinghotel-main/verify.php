<?php
session_start();
include 'includes/config.php';
$pageTitle = 'Xác nhận Email';
include 'includes/header.php';

if (!isset($_SESSION['verify_email'])) {
    header('Location: loginregister.php');
    exit();
}
?>

<div class="verify-page">
    <div class="verify-card">
        <div class="verify-icon">
            <i class="fas fa-envelope-open-text"></i>
        </div>
        <h2>Xác nhận Email</h2>
        <p class="verify-text">Mã xác nhận đã được gửi đến 
            <strong><?= htmlspecialchars($_SESSION['verify_email']) ?></strong>
        </p>

        <div class="countdown">
            Thời gian còn lại: 
            <span id="timer" data-expire="<?= $_SESSION['verify_otp_expire'] ?? 0 ?>"></span>
        </div>

        <form method="POST" id="verifyForm">
            <div class="otp-inputs">
                <?php for($i = 0; $i < 6; $i++): ?>
                <input type="text" 
                       class="otp-input"
                       name="otp[]"
                       maxlength="1"
                       required
                       pattern="\d"
                       data-index="<?= $i ?>">
                <?php endfor; ?>
            </div>

            <div id="verification-message"></div>

            <button type="submit" class="verify-btn">Xác nhận</button>
            
            <p class="resend-link">
                Không nhận được mã?
                <a href="resendotp.php" id="resendOtp">Gửi lại mã OTP</a>
            </p>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>