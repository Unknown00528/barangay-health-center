<?php
require_once "config.php";
require_once "includes/auth.php";

if (isset($_SESSION['user_id'])) {
header("Location: index.php");
exit;
}

$error = "";

if (isset($_POST['login'])) {
verify_csrf();
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$valid_password = $user && password_verify($password, $user['password']);
if ($user && !$valid_password && hash_equals($user['password'], $password)) {
$upgrade_hash = password_hash($password, PASSWORD_DEFAULT);
$upgrade = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$upgrade->bind_param("si", $upgrade_hash, $user['id']);
$upgrade->execute();
$valid_password = true;
}

if ($user && $valid_password) {
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$history = $conn->prepare("INSERT INTO login_history (user_id, username, login_at, ip_address, role) VALUES (?, ?, NOW(), ?, ?)");
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$history->bind_param("isss", $user['id'], $user['username'], $ip_address, $user['role']);
$history->execute();
$_SESSION['login_history_id'] = $conn->insert_id;
header("Location: index.php");
exit;
} else {
$error = "Invalid username or password.";
}
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BHW Assist - Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/style.css" rel="stylesheet">
    </head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height:100vh;">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold">🏥 BHW Assist</h2>
                            <p class="text-muted">Barangay Health Worker Management System</p>
                        </div>
                    <?php if ($error != ""): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
        <button type="submit" name="login" class="btn btn-success w-100">Login</button>
    </form>
</div>
</div>
</div>
</div>
</div>
</body>
</html>
