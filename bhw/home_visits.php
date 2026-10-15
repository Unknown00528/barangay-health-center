<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();
if (isset($_POST['add'])) {
verify_csrf();
$stmt=$conn->prepare("INSERT INTO home_visits (patient_id, visit_date, reason, findings, action_taken) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $_POST['patient_id'], $_POST['visit_date'], $_POST['reason'], $_POST['findings'], $_POST['action_taken']);
$stmt->execute(); header("Location: home_visits.php"); exit;
}
$patients=$conn->query("SELECT id,full_name FROM patients ORDER BY full_name");
$rows=$conn->query("SELECT h.*,p.full_name FROM home_visits h JOIN patients p ON p.id=h.patient_id ORDER BY h.id DESC");
include "includes/header.php"; include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between mb-3">
        <h2>Home Visits</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add">Record Visit</button>
    </div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Reason for Visit</th>
                        <th>Clinical Findings</th>
                        <th>Treatment & Action Taken</th>
                    </tr>
            </thead>
        <tbody>
            <?php while($r=$rows->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($r['full_name']); ?>
                </td>
            <td>
                <?php echo $r['visit_date']; ?>
            </td>
        <td>
            <?php echo htmlspecialchars($r['reason'] ?: '-'); ?>
        </td>
    <td>
        <?php echo htmlspecialchars($r['findings'] ?: '-'); ?>
    </td>
<td>
    <?php echo htmlspecialchars($r['action_taken'] ?: '-'); ?>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Record Home Visit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                    </button>
            </div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Patient</label>
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
    <label class="form-label fw-semibold">Date of Visit</label>
    <input type="date" name="visit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
</div>
<div class="mb-3">
    <label class="form-label fw-semibold">Reason for Visit (Layunin / Dahilan ng Pagbisita)</label>
    <textarea name="reason" class="form-control" placeholder="hal. Routine maternal checkup, bedridden patient monitoring, post-hospitalization visit..."></textarea>
</div>
<div class="mb-3">
    <label class="form-label fw-semibold">Clinical Findings (Klinikal na Obserbasyon at Pagsusuri)</label>
    <textarea name="findings" class="form-control" placeholder="hal. Vital signs, kalagayan sa tahanan, kalusugan ng pasyente..."></textarea>
</div>
<div class="mb-3">
    <label class="form-label fw-semibold">Treatment & Action Taken (Lunas, Payo, o Isinagawang Aksyon)</label>
    <textarea name="action_taken" class="form-control" placeholder="hal. Pagbibigay ng gamot, health teaching, pag-iskedyul ng clinic follow-up..."></textarea>
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
