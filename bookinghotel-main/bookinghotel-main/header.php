
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'CONANDOYCLEHOTELBOOKING' ?></title>
    <link rel="stylesheet" href="assets/css/style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> </head>
<body>
<header class="site-header-layout2"> <div class="header-row-logo"> <a href="index.php" class="logo-main-link">
            <h1 class="logo-text-main">CONANDOYCLEHOTELKING</h1>
        </a>
    </div>

    <nav class="header-row-nav"> <div class="nav-left-section">
            <a href="index.php" class="nav-item-main">
                <i class="fas fa-home"></i>
                <span class="nav-item-text-main">Trang chủ</span>
            </a>
        </div>

        <div class="nav-right-section"> <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="loginregister.php?tab=register" class="nav-item-main">
                    <i class="fas fa-user-plus"></i>
                    <span class="nav-item-text-main">Đăng ký</span>
                </a>
                <a href="loginregister.php?tab=login" class="nav-item-main">
                    <i class="fas fa-sign-in-alt"></i>
                    <span class="nav-item-text-main">Đăng nhập</span>
                </a>
            <?php else: ?>
                <div class="user-actions-group"> <a href="my_bookings.php" class="nav-item-main user-action-link bookings-link" title="Đơn hàng của tôi">
                        <i class="fas fa-receipt"></i>
                        <span class="user-action-link-text">Đơn hàng</span>
                    </a>
                    <a href="my_profile.php" class="nav-item-main user-action-link user-profile-link" title="Tài khoản của tôi">
                        <i class="fas fa-user"></i>
                        <span class="user-action-link-text user-name-display"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </a>
                    <a href="logout.php" class="nav-item-main user-action-link logout-link" title="Đăng xuất">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="user-action-link-text">Đăng xuất</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
</header>
<div class="container content">