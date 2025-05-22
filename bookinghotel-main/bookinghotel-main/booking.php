<?php
session_start();
include 'includes/config.php';

// --- PHẦN 1: KIỂM TRA ĐĂNG NHẬP VÀ THAM SỐ ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message_global'] = "Vui lòng đăng nhập để có thể đặt phòng.";
    $queryString = http_build_query($_GET);
    $_SESSION['redirect_after_login'] = 'booking.php?' . $queryString;
    header('Location: loginregister.php?tab=login');
    exit();
}

// Kiểm tra các tham số cần thiết từ GET (từ detail.php)
if (!isset($_GET['hotel_id']) || !is_numeric($_GET['hotel_id']) ||
    !isset($_GET['checkin_date']) || empty($_GET['checkin_date']) ||
    !isset($_GET['checkout_date']) || empty($_GET['checkout_date']) ||
    !isset($_GET['guests']) || !is_numeric($_GET['guests']) || intval($_GET['guests']) < 1) {
    $_SESSION['error_message_global'] = "Thông tin yêu cầu đặt phòng không đầy đủ. Vui lòng thử lại từ trang chi tiết khách sạn.";
    header('Location: index.php'); // Hoặc quay lại trang trước đó nếu có thể
    exit();
}

$hotelId = intval($_GET['hotel_id']);
$checkinDateStr = htmlspecialchars($_GET['checkin_date']);
$checkoutDateStr = htmlspecialchars($_GET['checkout_date']);
$guests = intval($_GET['guests']);
$userId = $_SESSION['user_id'];

// Validate ngày tháng
try {
    $checkinDT = new DateTime($checkinDateStr);
    $checkoutDT = new DateTime($checkoutDateStr);
    $currentDate = new DateTime(date('Y-m-d'));

    if ($checkinDT < $currentDate) {
        $_SESSION['error_message_global'] = "Ngày nhận phòng không thể là một ngày trong quá khứ.";
        header('Location: detail.php?id=' . $hotelId); exit();
    }
    if ($checkoutDT <= $checkinDT) {
        $_SESSION['error_message_global'] = "Ngày trả phòng phải sau ngày nhận phòng.";
        header('Location: detail.php?id=' . $hotelId . '&checkin=' . $checkinDateStr . '&guests=' . $guests); exit();
    }
    $interval = $checkinDT->diff($checkoutDT);
    $nights = $interval->days;
    if ($nights <= 0) $nights = 1; // Đảm bảo ít nhất 1 đêm

} catch (Exception $e) {
    $_SESSION['error_message_global'] = "Định dạng ngày cung cấp không hợp lệ.";
    header('Location: detail.php?id=' . $hotelId); exit();
}


// --- PHẦN 2: LẤY DỮ LIỆU KHÁCH SẠN, PHÒNG, USER ---
try {
    $stmtHotel = $pdo->prepare("SELECT name, location FROM hotels WHERE id = ?");
    $stmtHotel->execute([$hotelId]);
    $hotel = $stmtHotel->fetch(PDO::FETCH_ASSOC);

    if (!$hotel) {
        $_SESSION['error_message_global'] = "Không tìm thấy thông tin khách sạn.";
        header('Location: index.php'); exit();
    }

    $stmtRooms = $pdo->prepare("SELECT id, room_type, price, capacity FROM rooms WHERE hotel_id = ? AND capacity >= ? ORDER BY price ASC");
    $stmtRooms->execute([$hotelId, $guests]);
    $availableRooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

    $stmtUser = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $loggedInUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Lỗi tải dữ liệu trang booking (Hotel ID: $hotelId): " . $e->getMessage());
    $_SESSION['error_message_global'] = "Lỗi hệ thống khi tải thông tin đặt phòng.";
    header('Location: detail.php?id=' . $hotelId); exit();
}


