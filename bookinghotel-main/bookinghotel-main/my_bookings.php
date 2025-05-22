<?php
session_start();
include 'includes/config.php'; // Kết nối CSDL và các cấu hình chung
$pageTitle = 'Lịch sử đặt phòng của tôi';
include 'includes/header.php'; // Giao diện đầu trang

// Kiểm tra người dùng đã đăng nhập chưa
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message_global'] = "Vui lòng đăng nhập để xem lịch sử đặt phòng.";
    // Lưu lại trang hiện tại để quay lại sau khi đăng nhập
    $_SESSION['redirect_after_login'] = 'my_bookings.php';
    header('Location: loginregister.php?tab=login');
    exit();
}

$userId = $_SESSION['user_id'];
$bookings = [];
$error_message = '';

try {
    // Truy vấn lấy lịch sử đặt phòng của người dùng hiện tại
    // Sắp xếp theo ngày tạo đơn hàng mới nhất lên đầu
    $stmt = $pdo->prepare("
        SELECT
            b.id AS booking_id,
            b.hotel_id,
            h.name AS hotel_name,
            h.image AS hotel_image, /* Thêm ảnh khách sạn */
            h.location AS hotel_location, /* Thêm địa điểm khách sạn */
            b.checkin_date,
            b.checkout_date,
            b.total_price,
            b.payment_method,
            b.transaction_id,
            b.created_at AS booking_date,
            (SELECT GROUP_CONCAT(CONCAT(r.room_type, ' (x', br_inner.quantity, ')') SEPARATOR '; ')
             FROM booking_rooms br_inner
             JOIN rooms r ON br_inner.room_id = r.id
             WHERE br_inner.booking_id = b.id) AS room_details_with_quantity, /* Chi tiết phòng và số lượng */
            b.status AS booking_status /* Thêm trạng thái đơn hàng nếu có */
        FROM bookings b
        JOIN hotels h ON b.hotel_id = h.id
        /* Không cần JOIN booking_rooms và rooms ở đây nữa nếu dùng subquery cho room_details */
        WHERE b.user_id = :user_id
        /* GROUP BY b.id  Không cần thiết nếu subquery đã xử lý group cho room_details */
        ORDER BY b.created_at DESC
    ");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Lỗi tải lịch sử đặt phòng cho user ID {$userId}: " . $e->getMessage());
    $error_message = "Có lỗi xảy ra khi tải lịch sử đặt phòng của bạn. Vui lòng thử lại sau.";
}

?>

<div class="container content my-bookings-page"> <h2><i class="fas fa-history"></i> Lịch sử đặt phòng của tôi</h2>

    <?php if (!empty($error_message)): ?>
        <div class="message error-message main-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if (empty($bookings) && empty($error_message)): ?>
        <div class="message info-message">
            <p>Bạn chưa có đơn đặt phòng nào.</p>
            <p><a href="index.php" class="btn btn-primary"><i class="fas fa-search"></i> Bắt đầu tìm kiếm khách sạn</a></p>
        </div>
    <?php elseif (!empty($bookings)): ?>
        <div class="my-bookings-list"> <?php foreach ($bookings as $booking): ?>
                <div class="my-booking-card"> <div class="my-booking-hotel-image">
                        <?php
                        $hotelImagePath = 'assets/images/' . htmlspecialchars($booking['hotel_image']);
                        if (!file_exists($hotelImagePath) || empty($booking['hotel_image'])) {
                            $hotelImagePath = 'assets/images/default.jpg'; // Ảnh mặc định
                        }
                        ?>
                        <img src="<?= $hotelImagePath ?>" alt="<?= htmlspecialchars($booking['hotel_name']) ?>">
                    </div>
                    <div class="my-booking-details">
                        <h3>
                            <a href="detail.php?id=<?= $booking['hotel_id'] ?>">
                                <?= htmlspecialchars($booking['hotel_name']) ?>
                            </a>
                        </h3>
                        <p class="hotel-location-history"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($booking['hotel_location']) ?></p>
                        <hr class="booking-card-divider">
                        <p><strong>Mã đặt phòng:</strong> #<?= htmlspecialchars($booking['booking_id']) ?></p>
                        <p><strong>Ngày đặt:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($booking['booking_date']))) ?></p>
                        <p><strong>Nhận phòng:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($booking['checkin_date']))) ?></p>
                        <p><strong>Trả phòng:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($booking['checkout_date']))) ?></p>
                        <p><strong>Phòng đã đặt:</strong> <?= htmlspecialchars($booking['room_details_with_quantity']) ?></p>
                        <p><strong>Tổng tiền:</strong> <span class="price-highlight-booking"><?= number_format($booking['total_price'], 0, ',', '.') ?> VNĐ</span></p>
                        <p><strong>Thanh toán:</strong> <?= strtoupper(htmlspecialchars($booking['payment_method'])) ?></p>
                        <?php if (!empty($booking['transaction_id'])): ?>
                            <p><strong>Mã giao dịch:</strong> <?= htmlspecialchars($booking['transaction_id']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($booking['booking_status'])): // Hiển thị trạng thái nếu có ?>
                            <p><strong>Trạng thái:</strong> <span class="status-<?= strtolower(htmlspecialchars($booking['booking_status'])) ?>"><?= ucfirst(htmlspecialchars($booking['booking_status'])) ?></span></p>
                        <?php endif; ?>

                        <div class="booking-card-actions">
                             </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>