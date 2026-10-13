<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$page_title = 'Payments';
$page_subtitle = 'Record and manage patient payments';
$page_active = 'payments';

$message = ''; $error = '';
$action = $_GET['action'] ?? '';
$filter_bill_id = intval($_GET['bill_id'] ?? 0);

// Patients dropdown
$pts = $conn->query("SELECT id, patient_id, CONCAT(first_name,' ',last_name) as full_name FROM patients ORDER BY first_name");
$pt_list = [];
while ($p = $pts->fetch_assoc()) $pt_list[$p['id']] = $p['patient_id'] . ' - ' . $p['full_name'];

// Get unpaid/partial bills for dropdown
$bill_q = $conn->query("SELECT b.id, b.bill_id, b.patient_id, b.balance, CONCAT(p.first_name,' ',p.last_name) as pname FROM bills b JOIN patients p ON b.patient_id=p.id WHERE b.payment_status IN ('Unpaid','Partially Paid') ORDER BY b.bill_date DESC");
$bill_list = [];
while ($b = $bill_q->fetch_assoc()) $bill_list[$b['id']] = $b['bill_id'] . ' — ' . $b['pname'] . ' (Balance: ₱' . number_format($b['balance'],2) . ')';

// RECORD PAYMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action']??'') === 'pay') {
    $new_id = generateID('PAY', 'payments', 'payment_id', $conn);
    $bid = intval($_POST['bill_id']);
    $amount = floatval($_POST['amount_paid']);
    $method = $_POST['payment_method'];
    $ref = trim($_POST['reference_number']);
    $date = $_POST['payment_date'];
    $notes = trim($_POST['notes']);
    $uid = $_SESSION['user_id'];

    // Get bill info
    $bill_s = $conn->prepare("SELECT * FROM bills WHERE id=?");
    $bill_s->bind_param("i",$bid); $bill_s->execute();
    $bill_row = $bill_s->get_result()->fetch_assoc(); $bill_s->close();

    if (!$bill_row) { $error = 'Bill not found.'; }
    elseif ($amount <= 0) { $error = 'Amount must be greater than zero.'; }
    elseif ($amount > $bill_row['balance'] + 0.01) { $error = 'Amount paid cannot exceed the remaining balance (₱' . number_format($bill_row['balance'],2) . ').'; }
    else {
        $pid = $bill_row['patient_id'];
        $stmt = $conn->prepare("INSERT INTO payments (payment_id,bill_id,patient_id,payment_date,amount_paid,payment_method,reference_number,received_by,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("siisdsssi",$new_id,$bid,$pid,$date,$amount,$method,$ref,$uid,$notes);
        if ($stmt->execute()) {
            // Update bill
            $new_paid = $bill_row['amount_paid'] + $amount;
            $new_balance = $bill_row['total_amount'] - $new_paid;
            $new_status = $new_balance <= 0.001 ? 'Paid' : 'Partially Paid';
            $upd = $conn->prepare("UPDATE bills SET amount_paid=?,balance=?,payment_status=? WHERE id=?");
            $upd->bind_param("ddsi",$new_paid,$new_balance,$new_status,$bid);
            $upd->execute(); $upd->close();

            $pname = $pt_list[$pid] ?? "Patient #$pid";
            logActivity($conn, $uid, 'Payment Recorded', "Recorded payment $new_id of ₱".number_format($amount,2)." for bill {$bill_row['bill_id']}");
            $message = "Payment $new_id of ₱" . number_format($amount,2) . " recorded successfully. Bill status: $new_status.";
            // Refresh bill list
            $bill_q2 = $conn->query("SELECT b.id, b.bill_id, b.patient_id, b.balance, CONCAT(p.first_name,' ',p.last_name) as pname FROM bills b JOIN patients p ON b.patient_id=p.id WHERE b.payment_status IN ('Unpaid','Partially Paid') ORDER BY b.bill_date DESC");
            $bill_list = [];
            while ($b = $bill_q2->fetch_assoc()) $bill_list[$b['id']] = $b['bill_id'] . ' — ' . $b['pname'] . ' (Balance: ₱' . number_format($b['balance'],2) . ')';
        } else { $error = 'Error recording payment.'; }
        $stmt->close();
    }
}

