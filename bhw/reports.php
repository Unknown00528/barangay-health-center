<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();

$report = $_GET['report'] ?? 'patients';
$allowed = ['patients', 'consultations', 'home_visits', 'vaccinations', 'medicines', 'followups'];
if (!in_array($report, $allowed, true)) {
    $report = 'patients';
}

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
// Default to showing all dates if user hasn't explicitly set both dates
$all_dates = isset($_GET['all_dates']) ? ($_GET['all_dates'] === '1') : (empty($date_from) && empty($date_to));
$search_name = trim($_GET['search_name'] ?? '');
$is_generated = isset($_GET['generate']) || !empty($_GET);

$configs = [
    'patients' => [
        'title' => 'Patient Registry Report',
        'subtitle' => 'Opisyal na Talaan ng mga Nakarehistrong Pasyente',
        'date_col' => 'DATE(p.created_at)',
        'base_sql' => "SELECT p.patient_no AS 'Patient ID', p.full_name AS 'Full Name', p.first_name AS 'First Name', p.last_name AS 'Last Name', p.age AS Age, CASE WHEN p.sex = 'Female' THEN 'Female' ELSE 'Male' END AS Sex, p.house_no AS 'House No.', p.street AS Street, p.purok AS 'Sitio/Purok', p.address AS Address, p.contact AS Contact, DATE(p.created_at) AS 'Date Registered' FROM patients p WHERE 1=1",
        'order' => 'ORDER BY p.id DESC'
    ],
    'consultations' => [
        'title' => 'Clinical Consultations Report',
        'subtitle' => 'Opisyal na Ulat ng mga Konsultasyon at Pagsusuri',
        'date_col' => 'c.consultation_date',
        'base_sql' => "SELECT p.patient_no AS 'Patient ID', p.full_name AS 'Patient Name', c.consultation_date AS 'Consultation Date', c.complaint AS 'Chief Complaint', c.assessment AS 'Clinical Assessment', c.action_taken AS 'Treatment & Action Taken', c.followup_date AS 'Follow-up Date' FROM consultations c JOIN patients p ON p.id = c.patient_id WHERE 1=1",
        'order' => 'ORDER BY c.consultation_date DESC, c.id DESC'
    ],
    'home_visits' => [
        'title' => 'Home Visits Report',
        'subtitle' => 'Opisyal na Talaan ng Pagbisita sa Tahanan ng mga Pasyente',
        'date_col' => 'h.visit_date',
        'base_sql' => "SELECT p.patient_no AS 'Patient ID', p.full_name AS 'Patient Name', h.visit_date AS 'Visit Date', h.reason AS 'Reason for Visit', h.findings AS 'Clinical Findings', h.action_taken AS 'Treatment & Action Taken' FROM home_visits h JOIN patients p ON p.id = h.patient_id WHERE 1=1",
        'order' => 'ORDER BY h.visit_date DESC, h.id DESC'
    ],
    'vaccinations' => [
        'title' => 'Immunization & Vaccination Report',
        'subtitle' => 'Opisyal na Talaan ng Pagbabakuna at Immunization',
        'date_col' => 'v.vaccination_date',
        'base_sql' => "SELECT p.patient_no AS 'Patient ID', p.full_name AS 'Patient Name', v.vaccine AS Vaccine, v.dose AS Dose, v.vaccination_date AS 'Vaccination Date', v.next_date AS 'Next Date' FROM vaccinations v JOIN patients p ON p.id = v.patient_id WHERE 1=1",
        'order' => 'ORDER BY v.vaccination_date DESC, v.id DESC'
    ],
    'medicines' => [
        'title' => 'Medicine Distribution Report',
        'subtitle' => 'Opisyal na Talaan ng Pamamahagi ng Gamot',
        'date_col' => 'm.distribution_date',
        'base_sql' => "SELECT p.patient_no AS 'Patient ID', p.full_name AS 'Patient Name', m.medicine_name AS 'Medicine Name', m.quantity AS Quantity, m.distribution_date AS 'Distribution Date', m.purpose AS Purpose FROM medicines m JOIN patients p ON p.id = m.patient_id WHERE 1=1",
        'order' => 'ORDER BY m.distribution_date DESC, m.id DESC'
    ],
    'followups' => [
        'title' => 'Follow-up Checkups Report',
        'subtitle' => 'Opisyal na Talaan ng Balik-Tugon at Pagsubaybay',
        'date_col' => 'f.followup_date',
        'base_sql' => "SELECT p.patient_no AS 'Patient ID', p.full_name AS 'Patient Name', f.followup_date AS 'Follow-up Date', f.reason AS Reason, CASE WHEN f.status = 'Pending' AND f.followup_date < CURDATE() THEN 'Overdue' ELSE f.status END AS Status, f.notes AS Notes FROM followups f JOIN patients p ON p.id = f.patient_id WHERE 1=1",
        'order' => 'ORDER BY f.followup_date ASC, f.id DESC'
    ]
];

$config = $configs[$report];
$sql = $config['base_sql'];
$types = '';
$params = [];

