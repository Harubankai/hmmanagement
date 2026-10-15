<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$page_title = 'Billing Management';
$page_subtitle = 'Manage patient bills and charges';
$page_active = 'billing';

$message = ''; $error = '';
$action = $_GET['action'] ?? '';
$bill_db_id = intval($_GET['id'] ?? 0);
$filter_pid = intval($_GET['patient_id'] ?? 0);

// Patients for dropdown
$pts = $conn->query("SELECT id, patient_id, CONCAT(first_name,' ',last_name) as full_name FROM patients ORDER BY first_name");
$pt_list = [];
while ($p = $pts->fetch_assoc()) $pt_list[$p['id']] = $p['patient_id'] . ' - ' . $p['full_name'];

$is_patient = isPatient();
$my_patient_id = getPatientId();
if ($is_patient && !$my_patient_id && !empty($_SESSION['username'])) {
    $p_stmt = $conn->prepare("SELECT id FROM patients WHERE email = ? OR contact_number = ? LIMIT 1");
    $p_stmt->bind_param("ss", $_SESSION['username'], $_SESSION['username']);
    $p_stmt->execute();
    if ($p_res = $p_stmt->get_result()->fetch_assoc()) {
        $my_patient_id = $p_res['id'];
        $_SESSION['patient_id'] = $my_patient_id;
    }
    $p_stmt->close();
}

// DELETE bill
if ($action === 'delete' && $bill_db_id && !$is_patient) {
    $stmt = $conn->prepare("DELETE FROM bills WHERE id=?");
    $stmt->bind_param("i",$bill_db_id); $stmt->execute(); $stmt->close();
    logActivity($conn, $_SESSION['user_id'], 'Bill Deleted', "Deleted bill ID: $bill_db_id");
    $message = 'Bill deleted successfully.';
}

// CREATE bill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'add' && !$is_patient) {
    $new_id = generateID('BILL', 'bills', 'bill_id', $conn);
    $pid = intval($_POST['patient_id']);
    $date = $_POST['bill_date'];
    $notes = trim($_POST['notes']);
    $uid = $_SESSION['user_id'];

    $descriptions = $_POST['desc'] ?? [];
    $service_types = $_POST['service_type'] ?? [];
    $quantities = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];

    if (!$pid || !$date || empty($descriptions)) {
        $error = 'Please fill in all required fields and add at least one item.';
    } else {
        $total = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            if (!trim($desc)) continue;
            $qty = max(1, intval($quantities[$i]));
            $price = floatval($prices[$i]);
            $sub = $qty * $price;
            $total += $sub;
            $items[] = [trim($desc), $service_types[$i] ?? '', $qty, $price, $sub];
        }

        $stmt = $conn->prepare("INSERT INTO bills (bill_id,patient_id,bill_date,total_amount,amount_paid,balance,payment_status,notes,created_by) VALUES (?,?,?,?,0,?,?,?,?)");
        $status = 'Unpaid';
        $stmt->bind_param("sisddssi",$new_id,$pid,$date,$total,$total,$status,$notes,$uid);
        if ($stmt->execute()) {
            $bid = $conn->insert_id;
            $item_stmt = $conn->prepare("INSERT INTO bill_items (bill_id,description,service_type,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)");
            foreach ($items as $item) {
                $item_stmt->bind_param("issids", $bid, $item[0], $item[1], $item[2], $item[3], $item[4]);
                $item_stmt->execute();
            }
            $item_stmt->close();
            $pname = $pt_list[$pid] ?? "Patient #$pid";
            logActivity($conn, $uid, 'Bill Created', "Created bill $new_id for $pname, total: ".number_format($total,2));
            $message = "Bill $new_id created successfully. Total: ₱" . number_format($total, 2);
        } else { $error = 'Error creating bill.'; }
        $stmt->close();
    }
}

// Search/filter
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';

