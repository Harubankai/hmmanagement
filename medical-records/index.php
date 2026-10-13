<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();
$page_title = 'Medical Records';
$page_subtitle = 'Patient medical history and consultation records';
$page_active = 'medical-records';

$message = ''; $error = '';
$action = $_GET['action'] ?? '';
$rec_id = intval($_GET['id'] ?? 0);
$filter_pid = intval($_GET['patient_id'] ?? 0);

// Patients for dropdown
$pts = $conn->query("SELECT id, patient_id, CONCAT(first_name,' ',last_name) as full_name FROM patients ORDER BY first_name");
$pt_list = [];
while ($p = $pts->fetch_assoc()) $pt_list[$p['id']] = $p['patient_id'] . ' - ' . $p['full_name'];

// Staff for dropdown
$staff = $conn->query("SELECT id, full_name FROM users WHERE status='active' ORDER BY full_name");
$staff_list = [];
while ($s = $staff->fetch_assoc()) $staff_list[$s['id']] = $s['full_name'];

// DELETE
if ($action === 'delete' && $rec_id) {
    $stmt = $conn->prepare("DELETE FROM medical_records WHERE id=?");
    $stmt->bind_param("i", $rec_id);
    $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'Medical Record Deleted', "Deleted medical record ID: $rec_id");
    $message = 'Medical record deleted successfully.';
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'add') {
    $new_id = generateID('MR', 'medical_records', 'record_id', $conn);
    $pid = intval($_POST['patient_id']);
    $date = $_POST['record_date'];
    $staff_id = intval($_POST['attending_staff']) ?: null;
    $complaint = trim($_POST['chief_complaint']);
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $prescription = trim($_POST['prescription']);
    $notes = trim($_POST['notes']);

    if (!$pid || !$date) {
        $error = 'Patient and date are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO medical_records (record_id,patient_id,record_date,attending_staff,chief_complaint,diagnosis,treatment,prescription,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sisssssss",$new_id,$pid,$date,$staff_id,$complaint,$diagnosis,$treatment,$prescription,$notes);
        if ($stmt->execute()) {
            $pname = $pt_list[$pid] ?? "Patient #$pid";
            logActivity($conn, $_SESSION['user_id'], 'Medical Record Added', "Added medical record $new_id for $pname");
            $message = "Medical record $new_id added successfully.";
        } else { $error = 'Error saving record.'; }
        $stmt->close();
    }
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'edit') {
    $eid = intval($_POST['edit_id']);
    $pid = intval($_POST['patient_id']);
    $date = $_POST['record_date'];
    $staff_id = intval($_POST['attending_staff']) ?: null;
    $complaint = trim($_POST['chief_complaint']);
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $prescription = trim($_POST['prescription']);
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("UPDATE medical_records SET patient_id=?,record_date=?,attending_staff=?,chief_complaint=?,diagnosis=?,treatment=?,prescription=?,notes=? WHERE id=?");
    $stmt->bind_param("isissssi", $pid,$date,$staff_id,$complaint,$diagnosis,$treatment,$prescription,$notes,$eid);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Medical Record Updated', "Updated medical record ID: $eid");
        $message = 'Medical record updated successfully.';
    } else { $error = 'Error updating record.'; }
    $stmt->close();
}

// Search/filter
$search = trim($_GET['search'] ?? '');
$where = "WHERE 1=1"; $params = []; $types = '';

if ($filter_pid) { $where .= " AND mr.patient_id=?"; $params[] = $filter_pid; $types .= 'i'; }
if ($search) {
    $s = "%$search%";
    $where .= " AND (mr.record_id LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR mr.diagnosis LIKE ?)";
    $params = array_merge($params, [$s,$s,$s]); $types .= 'sss';
}

$per_page = 12; $page_num = max(1, intval($_GET['page']??1)); $offset = ($page_num-1)*$per_page;

