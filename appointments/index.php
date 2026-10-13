<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$page_title = 'Appointments';
$page_subtitle = 'Manage patient appointments';
$page_active = 'appointments';

$message = '';
$error = '';

$action = $_GET['action'] ?? '';
$appt_id = intval($_GET['id'] ?? 0);

// Fetch departments for dropdown
$depts = $conn->query("SELECT id, name FROM departments WHERE status='active' ORDER BY name");
$dept_list = [];
while ($d = $depts->fetch_assoc()) $dept_list[$d['id']] = $d['name'];

// Fetch patients for dropdown
$pts = $conn->query("SELECT id, patient_id, CONCAT(first_name,' ',last_name) as full_name FROM patients WHERE status='Active' ORDER BY first_name");
$pt_list = [];
while ($p = $pts->fetch_assoc()) $pt_list[$p['id']] = $p['patient_id'] . ' - ' . $p['full_name'];

// Fetch doctors for dropdown
$doctors_list = [
    'Dr. Maria Garcia',
    'Dr. Jose Reyes',
    'Dr. Carl Bautista',
    'Dr. Lisa Navarro',
    'Dr. Ana Lim',
    'Dr. Mark Torres',
    'Dr. Juan Santos',
];
$doc_appts = $conn->query("SELECT DISTINCT doctor_name FROM appointments WHERE doctor_name IS NOT NULL AND doctor_name != ''");
if ($doc_appts) {
    while ($da = $doc_appts->fetch_assoc()) {
        if (!in_array($da['doctor_name'], $doctors_list)) {
            $doctors_list[] = $da['doctor_name'];
        }
    }
}
sort($doctors_list);

