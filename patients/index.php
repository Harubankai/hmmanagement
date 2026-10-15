<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();
$page_title = 'Patient Management';
$page_subtitle = 'Manage patient records and information';
$page_active = 'patients';

$message = '';
$error = '';

// Handle actions
$action = $_GET['action'] ?? '';
$patient_id = intval($_GET['id'] ?? 0);

// DELETE patient
if ($action === 'delete' && $patient_id) {
    requireAdmin();
    $check = $conn->prepare("SELECT full_name FROM (SELECT CONCAT(first_name,' ',last_name) as full_name FROM patients WHERE id=?) x");
    $stmt = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) as full_name FROM patients WHERE id=?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $del = $conn->prepare("DELETE FROM patients WHERE id=?");
        $del->bind_param("i", $patient_id);
        $del->execute();
        $del->close();
        logActivity($conn, $_SESSION['user_id'], 'Patient Deleted', 'Deleted patient: ' . $row['full_name']);
        $message = 'Patient deleted successfully.';
    }
}

// ADD patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add') {
    $new_id = generateID('PT', 'patients', 'patient_id', $conn);
    $fn = trim($_POST['first_name']); $mn = trim($_POST['middle_name']); $ln = trim($_POST['last_name']);
    $dob = $_POST['date_of_birth']; $gender = $_POST['gender'];
    $contact = trim($_POST['contact_number']); $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $ec_name = trim($_POST['emergency_contact_name']); $ec_num = trim($_POST['emergency_contact_number']);
    $blood = $_POST['blood_type']; $reg_date = $_POST['registration_date'];
    $notes = trim($_POST['notes']);

    if (!$fn || !$ln || !$dob || !$gender || !$reg_date) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO patients (patient_id,first_name,middle_name,last_name,date_of_birth,gender,contact_number,email,address,emergency_contact_name,emergency_contact_number,blood_type,registration_date,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssssssssss", $new_id,$fn,$mn,$ln,$dob,$gender,$contact,$email,$address,$ec_name,$ec_num,$blood,$reg_date,$notes);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Patient Registration', "Registered new patient: $fn $ln ($new_id)");
            $message = "Patient $fn $ln registered successfully with ID: $new_id";
        } else {
            $error = 'Error saving patient. Please try again.';
        }
        $stmt->close();
    }
}

// EDIT patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'edit') {
    $eid = intval($_POST['edit_id']);
    $fn = trim($_POST['first_name']); $mn = trim($_POST['middle_name']); $ln = trim($_POST['last_name']);
    $dob = $_POST['date_of_birth']; $gender = $_POST['gender'];
    $contact = trim($_POST['contact_number']); $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $ec_name = trim($_POST['emergency_contact_name']); $ec_num = trim($_POST['emergency_contact_number']);
    $blood = $_POST['blood_type']; $status = $_POST['status'];
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("UPDATE patients SET first_name=?,middle_name=?,last_name=?,date_of_birth=?,gender=?,contact_number=?,email=?,address=?,emergency_contact_name=?,emergency_contact_number=?,blood_type=?,status=?,notes=? WHERE id=?");
    $stmt->bind_param("sssssssssssssi",$fn,$mn,$ln,$dob,$gender,$contact,$email,$address,$ec_name,$ec_num,$blood,$status,$notes,$eid);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Patient Updated', "Updated patient: $fn $ln (ID: $eid)");
        $message = "Patient information updated successfully.";
    } else {
        $error = 'Error updating patient.';
    }
    $stmt->close();
}

// Search/filter
$search = trim($_GET['search'] ?? '');
$filter_gender = $_GET['gender'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $s = "%$search%";
    $where .= " AND (patient_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR contact_number LIKE ?)";
    $params = array_merge($params, [$s,$s,$s,$s]);
    $types .= 'ssss';
}
if ($filter_gender) {
    $where .= " AND gender=?";
    $params[] = $filter_gender;
    $types .= 's';
}
if ($filter_status) {
    $where .= " AND status=?";
    $params[] = $filter_status;
    $types .= 's';
}

// Pagination
$per_page = 10;
$page_num = max(1, intval($_GET['page'] ?? 1));
$offset = ($page_num - 1) * $per_page;

$count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM patients $where");
if ($types) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);
$count_stmt->close();

