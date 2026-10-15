<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();

$message = '';
$error = '';
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

if (isset($_POST['add'])) {
    verify_csrf();
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $consultation_date = trim($_POST['consultation_date'] ?? date('Y-m-d'));
    $complaint = trim($_POST['complaint'] ?? '');
    $assessment = trim($_POST['assessment'] ?? '');
    $action_taken = trim($_POST['action_taken'] ?? '');
    $followup_date = !empty($_POST['followup_date']) ? trim($_POST['followup_date']) : null;

    if ($patient_id <= 0 || $complaint === '') {
        $error = 'Paki-pili ang pasyente at ilagay ang Chief Complaint (Pangunahing Reklamo/Sintomas).';
    } else {
        $stmt = $conn->prepare("INSERT INTO consultations (patient_id, consultation_date, complaint, assessment, action_taken, followup_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $patient_id, $consultation_date, $complaint, $assessment, $action_taken, $followup_date);
        $stmt->execute();

        // If follow-up date is provided, optionally create follow-up reminder if followups table exists
        if (!empty($followup_date)) {
            $f_reason = "Follow-up for: " . substr($complaint, 0, 100);
            $f_notes = "Consultation on $consultation_date";
            $f_status = 'Pending';
            $f_stmt = $conn->prepare("INSERT INTO followups (patient_id, followup_date, reason, status, notes) VALUES (?, ?, ?, ?, ?)");
            $f_stmt->bind_param("issss", $patient_id, $followup_date, $f_reason, $f_status, $f_notes);
            $f_stmt->execute();
        }

        $_SESSION['flash_message'] = 'Matagumpay na naitala ang bagong clinical consultation.';
        header("Location: consultations.php");
        exit;
    }
}

$patients = $conn->query("SELECT id, patient_no, full_name FROM patients ORDER BY full_name");
$rows = $conn->query("SELECT c.*, p.patient_no, p.full_name FROM consultations c JOIN patients p ON p.id=c.patient_id ORDER BY c.consultation_date DESC, c.id DESC");

include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">Consultations</h2>
            <p class="text-muted mb-0">Record and manage clinical checkups and patient consultations</p>
        </div>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add">
            <i class="bi bi-plus-circle me-1"></i>New Consultation
        </button>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?php echo e($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="py-3 px-3">Patient</th>
                            <th class="py-3 px-3">Date</th>
                            <th class="py-3 px-3">Chief Complaint</th>
                            <th class="py-3 px-3">Clinical Assessment</th>
                            <th class="py-3 px-3">Treatment & Action Taken</th>
                            <th class="py-3 px-3">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = $rows->fetch_assoc()): ?>
                        <tr>
                            <td class="py-3 px-3">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['full_name']); ?></div>
                                <span class="badge bg-light text-secondary border font-monospace small">
                                    <?php echo htmlspecialchars($r['patient_no']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-nowrap">
                                <i class="bi bi-calendar3 me-1 text-muted"></i><?php echo htmlspecialchars($r['consultation_date']); ?>
                            </td>
                            <td class="py-3 px-3" style="max-width: 250px;">
                                <span class="text-dark fw-medium"><?php echo nl2br(htmlspecialchars($r['complaint'] ?: '-')); ?></span>
                            </td>
                            <td class="py-3 px-3" style="max-width: 280px;">
                                <span class="text-secondary"><?php echo nl2br(htmlspecialchars($r['assessment'] ?: '-')); ?></span>
                            </td>
                            <td class="py-3 px-3" style="max-width: 280px;">
                                <?php if (!empty($r['action_taken'])): ?>
                                <span class="text-success-emphasis fw-medium"><?php echo nl2br(htmlspecialchars($r['action_taken'])); ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-nowrap">
                                <?php if (!empty($r['followup_date'])): ?>
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle">
                                    <i class="bi bi-clock-history me-1"></i><?php echo htmlspecialchars($r['followup_date']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; if ($rows->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-heart-pulse fs-1 d-block mb-2 text-secondary"></i>
                                Walang nakatalang clinical consultations.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Consultation -->
<div class="modal fade" id="add" tabindex="-1" aria-labelledby="addConsultationLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="consultations.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addConsultationLabel">
                        <i class="bi bi-heart-pulse-fill text-success me-2"></i>New Clinical Consultation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Patient (Pasyente) <span class="text-danger">*</span></label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">-- Piliin ang Pasyente --</option>
                                <?php while ($p = $patients->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['patient_no']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Consultation Date <span class="text-danger">*</span></label>
                            <input type="date" name="consultation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clipboard2-pulse me-1 text-danger"></i>Chief Complaint (Pangunahing Reklamo / Sintomas) <span class="text-danger">*</span>
                            </label>
                            <textarea name="complaint" class="form-control" rows="2" placeholder="hal. Pananakit ng ulo, mataas na lagnat, ubo na may plema, pagsusuka..." required></textarea>
                            <div class="form-text text-muted">Ang pangunahing idinaing o naramdamang sintomas ng pasyente.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-activity me-1 text-primary"></i>Clinical Assessment (Klinikal na Pagsusuri / Diagnosis)
                            </label>
                            <textarea name="assessment" class="form-control" rows="2" placeholder="hal. BP: 120/80, Temp: 38.2°C. Paunang obserbasyon: Posibleng Acute Viral Infection o Sinusitis..."></textarea>
                            <div class="form-text text-muted">Pagsusuri sa vital signs, pisikal na kondisyon, o diagnosis ng BHW/Health Worker.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-prescription2 me-1 text-success"></i>Treatment & Action Taken (Lunas, Gamot, at Interbensyon)
                            </label>
                            <textarea name="action_taken" class="form-control" rows="2" placeholder="hal. Binigyan ng Paracetamol 500mg (1 tab q4h), pinayuhan ng oral hydration at bed rest, referral sa doktor kung lumala..."></textarea>
                            <div class="form-text text-muted">Gamot na ibinigay, payong medikal, lunas, o referral na isinagawa.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Follow-up Date (Petsa ng Balik-Checkup)</label>
                            <input type="date" name="followup_date" class="form-control">
                            <div class="form-text text-muted">Opsyonal: Petsa kung kailan babalik ang pasyente para sa monitoring.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add" class="btn btn-success">
                        <i class="bi bi-save me-1"></i>Save Consultation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
