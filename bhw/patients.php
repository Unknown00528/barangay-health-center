<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();

// 1. Download CSV Template Handler
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="patient_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['First Name', 'Last Name', 'Age', 'Sex', 'House No', 'Street', 'Purok', 'Address', 'Contact Number']);
    fputcsv($out, ['Juan', 'Dela Cruz', '35', 'Male', 'Blk 5 Lot 3', 'Mabini Street', 'Purok 1', 'Barangay Proper', '09171234567']);
    fputcsv($out, ['Maria', 'Santos', '28', 'Female', '', 'Rizal Avenue', 'Purok 3', 'Riverside', '09289876543']);
    fputcsv($out, ['Pedro', 'Reyes', '62', 'Male', '', '', 'Purok 2', 'San Jose', '09395551234']);
    fclose($out);
    exit;
}

$message = '';
$error = '';
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 2. Add Patient Handler
if (isset($_POST['add_patient'])) {
    verify_csrf();
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $full_name = trim("{$first_name} {$last_name}");
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
        $duplicate = $conn->prepare("SELECT id FROM patients WHERE LOWER(full_name) = LOWER(?) AND age = ? AND COALESCE(address, '') = ? LIMIT 1");
        $duplicate->bind_param("sis", $full_name, $age, $address);
        $duplicate->execute();
        if ($duplicate->get_result()->num_rows > 0) {
            $error = 'Mayroon nang pasyente na may parehong pangalan, edad, at tirahan sa talaan.';
        } else {
            $conn->begin_transaction();
            try {
                $temporary_patient_no = uniqid('TEMP-', true);
                $stmt = $conn->prepare("INSERT INTO patients (patient_no, full_name, first_name, last_name, age, sex, address, house_no, barangay, purok, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssissssss", $temporary_patient_no, $full_name, $first_name, $last_name, $age, $sex, $address, $house_no, $barangay, $purok, $contact);
                $stmt->execute();

                $patient_id = $conn->insert_id;
                $patient_no = sprintf('PAT-%06d', $patient_id);
                $update_stmt = $conn->prepare("UPDATE patients SET patient_no = ? WHERE id = ?");
                $update_stmt->bind_param("si", $patient_no, $patient_id);
                $update_stmt->execute();

                $conn->commit();
                $message = "Matagumpay na nairehistro si {$full_name} ({$patient_no}).";
            } catch (Throwable $exception) {
                $conn->rollback();
                $error = 'Hindi maipasa ang talaan ng pasyente: ' . $exception->getMessage();
            }
        }
    }
}

