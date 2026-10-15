<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();

function count_query(mysqli $conn, string $sql): int
{
    return (int)$conn->query($sql)->fetch_assoc()['total'];
}

$patients = count_query($conn, "SELECT COUNT(*) AS total FROM patients");
$consultations = count_query($conn, "SELECT COUNT(*) AS total FROM consultations WHERE consultation_date = CURDATE()");
$visits = count_query($conn, "SELECT COUNT(*) AS total FROM home_visits WHERE visit_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$vaccinations = count_query($conn, "SELECT COUNT(*) AS total FROM vaccinations WHERE vaccination_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$medicines = count_query($conn, "SELECT COUNT(*) AS total FROM medicines");
$pending_followups = count_query($conn, "SELECT COUNT(*) AS total FROM followups WHERE status = 'Pending' AND followup_date >= CURDATE()");
$overdue_followups = count_query($conn, "SELECT COUNT(*) AS total FROM followups WHERE status = 'Pending' AND followup_date < CURDATE()");
$due_today_followups = count_query($conn, "SELECT COUNT(*) AS total FROM followups WHERE status = 'Pending' AND followup_date = CURDATE()");

// 1. Monthly Activity (Last 6 Months)
$months_map = [];
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("-$i months");
    $key = date('Y-m', $time);
    $label = date('M Y', $time);
    $months_map[$key] = [
        'label' => $label,
        'consultations' => 0,
        'visits' => 0,
        'vaccinations' => 0,
        'medicines' => 0
    ];
}

$activity_result = $conn->query("
    SELECT DATE_FORMAT(activity_date, '%Y-%m') AS month_key, 
           SUM(consultations) AS consultations, 
           SUM(visits) AS visits, 
           SUM(vaccinations) AS vaccinations, 
           SUM(medicines) AS medicines 
    FROM (
        SELECT consultation_date AS activity_date, 1 AS consultations, 0 AS visits, 0 AS vaccinations, 0 AS medicines FROM consultations WHERE consultation_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) 
        UNION ALL 
        SELECT visit_date, 0, 1, 0, 0 FROM home_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) 
        UNION ALL 
        SELECT vaccination_date, 0, 0, 1, 0 FROM vaccinations WHERE vaccination_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) 
        UNION ALL 
        SELECT distribution_date, 0, 0, 0, 1 FROM medicines WHERE distribution_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    ) activity 
    GROUP BY month_key
");
while ($activity = $activity_result->fetch_assoc()) {
    $m = $activity['month_key'];
    if (isset($months_map[$m])) {
        $months_map[$m]['consultations'] = (int)$activity['consultations'];
        $months_map[$m]['visits'] = (int)$activity['visits'];
        $months_map[$m]['vaccinations'] = (int)$activity['vaccinations'];
        $months_map[$m]['medicines'] = (int)$activity['medicines'];
    }
}
$monthly_activity = [
    'labels' => array_column($months_map, 'label'),
    'consultations' => array_column($months_map, 'consultations'),
    'visits' => array_column($months_map, 'visits'),
    'vaccinations' => array_column($months_map, 'vaccinations'),
    'medicines' => array_column($months_map, 'medicines')
];

// 2. Patient Age Brackets
$age_row = $conn->query("
    SELECT 
        COALESCE(SUM(CASE WHEN age BETWEEN 0 AND 12 THEN 1 ELSE 0 END), 0) AS children,
        COALESCE(SUM(CASE WHEN age BETWEEN 13 AND 19 THEN 1 ELSE 0 END), 0) AS teens,
        COALESCE(SUM(CASE WHEN age BETWEEN 20 AND 59 THEN 1 ELSE 0 END), 0) AS adults,
        COALESCE(SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END), 0) AS seniors
    FROM patients
")->fetch_assoc();

$age_stats = [
    'labels' => ['Children (0-12)', 'Teens (13-19)', 'Adults (20-59)', 'Seniors (60+)'],
    'data' => [
        (int)($age_row['children'] ?? 0),
        (int)($age_row['teens'] ?? 0),
        (int)($age_row['adults'] ?? 0),
        (int)($age_row['seniors'] ?? 0)
    ]
];

