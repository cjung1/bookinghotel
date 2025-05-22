<?php
session_start();

// Hủy tất cả các biến session
$_SESSION = array();

// Nếu muốn hủy luôn cả cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session
session_destroy();

// Chuyển hướng người dùng về trang chủ hoặc trang đăng nhập
header("Location: index.php"); // Hoặc header("Location: loginregister.php?tab=login");
exit();
?>