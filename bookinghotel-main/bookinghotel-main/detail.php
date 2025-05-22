<?php
session_start();
include 'includes/config.php'; // Kết nối CSDL

// --- PHẦN 1: XỬ LÝ LOGIC, LẤY DỮ LIỆU, KIỂM TRA ---

// Kiểm tra ID khách sạn từ GET
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || intval($_GET['id']) <= 0) {
    $_SESSION['error_message_global'] = "Yêu cầu không hợp lệ. ID khách sạn không được cung cấp hoặc không đúng.";
    header('Location: index.php');
    exit();
}
$hotelId = intval($_GET['id']);

// Mặc định ngày nhận/trả phòng và số khách (có thể lấy từ session/GET nếu từ trang tìm kiếm)
$defaultCheckin = date('Y-m-d', strtotime("+1 day"));
$defaultCheckout = date('Y-m-d', strtotime("+2 day"));
$checkinDate = htmlspecialchars($_GET['checkin'] ?? $_SESSION['search_checkin'] ?? $defaultCheckin);
$checkoutDate = htmlspecialchars($_GET['checkout'] ?? $_SESSION['search_checkout'] ?? $defaultCheckout);
$guests = intval($_GET['guests'] ?? $_SESSION['search_guests'] ?? 1);
if ($guests < 1) $guests = 1;

// Validate ngày tháng cơ bản
try {
    $checkinDT = new DateTime($checkinDate);
    $checkoutDT = new DateTime($checkoutDate);
    if ($checkoutDT <= $checkinDT) {
        // Nếu ngày checkout không hợp lệ, đặt lại thành ngày sau ngày checkin
        $checkoutDate = date('Y-m-d', strtotime($checkinDate . " +1 day"));
        $_SESSION['warning_message_detail'] = "Ngày trả phòng đã được tự động điều chỉnh.";
    }
} catch (Exception $e) {
    // Nếu ngày không hợp lệ, dùng ngày mặc định
    $checkinDate = $defaultCheckin;
    $checkoutDate = $defaultCheckout;
    $_SESSION['warning_message_detail'] = "Ngày cung cấp không hợp lệ, đã sử dụng ngày mặc định.";
}


