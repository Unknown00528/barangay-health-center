<?php $active_page = basename($_SERVER['PHP_SELF']); ?>
<aside class="bg-dark text-white p-3 sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar">
<div class="offcanvas-header d-lg-none"><h5 class="mb-0">BHW Assist</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
<div class="sidebar-brand"><h4 class="fw-bold"><i class="bi bi-heart-pulse-fill text-success"></i> BHW Assist</h4>
<small class="text-secondary">Barangay Health System</small>
</div>
<hr>
<div class="mb-2 text-secondary">MAIN</div>
<?php
$links = [
	['index.php', 'bi-speedometer2', 'Dashboard'], ['patients.php', 'bi-people', 'Patients'],
	['consultations.php', 'bi-heart-pulse', 'Consultations'], ['home_visits.php', 'bi-house', 'Home Visits'],
	['vaccinations.php', 'bi-capsule', 'Vaccinations'], ['medicines.php', 'bi-bandaid', 'Medicines'],
	['followups.php', 'bi-calendar-check', 'Follow-ups']
];
foreach ($links as [$href, $icon, $label]):
?>
<a href="<?php echo $href; ?>" class="nav-link text-white p-2 <?php echo $active_page === $href ? 'active' : ''; ?>"><i class="bi <?php echo $icon; ?>"></i> <?php echo $label; ?></a>
<?php endforeach; ?>
<div class="mt-4 mb-2 text-secondary">MANAGEMENT</div>
<a href="reports.php" class="nav-link text-white p-2 <?php echo $active_page === 'reports.php' ? 'active' : ''; ?>"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
<?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
<a href="bhw_workers.php" class="nav-link text-white p-2 <?php echo $active_page === 'bhw_workers.php' ? 'active' : ''; ?>"><i class="bi bi-person-badge"></i> BHW Workers</a>
<a href="login_history.php" class="nav-link text-white p-2 <?php echo $active_page === 'login_history.php' ? 'active' : ''; ?>"><i class="bi bi-clock-history"></i> Login History</a>
<?php endif; ?>
<hr>
<a href="logout.php" class="btn btn-outline-light w-100"><i class="bi bi-box-arrow-right"></i> Logout</a>
</aside>
<div class="flex-grow-1">