// Search/filter
$search = trim($_GET['search'] ?? '');
$filter_method = $_GET['method'] ?? '';

$where = "WHERE 1=1"; $params=[]; $types='';
if ($search) {
    $s="%$search%";
    $where .= " AND (py.payment_id LIKE ? OR CONCAT(p.first_name,' ',p.last_name) LIKE ? OR b.bill_id LIKE ?)";
    $params=[$s,$s,$s]; $types='sss';
}
if ($filter_method) { $where .= " AND py.payment_method=?"; $params[]=$filter_method; $types.='s'; }
if ($filter_bill_id) { $where .= " AND py.bill_id=?"; $params[]=$filter_bill_id; $types.='i'; }

$per_page=15; $page_num=max(1,intval($_GET['page']??1)); $offset=($page_num-1)*$per_page;

$cq=$conn->prepare("SELECT COUNT(*) as cnt FROM payments py JOIN patients p ON py.patient_id=p.id JOIN bills b ON py.bill_id=b.id $where");
if ($types) $cq->bind_param($types,...$params);
$cq->execute();
$total_rows=$cq->get_result()->fetch_assoc()['cnt']; $total_pages=ceil($total_rows/$per_page); $cq->close();

$ap=$params; $ap[]=$per_page; $ap[]=$offset; $at=$types.'ii';
$stmt=$conn->prepare("SELECT py.*, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, b.bill_id as bill_ref, b.total_amount as bill_total, u.full_name as received_by_name FROM payments py JOIN patients p ON py.patient_id=p.id JOIN bills b ON py.bill_id=b.id LEFT JOIN users u ON py.received_by=u.id $where ORDER BY py.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($at,...$ap); $stmt->execute();
$payments=$stmt->get_result(); $stmt->close();

// Pre-fill bill if from billing
$prefill_bill = null;
if ($filter_bill_id) {
    $bf = $conn->prepare("SELECT b.*, CONCAT(p.first_name,' ',p.last_name) as pname FROM bills b JOIN patients p ON b.patient_id=p.id WHERE b.id=?");
    $bf->bind_param("i",$filter_bill_id); $bf->execute();
    $prefill_bill = $bf->get_result()->fetch_assoc(); $bf->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Gerona Hospital IBMS</title>
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
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

            <?php if ($prefill_bill): ?>
            <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;">
                <span><i class="fas fa-info-circle"></i> Recording payment for Bill: <strong><?=htmlspecialchars($prefill_bill['bill_id'])?></strong> | Patient: <strong><?=htmlspecialchars($prefill_bill['pname'])?></strong> | Balance: <strong>₱<?=number_format($prefill_bill['balance'],2)?></strong></span>
                <button class="btn btn-primary btn-sm" onclick="openModal('payModal')"><i class="fas fa-money-bill"></i> Record Payment</button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Payment Records (<?=number_format($total_rows)?>)</h3>
                    <button class="btn btn-success" onclick="openModal('payModal')"><i class="fas fa-plus"></i> Record Payment</button>
                </div>
                <div class="card-body" style="padding-bottom:0;">
                    <form method="GET" class="filter-bar">
                        <div class="search-box"><i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search payments..." value="<?=htmlspecialchars($search)?>">
                        </div>
                        <select name="method" class="form-control" style="width:160px;">
                            <option value="">All Methods</option>
                            <?php foreach (['Cash','GCash','Bank Transfer','Other'] as $m): ?>
                            <option value="<?=$m?>" <?=$filter_method===$m?'selected':''?>><?=$m?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="/hmsystem/payments/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>Payment ID</th><th>Bill ID</th><th>Patient</th><th>Date</th>
                            <th>Amount Paid</th><th>Method</th><th>Reference #</th><th>Received By</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($payments->num_rows===0): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-money-bill-wave"></i><h4>No payment records found</h4><p>Record a payment to get started.</p></div></td></tr>
                        <?php else: while ($py=$payments->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-600 text-primary"><?=htmlspecialchars($py['payment_id'])?></span></td>
                            <td><a href="/hmsystem/billing/index.php?action=view&id=<?=$py['bill_id']?>" class="text-primary"><?=htmlspecialchars($py['bill_ref'])?></a></td>
                            <td>
                                <div class="fw-600"><?=htmlspecialchars($py['patient_name'])?></div>
                                <div class="text-muted text-small"><?=htmlspecialchars($py['pid'])?></div>
                            </td>
                            <td><?=date('M d, Y',strtotime($py['payment_date']))?></td>
                            <td class="fw-600 text-success">&#8369;<?=number_format($py['amount_paid'],2)?></td>
                            <td>
                                <?php
                                $method_icons=['Cash'=>'fa-money-bill','GCash'=>'fa-mobile-alt','Bank Transfer'=>'fa-university','Other'=>'fa-credit-card'];
                                ?>
                                <i class="fas <?=$method_icons[$py['payment_method']]??'fa-money-bill'?>" style="color:var(--primary);margin-right:4px;"></i>
                                <?=htmlspecialchars($py['payment_method'])?>
                            </td>
                            <td><?=htmlspecialchars($py['reference_number']??'—')?></td>
                            <td><?=htmlspecialchars($py['received_by_name']??'—')?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages>1): ?><div style="padding:16px 24px;"><div class="pagination">
                <?php for ($i=1;$i<=$total_pages;$i++): ?>
                <a href="?page=<?=$i?>&search=<?=urlencode($search)?>&method=<?=urlencode($filter_method)?>" class="page-link <?=$i===$page_num?'active':''?>"><?=$i?></a>
                <?php endfor; ?></div></div><?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- RECORD PAYMENT MODAL -->
<div class="modal-overlay <?=$filter_bill_id&&!$message?'active':''?>" id="payModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-money-bill-wave"></i> Record Payment</h4>
            <button class="modal-close" onclick="closeModal('payModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
        <input type="hidden" name="form_action" value="pay">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Bill <span class="text-danger">*</span></label>
                <select name="bill_id" class="form-control" required id="billSelect" onchange="loadBillBalance(this)">
                    <option value="">Select Bill (Unpaid / Partially Paid)</option>
                    <?php foreach ($bill_list as $id => $label): ?>
                    <option value="<?=$id?>" <?=$filter_bill_id==$id?'selected':''?> data-balance="<?=/* fetch balance */ ''?>"><?=htmlspecialchars($label)?></option>
                    <?php endforeach; ?>
                </select>
                <div id="billBalanceInfo" style="margin-top:6px;display:none;" class="alert alert-info" style="padding:8px 12px;font-size:13px;"></div>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control" required value="<?=date('Y-m-d')?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Amount Paid (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="amount_paid" class="form-control" required step="0.01" min="0.01"
                           value="<?= $prefill_bill ? number_format($prefill_bill['balance'], 2, '.', '') : '' ?>">
                </div>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-control" required>
                        <?php foreach (['Cash','GCash','Bank Transfer','Other'] as $m): ?>
                        <option value="<?=$m?>"><?=$m?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" placeholder="Optional (for GCash/Bank)">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Additional payment notes..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('payModal')">Cancel</button>
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Payment</button>
        </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
document.querySelectorAll('.modal-overlay').forEach(el=>{
    el.addEventListener('click',function(e){if(e.target===el)closeModal(el.id);});
});

// Bill balances (for reference)
const billBalances = {
    <?php
    $bq = $conn->query("SELECT id, balance FROM bills WHERE payment_status IN ('Unpaid','Partially Paid')");
    $bal_arr = [];
    while ($br = $bq->fetch_assoc()) $bal_arr[] = $br['id'] . ': ' . $br['balance'];
    echo implode(",\n    ", $bal_arr);
    ?>
};

function loadBillBalance(sel){
    const id = sel.value;
    const info = document.getElementById('billBalanceInfo');
    if(id && billBalances[id] !== undefined){
        info.style.display='block';
        info.innerHTML = '<i class="fas fa-info-circle"></i> Remaining balance: <strong>₱' + parseFloat(billBalances[id]).toLocaleString('en-PH',{minimumFractionDigits:2}) + '</strong>';
    } else { info.style.display='none'; }
}
</script>
</body>
</html>
<?php $conn->close(); ?>