// Apply Date Filter if not All Dates and dates are provided
if (!$all_dates && !empty($config['date_col']) && !empty($date_from) && !empty($date_to)) {
    $sql .= " AND ({$config['date_col']} BETWEEN ? AND ?)";
    $types .= 'ss';
    $params[] = $date_from;
    $params[] = $date_to;
}

// Apply Patient Search (First Name, Last Name, Full Name, Patient ID)
if ($search_name !== '') {
    $tokens = preg_split('/\s+/', $search_name, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($tokens as $token) {
        $t_like = "%{$token}%";
        $sql .= " AND (p.full_name LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ?)";
        $types .= 'ssss';
        $params[] = $t_like;
        $params[] = $t_like;
        $params[] = $t_like;
        $params[] = $t_like;
    }
}

$sql .= " " . $config['order'];

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result();

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = $report . '-report-' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    $fields = $rows->fetch_fields();
    fputcsv($output, array_map(fn($f) => $f->name, $fields));
    while ($row = $rows->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

include "includes/header.php";
include "includes/sidebar.php";
?>
<style>
@media print {
    .no-print, .topbar, .sidebar, #appSidebar, form, .btn {
        display: none !important;
    }
    .print-sheet {
        box-shadow: none !important;
        border: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    body, .app-shell {
        background: #fff !important;
    }
    .table th {
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
    }
}
</style>

<div class="p-4">
    <!-- Header Section (Screen Only) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <div>
            <h2 class="fw-bold mb-1">Reports Generator</h2>
            <p class="text-muted mb-0">Pumili ng ulat, mag-filter ng pasyente o petsa, at i-click ang <strong>Generate</strong> upang buuin ang opisyal na ulat.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print / Save PDF
            </button>
        </div>
    </div>

    <!-- Filter & Generator Form (Screen Only) -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body p-4">
            <form method="GET" action="reports.php" id="reportForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">1. Uri ng Report (Category)</label>
                    <select name="report" class="form-select" onchange="document.getElementById('reportForm').submit()">
                        <option value="patients" <?php echo $report === 'patients' ? 'selected' : ''; ?>>Patients Registry (Talaan ng Pasyente)</option>
                        <option value="consultations" <?php echo $report === 'consultations' ? 'selected' : ''; ?>>Consultations (Konsultasyon)</option>
                        <option value="home_visits" <?php echo $report === 'home_visits' ? 'selected' : ''; ?>>Home Visits (Pagbisita sa Bahay)</option>
                        <option value="vaccinations" <?php echo $report === 'vaccinations' ? 'selected' : ''; ?>>Vaccinations (Pagbabakuna)</option>
                        <option value="medicines" <?php echo $report === 'medicines' ? 'selected' : ''; ?>>Medicine Distribution (Pamamahagi ng Gamot)</option>
                        <option value="followups" <?php echo $report === 'followups' ? 'selected' : ''; ?>>Follow-up Checkups (Balik-Checkup)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        2. Patient Search (First / Last Name)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person-search text-success"></i></span>
                        <input type="text" name="search_name" class="form-control" placeholder="Pangalan o Apelyido..." value="<?php echo e($search_name); ?>">
                    </div>
                </div>

                <div class="col-md-2 date-filter-col">
                    <label class="form-label fw-semibold">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo e($date_from); ?>" <?php echo $all_dates ? 'disabled' : ''; ?>>
                </div>

                <div class="col-md-2 date-filter-col">
                    <label class="form-label fw-semibold">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo e($date_to); ?>" <?php echo $all_dates ? 'disabled' : ''; ?>>
                </div>

                <div class="col-md-2">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="all_dates" id="all_dates" value="1" <?php echo $all_dates ? 'checked' : ''; ?> onchange="toggleDateInputs(this.checked)">
                        <label class="form-check-label small fw-semibold text-muted" for="all_dates">
                            Lahat ng Petsa (All Dates)
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="generate" value="1" class="btn btn-success flex-grow-1 fw-bold shadow-sm" title="I-generate ang opisyal na report">
                            <i class="bi bi-file-earmark-bar-graph-fill me-1"></i>Generate
                        </button>
                        <button type="submit" name="export" value="csv" class="btn btn-outline-primary" title="I-download bilang CSV file">
                            <i class="bi bi-download"></i> CSV
                        </button>
                        <a href="reports.php?report=<?php echo e($report); ?>" class="btn btn-outline-secondary" title="I-reset ang filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Feedback Notice on Generation (Screen Only) -->
    <?php if (isset($_GET['generate'])): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-3 no-print" role="alert">
        <i class="bi bi-check-circle-fill fs-4 me-2 text-success"></i>
        <div>
            <strong>Report Generated Successfully!</strong> Nabuo na ang opisyal na talaan para sa <strong><?php echo e($config['title']); ?></strong> (<?php echo $rows->num_rows; ?> talaan na natagpuan). Handa na itong i-print, i-save bilang PDF, o i-export sa CSV.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- OFFICIAL REPORT SHEET (Printable & Screen View) -->
    <div class="card shadow-sm border-0 print-sheet p-4 bg-white">
        <!-- Official Health Center Header -->
        <div class="text-center pb-3 border-bottom mb-4">
            <div class="text-uppercase text-secondary small fw-bold letter-spacing-1">Republic of the Philippines</div>
            <div class="text-uppercase text-secondary small fw-bold">Province / City / Municipality</div>
            <h4 class="fw-bold mb-0 text-success mt-1">BARANGAY HEALTH CENTER</h4>
            <div class="text-muted small">Barangay Health Workers Information & Patient Management System</div>
            <div class="mt-3">
                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 fs-6">
                    OFFICIAL REPORT: <?php echo strtoupper($config['title']); ?>
                </span>
            </div>
            <div class="text-muted small mt-1"><?php echo e($config['subtitle']); ?></div>
        </div>

        <!-- Report Metadata Row -->
        <div class="row g-2 mb-4 p-3 bg-light rounded-3 small">
            <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Report Type:</span>
                <strong class="text-dark"><?php echo e($config['title']); ?></strong>
            </div>
            <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Saklaw na Panahon (Period):</span>
                <strong class="text-dark">
                    <?php if (!$all_dates && !empty($date_from) && !empty($date_to)): ?>
                        <?php echo e($date_from); ?> hanggang <?php echo e($date_to); ?>
                    <?php else: ?>
                        Lahat ng Talaan (All Records to Date)
                    <?php endif; ?>
                </strong>
            </div>
            <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Pasyenteng Sinala (Filter):</span>
                <strong class="text-dark">
                    <?php echo $search_name !== '' ? e($search_name) : 'Lahat ng Pasyente'; ?>
                </strong>
            </div>
            <div class="col-sm-6 col-md-3">
                <span class="text-muted d-block">Kabuuang Bilang (Total Records):</span>
                <span class="badge bg-success fs-6"><?php echo $rows->num_rows; ?> talaan</span>
            </div>
        </div>

        <!-- Report Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 45px;">#</th>
                        <?php foreach ($rows->fetch_fields() as $field): ?>
                        <th class="py-2 px-3"><?php echo e($field->name); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_number = 1;
                    while ($row = $rows->fetch_assoc()): 
                    ?>
                    <tr>
                        <td class="text-center text-muted small"><?php echo $row_number++; ?></td>
                        <?php foreach ($row as $colName => $value): ?>
                        <td class="py-2 px-3">
                            <?php if ($colName === 'Patient ID'): ?>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?php echo e((string)$value); ?>
                                </span>
                            <?php elseif ($colName === 'Status'): ?>
                                <span class="badge <?php echo $value === 'Completed' ? 'bg-success' : ($value === 'Overdue' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                    <?php echo e((string)$value); ?>
                                </span>
                            <?php elseif ($colName === 'Patient' || $colName === 'Patient Name' || $colName === 'Full Name'): ?>
                                <strong class="text-dark"><?php echo e((string)$value); ?></strong>
                            <?php elseif ($colName === 'Sex'): ?>
                                <span class="badge <?php echo $value === 'Female' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary'; ?>">
                                    <?php echo e((string)$value); ?>
                                </span>
                            <?php else: ?>
                                <?php echo nl2br(e((string)$value ?: '-')); ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endwhile; if ($rows->num_rows === 0): ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted py-5">
                            <i class="bi bi-folder2-open fs-1 d-block mb-2 text-secondary"></i>
                            Walang talaan na natagpuan sa napiling filter.
                            <div class="small mt-1 text-secondary">Subukang i-tsek ang "Lahat ng Petsa (All Dates)" o alisin ang filter sa pangalan.</div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Official Sign-off Footer (Ready for Printing and Document Signing) -->
        <div class="row mt-5 pt-4 text-center">
            <div class="col-6">
                <div class="border-bottom mx-auto" style="width: 220px; height: 40px;"></div>
                <div class="small fw-bold mt-2">Inihanda ni (Prepared by):</div>
                <div class="text-muted small"><?php echo e($_SESSION['username'] ?? 'BHW Staff'); ?> (<?php echo e($_SESSION['role'] ?? 'BHW'); ?>)</div>
                <div class="text-muted small">Barangay Health Worker / Staff</div>
            </div>
            <div class="col-6">
                <div class="border-bottom mx-auto" style="width: 220px; height: 40px;"></div>
                <div class="small fw-bold mt-2">Pinagtibay ni (Approved / Noted by):</div>
                <div class="text-muted small">Barangay Health Officer / Midwife / Nurse</div>
                <div class="text-muted small">Barangay Health Station</div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4 pt-3 border-top d-print-block">
            Petsa ng Pagbuo (Date Generated): <?php echo date('F d, Y - h:i A'); ?> | BHW Assist Health System
        </div>
    </div>
</div>

<script>
function toggleDateInputs(isAllDates) {
    document.getElementById('date_from').disabled = isAllDates;
    document.getElementById('date_to').disabled = isAllDates;
}
</script>
<?php include "includes/footer.php"; ?>