$stmt = $conn->prepare("SELECT * FROM patients $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$all_params = $params;
$all_params[] = $per_page; $all_params[] = $offset;
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$patients = $stmt->get_result();
$stmt->close();

// Fetch patient for view/edit modal
$view_patient = null;
if (in_array($action, ['view','edit']) && $patient_id) {
    $s = $conn->prepare("SELECT * FROM patients WHERE id=?");
    $s->bind_param("i", $patient_id);
    $s->execute();
    $view_patient = $s->get_result()->fetch_assoc();
    $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Gerona Hospital </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="page-body">

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Patient List (<?= number_format($total_rows) ?>)</h3>
                    <button class="btn btn-primary" onclick="openModal('addModal')">
                        <i class="fas fa-user-plus"></i> Register Patient
                    </button>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <!-- Filter Bar -->
                    <form method="GET" class="filter-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search by ID, name, contact..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="gender" class="form-control" style="width:140px;">
                            <option value="">All Genders</option>
                            <option value="Male" <?= $filter_gender==='Male'?'selected':'' ?>>Male</option>
                            <option value="Female" <?= $filter_gender==='Female'?'selected':'' ?>>Female</option>
                            <option value="Other" <?= $filter_gender==='Other'?'selected':'' ?>>Other</option>
                        </select>
                        <select name="status" class="form-control" style="width:140px;">
                            <option value="">All Status</option>
                            <option value="Active" <?= $filter_status==='Active'?'selected':'' ?>>Active</option>
                            <option value="Inactive" <?= $filter_status==='Inactive'?'selected':'' ?>>Inactive</option>
                            <option value="Discharged" <?= $filter_status==='Discharged'?'selected':'' ?>>Discharged</option>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="/hmsystem/patients/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>Patient ID</th><th>Full Name</th><th>Gender</th><th>Date of Birth</th>
                            <th>Contact</th><th>Blood Type</th><th>Reg. Date</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($patients->num_rows === 0): ?>
                        <tr><td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h4>No patients found</h4>
                                <p>Try adjusting your search or register a new patient.</p>
                            </div>
                        </td></tr>
                        <?php else: ?>
                        <?php while ($p = $patients->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-600 text-primary"><?= htmlspecialchars($p['patient_id']) ?></span></td>
                            <td><strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['gender']) ?></td>
                            <td><?= date('M d, Y', strtotime($p['date_of_birth'])) ?>
                                <small class="text-muted">(<?= getAge($p['date_of_birth']) ?> yrs)</small>
                            </td>
                            <td><?= htmlspecialchars($p['contact_number'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($p['blood_type']) ?></td>
                            <td><?= date('M d, Y', strtotime($p['registration_date'])) ?></td>
                            <td>
                                <?php
                                $sc = ['Active'=>'success','Inactive'=>'secondary','Discharged'=>'warning'];
                                ?>
                                <span class="badge badge-<?= $sc[$p['status']] ?? 'secondary' ?>"><?= $p['status'] ?></span>
                            </td>
                            <td class="actions-cell">
                                <a href="?action=view&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="View"><i class="fas fa-eye"></i></a>
                                <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="/hmsystem/medical-records/index.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary" title="Medical Records"><i class="fas fa-file-medical"></i></a>
                                <?php if (isAdmin()): ?>
                                <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete <?= htmlspecialchars(addslashes($p['first_name'].' '.$p['last_name'])) ?>? This will also delete related records.')">
                                   <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="padding:16px 24px;">
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&gender=<?= urlencode($filter_gender) ?>&status=<?= urlencode($filter_status) ?>"
                           class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- ADD PATIENT MODAL -->
<div class="modal-overlay <?= (!$error && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action']??'')!=='add') ? '' : ($error ? 'active' : '') ?>" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-user-plus"></i> Register New Patient</h4>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="add">
        <div class="modal-body">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control" required value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($_POST['gender']??'')==='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= ($_POST['gender']??'')==='Female'?'selected':'' ?>>Female</option>
                        <option value="Other" <?= ($_POST['gender']??'')==='Other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-control">
                        <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= ($_POST['blood_type']??'Unknown')===$bt?'selected':'' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Home Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Emergency Contact Number</label>
                    <input type="text" name="emergency_contact_number" class="form-control" value="<?= htmlspecialchars($_POST['emergency_contact_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Registration Date <span class="text-danger">*</span></label>
                <input type="date" name="registration_date" class="form-control" required value="<?= htmlspecialchars($_POST['registration_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Register Patient</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW PATIENT MODAL -->
<?php if ($view_patient && $action === 'view'): ?>
<div class="modal-overlay active" id="viewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-user"></i> Patient Details — <?= htmlspecialchars($view_patient['patient_id']) ?></h4>
            <a href="/hmsystem/patients/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-item"><div class="label">Patient ID</div><div class="value text-primary fw-600"><?= htmlspecialchars($view_patient['patient_id']) ?></div></div>
                <div class="info-item"><div class="label">Full Name</div><div class="value"><?= htmlspecialchars($view_patient['first_name'].' '.($view_patient['middle_name']?' '.$view_patient['middle_name'].' ':'').$view_patient['last_name']) ?></div></div>
                <div class="info-item"><div class="label">Date of Birth</div><div class="value"><?= date('F d, Y', strtotime($view_patient['date_of_birth'])) ?> (<?= getAge($view_patient['date_of_birth']) ?> years old)</div></div>
                <div class="info-item"><div class="label">Gender</div><div class="value"><?= htmlspecialchars($view_patient['gender']) ?></div></div>
                <div class="info-item"><div class="label">Blood Type</div><div class="value"><?= htmlspecialchars($view_patient['blood_type']) ?></div></div>
                <div class="info-item"><div class="label">Status</div><div class="value"><span class="badge badge-success"><?= htmlspecialchars($view_patient['status']) ?></span></div></div>
                <div class="info-item"><div class="label">Contact Number</div><div class="value"><?= htmlspecialchars($view_patient['contact_number'] ?: '—') ?></div></div>
                <div class="info-item"><div class="label">Email</div><div class="value"><?= htmlspecialchars($view_patient['email'] ?: '—') ?></div></div>
                <div class="info-item" style="grid-column:1/-1;"><div class="label">Address</div><div class="value"><?= htmlspecialchars($view_patient['address'] ?: '—') ?></div></div>
                <div class="info-item"><div class="label">Emergency Contact</div><div class="value"><?= htmlspecialchars($view_patient['emergency_contact_name'] ?: '—') ?></div></div>
                <div class="info-item"><div class="label">Emergency Number</div><div class="value"><?= htmlspecialchars($view_patient['emergency_contact_number'] ?: '—') ?></div></div>
                <div class="info-item"><div class="label">Registration Date</div><div class="value"><?= date('F d, Y', strtotime($view_patient['registration_date'])) ?></div></div>
                <?php if ($view_patient['notes']): ?>
                <div class="info-item" style="grid-column:1/-1;"><div class="label">Notes</div><div class="value"><?= htmlspecialchars($view_patient['notes']) ?></div></div>
                <?php endif; ?>
            </div>
            <div style="margin-top:20px;display:flex;gap:10px;">
                <a href="/hmsystem/medical-records/index.php?patient_id=<?= $view_patient['id'] ?>" class="btn btn-primary"><i class="fas fa-file-medical"></i> View Medical Records</a>
                <a href="/hmsystem/appointments/index.php?patient_id=<?= $view_patient['id'] ?>" class="btn btn-outline"><i class="fas fa-calendar-check"></i> View Appointments</a>
                <a href="/hmsystem/billing/index.php?patient_id=<?= $view_patient['id'] ?>" class="btn btn-outline"><i class="fas fa-file-invoice-dollar"></i> View Bills</a>
            </div>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/patients/index.php" class="btn btn-secondary">Close</a>
            <a href="?action=edit&id=<?= $view_patient['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit Patient</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- EDIT PATIENT MODAL -->
<?php if ($view_patient && $action === 'edit'): ?>
<div class="modal-overlay active" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-user-edit"></i> Edit Patient — <?= htmlspecialchars($view_patient['patient_id']) ?></h4>
            <a href="/hmsystem/patients/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="edit">
        <input type="hidden" name="edit_id" value="<?= $view_patient['id'] ?>">
        <div class="modal-body">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($view_patient['first_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($view_patient['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($view_patient['last_name']) ?>">
                </div>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= $view_patient['date_of_birth'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $view_patient['gender']===$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-control">
                        <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= $view_patient['blood_type']===$bt?'selected':'' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($view_patient['contact_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($view_patient['email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($view_patient['address'] ?? '') ?></textarea>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($view_patient['emergency_contact_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Emergency Contact Number</label>
                    <input type="text" name="emergency_contact_number" class="form-control" value="<?= htmlspecialchars($view_patient['emergency_contact_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['Active','Inactive','Discharged'] as $st): ?>
                    <option value="<?= $st ?>" <?= $view_patient['status']===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($view_patient['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/patients/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) { if (e.target === el) closeModal(el.id); });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
