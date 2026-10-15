<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();
if (isset($_POST['add'])) {
verify_csrf();
$stmt=$conn->prepare("INSERT INTO medicines (patient_id,medicine_name,quantity,distribution_date,purpose) VALUES (?,?,?,?,?)");
$stmt->bind_param("isiss", $_POST['patient_id'], $_POST['medicine_name'], $_POST['quantity'], $_POST['distribution_date'], $_POST['purpose']);
$stmt->execute(); header("Location: medicines.php"); exit;
}
$patients=$conn->query("SELECT id,full_name FROM patients ORDER BY full_name");
$rows=$conn->query("SELECT m.*,p.full_name FROM medicines m JOIN patients p ON p.id=m.patient_id ORDER BY m.id DESC");
include "includes/header.php"; include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between mb-3">
        <h2>Medicine Distribution</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add">Distribute Medicine</button>
    </div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Medicine</th>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>Purpose</th>
                    </tr>
            </thead>
        <tbody>
            <?php while($r=$rows->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($r['full_name']); ?>
                </td>
            <td>
                <?php echo htmlspecialchars($r['medicine_name']); ?>
            </td>
        <td>
            <?php echo $r['quantity']; ?>
        </td>
    <td>
        <?php echo $r['distribution_date']; ?>
    </td>
<td>
    <?php echo htmlspecialchars($r['purpose']); ?>
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
                    <h5>Distribute Medicine</h5>
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
    <label>Medicine</label>
    <input name="medicine_name" class="form-control" required>
</div>
<div class="mb-3">
    <label>Quantity</label>
    <input type="number" name="quantity" class="form-control" min="1" required>
</div>
<div class="mb-3">
    <label>Date</label>
    <input type="date" name="distribution_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
</div>
<div class="mb-3">
    <label>Purpose</label>
    <input name="purpose" class="form-control">
</div>
</div>
<div class="modal-footer">
    <button name="add" class="btn btn-success">Save</button>
</div>
</form>
</div>
</div>
</div>
<?php include "includes/footer.php"; ?>