// 3. Patient Sex Demographics
$sex_stats = ['Male' => 0, 'Female' => 0];
$sex_result = $conn->query("SELECT CASE WHEN sex = 'Female' THEN 'Female' ELSE 'Male' END AS normalized_sex, COUNT(*) AS total FROM patients GROUP BY normalized_sex");
while ($sex = $sex_result->fetch_assoc()) {
    if (isset($sex_stats[$sex['normalized_sex']])) {
        $sex_stats[$sex['normalized_sex']] = (int)$sex['total'];
    }
}
$sex_chart_data = [
    'labels' => ['Male (Lalaki)', 'Female (Babae)'],
    'data' => [$sex_stats['Male'], $sex_stats['Female']]
];

// 4. Overall Health Services Overview
$total_consultations = count_query($conn, "SELECT COUNT(*) AS total FROM consultations");
$total_visits = count_query($conn, "SELECT COUNT(*) AS total FROM home_visits");
$total_vaccinations = count_query($conn, "SELECT COUNT(*) AS total FROM vaccinations");
$total_medicines = count_query($conn, "SELECT COUNT(*) AS total FROM medicines");
$total_followups = count_query($conn, "SELECT COUNT(*) AS total FROM followups");

$services_breakdown = [
    'labels' => ['Consultations', 'Home Visits', 'Vaccinations', 'Medicines', 'Follow-ups'],
    'data' => [
        $total_consultations,
        $total_visits,
        $total_vaccinations,
        $total_medicines,
        $total_followups
    ]
];