$cq = $conn->prepare("SELECT COUNT(*) as cnt FROM medical_records mr JOIN patients p ON mr.patient_id=p.id $where");
if ($types) $cq->bind_param($types,...$params);
$cq->execute();
$total_rows = $cq->get_result()->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows/$per_page);
$cq->close();

$all_p = $params; $all_p[] = $per_page; $all_p[] = $offset; $all_t = $types.'ii';
$stmt = $conn->prepare("SELECT mr.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, u.full_name as staff_name FROM medical_records mr JOIN patients p ON mr.patient_id=p.id LEFT JOIN users u ON mr.attending_staff=u.id $where ORDER BY mr.record_date DESC LIMIT ? OFFSET ?");
$stmt->bind_param($all_t,...$all_p); $stmt->execute();
$records = $stmt->get_result(); $stmt->close();

$view_rec = null;
if (in_array($action,['view','edit']) && $rec_id) {
    $s = $conn->prepare("SELECT mr.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, u.full_name as staff_name FROM medical_records mr JOIN patients p ON mr.patient_id=p.id LEFT JOIN users u ON mr.attending_staff=u.id WHERE mr.id=?");
    $s->bind_param("i",$rec_id); $s->execute();
    $view_rec = $s->get_result()->fetch_assoc(); $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Gerona Hospital IBMS</title>
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
                Showing medical records for: <strong><?= htmlspecialchars($pt_list[$filter_pid] ?? "Patient #$filter_pid") ?></strong>
                <a href="/hmsystem/medical-records/index.php" style="margin-left:12px;" class="btn btn-sm btn-secondary">Show All</a>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-medical"></i> Medical Records (<?= number_format($total_rows) ?>)</h3>
                    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Record</button>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <form method="GET" class="filter-bar">
                        <?php if ($filter_pid): ?><input type="hidden" name="patient_id" value="<?=$filter_pid?>"><?php endif; ?>
                        <div class="search-box"><i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search by patient, diagnosis..." value="<?=htmlspecialchars($search)?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Search</button>
                        <a href="/hmsystem/medical-records/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>Record ID</th><th>Patient</th><th>Date</th><th>Attending Staff</th>
                            <th>Chief Complaint</th><th>Diagnosis</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($records->num_rows === 0): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-file-medical"></i><h4>No records found</h4><p>Add a new medical record to get started.</p></div></td></tr>
                        <?php else: ?>
                        <?php while ($r = $records->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-600 text-primary"><?= htmlspecialchars($r['record_id']) ?></span></td>
                            <td>
                                <div class="fw-600"><?= htmlspecialchars($r['patient_name']) ?></div>
                                <div class="text-muted text-small"><?= htmlspecialchars($r['pid']) ?></div>
                            </td>
                            <td><?= date('M d, Y', strtotime($r['record_date'])) ?></td>
                            <td><?= htmlspecialchars($r['staff_name'] ?: '—') ?></td>
                            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?=htmlspecialchars($r['chief_complaint'])?>"><?= htmlspecialchars($r['chief_complaint'] ?: '—') ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?=htmlspecialchars($r['diagnosis'])?>"><?= htmlspecialchars($r['diagnosis'] ?: '—') ?></td>
                            <td class="actions-cell">
                                <a href="?action=view&id=<?=$r['id']?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
                                <a href="?action=edit&id=<?=$r['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="?action=delete&id=<?=$r['id']?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this medical record?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages>1): ?><div style="padding:16px 24px;"><div class="pagination">
                <?php for ($i=1;$i<=$total_pages;$i++): ?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>" class="page-link <?=$i===$page_num?'active':''?>"><?=$i?></a>
                <?php endfor; ?></div></div><?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-file-medical-alt"></i> Add Medical Record</h4>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="add">
        <div class="modal-body">
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Patient <span class="text-danger">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($pt_list as $id => $name): ?>
                        <option value="<?=$id?>" <?=$filter_pid==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="record_date" class="form-control" required value="<?=date('Y-m-d')?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Attending Staff / Doctor</label>
                <select name="attending_staff" class="form-control">
                    <option value="">Select Staff</option>
                    <?php foreach ($staff_list as $id => $name): ?>
                    <option value="<?=$id?>"><?=htmlspecialchars($name)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Chief Complaint</label>
                <textarea name="chief_complaint" class="form-control" rows="2" placeholder="Patient's main complaint or symptoms..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Diagnosis</label>
                <textarea name="diagnosis" class="form-control" rows="2" placeholder="Diagnosis or findings..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment</label>
                <textarea name="treatment" class="form-control" rows="2" placeholder="Treatment plan or procedures performed..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Prescription</label>
                <textarea name="prescription" class="form-control" rows="2" placeholder="Medications prescribed..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes or follow-up instructions..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Record</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<?php if ($view_rec && $action==='view'): ?>
<div class="modal-overlay active" id="viewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-file-medical"></i> Medical Record — <?=htmlspecialchars($view_rec['record_id'])?></h4>
            <a href="/hmsystem/medical-records/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-item"><div class="label">Record ID</div><div class="value text-primary fw-600"><?=htmlspecialchars($view_rec['record_id'])?></div></div>
                <div class="info-item"><div class="label">Patient</div><div class="value"><?=htmlspecialchars($view_rec['patient_name'])?> (<?=htmlspecialchars($view_rec['pid'])?>)</div></div>
                <div class="info-item"><div class="label">Date</div><div class="value"><?=date('F d, Y',strtotime($view_rec['record_date']))?></div></div>
                <div class="info-item"><div class="label">Attending Staff</div><div class="value"><?=htmlspecialchars($view_rec['staff_name']??'—')?></div></div>
            </div>
            <?php
            $fields = ['chief_complaint'=>'Chief Complaint','diagnosis'=>'Diagnosis','treatment'=>'Treatment','prescription'=>'Prescription','notes'=>'Notes'];
            foreach ($fields as $fk => $fl):
                if (!empty($view_rec[$fk])):
            ?>
            <div style="margin-top:16px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:6px;"><?=$fl?></div>
                <div style="background:#f8f9fa;padding:12px 16px;border-radius:8px;font-size:13.5px;line-height:1.6;"><?=nl2br(htmlspecialchars($view_rec[$fk]))?></div>
            </div>
            <?php endif; endforeach; ?>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/medical-records/index.php" class="btn btn-secondary">Close</a>
            <a href="?action=edit&id=<?=$view_rec['id']?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- EDIT MODAL -->
<?php if ($view_rec && $action==='edit'): ?>
<div class="modal-overlay active" id="editModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-file-pen"></i> Edit Medical Record — <?=htmlspecialchars($view_rec['record_id'])?></h4>
            <a href="/hmsystem/medical-records/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="edit">
        <input type="hidden" name="edit_id" value="<?=$view_rec['id']?>">
        <div class="modal-body">
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-control" required>
                        <?php foreach ($pt_list as $id => $name): ?>
                        <option value="<?=$id?>" <?=$view_rec['patient_id']==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="record_date" class="form-control" required value="<?=$view_rec['record_date']?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Attending Staff</label>
                <select name="attending_staff" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($staff_list as $id => $name): ?>
                    <option value="<?=$id?>" <?=$view_rec['attending_staff']==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php foreach (['chief_complaint'=>'Chief Complaint','diagnosis'=>'Diagnosis','treatment'=>'Treatment','prescription'=>'Prescription','notes'=>'Notes'] as $fk => $fl): ?>
            <div class="form-group">
                <label class="form-label"><?=$fl?></label>
                <textarea name="<?=$fk?>" class="form-control" rows="2"><?=htmlspecialchars($view_rec[$fk]??'')?></textarea>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/medical-records/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
document.querySelectorAll('.modal-overlay').forEach(el=>{
    el.addEventListener('click',function(e){if(e.target===el)closeModal(el.id);});
});
</script>
</body>
</html>
<?php $conn->close(); ?>