$where = "WHERE 1=1"; $params=[]; $types='';
if ($is_patient) {
    $where .= " AND b.patient_id=?";
    $params[] = $my_patient_id ? $my_patient_id : 0;
    $types .= 'i';
} elseif ($filter_pid) {
    $where .= " AND b.patient_id=?";
    $params[] = $filter_pid;
    $types .= 'i';
}
if ($search) {
    $s="%$search%";
    $where .= " AND (b.bill_id LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ?)";
    $params=array_merge($params,[$s,$s]); $types.='ss';
}
if ($filter_status) { $where .= " AND b.payment_status=?"; $params[]=$filter_status; $types.='s'; }

$per_page=12; $page_num=max(1,intval($_GET['page']??1)); $offset=($page_num-1)*$per_page;

$cq=$conn->prepare("SELECT COUNT(*) as cnt FROM bills b JOIN patients p ON b.patient_id=p.id $where");
if ($types) $cq->bind_param($types,...$params);
$cq->execute();
$total_rows=$cq->get_result()->fetch_assoc()['cnt']; $total_pages=ceil($total_rows/$per_page); $cq->close();

$ap=$params; $ap[]=$per_page; $ap[]=$offset; $at=$types.'ii';
$stmt=$conn->prepare("SELECT b.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid FROM bills b JOIN patients p ON b.patient_id=p.id $where ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($at,...$ap); $stmt->execute();
$bills=$stmt->get_result(); $stmt->close();

// View bill details
$view_bill = null; $bill_items_list = [];
if (in_array($action,['view']) && $bill_db_id) {
    $s=$conn->prepare("SELECT b.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid FROM bills b JOIN patients p ON b.patient_id=p.id WHERE b.id=?");
    $s->bind_param("i",$bill_db_id); $s->execute();
    $view_bill=$s->get_result()->fetch_assoc(); $s->close();
    $si=$conn->prepare("SELECT * FROM bill_items WHERE bill_id=? ORDER BY id");
    $si->bind_param("i",$bill_db_id); $si->execute();
    $bill_items_list=$si->get_result()->fetch_all(MYSQLI_ASSOC); $si->close();
    // Payments for this bill
    $sp=$conn->prepare("SELECT py.*, u.full_name as received_by_name FROM payments py LEFT JOIN users u ON py.received_by=u.id WHERE py.bill_id=? ORDER BY py.payment_date");
    $sp->bind_param("i",$bill_db_id); $sp->execute();
    $bill_payments=$sp->get_result()->fetch_all(MYSQLI_ASSOC); $sp->close();
}

$status_colors=['Unpaid'=>'danger','Partially Paid'=>'warning','Paid'=>'success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Gerona Hospital </title>
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
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>
            <?php if ($filter_pid): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle"></i>
                Billing history for: <strong><?=htmlspecialchars($pt_list[$filter_pid]??'Patient')?></strong>
                <a href="/hmsystem/billing/index.php" style="margin-left:12px;" class="btn btn-sm btn-secondary">Show All</a>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Bills (<?=number_format($total_rows)?>)</h3>
                    <?php if (!$is_patient): ?>
                    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Create Bill</button>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <form method="GET" class="filter-bar">
                        <?php if ($filter_pid): ?><input type="hidden" name="patient_id" value="<?=$filter_pid?>"><?php endif; ?>
                        <div class="search-box"><i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search by bill ID or patient..." value="<?=htmlspecialchars($search)?>">
                        </div>
                        <select name="status" class="form-control" style="width:160px;">
                            <option value="">All Status</option>
                            <?php foreach (['Unpaid','Partially Paid','Paid'] as $s): ?>
                            <option value="<?=$s?>" <?=$filter_status===$s?'selected':''?>><?=$s?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="/hmsystem/billing/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>Bill ID</th><th>Patient</th><th>Date</th><th>Total Amount</th>
                            <th>Amount Paid</th><th>Balance</th><th>Status</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($bills->num_rows===0): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-file-invoice-dollar"></i><h4>No bills found</h4><p>Create a new bill to get started.</p></div></td></tr>
                        <?php else: while ($b=$bills->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-600 text-primary"><?=htmlspecialchars($b['bill_id'])?></span></td>
                            <td>
                                <div class="fw-600"><?=htmlspecialchars($b['patient_name'])?></div>
                                <div class="text-muted text-small"><?=htmlspecialchars($b['pid'])?></div>
                            </td>
                            <td><?=date('M d, Y',strtotime($b['bill_date']))?></td>
                            <td class="fw-600">&#8369;<?=number_format($b['total_amount'],2)?></td>
                            <td class="text-success fw-600">&#8369;<?=number_format($b['amount_paid'],2)?></td>
                            <td class="<?=$b['balance']>0?'text-danger':'text-success'?> fw-600">&#8369;<?=number_format($b['balance'],2)?></td>
                            <td><span class="badge badge-<?=$status_colors[$b['payment_status']]??'secondary'?>"><?=$b['payment_status']?></span></td>
                            <td class="actions-cell">
                                <a href="?action=view&id=<?=$b['id']?>" class="btn btn-sm btn-outline" title="View Bill Statement"><i class="fas fa-eye"></i></a>
                                <?php if (!$is_patient): ?>
                                <a href="/hmsystem/payments/index.php?bill_id=<?=$b['id']?>" class="btn btn-sm btn-success" title="Record Payment"><i class="fas fa-money-bill"></i></a>
                                <a href="?action=delete&id=<?=$b['id']?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete bill <?=htmlspecialchars(addslashes($b['bill_id']))?> for <?=htmlspecialchars(addslashes($b['patient_name']))?> ?')">
                                   <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages>1): ?><div style="padding:16px 24px;"><div class="pagination">
                <?php for ($i=1;$i<=$total_pages;$i++): ?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($filter_status)?>" class="page-link <?=$i===$page_num?'active':''?>"><?=$i?></a>
                <?php endfor; ?></div></div><?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- CREATE BILL MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <h4><i class="fas fa-file-invoice-dollar"></i> Create New Bill</h4>
            <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="billForm">
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
                    <label class="form-label">Bill Date <span class="text-danger">*</span></label>
                    <input type="date" name="bill_date" class="form-control" required value="<?=date('Y-m-d')?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" placeholder="Bill notes or summary...">
            </div>

            <div style="margin-top:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <h5 style="font-size:14px;font-weight:700;color:var(--dark);"><i class="fas fa-list" style="color:var(--primary);"></i> Bill Items</h5>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addBillRow()"><i class="fas fa-plus"></i> Add Item</button>
                </div>
                <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;" id="billItemsTable">
                    <thead><tr style="background:var(--gray-100);">
                        <th style="padding:10px 12px;font-size:12px;font-weight:700;color:var(--gray-600);text-transform:uppercase;">Description</th>
                        <th style="padding:10px 12px;font-size:12px;font-weight:700;color:var(--gray-600);text-transform:uppercase;">Service Type</th>
                        <th style="padding:10px 12px;font-size:12px;font-weight:700;color:var(--gray-600);text-transform:uppercase;width:80px;">Qty</th>
                        <th style="padding:10px 12px;font-size:12px;font-weight:700;color:var(--gray-600);text-transform:uppercase;width:130px;">Unit Price (₱)</th>
                        <th style="padding:10px 12px;font-size:12px;font-weight:700;color:var(--gray-600);text-transform:uppercase;width:130px;">Subtotal</th>
                        <th style="padding:10px 12px;width:40px;"></th>
                    </tr></thead>
                    <tbody id="billItemsBody">
                        <tr class="bill-row">
                            <td style="padding:6px 8px;"><input type="text" name="desc[]" class="form-control" placeholder="e.g. Consultation Fee" required></td>
                            <td style="padding:6px 8px;"><select name="service_type[]" class="form-control">
                                <?php foreach (['Consultation','Laboratory','Radiology','Procedure','Medicine','Vaccination','Dental','Accommodation','Service','Other'] as $st): ?>
                                <option><?=$st?></option><?php endforeach; ?>
                            </select></td>
                            <td style="padding:6px 8px;"><input type="number" name="qty[]" class="form-control qty-inp" value="1" min="1" onchange="calcRow(this)"></td>
                            <td style="padding:6px 8px;"><input type="number" name="price[]" class="form-control price-inp" value="0" step="0.01" min="0" onchange="calcRow(this)"></td>
                            <td style="padding:6px 8px;"><input type="text" class="form-control subtotal-inp" readonly value="0.00" style="background:#f8f9fa;"></td>
                            <td style="padding:6px 8px;"><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                </table>
                </div>
                <div style="margin-top:14px;text-align:right;">
                    <div style="display:inline-block;background:var(--primary-light);padding:12px 24px;border-radius:10px;">
                        <span style="font-size:13px;color:var(--gray-600);">Total Amount: </span>
                        <span id="grandTotal" style="font-size:20px;font-weight:800;color:var(--primary);">₱0.00</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Bill</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW BILL MODAL -->
<?php if ($view_bill && $action==='view'): ?>
<div class="modal-overlay active" id="viewModal">
    <div class="modal modal-xl">
        <div class="modal-header">
            <h4><i class="fas fa-file-invoice-dollar"></i> Bill Details — <?=htmlspecialchars($view_bill['bill_id'])?></h4>
            <a href="/hmsystem/billing/index.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div>
                    <div class="info-item" style="margin-bottom:10px;"><div class="label">Bill ID</div><div class="value text-primary fw-600"><?=htmlspecialchars($view_bill['bill_id'])?></div></div>
                    <div class="info-item" style="margin-bottom:10px;"><div class="label">Patient</div><div class="value"><?=htmlspecialchars($view_bill['patient_name'])?> (<?=htmlspecialchars($view_bill['pid'])?>)</div></div>
                    <div class="info-item" style="margin-bottom:10px;"><div class="label">Bill Date</div><div class="value"><?=date('F d, Y',strtotime($view_bill['bill_date']))?></div></div>
                    <?php if ($view_bill['notes']): ?><div class="info-item"><div class="label">Notes</div><div class="value"><?=htmlspecialchars($view_bill['notes'])?></div></div><?php endif; ?>
                </div>
                <div style="background:var(--gray-100);border-radius:12px;padding:20px;">
                    <div style="margin-bottom:10px;display:flex;justify-content:space-between;"><span style="color:var(--gray-600);">Total Amount:</span><span class="fw-600 text-dark">&#8369;<?=number_format($view_bill['total_amount'],2)?></span></div>
                    <div style="margin-bottom:10px;display:flex;justify-content:space-between;"><span style="color:var(--gray-600);">Amount Paid:</span><span class="fw-600 text-success">&#8369;<?=number_format($view_bill['amount_paid'],2)?></span></div>
                    <div style="margin-bottom:14px;display:flex;justify-content:space-between;"><span style="color:var(--gray-600);">Balance:</span><span class="fw-600 <?=$view_bill['balance']>0?'text-danger':'text-success'?>">&#8369;<?=number_format($view_bill['balance'],2)?></span></div>
                    <div style="text-align:center;">
                        <span class="badge badge-<?=$status_colors[$view_bill['payment_status']]?>" style="font-size:13px;padding:6px 16px;"><?=$view_bill['payment_status']?></span>
                    </div>
                </div>
            </div>

            <!-- Bill Items -->
            <h5 style="font-weight:700;font-size:14px;margin-bottom:10px;color:var(--dark);"><i class="fas fa-list" style="color:var(--primary);"></i> Bill Items</h5>
            <div class="table-wrapper" style="margin-bottom:20px;">
                <table>
                    <thead><tr><th>Description</th><th>Service Type</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($bill_items_list as $item): ?>
                    <tr>
                        <td><?=htmlspecialchars($item['description'])?></td>
                        <td><?=htmlspecialchars($item['service_type']??'')?></td>
                        <td><?=htmlspecialchars($item['quantity'])?></td>
                        <td>&#8369;<?=number_format($item['unit_price'],2)?></td>
                        <td class="fw-600">&#8369;<?=number_format($item['subtotal'],2)?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:var(--gray-100);">
                        <td colspan="4" style="text-align:right;font-weight:700;">TOTAL</td>
                        <td class="fw-600 text-primary">&#8369;<?=number_format($view_bill['total_amount'],2)?></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Payment History -->
            <?php if (!empty($bill_payments)): ?>
            <h5 style="font-weight:700;font-size:14px;margin-bottom:10px;color:var(--dark);"><i class="fas fa-money-bill" style="color:var(--success);"></i> Payment History</h5>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Payment ID</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Received By</th></tr></thead>
                    <tbody>
                    <?php foreach ($bill_payments as $py): ?>
                    <tr>
                        <td class="fw-600 text-primary"><?=htmlspecialchars($py['payment_id'])?></td>
                        <td><?=date('M d, Y',strtotime($py['payment_date']))?></td>
                        <td class="fw-600 text-success">&#8369;<?=number_format($py['amount_paid'],2)?></td>
                        <td><?=htmlspecialchars($py['payment_method'])?></td>
                        <td><?=htmlspecialchars($py['reference_number']??'—')?></td>
                        <td><?=htmlspecialchars($py['received_by_name']??'—')?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <a href="/hmsystem/billing/index.php" class="btn btn-secondary">Close</a>
            <a href="/hmsystem/payments/index.php?bill_id=<?=$view_bill['id']?>" class="btn btn-success"><i class="fas fa-money-bill"></i> Record Payment</a>
            <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
document.querySelectorAll('.modal-overlay').forEach(el=>{
    el.addEventListener('click',function(e){if(e.target===el)closeModal(el.id);});
});

const serviceTypes = ['Consultation','Laboratory','Radiology','Procedure','Medicine','Vaccination','Dental','Accommodation','Service','Other'];

function addBillRow(){
    const tbody = document.getElementById('billItemsBody');
    const tr = document.createElement('tr');
    tr.className='bill-row';
    tr.innerHTML = `
        <td style="padding:6px 8px;"><input type="text" name="desc[]" class="form-control" placeholder="Description" required></td>
        <td style="padding:6px 8px;"><select name="service_type[]" class="form-control">${serviceTypes.map(t=>`<option>${t}</option>`).join('')}</select></td>
        <td style="padding:6px 8px;"><input type="number" name="qty[]" class="form-control qty-inp" value="1" min="1" onchange="calcRow(this)"></td>
        <td style="padding:6px 8px;"><input type="number" name="price[]" class="form-control price-inp" value="0" step="0.01" min="0" onchange="calcRow(this)"></td>
        <td style="padding:6px 8px;"><input type="text" class="form-control subtotal-inp" readonly value="0.00" style="background:#f8f9fa;"></td>
        <td style="padding:6px 8px;"><button type="button" class="btn btn-sm btn-danger btn-icon" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>`;
    tbody.appendChild(tr);
}

function removeRow(btn){
    const rows = document.querySelectorAll('.bill-row');
    if(rows.length > 1){ btn.closest('tr').remove(); updateTotal(); }
}

function calcRow(inp){
    const row = inp.closest('tr');
    const qty = parseFloat(row.querySelector('.qty-inp').value) || 0;
    const price = parseFloat(row.querySelector('.price-inp').value) || 0;
    const sub = qty * price;
    row.querySelector('.subtotal-inp').value = sub.toFixed(2);
    updateTotal();
}

function updateTotal(){
    let total = 0;
    document.querySelectorAll('.subtotal-inp').forEach(inp => { total += parseFloat(inp.value) || 0; });
    document.getElementById('grandTotal').textContent = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
}
</script>
</body>
</html>
<?php $conn->close(); ?>