// 3. Import Patients CSV Handler
if (isset($_POST['import_patients'])) {
    verify_csrf();
    if (empty($_FILES['patient_file']['name']) || $_FILES['patient_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Paki-pili ang CSV file na nais i-import.';
    } else {
        $file_tmp = $_FILES['patient_file']['tmp_name'];
        $handle = fopen($file_tmp, 'r');
        if (!$handle) {
            $error = 'Hindi mabuksan ang na-upload na CSV file.';
        } else {
            // Read header
            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                $error = 'Walang laman ang na-upload na CSV file.';
            } else {
                $col_first = null;
                $col_last = null;
                $col_full = null;
                $col_age = null;
                $col_sex = null;
                $col_address = null;
                $col_contact = null;
                $col_patient_no = null;
                $col_house_no = null;
                $col_barangay = null;
                $col_purok = null;

                foreach ($header as $idx => $raw_col) {
                    $c = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string)$raw_col)));
                    if (in_array($c, ['firstname', 'first', 'pangalan'], true)) $col_first = $idx;
                    elseif (in_array($c, ['lastname', 'last', 'apelyido'], true)) $col_last = $idx;
                    elseif (in_array($c, ['fullname', 'name', 'buongpangalan'], true)) $col_full = $idx;
                    elseif (in_array($c, ['age', 'edad'], true)) $col_age = $idx;
                    elseif (in_array($c, ['sex', 'gender', 'kasarian'], true)) $col_sex = $idx;
                    elseif (in_array($c, ['address', 'tirahan', 'lugar'], true)) $col_address = $idx;
                    elseif (in_array($c, ['houseno', 'housenumber', 'housenum', 'blk', 'block', 'lot', 'houseno'], true)) $col_house_no = $idx;
                    elseif (in_array($c, ['barangay', 'kalye', 'daan'], true)) $col_barangay = $idx;
                    elseif (in_array($c, ['purok', 'sitio', 'sityo', 'zone', 'zona'], true)) $col_purok = $idx;
                    elseif (in_array($c, ['contact', 'contactnumber', 'contactno', 'phone', 'mobile', 'cel', 'numero'], true)) $col_contact = $idx;
                    elseif (in_array($c, ['patientno', 'patientid', 'id'], true)) $col_patient_no = $idx;
                }

                $imported_count = 0;
                $duplicate_count = 0;
                $invalid_count = 0;

                $conn->begin_transaction();
                try {
                    while (($row = fgetcsv($handle)) !== false) {
                        // Skip blank row
                        if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) continue;

                        $f_first = $col_first !== null ? trim((string)($row[$col_first] ?? '')) : '';
                        $f_last = $col_last !== null ? trim((string)($row[$col_last] ?? '')) : '';
                        $f_full = $col_full !== null ? trim((string)($row[$col_full] ?? '')) : '';

                        if ($f_first !== '' || $f_last !== '') {
                            if ($f_full === '') $f_full = trim("{$f_first} {$f_last}");
                        } elseif ($f_full !== '') {
                            $parts = explode(' ', $f_full);
                            $f_first = array_shift($parts);
                            $f_last = implode(' ', $parts);
                        } else {
                            $invalid_count++;
                            continue;
                        }

                        $raw_age = $col_age !== null ? trim((string)($row[$col_age] ?? '')) : '';
                        if ($raw_age === '' || !is_numeric($raw_age) || (int)$raw_age < 0) {
                            $invalid_count++;
                            continue;
                        }
                        $f_age = (int)$raw_age;

                        $raw_sex = $col_sex !== null ? strtolower(trim((string)($row[$col_sex] ?? ''))) : 'male';
                        $f_sex = in_array($raw_sex, ['f', 'female', 'babae'], true) ? 'Female' : 'Male';

                        $f_address = $col_address !== null ? trim((string)($row[$col_address] ?? '')) : '';
                        $f_house_no = $col_house_no !== null ? trim((string)($row[$col_house_no] ?? '')) : '';
                        $f_barangay   = $col_barangay !== null ? trim((string)($row[$col_barangay] ?? '')) : '';
                        $f_purok    = $col_purok !== null ? trim((string)($row[$col_purok] ?? '')) : '';
                        // Compose full address from sub-fields + any explicit address column
                        $parts_addr = array_filter([$f_house_no, $f_barangay, $f_purok, $f_address], fn($v) => $v !== '');
                        $f_address  = implode(', ', $parts_addr);

                        // Contact normalization and validation
                        $raw_contact = $col_contact !== null ? trim((string)($row[$col_contact] ?? '')) : '';
                        $f_contact = preg_replace('/[^0-9]/', '', $raw_contact);
                        if (str_starts_with($f_contact, '639') && strlen($f_contact) === 12) {
                            $f_contact = '0' . substr($f_contact, 2);
                        } elseif (str_starts_with($f_contact, '9') && strlen($f_contact) === 10) {
                            $f_contact = '0' . $f_contact;
                        }

                        // If contact is provided, it must be valid 11 digits starting with 09
                        if ($f_contact !== '') {
                            if (strlen($f_contact) !== 11 || !str_starts_with($f_contact, '09')) {
                                // Skip rows with invalid contacts like 123
                                $invalid_count++;
                                continue;
                            }
                        }

                        // Check duplicate
                        $dup_chk = $conn->prepare("SELECT id FROM patients WHERE LOWER(full_name) = LOWER(?) AND age = ? AND COALESCE(address, '') = ? LIMIT 1");
                        $dup_chk->bind_param("sis", $f_full, $f_age, $f_address);
                        $dup_chk->execute();
                        if ($dup_chk->get_result()->num_rows > 0) {
                            $duplicate_count++;
                            continue;
                        }

                        // Insert patient
                        $temp_no = uniqid('TEMP-', true);
                        $ins = $conn->prepare("INSERT INTO patients (patient_no, full_name, first_name, last_name, age, sex, address, house_no, barangay, purok, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->bind_param("ssssissssss", $temp_no, $f_full, $f_first, $f_last, $f_age, $f_sex, $f_address, $f_house_no, $f_barangay, $f_purok, $f_contact);
                        $ins->execute();

                        $new_id = $conn->insert_id;
                        $new_no = sprintf('PAT-%06d', $new_id);
                        $upd = $conn->prepare("UPDATE patients SET patient_no = ? WHERE id = ?");
                        $upd->bind_param("si", $new_no, $new_id);
                        $upd->execute();

                        $imported_count++;
                    }
                    fclose($handle);
                    $conn->commit();

                    $message = "Matagumpay na na-import ang {$imported_count} pasyente. (Nilaktawan: {$duplicate_count} duplicate, {$invalid_count} di-wastong talaan/contact).";
                } catch (Throwable $e) {
                    fclose($handle);
                    $conn->rollback();
                    $error = 'Nagka-aberya sa pag-import: ' . $e->getMessage();
                }
            }
        }
    }
}

// 4. Search Query: Smart Name (First, Last, Full), Patient No, and Contact Search
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $tokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    $clause_parts = [];
    $params = [];
    $types = '';

    foreach ($tokens as $token) {
        $t_like = "%{$token}%";
        $clause_parts[] = "(full_name LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR patient_no LIKE ? OR contact LIKE ?)";
        $params[] = $t_like;
        $params[] = $t_like;
        $params[] = $t_like;
        $params[] = $t_like;
        $params[] = $t_like;
        $types .= 'sssss';
    }

    $sql_where = implode(' AND ', $clause_parts);
    $stmt = $conn->prepare("SELECT * FROM patients WHERE {$sql_where} ORDER BY id DESC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $patients = $stmt->get_result();
} else {
    $patients = $conn->query("SELECT * FROM patients ORDER BY id DESC");
}

