<?php
require_once "config.php";
require_once "includes/auth.php";
require_admin();

$message = '';
$error = '';
if (isset($_POST['save_worker'])) {
verify_csrf();
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'BHW';
if ($username === '' || ($password === '' && empty($_POST['id']))) {
$error = 'Username and password are required for new accounts.';
} elseif (!in_array($role, ['Admin', 'BHW'], true)) {
$error = 'Invalid role selected.';
} else {
$id = (int)($_POST['id'] ?? 0);
if ($id > 0 && $password !== '') {
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
$stmt->bind_param('sssi', $username, $hash, $role, $id);
} elseif ($id > 0) {
$stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
$stmt->bind_param('ssi', $username, $role, $id);
} else {
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $username, $hash, $role);
}
try { $stmt->execute(); $message = 'BHW account saved successfully.'; } catch (Throwable $exception) { $error = 'Unable to save account. Username may already exist.'; }
}
}
$workers = $conn->query("SELECT id, username, role FROM users ORDER BY username");
include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between mb-3">
        <div>
            <h2 class="fw-bold mb-1">BHW Workers</h2>
            <p class="text-muted mb-0">Manage authorized system accounts</p>
        </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#workerModal">
        <i class="bi bi-person-plus me-1">
        </i>Add Account</button>
</div>
<?php if ($message): ?>
<div class="alert alert-success">
    <?php echo e($message); ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
    <?php echo e($error); ?>
</div>
<?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
            </thead>
        <tbody>
            <?php while ($row = $workers->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php echo (int)$row['id']; ?>
                </td>
            <td>
                <?php echo e($row['username']); ?>
            </td>
        <td>
            <span class="badge bg-<?php echo $row['role'] === 'Admin' ? 'danger' : 'success'; ?>">
                <?php echo e($row['role']); ?>
            </span>
    </td>
<td>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#workerModal" data-id="<?php echo (int)$row['id']; ?>" data-username="<?php echo e($row['username']); ?>" data-role="<?php echo e($row['role']); ?>">
        <i class="bi bi-pencil">
        </i>
</button>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<div class="modal fade" id="workerModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">BHW Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                    </button>
            </div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="id" id="workerId">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input name="username" id="workerUsername" class="form-control" required>
            </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Required for new accounts">
        </div>
    <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" id="workerRole" class="form-select">
            <option>BHW</option>
            <option>Admin</option>
        </select>
</div>
</div>
<div class="modal-footer">
    <button name="save_worker" class="btn btn-success">Save Account</button>
</div>
</form>
</div>
</div>
</div>
<script>document.querySelectorAll('[data-bs-target="#workerModal"]').forEach(function (button) { button.addEventListener('click', function () { document.getElementById('workerId').value = button.dataset.id || ''; document.getElementById('workerUsername').value = button.dataset.username || ''; document.getElementById('workerRole').value = button.dataset.role || 'BHW'; }); });</script>
<?php include "includes/footer.php"; ?>
