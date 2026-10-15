<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();
if (isset($_POST['add'])) {
verify_csrf();
$stmt=$conn->prepare("INSERT INTO vaccinations (patient_id,vaccine,dose,vaccination_date,next_date) VALUES (?,?,?,?,?)");
$stmt->bind_param("issss", $_POST['patient_id'], $_POST['vaccine'], $_POST['dose'], $_POST['vaccination_date'], $_POST['next_date']);
$stmt->execute(); header("Location: vaccinations.php"); exit;
}
$patients=$conn->query("SELECT id,full_name FROM patients ORDER BY full_name");
$rows=$conn->query("SELECT v.*,p.full_name FROM vaccinations v JOIN patients p ON p.id=v.patient_id ORDER BY v.id DESC");
include "includes/header.php"; include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between mb-3">
        <h2>Vaccinations</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add">Add Vaccination</button>
    </div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Vaccine</th>
                        <th>Dose</th>
                        <th>Date</th>
                        <th>Next Date</th>
                    </tr>
            </thead>
        <tbody>
            <?php while($r=$rows->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($r['full_name']); ?>
                </td>
            <td>
                <?php echo htmlspecialchars($r['vaccine']); ?>
            </td>
        <td>
            <?php echo htmlspecialchars($r['dose']); ?>
        </td>
    <td>
        <?php echo $r['vaccination_date']; ?>
    </td>
<td>
    <?php echo $r['next_date']; ?>
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
                    <h5>Add Vaccination</h5>
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
    <label>Vaccine</label>
    <input name="vaccine" class="form-control" required>
</div>
<div class="mb-3">
    <label>Dose</label>
    <input name="dose" class="form-control">
</div>
<div class="mb-3">
    <label>Vaccination Date</label>
    <input type="date" name="vaccination_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
</div>
<div class="mb-3">
    <label>Next Date</label>
    <input type="date" name="next_date" class="form-control">
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
