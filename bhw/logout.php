<?php
require_once "config.php";

if (!empty($_SESSION['login_history_id'])) {
$stmt = $conn->prepare("UPDATE login_history SET logout_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $_SESSION['login_history_id']);
$stmt->execute();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header("Location: login.php");
exit;
?>