$is_patient = isPatient();
$my_patient_id = getPatientId();
if ($is_patient && (!$my_patient_id || $my_patient_id == 0) && !empty($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $u_stmt = $conn->prepare("SELECT patient_id, email, full_name FROM users WHERE id = ? LIMIT 1");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    if ($u_row = $u_stmt->get_result()->fetch_assoc()) {
        if (!empty($u_row['patient_id'])) {
            $my_patient_id = $u_row['patient_id'];
        } else {
            $email = $u_row['email'];
            $p_stmt = $conn->prepare("SELECT id FROM patients WHERE email = ? LIMIT 1");
            $p_stmt->bind_param("s", $email);
            $p_stmt->execute();
            if ($p_res = $p_stmt->get_result()->fetch_assoc()) {
                $my_patient_id = $p_res['id'];
            } else {
                $new_pt_str = generateID('PT', 'patients', 'patient_id', $conn);
                $names = explode(' ', $u_row['full_name'], 2);
                $fname = $names[0] ?? 'Patient';
                $lname = $names[1] ?? 'User';
                $reg_date = date('Y-m-d');
                $ins_p = $conn->prepare("INSERT INTO patients (patient_id, first_name, last_name, email, status, registration_date) VALUES (?, ?, ?, ?, 'Active', ?)");
                $ins_p->bind_param("sssss", $new_pt_str, $fname, $lname, $email, $reg_date);
                $ins_p->execute();
                $my_patient_id = $conn->insert_id;
                $ins_p->close();
            }
            $p_stmt->close();
            $conn->query("UPDATE users SET patient_id = $my_patient_id WHERE id = $user_id");
        }
        $_SESSION['patient_id'] = $my_patient_id;
    }
    $u_stmt->close();
}

// DELETE
if ($action === 'delete' && $appt_id && !$is_patient) {
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id=?");
    $stmt->bind_param("i", $appt_id);
    $stmt->execute();
    $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'Appointment Deleted', "Deleted appointment ID: $appt_id");
    $message = 'Appointment deleted successfully.';
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'add') {
    $new_id = generateID('APT', 'appointments', 'appointment_id', $conn);
    $pid = $is_patient ? (intval($my_patient_id) ?: intval($_POST['patient_id'] ?? 0)) : intval($_POST['patient_id'] ?? 0);

    // Extra safety: if patient user, always guarantee patient_id exists
    if ($is_patient && (!$pid || $pid == 0)) {
        $uid_check = intval($_SESSION['user_id'] ?? 0);
        $u_row = $conn->query("SELECT patient_id, email, full_name FROM users WHERE id = $uid_check")->fetch_assoc();
        if (!empty($u_row['patient_id'])) {
            $pid = intval($u_row['patient_id']);
        } else {
            $em_safe = $conn->real_escape_string($u_row['email'] ?? '');
            $pt_check = $conn->query("SELECT id FROM patients WHERE email = '$em_safe' LIMIT 1")->fetch_assoc();
            if ($pt_check) {
                $pid = intval($pt_check['id']);
            } else {
                $new_pt = generateID('PT', 'patients', 'patient_id', $conn);
                $names = explode(' ', $u_row['full_name'] ?? 'Patient User', 2);
                $fname = $names[0] ?? 'Patient';
                $lname = $names[1] ?? 'User';
                $today = date('Y-m-d');
                $conn->query("INSERT INTO patients (patient_id, first_name, last_name, email, status, registration_date) VALUES ('$new_pt', '" . $conn->real_escape_string($fname) . "', '" . $conn->real_escape_string($lname) . "', '$em_safe', 'Active', '$today')");
                $pid = $conn->insert_id;
            }
            $conn->query("UPDATE users SET patient_id = $pid WHERE id = $uid_check");
        }
        $my_patient_id = $pid;
        $_SESSION['patient_id'] = $pid;
    }

    $did = intval($_POST['department_id'] ?? 0);
    $doc = trim($_POST['doctor_name'] ?? '');
    $date = trim($_POST['appointment_date'] ?? '');
    $time = trim($_POST['appointment_time'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $status = $is_patient ? 'Pending' : ($_POST['status'] ?? 'Pending');
    $notes = trim($_POST['notes'] ?? '');

    if (!$pid || !$did || !$date || !$time) {
        $missing = [];
        if (!$pid) $missing[] = 'Patient Profile';
        if (!$did) $missing[] = 'Department';
        if (!$date) $missing[] = 'Appointment Date';
        if (!$time) $missing[] = 'Appointment Time';
        $error = 'Please fill in all required fields (' . implode(', ', $missing) . ').';
    } else {
        $uid = intval($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare("INSERT INTO appointments (appointment_id,patient_id,department_id,doctor_name,appointment_date,appointment_time,purpose,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("siissssssi",$new_id,$pid,$did,$doc,$date,$time,$purpose,$status,$notes,$uid);
        if ($stmt->execute()) {
            $pname = $pt_list[$pid] ?? "Patient #$pid";
            logActivity($conn, $uid, 'Appointment Created', "Created appointment $new_id for $pname");
            $message = "Appointment $new_id requested successfully!";
        } else {
            $error = 'Error creating appointment: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'edit' && !$is_patient) {
    $eid = intval($_POST['edit_id']);
    $pid = intval($_POST['patient_id']);
    $did = intval($_POST['department_id']);
    $doc = trim($_POST['doctor_name']);
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $purpose = trim($_POST['purpose']);
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("UPDATE appointments SET patient_id=?,department_id=?,doctor_name=?,appointment_date=?,appointment_time=?,purpose=?,status=?,notes=? WHERE id=?");
    $stmt->bind_param("iissssssi",$pid,$did,$doc,$date,$time,$purpose,$status,$notes,$eid);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Appointment Updated', "Updated appointment ID: $eid");
        $message = 'Appointment updated successfully.';
    } else {
        $error = 'Error updating appointment.';
    }
    $stmt->close();
}

// Search/filter
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_dept = intval($_GET['dept'] ?? 0);
$filter_date = $_GET['date'] ?? '';
$filter_pid = intval($_GET['patient_id'] ?? 0);

$where = "WHERE 1=1";
$params = []; $types = '';

if ($is_patient) {
    $where .= " AND a.patient_id=?";
    $params[] = $my_patient_id ? $my_patient_id : 0;
    $types .= 'i';
} elseif ($filter_pid) {
    $where .= " AND a.patient_id=?";
    $params[] = $filter_pid;
    $types .= 'i';
}
if ($search) {
    $s = "%$search%";
    $where .= " AND (a.appointment_id LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR a.doctor_name LIKE ?)";
    $params = array_merge($params, [$s,$s,$s]); $types .= 'sss';
}
if ($filter_status) { $where .= " AND a.status=?"; $params[] = $filter_status; $types .= 's'; }
if ($filter_dept) { $where .= " AND a.department_id=?"; $params[] = $filter_dept; $types .= 'i'; }
if ($filter_date) { $where .= " AND a.appointment_date=?"; $params[] = $filter_date; $types .= 's'; }

$per_page = 12; $page_num = max(1, intval($_GET['page'] ?? 1)); $offset = ($page_num-1)*$per_page;

$count_q = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments a JOIN patients p ON a.patient_id=p.id $where");
if ($types) $count_q->bind_param($types, ...$params);
$count_q->execute();
$total_rows = $count_q->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows/$per_page);
$count_q->close();

$all_params = $params; $all_params[] = $per_page; $all_params[] = $offset;
$all_types = $types . 'ii';
$stmt = $conn->prepare("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, d.name as dept_name FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN departments d ON a.department_id=d.id $where ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?");
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$appointments = $stmt->get_result();
$stmt->close();

// Fetch for view/edit
$view_appt = null;
if (in_array($action,['view','edit']) && $appt_id) {
    $s = $conn->prepare("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid FROM appointments a JOIN patients p ON a.patient_id=p.id WHERE a.id=?");
    $s->bind_param("i",$appt_id);
    $s->execute();
    $view_appt = $s->get_result()->fetch_assoc();
    $s->close();
}

$status_colors = ['Scheduled'=>'info','Completed'=>'success','Cancelled'=>'danger','Pending'=>'warning'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Gerona Hospital IBMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="page-body">

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($filter_pid): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle"></i>
                Showing appointments for: <?= htmlspecialchars($pt_list[$filter_pid] ?? "Patient #$filter_pid") ?>
                <a href="/hmsystem/appointments/index.php" style="margin-left:12px;" class="btn btn-sm btn-secondary">Show All</a>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Appointments (<?= number_format($total_rows) ?>)</h3>
                    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> New Appointment</button>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <form method="GET" class="filter-bar">
                        <?php if ($filter_pid): ?><input type="hidden" name="patient_id" value="<?= $filter_pid ?>"><?php endif; ?>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search appointments..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <input type="date" name="date" class="form-control" style="width:160px;" value="<?= htmlspecialchars($filter_date) ?>">
                        <select name="status" class="form-control" style="width:140px;">
                            <option value="">All Status</option>
                            <?php foreach (['Pending','Scheduled','Completed','Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="dept" class="form-control" style="width:160px;">
                            <option value="">All Departments</option>
                            <?php foreach ($dept_list as $did => $dname): ?>
                            <option value="<?= $did ?>" <?= $filter_dept===$did?'selected':'' ?>><?= htmlspecialchars($dname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="/hmsystem/appointments/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>Appt ID</th><th>Patient</th><th>Department</th><th>Doctor</th>
                            <th>Date & Time</th><th>Purpose</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($appointments->num_rows === 0): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-calendar-xmark"></i><h4>No appointments found</h4><p>Create a new appointment to get started.</p></div></td></tr>
                        <?php else: ?>
                        <?php while ($a = $appointments->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-600 text-primary"><?= htmlspecialchars($a['appointment_id']) ?></span></td>
                            <td>
                                <div class="fw-600"><?= htmlspecialchars($a['patient_name']) ?></div>
                                <div class="text-muted text-small"><?= htmlspecialchars($a['pid']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($a['dept_name']) ?></td>
                            <td><?= htmlspecialchars($a['doctor_name'] ?: '—') ?></td>
                            <td>
                                <div><?= date('M d, Y', strtotime($a['appointment_date'])) ?></div>
                                <div class="text-muted text-small"><?= date('h:i A', strtotime($a['appointment_time'])) ?></div>
                            </td>
                            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($a['purpose']) ?>"><?= htmlspecialchars($a['purpose'] ?: '—') ?></td>
                            <td><span class="badge badge-<?= $status_colors[$a['status']] ?? 'secondary' ?>"><?= $a['status'] ?></span></td>
                            <td class="actions-cell">
                                <a href="?action=view&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline" title="View Details"><i class="fas fa-eye"></i></a>
                                <?php if (!$is_patient): ?>
                                <a href="?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="?action=delete&id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" title="Delete"
                                   onclick="return confirm('Delete appointment <?= htmlspecialchars(addslashes($a['appointment_id'])) ?>?')">
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
                <?php if ($total_pages > 1): ?>
                <div style="padding:16px 24px;">
                    <div class="pagination">
                        <?php for ($i=1;$i<=$total_pages;$i++): ?>
                        <a href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($filter_status)?>&dept=<?=$filter_dept?>&date=<?=urlencode($filter_date)?>"
                           class="page-link <?=$i===$page_num?'active':''?>"><?=$i?></a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-calendar-plus"></i> New Appointment</h4>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="add">
        <div class="modal-body">
            <div class="form-grid form-grid-2">
                <?php if ($is_patient): ?>
                <div class="col-span-2" style="background: var(--bg-cream-box); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary-red); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;">
                            <?= strtoupper(substr($_SESSION['full_name'] ?? 'P', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: 600;">Booking Patient</div>
                            <div style="font-size: 14.5px; font-weight: 700; color: var(--navy-dark);"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                        </div>
                    </div>
                    <span class="badge badge-success" style="font-size: 11.5px;"><i class="fas fa-check-circle"></i> Verified Patient</span>
                </div>
                <input type="hidden" name="patient_id" value="<?= $my_patient_id ?>">
                <input type="hidden" name="status" value="Pending">

                <div class="form-group">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-control" required style="width: 100%;">
                        <option value="">Select Department</option>
                        <?php foreach ($dept_list as $id => $name): ?>
                        <option value="<?=$id?>"><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Doctor / Attending Physician <span class="text-danger">*</span></label>
                    <select name="doctor_name" class="form-control" required style="width: 100%;">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors_list as $doc): ?>
                        <option value="<?= htmlspecialchars($doc) ?>"><?= htmlspecialchars($doc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Patient <span class="text-danger">*</span></label>
                    <select name="patient_id" class="form-control" required style="width: 100%;">
                        <option value="">Select Patient</option>
                        <?php foreach ($pt_list as $id => $name): ?>
                        <option value="<?=$id?>"><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-control" required style="width: 100%;">
                        <option value="">Select Department</option>
                        <?php foreach ($dept_list as $id => $name): ?>
                        <option value="<?=$id?>"><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Doctor / Attending Physician <span class="text-danger">*</span></label>
                    <select name="doctor_name" class="form-control" required style="width: 100%;">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors_list as $doc): ?>
                        <option value="<?= htmlspecialchars($doc) ?>"><?= htmlspecialchars($doc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" style="width: 100%;">
                        <option value="Pending">Pending</option>
                        <option value="Scheduled">Scheduled</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Appointment Date <span class="text-danger">*</span></label>
                    <input type="date" name="appointment_date" class="form-control" required value="<?=date('Y-m-d')?>" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label class="form-label">Appointment Time <span class="text-danger">*</span></label>
                    <input type="time" name="appointment_time" class="form-control" required value="08:00" style="width: 100%;">
                </div>

                <div class="form-group col-span-2">
                    <label class="form-label">Purpose / Reason for Visit</label>
                    <textarea name="purpose" class="form-control" rows="2" placeholder="Describe the purpose of the appointment..." style="width: 100%;"></textarea>
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Additional notes..." style="width: 100%;">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Appointment</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW / EDIT MODALS -->
<?php if ($view_appt && $action === 'view'): ?>
<div class="modal-overlay active" id="viewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-calendar-check"></i> Appointment Details — <?= htmlspecialchars($view_appt['appointment_id']) ?></h4>
            <a href="/hmsystem/appointments/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-item"><div class="label">Appointment ID</div><div class="value text-primary fw-600"><?= htmlspecialchars($view_appt['appointment_id']) ?></div></div>
                <div class="info-item"><div class="label">Patient</div><div class="value"><?= htmlspecialchars($view_appt['patient_name']) ?> (<?= htmlspecialchars($view_appt['pid']) ?>)</div></div>
                <div class="info-item"><div class="label">Department</div><div class="value"><?= htmlspecialchars($dept_list[$view_appt['department_id']] ?? '—') ?></div></div>
                <div class="info-item"><div class="label">Doctor</div><div class="value"><?= htmlspecialchars($view_appt['doctor_name'] ?: '—') ?></div></div>
                <div class="info-item"><div class="label">Date</div><div class="value"><?= date('F d, Y', strtotime($view_appt['appointment_date'])) ?></div></div>
                <div class="info-item"><div class="label">Time</div><div class="value"><?= date('h:i A', strtotime($view_appt['appointment_time'])) ?></div></div>
                <div class="info-item"><div class="label">Status</div><div class="value"><span class="badge badge-<?= $status_colors[$view_appt['status']] ?? 'secondary' ?>"><?= $view_appt['status'] ?></span></div></div>
                <div class="info-item" style="grid-column:1/-1;"><div class="label">Purpose</div><div class="value"><?= htmlspecialchars($view_appt['purpose'] ?: '—') ?></div></div>
                <?php if ($view_appt['notes']): ?><div class="info-item" style="grid-column:1/-1;"><div class="label">Notes</div><div class="value"><?= htmlspecialchars($view_appt['notes']) ?></div></div><?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/appointments/index.php" class="btn btn-secondary">Close</a>
            <a href="?action=edit&id=<?= $view_appt['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($view_appt && $action === 'edit'): ?>
<div class="modal-overlay active" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-calendar-pen"></i> Edit Appointment — <?= htmlspecialchars($view_appt['appointment_id']) ?></h4>
            <a href="/hmsystem/appointments/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="edit">
        <input type="hidden" name="edit_id" value="<?= $view_appt['id'] ?>">
        <div class="modal-body">
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Patient <span class="text-danger">*</span></label>
                    <select name="patient_id" class="form-control" required style="width: 100%;">
                        <?php foreach ($pt_list as $id => $name): ?>
                        <option value="<?=$id?>" <?=$view_appt['patient_id']==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <select name="department_id" class="form-control" required style="width: 100%;">
                        <?php foreach ($dept_list as $id => $name): ?>
                        <option value="<?=$id?>" <?=$view_appt['department_id']==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Doctor / Attending Physician <span class="text-danger">*</span></label>
                    <select name="doctor_name" class="form-control" required style="width: 100%;">
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors_list as $doc): ?>
                        <option value="<?= htmlspecialchars($doc) ?>" <?= (($view_appt['doctor_name']??'') === $doc) ? 'selected' : '' ?>><?= htmlspecialchars($doc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" style="width: 100%;">
                        <?php foreach (['Pending','Scheduled','Completed','Cancelled'] as $s): ?>
                        <option value="<?=$s?>" <?=$view_appt['status']===$s?'selected':''?>><?=$s?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Appointment Date <span class="text-danger">*</span></label>
                    <input type="date" name="appointment_date" class="form-control" required value="<?=$view_appt['appointment_date']?>" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label class="form-label">Appointment Time <span class="text-danger">*</span></label>
                    <input type="time" name="appointment_time" class="form-control" required value="<?=$view_appt['appointment_time']?>" style="width: 100%;">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Purpose / Reason for Visit</label>
                    <textarea name="purpose" class="form-control" rows="2" placeholder="Describe the purpose of the appointment..." style="width: 100%;"><?=htmlspecialchars($view_appt['purpose']??'')?></textarea>
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Additional notes..." value="<?=htmlspecialchars($view_appt['notes']??'')?>" style="width: 100%;">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/appointments/index.php" class="btn btn-secondary">Cancel</a>
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
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        openModal('addModal');
    }
});
</script>
<script src="/hmsystem/assets/js/main.js"></script>
</body>
</html>
<?php $conn->close(); ?>
