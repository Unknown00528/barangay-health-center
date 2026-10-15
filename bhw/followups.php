<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();
if (isset($_POST['add'])) {
verify_csrf();
$stmt=$conn->prepare("INSERT INTO followups (patient_id,followup_date,reason,status,notes) VALUES (?,?,?,?,?)");
$stmt->bind_param("issss", $_POST['patient_id'], $_POST['followup_date'], $_POST['reason'], $_POST['status'], $_POST['notes']);
$stmt->execute(); header("Location: followups.php"); exit;
}
if (isset($_POST['update_status'])) {
verify_csrf();
$followup_id = (int)($_POST['followup_id'] ?? 0);
$status = $_POST['status'] ?? 'Pending';
if (in_array($status, ['Pending', 'Completed', 'Cancelled'], true)) {
$stmt = $conn->prepare("UPDATE followups SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $followup_id);
$stmt->execute();
}
header("Location: followups.php"); exit;
}
$patients=$conn->query("SELECT id,full_name FROM patients ORDER BY full_name");
$rows=$conn->query("SELECT f.*,p.full_name, CASE WHEN f.status = 'Pending' AND f.followup_date < CURDATE() THEN 'Overdue' ELSE f.status END AS display_status FROM followups f JOIN patients p ON p.id=f.patient_id ORDER BY f.followup_date ASC");
include "includes/header.php"; include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between mb-3">
        <h2>Follow-ups</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add">Add Follow-up</button>
    </div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
            </thead>
        <tbody>
            <?php while($r=$rows->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($r['full_name']); ?>
                </td>
            <td>
                <?php echo $r['followup_date']; ?>
            </td>
        <td>
            <?php echo htmlspecialchars($r['reason']); ?>
        </td>
    <td>
        <span class="badge bg-<?php echo $r['display_status']=='Completed'?'success':($r['display_status']=='Overdue'?'danger':'warning'); ?>">
            <?php echo htmlspecialchars($r['display_status']); ?>
        </span>
</td>
<td>
    <?php echo htmlspecialchars($r['notes']); ?>
</td>
<td>
    <button class="btn btn-sm btn-outline-primary edit-status" data-bs-toggle="modal" data-bs-target="#editStatus" data-id="<?php echo (int)$r['id']; ?>" data-status="<?php echo htmlspecialchars($r['status']); ?>" title="Edit status">
        <i class="bi bi-pencil"></i>
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
<div class="modal fade" id="add">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5>Add Follow-up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                    </button>
            </div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <div class="mb-3">
                <label>Patient</label>
                <select name="patient_id" class="form-select" required>
                    <option value="">Select patient</option>
                    <?php while($p=$patients->fetch_assoc()): ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['full_name']); ?>
                    </option>
            <?php endwhile; ?>
        </select>
</div>
<div class="mb-3">
    <label>Follow-up Date</label>
    <input type="date" name="followup_date" class="form-control" required>
</div>
<div class="mb-3">
    <label>Reason</label>
    <textarea name="reason" class="form-control">
    </textarea>
</div>
<div class="mb-3">
    <label>Status</label>
    <select name="status" class="form-select">
        <option>Pending</option>
        <option>Completed</option>
    </select>
</div>
<div class="mb-3">
    <label>Notes</label>
    <textarea name="notes" class="form-control">
    </textarea>
</div>
</div>
<div class="modal-footer">
    <button name="add" class="btn btn-success">Save</button>
</div>
</form>
</div>
</div>
</div>
<div class="modal fade" id="editStatus">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5>Edit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="followup_id" id="editFollowupId">
                    <label class="form-label">Status</label>
                    <select name="status" id="editFollowupStatus" class="form-select" required>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.edit-status').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('editFollowupId').value = button.dataset.id;
        document.getElementById('editFollowupStatus').value = button.dataset.status;
    });
});
</script>
<?php include "includes/footer.php"; ?>