include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="fw-bold mb-1">Patients</h2>
            <p class="text-muted mb-0">Manage the barangay patient registry</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="patients.php?download_template=1" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1"></i>Template CSV
            </a>
            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>Import Patient List
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPatient">
                <i class="bi bi-person-plus me-1"></i>Add Patient
            </button>
        </div>
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
        <div class="card-body">
            <form class="row g-2 mb-3" method="GET" action="patients.php">
                <div class="col-md-8 col-lg-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" name="search" class="form-control" placeholder="Search by First Name, Last Name, Full Name, Patient ID, or Contact..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
                <?php if ($search !== ''): ?>
                <div class="col-auto">
                    <a href="patients.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>

            <?php if ($search !== ''): ?>
            <div class="mb-3">
                <span class="badge bg-light text-dark border">
                    Search Results for: "<strong><?php echo e($search); ?></strong>" (<?php echo $patients->num_rows; ?> found)
                </span>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Patient ID</th>
                            <th>Name (Pangalan at Apelyido)</th>
                            <th>Age</th>
                            <th>Sex</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $patients->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="badge bg-success-subtle text-success font-monospace">
                                    <?php echo e($row['patient_no']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?php echo e($row['full_name']); ?>
                                </div>
                                <?php if (!empty($row['first_name']) || !empty($row['last_name'])): ?>
                                <small class="text-muted">
                                    First: <span class="text-secondary"><?php echo e($row['first_name'] ?: '-'); ?></span> | 
                                    Last: <span class="text-secondary"><?php echo e($row['last_name'] ?: '-'); ?></span>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e((string)$row['age']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['sex'] === 'Female' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'; ?>">
                                    <?php echo e($row['sex'] === 'Female' ? 'Female' : 'Male'); ?>
                                </span>
                            </td>
                            <td><?php echo e($row['address'] ?: '-'); ?></td>
                            <td>
                                <?php if (!empty($row['contact'])): ?>
                                <span class="font-monospace fw-semibold text-dark">
                                    <i class="bi bi-telephone text-success me-1"></i><?php echo e($row['contact']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="patient_profile.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-success" title="View Profile">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="patient_edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Patient">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="patient_delete.php" class="d-inline" onsubmit="return confirm('Sigurado ka bang nais mong tanggalin ang pasyenteng ito?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete Patient" aria-label="Delete patient">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; if ($patients->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                Walang nahanap na pasyente<?php echo $search !== '' ? ' para sa "' . e($search) . '"' : ''; ?>.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Add Patient -->
<div class="modal fade" id="addPatient" tabindex="-1" aria-labelledby="addPatientLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="patients.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addPatientLabel">
                        <i class="bi bi-person-plus-fill text-success me-2"></i>Add New Patient
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name (Pangalan) <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="hal. Juan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name (Apelyido) <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="hal. Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Age (Edad) <span class="text-danger">*</span></label>
                            <input type="number" name="age" min="0" max="150" class="form-control" placeholder="hal. 25" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sex (Kasarian) <span class="text-danger">*</span></label>
                            <select name="sex" class="form-select" required>
                                <option value="">Select Sex</option>
                                <option value="Male">Male (Lalaki)</option>
                                <option value="Female">Female (Babae)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">House No. / Blk / Lot</label>
                            <input type="text" name="house_no" class="form-control" placeholder="hal. Blk 5 Lot 3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">barangay<span class="text-danger">*</span></label>
                            <input type="text" name="barangay" class="form-control" placeholder=" barangay Mabini" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sitio / Purok<span class="text-danger">*</span></label>
                            <input type="text" name="purok" class="form-control" placeholder="hal. Purok 3 / Sitio Malabo" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Contact Number<span class="text-danger">*</span></label>
                            <input type="tel" name="contact" id="add_contact" class="form-control font-monospace" placeholder="09XXXXXXXXX" minlength="11" pattern="09[0-9]{9}" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_patient" class="btn btn-success">
                        <i class="bi bi-save me-1"></i>Save Patient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Import Patient List -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="patients.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="importModalLabel">
                        <i class="bi bi-file-earmark-arrow-up-fill text-success me-2"></i>Import Old Patient List
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Mag-upload ng lumang listahan ng pasyente sa pamamagitan ng <strong>CSV file</strong>. Awtomatikong babasahin ang mga kolum para sa pangalan, edad, kasarian, tirahan, at contact number.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pumili ng CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="patient_file" accept=".csv,text/csv,text/plain" class="form-control" required>
                    </div>
                    <div class="card bg-light border-0 p-3 mb-2 small">
                        <div class="fw-bold mb-1"><i class="bi bi-lightbulb text-warning me-1"></i>Format ng CSV Columns:</div>
                        <code>First Name, Last Name, Age, Sex, Address, Contact Number</code>
                    </div>
                    <div class="text-end">
                        <a href="patients.php?download_template=1" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i>Download CSV Template
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="import_patients" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>
