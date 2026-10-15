<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BHW Assist</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
<nav class="topbar d-flex align-items-center justify-content-between px-3 py-2 bg-white border-bottom">
<button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-label="Open navigation"><i class="bi bi-list"></i></button>
<div class="fw-semibold text-success">Barangay Health Center</div>
<div class="d-flex align-items-center gap-3 small"><span class="text-muted"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span><a href="logout.php" class="text-danger text-decoration-none"><i class="bi bi-box-arrow-right"></i></a></div>
</nav>
<div class="d-flex">