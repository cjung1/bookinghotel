<?php
session_start();
include 'includes/config.php';
$pageTitle = 'Trang chủ';
include 'includes/header.php';

// Lấy 3 khách sạn nổi bật
try {
    $stmt = $pdo->query("
        SELECT h.*
        FROM hotels h
        ORDER BY h.rating DESC
        LIMIT 3
    ");
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Lỗi tải dữ liệu trang chủ: " . $e->getMessage());
    die("<div class='error'>Có lỗi xảy ra khi tải dữ liệu. Vui lòng thử lại sau.</div>");
}

// Xử lý giá trị mặc định cho ngày
$defaultCheckin = date('Y-m-d');
$defaultCheckout = date('Y-m-d', strtotime('+1 day'));
?>

<div class="hero">
    <div class="search-container">
        <h1>Khám phá những điểm đến tuyệt vời</h1>
        <form class="search-form" method="GET" action="search.php">
            <div class="form-row">
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="destination" name="destination" placeholder="Bạn muốn đi đâu?" required value="<?= htmlspecialchars($_GET['destination'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="checkin" name="checkin" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_GET['checkin'] ?? $defaultCheckin) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="checkout" name="checkout" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= htmlspecialchars($_GET['checkout'] ?? $defaultCheckout) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="number" id="guests" name="guests" min="1" placeholder="Số lượng người lớn" required value="<?= htmlspecialchars($_GET['guests'] ?? 1) ?>">
                    </div>
                </div>
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
            </div>
        </form>
    </div>
</div>

<section class="featured-hotels">
    <h2>Khách sạn nổi bật</h2>
    <div class="hotel-grid">
        <?php if(count($hotels) > 0): ?>
            <?php foreach($hotels as $hotel):
                $imagePath = "assets/images/" . $hotel['image']; // Giả sử bạn có ảnh local cho khách sạn
                // Kiểm tra nếu ảnh tồn tại, nếu không thì dùng ảnh mặc định
                $displayImage = file_exists($imagePath) ? $imagePath : "assets/images/default.jpg";
            ?>
                <div class="hotel-card">
                    <div class="hotel-image">
                        <img src="<?= htmlspecialchars($displayImage) ?>"
                             alt="<?= htmlspecialchars($hotel['name']) ?>">
                        <div class="hotel-rating">
                            <i class="fas fa-star"></i>
                            <?= number_format($hotel['rating'], 1) ?>
                        </div>
                    </div>
                    <div class="hotel-info">
                        <h3>
                            <a href="detail.php?id=<?= urlencode($hotel['id']) ?>">
                                <?= htmlspecialchars($hotel['name']) ?>
                            </a>
                        </h3>
                        <div class="hotel-meta">
                            <span class="location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($hotel['location']) ?>
                            </span>
                        </div>
                        <p class="hotel-description">
                            <?= htmlspecialchars(substr($hotel['description'], 0, 100)) ?>...
                        </p>
                        <div class="hotel-price">
                            Từ <?= number_format($hotel['price'], 0, ',', '.') ?>đ/đêm
                        </div>
                        <a href="detail.php?id=<?= urlencode($hotel['id']) ?>" class="book-btn">Xem chi tiết</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">Không tìm thấy khách sạn nổi bật.</div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const heroElement = document.querySelector('.hero');
    
    // THAY THẾ CÁC ĐƯỜNG DẪN LOCAL BẰNG CÁC URL TRỰC TUYẾN
    // Bạn có thể thay đổi các URL này thành các ảnh bạn muốn
    const imageUrls = [
        "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?q=80&w=2070&auto=format&fit=crop",
        "https://images.unsplash.com/photo-1475924156734-496f6cac6ec1?q=80&w=2070&auto=format&fit=crop",
        "https://images.unsplash.com/photo-1519046904884-53103b34b206?q=80&w=2070&auto=format&fit=crop",
        "https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=2070&auto=format&fit=crop",
        "https://images.unsplash.com/photo-1506744038136-46273834b3fb?q=80&w=2070&auto=format&fit=crop"
    ];

    let currentImageIndex = 0;

    function changeBackgroundImage() {
        // Lớp phủ gradient để chữ dễ đọc hơn
        const gradient = 'linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.25)), '; 
        heroElement.style.backgroundImage = gradient + 'url("' + imageUrls[currentImageIndex] + '")';
        
        currentImageIndex = (currentImageIndex + 1) % imageUrls.length;
    }

    // Thay đổi ảnh nền lần đầu tiên
    if (imageUrls.length > 0) { // Chỉ chạy nếu có ảnh trong danh sách
        changeBackgroundImage();
        // Thiết lập thời gian tự động chuyển ảnh (ví dụ: 7 giây)
        setInterval(changeBackgroundImage, 7000); // 7000 milliseconds = 7 seconds
    } else {
        // Fallback nếu không có ảnh nào trong imageUrls (tùy chọn)
        heroElement.style.backgroundColor = '#333'; // Một màu nền mặc định
        console.warn("Không có ảnh nào được cung cấp cho slideshow hero.");
    }
});
</script>
</body> </html>