// --- PHẦN 3: XỬ LÝ FORM SUBMIT (POST) ---
$form_errors_booking = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_room_id = $_POST['room_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    if ($quantity < 1) $quantity = 1;

    $customer_name = trim(htmlspecialchars($_POST['customer_name'] ?? ''));
    $customer_email = trim(htmlspecialchars($_POST['customer_email'] ?? ''));
    $customer_phone = trim(htmlspecialchars($_POST['customer_phone'] ?? ''));
    $special_requests = trim(htmlspecialchars($_POST['special_requests'] ?? ''));
    $chosen_payment_method = $_POST['payment_method'] ?? null;

    // Validate
    if (empty($selected_room_id)) $form_errors_booking['room_id'] = "Vui lòng chọn loại phòng.";
    if (empty($customer_name)) $form_errors_booking['customer_name'] = "Vui lòng nhập họ tên.";
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) $form_errors_booking['customer_email'] = "Email không hợp lệ.";
    if (empty($customer_phone)) $form_errors_booking['customer_phone'] = "Vui lòng nhập số điện thoại.";
    if (empty($chosen_payment_method)) $form_errors_booking['payment_method'] = "Vui lòng chọn phương thức thanh toán.";

    $selected_room_info = null;
    if ($selected_room_id) {
        foreach ($availableRooms as $r) {
            if ($r['id'] == $selected_room_id) {
                $selected_room_info = $r;
                break;
            }
        }
    }

    if (!$selected_room_info && !isset($form_errors_booking['room_id'])) {
        $form_errors_booking['room_id'] = "Loại phòng đã chọn không hợp lệ.";
    } elseif ($selected_room_info) {
        if (($selected_room_info['capacity'] * $quantity) < $guests) {
            $form_errors_booking['quantity'] = "Số lượng phòng đã chọn không đủ sức chứa cho " . $guests . " khách.";
        }
    }


    if (empty($form_errors_booking) && $selected_room_info) {
        $totalPrice = $selected_room_info['price'] * $nights * $quantity;

        $_SESSION['pending_booking_details'] = [
            'hotel_id' => $hotelId,
            'hotel_name' => $hotel['name'],
            'room_id' => $selected_room_id,
            'room_type_name' => $selected_room_info['room_type'],
            'room_price_at_booking' => $selected_room_info['price'],
            'quantity' => $quantity,
            'checkin_date' => $checkinDateStr,
            'checkout_date' => $checkoutDateStr,
            'nights' => $nights,
            'guests' => $guests,
            'total_price' => $totalPrice,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'special_requests' => $special_requests,
            'chosen_payment_method' => $chosen_payment_method,
            'user_id' => $userId
        ];
        header('Location: payment.php');
        exit();
    }
}

