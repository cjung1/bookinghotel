<?php
session_start();
include 'includes/config.php';
$pageTitle = 'Kết quả tìm kiếm';
include 'includes/header.php';

// Xử lý tham số tìm kiếm
$destination = htmlspecialchars($_GET['destination'] ?? '');
$checkin = htmlspecialchars($_GET['checkin'] ?? date('Y-m-d'));
$checkout = htmlspecialchars($_GET['checkout'] ?? date('Y-m-d', strtotime('+1 day')));
$guests = isset($_GET['guests']) ? max(1, (int)$_GET['guests']) : 1;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$hotels = [];
$total = 0;

try {
    // Query chỉ chọn khách sạn có ít nhất một phòng đủ sức chứa và trống
    $sql = "SELECT
        h.id,
        h.name,
        h.location,
        h.description,
        h.image,
        h.rating,
        MIN(r.price) AS min_price
    FROM hotels h
    JOIN rooms r ON h.id = r.hotel_id
    LEFT JOIN booking_rooms br ON r.id = br.room_id
    LEFT JOIN bookings b ON br.booking_id = b.id
    WHERE
        (h.name LIKE :destination OR h.location LIKE :destination)
        AND r.capacity >= :guests
        AND (
            b.id IS NULL OR
            NOT (
                b.checkin_date < :checkout AND
                b.checkout_date > :checkin
            )
        )
    GROUP BY h.id
    ORDER BY h.rating DESC
    LIMIT :perPage OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':destination', "%$destination%", PDO::PARAM_STR);
    $stmt->bindValue(':checkin', $checkin, PDO::PARAM_STR);
    $stmt->bindValue(':checkout', $checkout, PDO::PARAM_STR);
    $stmt->bindValue(':guests', $guests, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Đếm tổng số khách sạn phù hợp
    $countSql = "SELECT COUNT(DISTINCT h.id)
                     FROM hotels h
                     JOIN rooms r ON h.id = r.hotel_id
                     LEFT JOIN booking_rooms br ON r.id = br.room_id
                     LEFT JOIN bookings b ON br.booking_id = b.id
                     WHERE (h.name LIKE :destination OR h.location LIKE :destination)
                     AND r.capacity >= :guests
                     AND (
                         b.id IS NULL OR
                         NOT (
                             b.checkin_date < :checkout AND
                             b.checkout_date > :checkin
                         )
                     )";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([
        ':destination' => "%$destination%",
        ':checkin' => $checkin,
        ':checkout' => $checkout,
        ':guests' => $guests
    ]);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $perPage);

} catch(PDOException $e) {
    error_log("Lỗi tìm kiếm: " . $e->getMessage());
    die("<div class='error'>Có lỗi xảy ra khi tìm kiếm. Vui lòng thử lại sau.</div>");
}

function buildQueryString($page) {
    $params = [
        'destination' => $_GET['destination'],
        'checkin' => $_GET['checkin'],
        'checkout' => $_GET['checkout'],
        'guests' => $_GET['guests'],
        'page' => $page
    ];
    return http_build_query($params);
}
?>

<section class="search-results">
    <div class="search-meta">
        <h2>
            <?php if ($total > 0): ?>
                <?= number_format($total) ?> kết quả cho "<?= htmlspecialchars($destination) ?>"
            <?php else: ?>
                Không có khách sạn nào phù hợp với yêu cầu của bạn.
            <?php endif; ?>
        </h2>
        <div class="search-filters">
            <span>Ngày nhận phòng: <?= date('d/m/Y', strtotime($checkin)) ?></span>
            <span>Ngày trả phòng: <?= date('d/m/Y', strtotime($checkout)) ?></span>
            <span>Số khách: <?= htmlspecialchars($guests) ?></span>
        </div>
    </div>

    <?php if (!empty($hotels)): ?>
        <div class="hotel-grid">
            <?php foreach($hotels as $hotel):
                $imagePath = "assets/images/" . $hotel['image'];
                if (file_exists($imagePath)): ?>
                    <div class="hotel-card">
                        <div class="hotel-image">
                            <img src="<?= htmlspecialchars($imagePath) ?>"
                                 alt="<?= htmlspecialchars($hotel['name']) ?>">
                            <div class="hotel-rating">
                                <i class="fas fa-star"></i>
                                <?= number_format($hotel['rating'], 1) ?>
                            </div>
                        </div>
                        <div class="hotel-info">
                            <h3>
                                <a href="detail.php?id=<?= urlencode($hotel['id']) ?>&checkin_date=<?= urlencode($checkin) ?>&checkout_date=<?= urlencode($checkout) ?>&guests=<?= urlencode($guests) ?>">
                                    <?= htmlspecialchars($hotel['name']) ?>
                                </a>
                            </h3>
                            <div class="hotel-meta">
                                <span class="location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($hotel['location']) ?>
                                </span>
                            </div>
                            <div class="price-from">
                                Từ <?= number_format($hotel['min_price'], 0, ',', '.') ?>đ/đêm
                            </div>
                            <a href="detail.php?id=<?= urlencode($hotel['id']) ?>&checkin_date=<?= urlencode($checkin) ?>&checkout_date=<?= urlencode($checkout) ?>&guests=<?= urlencode($guests) ?>" class="book-btn">Xem chi tiết</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hotel-card">
                        <div class="hotel-image">
                             <img src="assets/images/default.jpg" alt="<?= htmlspecialchars($hotel['name']) ?>">
                            <div class="hotel-rating">
                                <i class="fas fa-star"></i>
                                <?= number_format($hotel['rating'], 1) ?>
                            </div>
                        </div>
                        <div class="hotel-info">
                            <h3>
                                <a href="detail.php?id=<?= urlencode($hotel['id']) ?>&checkin_date=<?= urlencode($checkin) ?>&checkout_date=<?= urlencode($checkout) ?>&guests=<?= urlencode($guests) ?>">
                                    <?= htmlspecialchars($hotel['name']) ?>
                                </a>
                            </h3>
                            <div class="hotel-meta">
                                <span class="location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($hotel['location']) ?>
                                </span>
                            </div>
                            <div class="price-from">
                                Từ <?= number_format($hotel['min_price'], 0, ',', '.') ?>đ/đêm
                            </div>
                            <a href="detail.php?id=<?= urlencode($hotel['id']) ?>&checkin_date=<?= urlencode($checkin) ?>&checkout_date=<?= urlencode($checkout) ?>&guests=<?= urlencode($guests) ?>" class="book-btn">Xem chi tiết</a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if($totalPages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="search.php?<?= buildQueryString($page - 1) ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <a class="<?= $i === $page ? 'active' : '' ?>"
                   href="search.php?<?= buildQueryString($i) ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if($page < $totalPages): ?>
                <a href="search.php?<?= buildQueryString($page + 1) ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p class="no-results">Không có khách sạn nào phù hợp với yêu cầu của bạn.</p>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>