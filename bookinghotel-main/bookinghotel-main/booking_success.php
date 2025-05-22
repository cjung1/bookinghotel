<?php
session_start();
include 'includes/config.php'; 
$pageTitle = 'Thông báo Đặt phòng'; 

$is_success = false;
$display_message = '';
$user_email = '';
$booking_id_info = null;
$transaction_id_info = null;
$payment_method_info = null;

// Kiểm tra trạng thái thành công
if (isset($_SESSION['booking_confirmation_message'])) {
    $is_success = true;
    $display_message = $_SESSION['booking_confirmation_message'];
    $user_email = $_SESSION['booking_user_email_for_confirmation'] ?? 'email của bạn';
    $booking_id_info = $_SESSION['last_booking_id_info'] ?? null;
    $transaction_id_info = $_SESSION['last_transaction_id_info'] ?? null;
    $payment_method_info = $_SESSION['last_payment_method_info'] ?? null;

    // Xóa các session thành công
    unset($_SESSION['booking_confirmation_message']);
    unset($_SESSION['booking_user_email_for_confirmation']);
    unset($_SESSION['last_booking_id_info']);
    unset($_SESSION['last_transaction_id_info']);
    unset($_SESSION['last_payment_method_info']);

} elseif (isset($_SESSION['payment_error_message'])) { // Kiểm tra trạng thái thất bại
    $is_success = false;
    $display_message = $_SESSION['payment_error_message'];
    $transaction_id_info = $_SESSION['failed_transaction_id_info'] ?? null; // Có thể có mã giao dịch thất bại

    // Xóa các session thất bại
    unset($_SESSION['payment_error_message']);
    unset($_SESSION['failed_transaction_id_info']);
    
    
} else {
   
    $_SESSION['error_message_global'] = "Không có thông tin trạng thái đặt phòng để hiển thị.";
    header('Location: index.php');
    exit();
}

include 'includes/header.php';
?>

<div class="booking-status-page-container">
    <div class="container content">
        <div class="status-card <?= $is_success ? 'success-card-custom' : 'failure-card-custom' ?>">
            <div class="status-icon-custom">
                <i class="fas <?= $is_success ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
            </div>
            <h2><?= $is_success ? 'Đặt phòng Thành công!' : 'Đặt phòng Thất bại!' ?></h2>
            <p class="status-main-message"><?= htmlspecialchars($display_message) ?></p>

            <?php if ($is_success && $booking_id_info): ?>
                <div class="booking-summary-status">
                    <h3>Thông tin đơn hàng của bạn:</h3>
                    <p><strong>Mã đặt phòng (Booking ID):</strong> <?= htmlspecialchars($booking_id_info) ?></p>
                    <?php if ($transaction_id_info): ?>
                        <p><strong>Mã giao dịch:</strong> <?= htmlspecialchars($transaction_id_info) ?></p>
                    <?php endif; ?>
                    <?php if ($payment_method_info): ?>
                        <p><strong>Phương thức thanh toán:</strong> <?= strtoupper(htmlspecialchars($payment_method_info)) ?></p>
                    <?php endif; ?>
                    <p>Chúng tôi cũng đã gửi một email (mô phỏng) đến <strong><?= htmlspecialchars($user_email) ?></strong> với chi tiết đặt phòng.</p>
                </div>
            <?php elseif (!$is_success && $transaction_id_info): ?>
                <div class="booking-summary-status failure-details">
                    <h3>Chi tiết giao dịch (thất bại):</h3>
                    <p><strong>Mã giao dịch tham chiếu:</strong> <?= htmlspecialchars($transaction_id_info) ?></p>
                    <p>Vui lòng liên hệ bộ phận hỗ trợ nếu bạn cần thêm thông tin.</p>
                </div>
            <?php endif; ?>

            <div class="status-actions-custom">
                <?php if ($is_success): ?>
                    <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Về trang chủ</a>
                    <a href="my_bookings.php" class="btn btn-secondary"><i class="fas fa-history"></i> Xem lịch sử đặt phòng</a>
                <?php else: // Trường hợp thất bại ?>
                    <?php

                    $retry_payment_link = 'index.php'; // Mặc định về trang chủ
                    if (isset($_SESSION['pending_booking_details'])) {

                        $retry_payment_link = 'payment.php'; 
                    }
                    ?>
                    <a href="<?= $retry_payment_link ?>" class="btn btn-warning"><i class="fas fa-redo"></i>
                        <?= (isset($_SESSION['pending_booking_details'])) ? 'Thử lại thanh toán' : 'Thử đặt lại' ?>
                    </a>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-home"></i> Về trang chủ</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