// Lấy thông tin khách sạn
try {
    $stmtHotel = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
    $stmtHotel->execute([$hotelId]);
    $hotel = $stmtHotel->fetch(PDO::FETCH_ASSOC);

    if (!$hotel) {
        $_SESSION['error_message_global'] = "Không tìm thấy khách sạn bạn yêu cầu (ID: $hotelId).";
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Lỗi tải chi tiết khách sạn (ID: $hotelId): " . $e->getMessage());
    $_SESSION['error_message_global'] = "Lỗi hệ thống khi tải thông tin khách sạn. Vui lòng thử lại sau.";
    header('Location: index.php');
    exit();
}

// --- PHẦN 2: CHUẨN BỊ DỮ LIỆU ĐỂ HIỂN THỊ ---
$pageTitle = "Chi tiết: " . htmlspecialchars($hotel['name']);

// --- PHẦN 3: INCLUDE HEADER VÀ HIỂN THỊ HTML ---
include 'includes/header.php';
?>

<div class="detail-page-container">
    <div class="container content">
        <?php
        // Hiển thị các thông báo từ session (nếu có)
        if (isset($_SESSION['error_message_global'])) {
            echo '<div class="message error-message main-error">' . $_SESSION['error_message_global'] . '</div>';
            unset($_SESSION['error_message_global']);
        }
        if (isset($_SESSION['warning_message_detail'])) {
            echo '<div class="message warning-message">' . $_SESSION['warning_message_detail'] . '</div>';
            unset($_SESSION['warning_message_detail']);
        }
        ?>

        <article class="hotel-detailed-view">
            <header class="hotel-header-detail">
                <h1><?= htmlspecialchars($hotel['name']) ?></h1>
                <p class="hotel-location-detail"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($hotel['location'] ?? 'Chưa cập nhật') ?></p>
                <div class="hotel-rating-stars">
                    Đánh giá: <?= number_format($hotel['rating'] ?? 0, 1) ?>/5
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?= ($i <= round($hotel['rating'] ?? 0)) ? '' : '-half-alt' // Hoặc dùng fa-reg fa-star cho sao trống ?>"></i>
                    <?php endfor; ?>
                </div>
            </header>

            <section class="hotel-main-content-detail">
                <div class="hotel-gallery-detail">
                    <?php
                    $imagePath = "assets/images/" . htmlspecialchars($hotel['image'] ?? 'default.jpg');
                    if (!file_exists($imagePath) || empty($hotel['image'])) {
                        $imagePath = "assets/images/default.jpg";
                    }
                    ?>
                    <img src="<?= $imagePath ?>" alt="Ảnh của <?= htmlspecialchars($hotel['name']) ?>" class="hotel-primary-image-detail">
                </div>
                <div class="hotel-description-detail">
                    <h2><i class="fas fa-info-circle"></i> Giới thiệu</h2>
                    <p><?= nl2br(htmlspecialchars($hotel['description'] ?? 'Chưa có mô tả cho khách sạn này.')) ?></p>
                </div>
            </section>

            <section class="hotel-amenities-policies-detail">
                <?php if (!empty($hotel['amenities'])): ?>
                    <div class="info-section-detail amenities-section-detail">
                        <h2><i class="fas fa-concierge-bell"></i> Tiện nghi</h2>
                        <ul class="amenities-list-detail">
                            <?php
                            $amenities = is_string($hotel['amenities']) ? json_decode($hotel['amenities'], true) : $hotel['amenities'];
                            if (json_last_error() === JSON_ERROR_NONE && is_array($amenities) && !empty($amenities)):
                                foreach ($amenities as $amenity): ?>
                                    <li><i class="fas fa-check"></i> <?= htmlspecialchars(trim($amenity)) ?></li>
                                <?php endforeach;
                            elseif (is_string($hotel['amenities'])): // Nếu không phải JSON, thử tách bằng dấu phẩy
                                $textAmenities = explode(',', $hotel['amenities']);
                                foreach ($textAmenities as $amenity): if(!empty(trim($amenity))): ?>
                                    <li><i class="fas fa-check"></i> <?= htmlspecialchars(trim($amenity)) ?></li>
                                <?php endif; endforeach;
                            endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($hotel['policies'])): ?>
                     <div class="info-section-detail policies-section-detail">
                        <h2><i class="fas fa-file-alt"></i> Chính sách</h2>
                        <?php
                            $policiesOutput = '';
                            $policiesData = is_string($hotel['policies']) ? json_decode($hotel['policies'], true) : $hotel['policies'];
                            if (json_last_error() === JSON_ERROR_NONE && is_array($policiesData)) {
                                foreach($policiesData as $key => $value){
                                     $policiesOutput .= "<p><strong>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $key))) . ":</strong> " . htmlspecialchars($value) . "</p>";
                                }
                            } elseif (is_string($hotel['policies'])) {
                                $policiesOutput = "<p>" . nl2br(htmlspecialchars($hotel['policies'])) . "</p>";
                            }
                        ?>
                        <div><?= $policiesOutput ?></div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="hotel-booking-action-detail">
                <h2><i class="fas fa-calendar-plus"></i> Đặt phòng ngay</h2>
                <p>Chọn ngày và số lượng khách để xem các loại phòng có sẵn và tiến hành đặt phòng.</p>
                <form action="booking.php" method="GET" class="view-rooms-form">
                    <input type="hidden" name="hotel_id" value="<?= $hotelId ?>">
                    <div class="form-row-detail">
                        <div class="form-group-detail">
                            <label for="checkin">Ngày nhận phòng:</label>
                            <input type="date" id="checkin" name="checkin_date" class="form-control-detail" value="<?= $checkinDate ?>" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group-detail">
                            <label for="checkout">Ngày trả phòng:</label>
                            <input type="date" id="checkout" name="checkout_date" class="form-control-detail" value="<?= $checkoutDate ?>" min="<?= date('Y-m-d', strtotime($checkinDate . ' +1 day')) ?>" required>
                        </div>
                        <div class="form-group-detail">
                            <label for="guests">Số khách:</label>
                            <input type="number" id="guests" name="guests" class="form-control-detail" value="<?= $guests ?>" min="1" max="20" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block btn-proceed-booking-detail">
                        <i class="fas fa-arrow-right"></i> Xem phòng & Đặt ngay
                    </button>
                </form>
            </section>
        </article>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
