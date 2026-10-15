<?php
require_once "config.php";
require_once "includes/auth.php";
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
header('Location: patients.php');
exit;
}
verify_csrf();
$patient_id = (int)($_POST['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
try {
$stmt->execute();
$_SESSION['flash_message'] = 'Patient deleted successfully.';
} catch (Throwable $error) {
$_SESSION['flash_error'] = 'Unable to delete patient with existing health records.';
}
header('Location: patients.php');
exit;