include "includes/header.php";
include "includes/sidebar.php";
?>
<div class="p-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2 class="fw-bold mb-1">Dashboard</h2>
            <p class="text-muted mb-0">Health center activity overview & visual analytics</p>
        </div>
        <span class="badge bg-success-subtle text-success p-2">
            <i class="bi bi-shield-check me-1"></i><?php echo e($_SESSION['role']); ?>
        </span>
    </div>

    <!-- Stat Cards Row -->
    <div class="row g-3">
        <?php
        $cards = [
            ["Total Patients", $patients, "bi-people", "success"],
            ["Today's Consultations", $consultations, "bi-heart-pulse", "danger"],
            ["Home Visits This Month", $visits, "bi-house", "primary"],
            ["Vaccinations This Month", $vaccinations, "bi-capsule", "info"],
            ["Medicine Distributions", $medicines, "bi-bandaid", "secondary"],
            ["Due Today Follow-ups", $due_today_followups, "bi-calendar-event", "primary"],
            ["Pending Follow-ups", $pending_followups, "bi-calendar-check", "warning"],
            ["Overdue Follow-ups", $overdue_followups, "bi-exclamation-triangle", "danger"]
        ];
        foreach ($cards as $card):
        ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">
                            <?php echo $card[0]; ?>
                        </div>
                        <h2 class="fw-bold mb-0">
                            <?php echo $card[1]; ?>
                        </h2>
                    </div>
                    <i class="bi <?php echo $card[2]; ?> fs-2 text-<?php echo $card[3]; ?>"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart Row 1: Line Chart & Quick Alerts -->
    <div class="row g-4 mt-2">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                    <div>
                        <h5 class="mb-0 fw-bold">Health Activities Trend</h5>
                        <small class="text-muted">Buwanang aktibidad sa nakalipas na 6 na buwan</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="bi bi-graph-up me-1"></i>Line Chart
                    </span>
                </div>
                <div class="card-body pt-0">
                    <div style="height: 310px; position: relative;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold">Follow-up Alerts & Actions</h5>
                    <small class="text-muted">Mabilisang aksyon at mga abiso</small>
                </div>
                <div class="card-body pt-0 d-flex flex-column justify-content-between">
                    <div>
                        <a href="followups.php?status=overdue" class="alert alert-danger d-flex justify-content-between align-items-center py-2 text-decoration-none mb-2 shadow-xs">
                            <span><i class="bi bi-exclamation-circle-fill me-2"></i>Overdue Follow-ups</span>
                            <strong><?php echo $overdue_followups; ?></strong>
                        </a>
                        <a href="followups.php?status=today" class="alert alert-warning d-flex justify-content-between align-items-center py-2 text-decoration-none mb-3 shadow-xs">
                            <span><i class="bi bi-calendar-event-fill me-2"></i>Due Today</span>
                            <strong><?php echo $due_today_followups; ?></strong>
                        </a>
                    </div>
                    <div class="pt-2">
                        <a href="patients.php" class="btn btn-success w-100 mb-2 py-2 fw-medium">
                            <i class="bi bi-person-plus me-1"></i>Add Patient
                        </a>
                        <a href="consultations.php" class="btn btn-outline-primary w-100 py-2 fw-medium">
                            <i class="bi bi-plus-circle me-1"></i>New Consultation
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Row 2: 3 Distinct Charts (Bar, Doughnut, Polar Area / Pie) -->
    <div class="row g-4 mt-2">
        <!-- Chart 2: Bar Chart -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                    <div>
                        <h5 class="mb-0 fw-bold">Age Demographics</h5>
                        <small class="text-muted">Distribusyon ayon sa edad</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                        <i class="bi bi-bar-chart-fill me-1"></i>Bar Chart
                    </span>
                </div>
                <div class="card-body pt-0">
                    <div style="height: 280px; position: relative;">
                        <canvas id="ageChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 3: Doughnut Chart -->
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                    <div>
                        <h5 class="mb-0 fw-bold">Gender Demographics</h5>
                        <small class="text-muted">Kasarian ng mga pasyente</small>
                    </div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                        <i class="bi bi-pie-chart me-1"></i>Doughnut Chart
                    </span>
                </div>
                <div class="card-body pt-0">
                    <div style="height: 280px; position: relative;">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 4: Polar Area / Pie Chart -->
        <div class="col-lg-4 col-md-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                    <div>
                        <h5 class="mb-0 fw-bold">Health Services Share</h5>
                        <small class="text-muted">Distribusyon ng serbisyo</small>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Chart switcher">
                        <button type="button" id="btnPolar" class="btn btn-outline-secondary active btn-sm" onclick="switchServicesChart('polarArea')">Polar</button>
                        <button type="button" id="btnPie" class="btn btn-outline-secondary btn-sm" onclick="switchServicesChart('pie')">Pie</button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div style="height: 280px; position: relative;">
                        <canvas id="servicesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // 1. Line Chart: Monthly Activity Trends
    new Chart(document.getElementById('activityChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_activity['labels']); ?>,
            datasets: [
                {
                    label: 'Consultations',
                    data: <?php echo json_encode($monthly_activity['consultations']); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Home Visits',
                    data: <?php echo json_encode($monthly_activity['visits']); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Vaccinations',
                    data: <?php echo json_encode($monthly_activity['vaccinations']); ?>,
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Medicines',
                    data: <?php echo json_encode($monthly_activity['medicines']); ?>,
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    tension: 0.35,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { padding: 10 }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // 2. Bar Chart: Patient Age Distribution
    new Chart(document.getElementById('ageChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($age_stats['labels']); ?>,
            datasets: [{
                label: 'Patients',
                data: <?php echo json_encode($age_stats['data']); ?>,
                backgroundColor: ['#20c997', '#0d6efd', '#ffc107', '#fd7e14'],
                borderRadius: 6,
                maxBarThickness: 45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { padding: 10 }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // 3. Doughnut Chart: Patient Gender Demographics
    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($sex_chart_data['labels']); ?>,
            datasets: [{
                data: <?php echo json_encode($sex_chart_data['data']); ?>,
                backgroundColor: ['#0d6efd', '#e83e8c'],
                hoverOffset: 6,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 14, padding: 15 } },
                tooltip: { padding: 10 }
            }
        }
    });

    // 4. Polar Area / Pie Chart: Health Services Share
    const servicesLabels = <?php echo json_encode($services_breakdown['labels']); ?>;
    const servicesData = <?php echo json_encode($services_breakdown['data']); ?>;
    const servicesColors = [
        'rgba(25, 135, 84, 0.8)',
        'rgba(13, 110, 253, 0.8)',
        'rgba(13, 202, 240, 0.8)',
        'rgba(253, 126, 20, 0.8)',
        'rgba(111, 66, 193, 0.8)'
    ];

    let currentServicesChartType = 'polarArea';
    let servicesChartInstance = null;

    function renderServicesChart(type) {
        if (servicesChartInstance) {
            servicesChartInstance.destroy();
        }
        servicesChartInstance = new Chart(document.getElementById('servicesChart'), {
            type: type,
            data: {
                labels: servicesLabels,
                datasets: [{
                    data: servicesData,
                    backgroundColor: servicesColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: { padding: 10 }
                }
            }
        });
    }

    function switchServicesChart(type) {
        if (type === currentServicesChartType) return;
        currentServicesChartType = type;
        document.getElementById('btnPolar').classList.toggle('active', type === 'polarArea');
        document.getElementById('btnPie').classList.toggle('active', type === 'pie');
        renderServicesChart(type);
    }

    // Initialize Chart 4
    renderServicesChart('polarArea');
</script>
<?php include "includes/footer.php"; ?>
