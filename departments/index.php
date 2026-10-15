<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$page_title = 'Departments';
$page_subtitle = 'Manage hospital departments and services';
$page_active = 'departments';

$message = ''; $error = '';
$action = $_GET['action'] ?? '';
$dept_id = intval($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $dept_id) {
    requireAdmin();
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE department_id=?");
    $check->bind_param("i", $dept_id); $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt']; $check->close();
    if ($cnt > 0) {
        $error = "Cannot delete this department. It has $cnt linked appointment(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id=?");
        $stmt->bind_param("i", $dept_id); $stmt->execute(); $stmt->close();
        logActivity($conn, $_SESSION['user_id'], 'Department Deleted', "Deleted department ID: $dept_id");
        $message = 'Department deleted successfully.';
    }
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'add') {
    requireAdmin();
    $name = trim($_POST['name']); $desc = trim($_POST['description']);
    $head = trim($_POST['head_doctor']); $contact = trim($_POST['contact_number']);
    $location = trim($_POST['location']); $status = $_POST['status'];
    if (!$name) { $error = 'Department name is required.'; }
    else {
        $stmt = $conn->prepare("INSERT INTO departments (name,description,head_doctor,contact_number,location,status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssss",$name,$desc,$head,$contact,$location,$status);
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user_id'], 'Department Added', "Added department: $name");
            $message = "Department '$name' added successfully.";
        } else { $error = 'Error adding department.'; }
        $stmt->close();
    }
}

// EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'edit') {
    requireAdmin();
    $eid = intval($_POST['edit_id']); $name = trim($_POST['name']); $desc = trim($_POST['description']);
    $head = trim($_POST['head_doctor']); $contact = trim($_POST['contact_number']);
    $location = trim($_POST['location']); $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE departments SET name=?,description=?,head_doctor=?,contact_number=?,location=?,status=? WHERE id=?");
    $stmt->bind_param("ssssssi",$name,$desc,$head,$contact,$location,$status,$eid);
    if ($stmt->execute()) {
        logActivity($conn, $_SESSION['user_id'], 'Department Updated', "Updated department: $name");
        $message = "Department updated successfully.";
    } else { $error = 'Error updating department.'; }
    $stmt->close();
}

$search = trim($_GET['search'] ?? '');
$where = "WHERE 1=1"; $params=[]; $types='';
if ($search) {
    $s="%$search%"; $where .= " AND (name LIKE ? OR head_doctor LIKE ? OR location LIKE ?)";
    $params=[$s,$s,$s]; $types='sss';
}

$cq = $conn->prepare("SELECT COUNT(*) as cnt FROM departments $where");
if ($types) $cq->bind_param($types,...$params);
$cq->execute();
$total_rows = $cq->get_result()->fetch_assoc()['cnt'];
$cq->close();

$stmt = $conn->prepare("SELECT d.*, (SELECT COUNT(*) FROM appointments a WHERE a.department_id=d.id) as appt_count FROM departments d $where ORDER BY d.name");
if ($types) $stmt->bind_param($types,...$params);
$stmt->execute();
$departments = $stmt->get_result();
$stmt->close();

$view_dept = null;
if (in_array($action,['edit']) && $dept_id) {
    $s = $conn->prepare("SELECT * FROM departments WHERE id=?");
    $s->bind_param("i",$dept_id); $s->execute();
    $view_dept = $s->get_result()->fetch_assoc(); $s->close();
}

