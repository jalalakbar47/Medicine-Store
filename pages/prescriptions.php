<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header("Location: patients.php");
    exit();
}

// Fetch Patient Info
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    setFlash('danger', 'Patient not found.');
    header("Location: patients.php");
    exit();
}

$pageTitle = "Prescriptions for " . $patient['name'];

// Handle Add Prescription
if (isset($_POST['add_prescription'])) {
    $doctor = cleanInput($_POST['doctor_name']);
    $diagnosis = cleanInput($_POST['diagnosis']);
    $meds = cleanInput($_POST['medicine_details']);
    $sale_id = !empty($_POST['sale_id']) ? (int)$_POST['sale_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_name, diagnosis, medicine_details, sale_id) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$patient_id, $doctor, $diagnosis, $meds, $sale_id]);
    setFlash('success', 'Prescription added successfully!');
    header("Location: prescriptions.php?patient_id=" . $patient_id);
    exit();
}

// Fetch History
$stmt = $pdo->prepare("SELECT pr.*, s.invoice_no 
                       FROM prescriptions pr 
                       LEFT JOIN sales s ON pr.sale_id = s.id 
                       WHERE pr.patient_id = ? 
                       ORDER BY pr.created_at DESC");
$stmt->execute([$patient_id]);
$history = $stmt->fetchAll();

// Fetch Sales for this patient to link
$stmt = $pdo->prepare("SELECT id, invoice_no, sale_date FROM sales WHERE patient_id = ? ORDER BY sale_date DESC");
$stmt->execute([$patient_id]);
$patientSales = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-sm-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="patients.php">Patients</a></li>
                <li class="breadcrumb-item active"><?= $patient['name'] ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Prescription History</h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- New Prescription Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 fw-bold text-primary">New Prescription</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Doctor's Name</label>
                        <input type="text" name="doctor_name" class="form-control" placeholder="Dr. John Doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Diagnosis</label>
                        <textarea name="diagnosis" class="form-control" rows="2" placeholder="Brief diagnosis..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Medicine & Dosage</label>
                        <textarea name="medicine_details" class="form-control" rows="4" placeholder="e.g. Paracetamol 500mg - 1x3 for 5 days" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Link to Sale (Optional)</label>
                        <select name="sale_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($patientSales as $sale): ?>
                                <option value="<?= $sale['id'] ?>"><?= $sale['invoice_no'] ?> (<?= formatDate($sale['sale_date']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_prescription" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-plus me-2"></i> Save Prescription
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- List -->
        <?php if (empty($history)): ?>
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-medical fa-4x text-gray-200 mb-3"></i>
                    <p class="text-muted">No prescriptions found for this patient.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($history as $pr): ?>
                <div class="card shadow mb-3 border-left-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="fas fa-user-md me-2 text-info"></i> <?= $pr['doctor_name'] ?: 'Unknown Doctor' ?>
                            </h5>
                            <span class="badge bg-light text-dark"><?= formatDate($pr['created_at']) ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="small fw-bold text-uppercase text-muted">Diagnosis:</span>
                            <p class="mb-2"><?= $pr['diagnosis'] ?: 'N/A' ?></p>
                        </div>
                        <div>
                            <span class="small fw-bold text-uppercase text-muted">Prescription:</span>
                            <div class="bg-light p-3 rounded" style="white-space: pre-line;"><?= $pr['medicine_details'] ?></div>
                        </div>
                        <?php if ($pr['sale_id']): ?>
                            <div class="mt-3 text-end">
                                <a href="invoice.php?id=<?= $pr['sale_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-link me-1"></i> View Linked Sale (<?= $pr['invoice_no'] ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
