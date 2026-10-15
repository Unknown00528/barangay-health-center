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

$error = '';
if (isset($_POST['update_patient'])) {
    verify_csrf();
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = trim("{$first_name} {$last_name}");
    if ($full_name === '' && !empty($_POST['full_name'])) {
        $full_name = trim($_POST['full_name']);
        $parts = explode(' ', $full_name);
        $first_name = array_shift($parts);
        $last_name = implode(' ', $parts);
    }

    $age = (int)($_POST['age'] ?? 0);
    $sex = trim($_POST['sex'] ?? '');
    $house_no = trim($_POST['house_no'] ?? '');
    $barangay   = trim($_POST['barangay'] ?? '');
    $purok    = trim($_POST['purok'] ?? '');
    $address  = implode(', ', array_filter([$house_no, $barangay, $purok], fn($v) => $v !== ''));
    $contact = preg_replace('/[^0-9]/', '', trim($_POST['contact'] ?? ''));

    if ($first_name === '' || $last_name === '') {
        $error = 'Paki-kumpleto ang First Name at Last Name ng pasyente.';
    } elseif ($age < 0 || !in_array($sex, ['Male', 'Female'], true)) {
        $error = 'Paki-pili ang tamang kasarian (Male o Female lamang).';
    } elseif (strlen($contact) !== 11) {
        $error = 'Ang contact number ay dapat eksaktong 11 digits (hal. 09123456789). Hindi pinapayagan ang kulang tulad ng 123.';
    } elseif (!str_starts_with($contact, '09')) {
        $error = 'Ang contact number ay dapat magsimula sa 09 at may 11 digits (hal. 09123456789).';
    } else {
        $update = $conn->prepare("UPDATE patients SET full_name = ?, first_name = ?, last_name = ?, age = ?, sex = ?, address = ?, house_no = ?, barangay = ?, purok = ?, contact = ? WHERE id = ?");
        $update->bind_param("sssissssssi", $full_name, $first_name, $last_name, $age, $sex, $address, $house_no, $barangay, $purok, $contact, $patient_id);
        $update->execute();
        $_SESSION['flash_message'] = "Matagumpay na na-update ang impormasyon ni {$full_name}.";
        header("Location: patient_profile.php?id={$patient_id}");
        exit;
    }
}

include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="patient_profile.php?id=<?php echo $patient_id; ?>" class="text-success text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Back to profile
            </a>
            <h2 class="fw-bold mt-2">Edit Patient Details</h2>
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Patient Number</label>
                            <input class="form-control bg-light font-monospace" value="<?php echo e($patient['patient_no']); ?>" readonly>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">First Name (Pangalan) <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo e($_POST['first_name'] ?? $patient['first_name'] ?? ''); ?>" placeholder="hal. Juan" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Last Name (Apelyido) <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo e($_POST['last_name'] ?? $patient['last_name'] ?? ''); ?>" placeholder="hal. Dela Cruz" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Age (Edad) <span class="text-danger">*</span></label>
                                <input type="number" name="age" min="0" max="150" class="form-control" value="<?php echo e($_POST['age'] ?? (string)$patient['age']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sex (Kasarian) <span class="text-danger">*</span></label>
                                <select name="sex" class="form-select" required>
                                    <option value="">Select Sex</option>
                                    <option value="Male" <?php echo ($_POST['sex'] ?? $patient['sex']) === 'Male' ? 'selected' : ''; ?>>Male (Lalaki)</option>
                                    <option value="Female" <?php echo ($_POST['sex'] ?? $patient['sex']) === 'Female' ? 'selected' : ''; ?>>Female (Babae)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">House No. / Blk / Lot</label>
                                <input type="text" name="house_no" class="form-control" value="<?php echo e($_POST['house_no'] ?? $patient['house_no'] ?? ''); ?>" placeholder="hal. Blk 5 Lot 3">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Barangay</label>
                                <input type="text" name="barangay" class="form-control" value="<?php echo e($_POST['barangay'] ?? $patient['barangay'] ?? ''); ?>" placeholder="hal. Mabini Street">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Sitio / Purok</label>
                                <input type="text" name="purok" class="form-control" value="<?php echo e($_POST['purok'] ?? $patient['purok'] ?? ''); ?>" placeholder="hal. Purok 3 / Sitio Malabo">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" name="contact" class="form-control font-monospace" value="<?php echo e($_POST['contact'] ?? $patient['contact']); ?>" placeholder="09XXXXXXXXX" maxlength="11" minlength="11" pattern="09[0-9]{9}" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        </div>

                        <div class="d-flex gap-2">
                            <a href="patient_profile.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" name="update_patient" class="btn btn-success">
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include "includes/footer.php"; ?>
