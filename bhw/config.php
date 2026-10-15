<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "root", "", "bhw_assist");
if ($conn->connect_error) {
die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS login_history (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
username VARCHAR(50) NOT NULL,
login_at DATETIME NOT NULL,
logout_at DATETIME NULL,
ip_address VARCHAR(45),
role VARCHAR(20) NOT NULL,
INDEX idx_login_at (login_at),
INDEX idx_login_username (username),
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$conn->query("ALTER TABLE patients ADD COLUMN IF NOT EXISTS first_name VARCHAR(50) NULL AFTER full_name");
$conn->query("ALTER TABLE patients ADD COLUMN IF NOT EXISTS last_name VARCHAR(50) NULL AFTER first_name");
$conn->query("ALTER TABLE patients ADD COLUMN IF NOT EXISTS house_no VARCHAR(50) NULL AFTER address");
$conn->query("ALTER TABLE patients ADD COLUMN IF NOT EXISTS street VARCHAR(100) NULL AFTER house_no");
$conn->query("ALTER TABLE patients ADD COLUMN IF NOT EXISTS purok VARCHAR(100) NULL AFTER street");
$bhw_hash = '$2y$10$X9WnE9.9kK9UHta72OuHmep3CG8lmwGIeuc2bP4KT2aDzXXjKdmGW';
$seed = $conn->prepare("INSERT INTO users (username, password, role) VALUES ('bhw', ?, 'BHW') ON DUPLICATE KEY UPDATE username = username");
$seed->bind_param("s", $bhw_hash);
$seed->execute();

if (session_status() !== PHP_SESSION_ACTIVE) {
session_set_cookie_params([
'httponly' => true,
'samesite' => 'Lax',
'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);
session_start();
}
?>
