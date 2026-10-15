<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();

$patient_id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) {
http_response_code(404);
exit('Patient not found.');
}

$consultations = $conn->prepare("SELECT * FROM consultations WHERE patient_id = ? ORDER BY consultation_date DESC, id DESC");
$consultations->bind_param("i", $patient_id);
$consultations->execute();
$consultation_rows = $consultations->get_result();

$visits = $conn->prepare("SELECT * FROM home_visits WHERE patient_id = ? ORDER BY visit_date DESC, id DESC");
$visits->bind_param("i", $patient_id);
$visits->execute();
$visit_rows = $visits->get_result();

$vaccinations = $conn->prepare("SELECT * FROM vaccinations WHERE patient_id = ? ORDER BY vaccination_date DESC, id DESC");
$vaccinations->bind_param("i", $patient_id);
$vaccinations->execute();
$vaccination_rows = $vaccinations->get_result();

$medicines = $conn->prepare("SELECT * FROM medicines WHERE patient_id = ? ORDER BY distribution_date DESC, id DESC");
$medicines->bind_param("i", $patient_id);
$medicines->execute();
$medicine_rows = $medicines->get_result();

$followups = $conn->prepare("SELECT * FROM followups WHERE patient_id = ? ORDER BY followup_date DESC, id DESC");
$followups->bind_param("i", $patient_id);
$followups->execute();
$followup_rows = $followups->get_result();

include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <a href="patients.php" class="text-success text-decoration-none">
                <i class="bi bi-arrow-left me-1">
                </i>Back to patients
        </a>
    <h2 class="fw-bold mt-2 mb-1">
        <?php echo e($patient['full_name']); ?>
    </h2>
<p class="text-muted mb-0">
    <?php echo e($patient['patient_no']); ?>
    · Registered <?php echo e(date('M d, Y', strtotime($patient['created_at']))); ?>
</p>
</div>
<a href="patient_edit.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-success">
    <i class="bi bi-pencil me-1">
    </i>Edit Patient
</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <small class="text-muted d-block">Age</small>
                <strong>
                    <?php echo (int)$patient['age']; ?> years old</strong>
            </div>
        <div class="col-md-3">
            <small class="text-muted d-block">Sex</small>
            <strong>
                <?php echo e($patient['sex'] === 'Female' ? 'Female' : 'Male'); ?>
            </strong>
        </div>
<div class="col-md-3">
    <small class="text-muted d-block">Contact</small>
    <strong>
        <?php echo e($patient['contact']) ?: 'Not provided'; ?>
    </strong>
</div>
<div class="col-md-3">
    <small class="text-muted d-block">Address</small>
    <strong>
        <?php
        $addr_parts = array_filter([
            !empty($patient['house_no']) ? 'House No: ' . e($patient['house_no']) : null,
            !empty($patient['barangay']) ? 'Street: ' . e($patient['barangay']) : null,
            !empty($patient['purok']) ? 'Sitio/Purok: ' . e($patient['purok']) : null,
        ]);
        if (!empty($addr_parts)) {
            echo implode('<br>', $addr_parts);
        } elseif (!empty($patient['address'])) {
            echo e($patient['address']);
        } else {
            echo 'Not provided';
        }
        ?>
    </strong>
</div>
</div>
</div>
</div>
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#consultations">Consultations</button>
    </li>
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#visits">Home Visits</button>
</li>
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vaccinations">Vaccinations</button>
</li>
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#medicines">Medicines</button>
</li>
<li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#followups">Follow-ups</button>
</li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="consultations">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Chief Complaint</th>
                                <th>Clinical Assessment</th>
                                <th>Treatment & Action Taken</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $consultation_rows->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo e($row['consultation_date']); ?>
                                </td>
                                <td>
                                    <?php echo nl2br(e($row['complaint'] ?: '-')); ?>
                                </td>
                                <td>
                                    <?php echo nl2br(e($row['assessment'] ?: '-')); ?>
                                </td>
                                <td>
                                    <?php echo nl2br(e($row['action_taken'] ?: '-')); ?>
                                </td>
                            </tr>
<?php endwhile; if ($consultation_rows->num_rows === 0): ?>
<tr>
    <td colspan="4" class="text-muted text-center">No consultation records.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="tab-pane fade" id="visits">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Findings</th>
                            <th>Action</th>
                        </tr>
                </thead>
            <tbody>
                <?php while ($row = $visit_rows->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php echo e($row['visit_date']); ?>
                    </td>
                <td>
                    <?php echo e($row['reason']); ?>
                </td>
            <td>
                <?php echo e($row['findings']); ?>
            </td>
        <td>
            <?php echo e($row['action_taken']); ?>
        </td>
</tr>
<?php endwhile; if ($visit_rows->num_rows === 0): ?>
<tr>
    <td colspan="4" class="text-muted text-center">No home visit records.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<div class="tab-pane fade" id="vaccinations">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Date</th>
                            <th>Next Date</th>
                        </tr>
                </thead>
            <tbody>
                <?php while ($row = $vaccination_rows->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php echo e($row['vaccine']); ?>
                    </td>
                <td>
                    <?php echo e($row['dose']); ?>
                </td>
            <td>
                <?php echo e($row['vaccination_date']); ?>
            </td>
        <td>
            <?php echo e($row['next_date']); ?>
        </td>
</tr>
<?php endwhile; if ($vaccination_rows->num_rows === 0): ?>
<tr>
    <td colspan="4" class="text-muted text-center">No vaccination records.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<div class="tab-pane fade" id="medicines">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Date</th>
                            <th>Purpose</th>
                        </tr>
                </thead>
            <tbody>
                <?php while ($row = $medicine_rows->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php echo e($row['medicine_name']); ?>
                    </td>
                <td>
                    <?php echo (int)$row['quantity']; ?>
                </td>
            <td>
                <?php echo e($row['distribution_date']); ?>
            </td>
        <td>
            <?php echo e($row['purpose']); ?>
        </td>
</tr>
<?php endwhile; if ($medicine_rows->num_rows === 0): ?>
<tr>
    <td colspan="4" class="text-muted text-center">No medicine records.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<div class="tab-pane fade" id="followups">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                </thead>
            <tbody>
                <?php while ($row = $followup_rows->fetch_assoc()): ?>
                <?php $status = $row['status'] === 'Pending' && $row['followup_date'] < date('Y-m-d') ? 'Overdue' : $row['status']; ?>
                <tr>
                    <td>
                        <?php echo e($row['followup_date']); ?>
                    </td>
                <td>
                    <?php echo e($row['reason']); ?>
                </td>
            <td>
                <span class="badge bg-<?php echo $status === 'Completed' ? 'success' : ($status === 'Overdue' ? 'danger' : 'warning'); ?>">
                    <?php echo e($status); ?>
                </span>
        </td>
    <td>
        <?php echo e($row['notes']); ?>
    </td>
</tr>
<?php endwhile; if ($followup_rows->num_rows === 0): ?>
<tr>
    <td colspan="4" class="text-muted text-center">No follow-up records.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>
<?php include "includes/footer.php"; ?>
