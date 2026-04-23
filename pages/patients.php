<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $name = cleanInput($_POST['name']);
                $age = (int)$_POST['age'];
                $gender = $_POST['gender'];
                $contact = cleanInput($_POST['contact']);
                $address = cleanInput($_POST['address']);

                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO patients (name, age, gender, contact, address) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $age, $gender, $contact, $address]);
                    setFlash("Patient registered successfully.");
                } else {
                    $id = $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE patients SET name=?, age=?, gender=?, contact=?, address=? WHERE id=?");
                    $stmt->execute([$name, $age, $gender, $contact, $address, $id]);
                    setFlash("Patient details updated.");
                }
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
                $stmt->execute([$id]);
                setFlash("Patient deleted successfully.", "danger");
            }
        } catch (Exception $e) {
            setFlash("Error: " . $e->getMessage(), "danger");
        }
        header("Location: patients.php");
        exit();
    }
}

// Initialize Filter Variables
$search = $_GET['search'] ?? '';
$genderFilter = $_GET['gender'] ?? '';

// Build Query
$query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR contact LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($genderFilter)) {
    $query .= " AND gender = ?";
    $params[] = $genderFilter;
}

$query .= " ORDER BY name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();
$totalResults = count($patients);

// FEATURE 1: Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "patients_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Age', 'Gender', 'Contact', 'Address', 'Registration Date']);
    
    foreach ($patients as $p) {
        fputcsv($output, [
            $p['name'],
            $p['age'],
            $p['gender'],
            $p['contact'],
            $p['address'],
            $p['created_at']
        ]);
    }
    fclose($output);
    exit();
}

$pageTitle = 'Patients Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Patients Directory</h1>
    <div>
        <a href="patients.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success me-2">
            <i class="fas fa-file-csv me-2"></i> Export CSV
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#patientModal">
            <i class="fas fa-plus me-2"></i> Add New Patient
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="card shadow mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" action="patients.php" class="row g-3 text-dark">
            <div class="col-md-5">
                <label class="form-label small fw-bold">Search Patient</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or contact..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Gender</label>
                <select name="gender" class="form-select">
                    <option value="">All Genders</option>
                    <option value="Male" <?= $genderFilter == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $genderFilter == 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $genderFilter == 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-filter me-2"></i> Apply</button>
                <a href="patients.php" class="btn btn-outline-secondary w-100"><i class="fas fa-undo me-2"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="mb-3 text-muted small">
    Showing <strong><?= $totalResults ?></strong> patients found.
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead class="table-light">
                    <tr>
                        <th>Patient Name</th>
                        <th>Age/Gender</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                        <tr><td colspan="6" class="text-center py-4">No patients matching your search criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($patients as $p): ?>
                            <tr>
                                <td><strong><?= $p['name'] ?></strong></td>
                                <td><?= $p['age'] ?> YRS / <?= $p['gender'] ?></td>
                                <td><?= $p['contact'] ?></td>
                                <td><?= $p['address'] ?: '<span class="text-muted">N/A</span>' ?></td>
                                <td><?= formatDate($p['created_at']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="prescriptions.php?patient_id=<?= $p['id'] ?>" class="btn btn-outline-info" title="View History">
                                            <i class="fas fa-history"></i>
                                        </a>
                                        <button class="btn btn-outline-primary edit-btn" 
                                                data-id="<?= $p['id'] ?>"
                                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                                data-age="<?= $p['age'] ?>"
                                                data-gender="<?= $p['gender'] ?>"
                                                data-contact="<?= htmlspecialchars($p['contact']) ?>"
                                                data-address="<?= htmlspecialchars($p['address']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#patientModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger delete-btn" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Patient Modal -->
<div class="modal fade" id="patientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="patients.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Register New Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="patientId">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="patName" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" id="patAge" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" id="patGender" class="form-select" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact" id="patContact" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="patAddress" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
$(document).ready(function() {
    $('.edit-btn').on('click', function() {
        $('#modalTitle').text('Edit Patient Details');
        $('#formAction').val('edit');
        $('#patientId').val($(this).data('id'));
        $('#patName').val($(this).data('name'));
        $('#patAge').val($(this).data('age'));
        $('#patGender').val($(this).data('gender'));
        $('#patContact').val($(this).data('contact'));
        $('#patAddress').val($(this).data('address'));
    });

    $('#patientModal').on('hidden.bs.modal', function () {
        $('#modalTitle').text('Register New Patient');
        $('#formAction').val('add');
        $('#patientId').val('');
        $('#patientModal form')[0].reset();
    });

    $('.delete-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (confirm('Are you sure you want to delete patient "' + name + '"?')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