// --- PHẦN 4: HIỂN THỊ HTML ---
$pageTitle = 'Đặt phòng tại ' . htmlspecialchars($hotel['name']);
include 'includes/header.php';
?>
<div class="booking-page-container">
    <div class="container content">
        <h2><i class="fas fa-calendar-check"></i> Đặt phòng: <?= htmlspecialchars($hotel['name']) ?></h2>

        <?php
        if (isset($_SESSION['error_message_global'])) {
            echo '<div class="message error-message main-error">' . $_SESSION['error_message_global'] . '</div>';
            unset($_SESSION['error_message_global']);
        }
        if (!empty($form_errors_booking)) {
            echo '<div class="message error-message form-validation-summary">Vui lòng kiểm tra lại các thông tin sau:<ul>';
            foreach ($form_errors_booking as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul></div>';
        }
        ?>

        <form action="booking.php?<?= http_build_query($_GET) // Giữ lại params từ URL ?>" method="POST" id="bookingFormPage">
            <div class="booking-section summary-section-booking">
                <h3><i class="fas fa-hotel"></i> Thông tin lưu trú</h3>
                <p><strong>Khách sạn:</strong> <?= htmlspecialchars($hotel['name']) ?></p>
                <p><strong>Địa điểm:</strong> <?= htmlspecialchars($hotel['location']) ?></p>
                <p><strong>Ngày nhận phòng:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($checkinDateStr))) ?></p>
                <p><strong>Ngày trả phòng:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($checkoutDateStr))) ?></p>
                <p><strong>Số đêm:</strong> <span id="booking_nights"><?= $nights ?></span></p>
                <p><strong>Số khách:</strong> <?= $guests ?></p>
            </div>

            <div class="booking-section room-selection-booking">
                <h3><i class="fas fa-bed"></i> Chọn phòng</h3>
                <?php if (empty($availableRooms)): ?>
                    <p class="no-rooms-available-booking">Rất tiếc, không có loại phòng nào phù hợp cho <?= $guests ?> khách tại khách sạn này trong thời gian bạn chọn, hoặc tất cả đã được đặt.</p>
                <?php else: ?>
                    <div class="form-group-booking <?= isset($form_errors_booking['room_id']) ? 'has-error' : '' ?>">
                        <label for="room_id">Loại phòng:</label>
                        <select name="room_id" id="room_id" class="form-control-booking" required>
                            <option value="">-- Chọn loại phòng --</option>
                            <?php foreach ($availableRooms as $room): ?>
                                <option value="<?= $room['id'] ?>"
                                        data-price="<?= $room['price'] ?>"
                                        data-capacity="<?= $room['capacity'] ?>"
                                        <?= (isset($_POST['room_id']) && $_POST['room_id'] == $room['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($room['room_type']) ?> (Tối đa <?= $room['capacity'] ?> khách) - <?= number_format($room['price'], 0, ',', '.') ?> VNĐ/đêm
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(isset($form_errors_booking['room_id'])): ?><span class="error-text-booking"><?= $form_errors_booking['room_id'] ?></span><?php endif; ?>
                    </div>
                    <div class="form-group-booking <?= isset($form_errors_booking['quantity']) ? 'has-error' : '' ?>">
                        <label for="quantity">Số lượng phòng:</label>
                        <input type="number" name="quantity" id="quantity" class="form-control-booking" value="<?= htmlspecialchars($_POST['quantity'] ?? 1) ?>" min="1" max="5" required>
                         <?php if(isset($form_errors_booking['quantity'])): ?><span class="error-text-booking"><?= $form_errors_booking['quantity'] ?></span><?php endif; ?>
                    </div>
                    <div class="form-group-booking">
                        <strong>Tổng tiền phòng (ước tính):</strong> <span id="estimated_total_price_booking" class="price-highlight-booking">Vui lòng chọn phòng</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($availableRooms)): // Chỉ hiển thị phần còn lại nếu có phòng để chọn ?>
            <div class="booking-section customer-info-booking">
                <h3><i class="fas fa-user-circle"></i> Thông tin liên hệ</h3>
                <div class="form-group-booking <?= isset($form_errors_booking['customer_name']) ? 'has-error' : '' ?>">
                    <label for="customer_name">Họ và tên:</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control-booking" value="<?= htmlspecialchars($_POST['customer_name'] ?? $loggedInUser['name'] ?? '') ?>" required>
                    <?php if(isset($form_errors_booking['customer_name'])): ?><span class="error-text-booking"><?= $form_errors_booking['customer_name'] ?></span><?php endif; ?>
                </div>
                <div class="form-group-booking <?= isset($form_errors_booking['customer_email']) ? 'has-error' : '' ?>">
                    <label for="customer_email">Email:</label>
                    <input type="email" name="customer_email" id="customer_email" class="form-control-booking" value="<?= htmlspecialchars($_POST['customer_email'] ?? $loggedInUser['email'] ?? '') ?>" required>
                     <?php if(isset($form_errors_booking['customer_email'])): ?><span class="error-text-booking"><?= $form_errors_booking['customer_email'] ?></span><?php endif; ?>
                </div>
                <div class="form-group-booking <?= isset($form_errors_booking['customer_phone']) ? 'has-error' : '' ?>">
                    <label for="customer_phone">Số điện thoại:</label>
                    <input type="tel" name="customer_phone" id="customer_phone" class="form-control-booking" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>" required>
                     <?php if(isset($form_errors_booking['customer_phone'])): ?><span class="error-text-booking"><?= $form_errors_booking['customer_phone'] ?></span><?php endif; ?>
                </div>
                <div class="form-group-booking">
                    <label for="special_requests">Yêu cầu đặc biệt (nếu có):</label>
                    <textarea name="special_requests" id="special_requests" class="form-control-booking" rows="3"><?= htmlspecialchars($_POST['special_requests'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="booking-section payment-method-selection-booking">
                <h3><i class="fas fa-credit-card"></i> Chọn phương thức thanh toán</h3>
                <div class="payment-options-booking <?= isset($form_errors_booking['payment_method']) ? 'has-error' : '' ?>">
                    <div class="payment-option-booking">
                        <input type="radio" name="payment_method" value="momo" id="pay_momo_booking" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'momo') ? 'checked' : '' ?> required>
                        <label for="pay_momo_booking">
                            <i class="fas fa-wallet payment-icon-booking payment-icon-momo"></i> Thanh toán Momo
                        </label>
                    </div>
                    <div class="payment-option-booking">
                        <input type="radio" name="payment_method" value="visa" id="pay_visa_booking" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'visa') ? 'checked' : '' ?> required>
                        <label for="pay_visa_booking">
                            <i class="fab fa-cc-visa payment-icon-booking payment-icon-visa"></i> Thanh toán Visa
                        </label>
                    </div>
                    <div class="payment-option-booking">
                        <input type="radio" name="payment_method" value="cod" id="pay_cod_booking" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cod') ? 'checked' : '' ?> required>
                        <label for="pay_cod_booking">
                            <i class="fas fa-money-bill-wave payment-icon-booking payment-icon-cod"></i> Thanh toán khi nhận phòng (COD)
                        </label>
                    </div>
                    <?php if(isset($form_errors_booking['payment_method'])): ?><span class="error-text-booking"><?= $form_errors_booking['payment_method'] ?></span><?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block btn-proceed-to-payment-booking" <?= empty($availableRooms) ? 'disabled' : '' ?>>
                <i class="fas fa-shield-alt"></i> Tiếp tục đến trang thanh toán
            </button>
            <?php endif; // End if !empty($availableRooms) ?>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomSelect = document.getElementById('room_id');
    const quantityInput = document.getElementById('quantity');
    const estimatedPriceSpan = document.getElementById('estimated_total_price_booking');
    const nights = parseInt(document.getElementById('booking_nights').textContent) || 1;

    function updateEstimatedPrice() {
        const selectedOption = roomSelect.options[roomSelect.selectedIndex];
        const pricePerNight = parseFloat(selectedOption.getAttribute('data-price'));
        const quantity = parseInt(quantityInput.value) || 1;

        if (pricePerNight && quantity > 0) {
            const total = pricePerNight * nights * quantity;
            estimatedPriceSpan.textContent = total.toLocaleString('vi-VN') + ' VNĐ';
        } else {
            estimatedPriceSpan.textContent = 'Vui lòng chọn phòng và số lượng';
        }
    }

    if (roomSelect && quantityInput && estimatedPriceSpan) {
        roomSelect.addEventListener('change', updateEstimatedPrice);
        quantityInput.addEventListener('change', updateEstimatedPrice);
        quantityInput.addEventListener('input', updateEstimatedPrice); // Cập nhật khi gõ
        
        // Cập nhật giá lần đầu nếu đã có giá trị được chọn (ví dụ khi form post bị lỗi và load lại)
        if (roomSelect.value) {
            updateEstimatedPrice();
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>