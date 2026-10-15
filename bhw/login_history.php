<?php
require_once "config.php";
require_once "includes/auth.php";
require_admin();

$search = trim($_GET['search'] ?? '');
$date = $_GET['date'] ?? '';
$sql = "SELECT username, role, login_at, logout_at, ip_address FROM login_history WHERE (username LIKE ? OR role LIKE ?)";
$params = ["%{$search}%", "%{$search}%"];
$types = 'ss';
if ($date !== '') { $sql .= " AND DATE(login_at) = ?"; $params[] = $date; $types .= 's'; }
$sql .= " ORDER BY login_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result();
include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="mb-3">
        <h2 class="fw-bold mb-1">Login History</h2>
        <p class="text-muted mb-0">Administrator audit trail for account access</p>
    </div>
<div class="card shadow-sm">
    <div class="card-body">
        <form class="row g-2 mb-3">
            <div class="col-md-5">
                <input name="search" class="form-control" placeholder="Search username or role" value="<?php echo e($search); ?>">
            </div>
        <div class="col-md-3">
            <input type="date" name="date" class="form-control" value="<?php echo e($date); ?>">
        </div>
    <div class="col-auto">
        <button class="btn btn-outline-success">
            <i class="bi bi-search">
            </i> Search</button>
</div>
<div class="col-auto">
    <a href="login_history.php" class="btn btn-outline-secondary">Clear</a>
</div>
</form>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Login</th>
                <th>Logout</th>
                <th>IP Address</th>
            </tr>
    </thead>
<tbody>
    <?php while ($row = $rows->fetch_assoc()): ?>
    <tr>
        <td>
            <?php echo e($row['username']); ?>
        </td>
    <td>
        <span class="badge bg-secondary">
            <?php echo e($row['role']); ?>
        </span>
</td>
<td>
    <?php echo e($row['login_at']); ?>
</td>
<td>
    <?php echo e($row['logout_at'] ?: 'Active session'); ?>
</td>
<td>
    <?php echo e($row['ip_address']); ?>
</td>
</tr>
<?php endwhile; if ($rows->num_rows === 0): ?>
<tr>
    <td colspan="5" class="text-center text-muted py-4">No login history found.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<?php include "includes/footer.php"; ?>