$dept_icons = ['General Medicine'=>'fa-stethoscope','Pediatrics'=>'fa-baby','Emergency'=>'fa-truck-medical','Laboratory'=>'fa-flask','Pharmacy'=>'fa-pills','Radiology'=>'fa-x-ray','Dental'=>'fa-tooth'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Gerona Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
    <style>
    .dept-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(300px,1fr)); gap: 20px; padding: 24px; }
    .dept-card { border: 1.5px solid var(--gray-200); border-radius: 12px; padding: 22px; transition: var(--transition); position: relative; }
    .dept-card:hover { border-color: var(--primary); box-shadow: var(--shadow); transform: translateY(-2px); }
    .dept-card-header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
    .dept-icon { width: 48px; height: 48px; background: var(--primary-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .dept-icon i { color: var(--primary); font-size: 20px; }
    .dept-card-title { font-size: 15px; font-weight: 700; color: var(--dark); }
    .dept-card-desc { font-size: 12.5px; color: var(--gray-600); line-height: 1.5; margin-bottom: 14px; }
    .dept-meta { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .dept-meta-item { display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: var(--gray-700); }
    .dept-meta-item i { color: var(--gray-500); width: 14px; flex-shrink: 0; margin-top: 2px; }
    .dept-card-actions { display: flex; gap: 8px; padding-top: 14px; border-top: 1px solid var(--gray-200); }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="page-body">
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building-columns"></i> Departments (<?=number_format($total_rows)?>)</h3>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Department</button>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <form method="GET" class="filter-bar">
                        <div class="search-box"><i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search departments..." value="<?=htmlspecialchars($search)?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Search</button>
                        <a href="/hmsystem/departments/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>
                <div class="dept-grid">
                    <?php while ($d = $departments->fetch_assoc()):
                        $icon = $dept_icons[$d['name']] ?? 'fa-hospital';
                    ?>
                    <div class="dept-card">
                        <div class="dept-card-header">
                            <div class="dept-icon"><i class="fas <?=$icon?>"></i></div>
                            <div>
                                <div class="dept-card-title"><?=htmlspecialchars($d['name'])?></div>
                                <span class="badge badge-<?=$d['status']==='active'?'success':'secondary'?>"><?=ucfirst($d['status'])?></span>
                            </div>
                        </div>
                        <div class="dept-card-desc"><?=htmlspecialchars($d['description']??'')?></div>
                        <div class="dept-meta">
                            <?php if ($d['head_doctor']): ?>
                            <div class="dept-meta-item"><i class="fas fa-user-md"></i> <?=htmlspecialchars($d['head_doctor'])?></div>
                            <?php endif; ?>
                            <?php if ($d['contact_number']): ?>
                            <div class="dept-meta-item"><i class="fas fa-phone"></i> <?=htmlspecialchars($d['contact_number'])?></div>
                            <?php endif; ?>
                            <?php if ($d['location']): ?>
                            <div class="dept-meta-item"><i class="fas fa-location-dot"></i> <?=htmlspecialchars($d['location'])?></div>
                            <?php endif; ?>
                            <div class="dept-meta-item"><i class="fas fa-calendar-check"></i> <?=number_format($d['appt_count'])?> appointment(s)</div>
                        </div>
                        <?php if (isAdmin()): ?>
                        <div class="dept-card-actions">
                            <a href="?action=edit&id=<?=$d['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
                            <a href="?action=delete&id=<?=$d['id']?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete department: <?=htmlspecialchars(addslashes($d['name']))?>')?">
                               <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ADD MODAL -->
<?php if (isAdmin()): ?>
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h4><i class="fas fa-building-columns"></i> Add Department</h4>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="add">
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Department Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea></div>
            <div class="form-group"><label class="form-label">Head Doctor</label>
                <input type="text" name="head_doctor" class="form-control"></div>
            <div class="form-grid form-grid-2">
                <div class="form-group"><label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control"></div>
                <div class="form-group"><label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Department</button>
        </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<?php if ($view_dept && $action==='edit'): ?>
<div class="modal-overlay active" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h4><i class="fas fa-pen"></i> Edit Department</h4>
            <a href="/hmsystem/departments/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="edit">
        <input type="hidden" name="edit_id" value="<?=$view_dept['id']?>">
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($view_dept['name'])?>"></div>
            <div class="form-group"><label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?=htmlspecialchars($view_dept['description']??'')?></textarea></div>
            <div class="form-group"><label class="form-label">Head Doctor</label>
                <input type="text" name="head_doctor" class="form-control" value="<?=htmlspecialchars($view_dept['head_doctor']??'')?>"></div>
            <div class="form-grid form-grid-2">
                <div class="form-group"><label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?=htmlspecialchars($view_dept['contact_number']??'')?>"></div>
                <div class="form-group"><label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?=htmlspecialchars($view_dept['location']??'')?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?=$view_dept['status']==='active'?'selected':''?>>Active</option>
                    <option value="inactive" <?=$view_dept['status']==='inactive'?'selected':''?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/departments/index.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
        </form>
    </div>
</div>
<?php endif; ?>
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
