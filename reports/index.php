<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$page_title = 'Reports';
$page_subtitle = 'Generate and export hospital reports';
$page_active = 'reports';

$report_type = $_GET['report'] ?? 'patients';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$filter_pid = intval($_GET['patient_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_dept = intval($_GET['dept'] ?? 0);

// Departments for filter
$depts = $conn->query("SELECT id, name FROM departments ORDER BY name");
$dept_list = []; while ($d=$depts->fetch_assoc()) $dept_list[$d['id']]=$d['name'];

// Patients for filter
$pts = $conn->query("SELECT id, patient_id, CONCAT(first_name,' ',last_name) as full_name FROM patients ORDER BY first_name");
$pt_list = []; while ($p=$pts->fetch_assoc()) $pt_list[$p['id']]=$p['patient_id'].' - '.$p['full_name'];

$report_data = null;
$report_title = '';
$summary_stats = [];

switch ($report_type) {
    case 'patients':
        $report_title = 'Patient Report';
        $where = "WHERE registration_date BETWEEN ? AND ?";
        $params = [$date_from, $date_to]; $types = 'ss';
        if ($filter_status) { $where .= " AND status=?"; $params[]=$filter_status; $types.='s'; }
        $stmt = $conn->prepare("SELECT * FROM patients $where ORDER BY registration_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $report_data = $stmt->get_result(); $stmt->close();
        $summary_stats = [
            'Total' => $conn->query("SELECT COUNT(*) as c FROM patients WHERE registration_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
            'Active' => $conn->query("SELECT COUNT(*) as c FROM patients WHERE status='Active' AND registration_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
        ];
        break;

    case 'appointments':
        $report_title = 'Appointment Report';
        $where = "WHERE a.appointment_date BETWEEN ? AND ?";
        $params = [$date_from,$date_to]; $types = 'ss';
        if ($filter_status) { $where .= " AND a.status=?"; $params[]=$filter_status; $types.='s'; }
        if ($filter_dept) { $where .= " AND a.department_id=?"; $params[]=$filter_dept; $types.='i'; }
        if ($filter_pid) { $where .= " AND a.patient_id=?"; $params[]=$filter_pid; $types.='i'; }
        $stmt = $conn->prepare("SELECT a.appointment_id, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, d.name as dept_name, a.doctor_name, a.appointment_date, a.appointment_time, a.purpose, a.status FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN departments d ON a.department_id=d.id $where ORDER BY a.appointment_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $report_data = $stmt->get_result(); $stmt->close();
        $summary_stats = [
            'Total' => $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
            'Completed' => $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Completed' AND appointment_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
            'Cancelled' => $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Cancelled' AND appointment_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
        ];
        break;

    case 'medical_records':
        $report_title = 'Medical Records Report';
        $where = "WHERE mr.record_date BETWEEN ? AND ?";
        $params = [$date_from,$date_to]; $types = 'ss';
        if ($filter_pid) { $where .= " AND mr.patient_id=?"; $params[]=$filter_pid; $types.='i'; }
        $stmt = $conn->prepare("SELECT mr.record_id, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, mr.record_date, u.full_name as staff_name, mr.diagnosis FROM medical_records mr JOIN patients p ON mr.patient_id=p.id LEFT JOIN users u ON mr.attending_staff=u.id $where ORDER BY mr.record_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $report_data = $stmt->get_result(); $stmt->close();
        $summary_stats = ['Total Records' => $conn->query("SELECT COUNT(*) as c FROM medical_records WHERE record_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c']];
        break;

    case 'billing':
        $report_title = 'Billing Report';
        $where = "WHERE b.bill_date BETWEEN ? AND ?";
        $params = [$date_from,$date_to]; $types = 'ss';
        if ($filter_status) { $where .= " AND b.payment_status=?"; $params[]=$filter_status; $types.='s'; }
        if ($filter_pid) { $where .= " AND b.patient_id=?"; $params[]=$filter_pid; $types.='i'; }
        $stmt = $conn->prepare("SELECT b.bill_id, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, b.bill_date, b.total_amount, b.amount_paid, b.balance, b.payment_status FROM bills b JOIN patients p ON b.patient_id=p.id $where ORDER BY b.bill_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $report_data = $stmt->get_result(); $stmt->close();
        $totals = $conn->query("SELECT SUM(total_amount) as ta, SUM(amount_paid) as ap, SUM(balance) as bal FROM bills WHERE bill_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc();
        $summary_stats = [
            'Total Bills' => $conn->query("SELECT COUNT(*) as c FROM bills WHERE bill_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
            'Total Billed' => '₱'.number_format($totals['ta']??0, 2),
            'Total Collected' => '₱'.number_format($totals['ap']??0, 2),
            'Outstanding' => '₱'.number_format($totals['bal']??0, 2),
        ];
        break;

    case 'payments':
        $report_title = 'Payment Report';
        $where = "WHERE py.payment_date BETWEEN ? AND ?";
        $params = [$date_from,$date_to]; $types = 'ss';
        if ($filter_pid) { $where .= " AND py.patient_id=?"; $params[]=$filter_pid; $types.='i'; }
        $stmt = $conn->prepare("SELECT py.payment_id, b.bill_id, CONCAT(p.first_name,' ',p.last_name) as patient_name, p.patient_id as pid, py.payment_date, py.amount_paid, py.payment_method, py.reference_number, u.full_name as received_by_name FROM payments py JOIN patients p ON py.patient_id=p.id JOIN bills b ON py.bill_id=b.id LEFT JOIN users u ON py.received_by=u.id $where ORDER BY py.payment_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $report_data = $stmt->get_result(); $stmt->close();
        $total_collected = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE payment_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['total'];
        $summary_stats = [
            'Total Transactions' => $conn->query("SELECT COUNT(*) as c FROM payments WHERE payment_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'],
            'Total Collected' => '₱'.number_format($total_collected??0, 2),
        ];
        break;
}

$status_colors=['Scheduled'=>'info','Completed'=>'success','Cancelled'=>'danger','Pending'=>'warning','Paid'=>'success','Unpaid'=>'danger','Partially Paid'=>'warning','Active'=>'success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Gerona Hospital </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
    <style>
    .report-tabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:20px; }
    .report-tab { padding:9px 18px; border-radius:8px; font-size:13.5px; font-weight:500; cursor:pointer; border:1.5px solid var(--gray-300); background:#fff; color:var(--gray-700); text-decoration:none; transition:var(--transition); display:flex; align-items:center; gap:6px; }
    .report-tab:hover { border-color:var(--primary); color:var(--primary); }
    .report-tab.active { background:var(--primary); border-color:var(--primary); color:#fff; }
    .summary-stats { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
    .summary-stat { background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:14px 20px; flex:1; min-width:130px; text-align:center; }
    .summary-stat .val { font-size:22px; font-weight:800; color:var(--primary); }
    .summary-stat .lbl { font-size:12px; color:var(--gray-500); margin-top:2px; }
    @media print { .no-print { display:none!important; } .sidebar,.header { display:none!important; } .main-content { margin:0!important; } }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="page-body">

            <div class="no-print">
                <!-- Report Type Tabs -->
                <div class="report-tabs">
                    <?php foreach ([
                        'patients' => ['fa-users','Patient Report'],
                        'appointments' => ['fa-calendar-check','Appointment Report'],
                        'medical_records' => ['fa-file-medical','Medical Records'],
                        'billing' => ['fa-file-invoice-dollar','Billing Report'],
                        'payments' => ['fa-money-bill-wave','Payment Report'],
                    ] as $key => [$icon, $label]): ?>
                    <a href="?report=<?=$key?>&date_from=<?=urlencode($date_from)?>&date_to=<?=urlencode($date_to)?>"
                       class="report-tab <?=$report_type===$key?'active':''?>">
                       <i class="fas <?=$icon?>"></i> <?=$label?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Filters -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-body">
                        <form method="GET" class="filter-bar" style="flex-wrap:wrap;">
                            <input type="hidden" name="report" value="<?=htmlspecialchars($report_type)?>">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="margin-bottom:4px;">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($date_from)?>">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="margin-bottom:4px;">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($date_to)?>">
                            </div>
                            <?php if (in_array($report_type,['appointments','medical_records','billing','payments'])): ?>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="margin-bottom:4px;">Patient</label>
                                <select name="patient_id" class="form-control" style="width:200px;">
                                    <option value="">All Patients</option>
                                    <?php foreach ($pt_list as $id=>$name): ?>
                                    <option value="<?=$id?>" <?=$filter_pid==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php if ($report_type==='appointments'): ?>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="margin-bottom:4px;">Department</label>
                                <select name="dept" class="form-control" style="width:160px;">
                                    <option value="">All Depts</option>
                                    <?php foreach ($dept_list as $id=>$name): ?>
                                    <option value="<?=$id?>" <?=$filter_dept==$id?'selected':''?>><?=htmlspecialchars($name)?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array($report_type,['patients','appointments','billing'])): ?>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="margin-bottom:4px;">Status</label>
                                <select name="status" class="form-control" style="width:150px;">
                                    <option value="">All Status</option>
                                    <?php if ($report_type==='billing'): ?>
                                    <?php foreach (['Unpaid','Partially Paid','Paid'] as $s): ?>
                                    <option value="<?=$s?>" <?=$filter_status===$s?'selected':''?>><?=$s?></option>
                                    <?php endforeach; ?>
                                    <?php elseif ($report_type==='appointments'): ?>
                                    <?php foreach (['Pending','Scheduled','Completed','Cancelled'] as $s): ?>
                                    <option value="<?=$s?>" <?=$filter_status===$s?'selected':''?>><?=$s?></option>
                                    <?php endforeach; ?>
                                    <?php elseif ($report_type==='patients'): ?>
                                    <?php foreach (['Active','Inactive','Discharged'] as $s): ?>
                                    <option value="<?=$s?>" <?=$filter_status===$s?'selected':''?>><?=$s?></option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;align-self:flex-end;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate</button>
                                <button type="button" onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
                                <a href="export.php?<?=http_build_query($_GET)?>" class="btn btn-success"><i class="fas fa-file-csv"></i> Export CSV</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Report Header (Printable) -->
            <div style="display:none;" class="print-header" id="printHeader">
                <div style="text-align:center;margin-bottom:16px;">
                    <h2 style="font-size:20px;font-weight:800;margin:0;">Gerona Hospital</h2>
                    <p style="font-size:13px;color:#666;margin:2px 0;">Information and Billing Management System</p>
                    <h3 style="margin-top:8px;"><?=htmlspecialchars($report_title)?></h3>
                    <p style="font-size:13px;color:#666;">Period: <?=date('F d, Y',strtotime($date_from))?> – <?=date('F d, Y',strtotime($date_to))?></p>
                    <p style="font-size:12px;color:#999;">Generated: <?=date('F d, Y h:i A')?> by <?=htmlspecialchars($_SESSION['full_name']??'')?></p>
                </div>
            </div>

            <!-- Summary Stats -->
            <?php if (!empty($summary_stats)): ?>
            <div class="summary-stats no-print">
                <?php foreach ($summary_stats as $lbl => $val): ?>
                <div class="summary-stat">
                    <div class="val"><?=$val?></div>
                    <div class="lbl"><?=htmlspecialchars($lbl)?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Report Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> <?=htmlspecialchars($report_title)?></h3>
                    <span class="text-muted text-small">
                        <?=date('M d, Y',strtotime($date_from))?> – <?=date('M d, Y',strtotime($date_to))?>
                    </span>
                </div>
                <div class="table-wrapper">
                <?php if ($report_type === 'patients'): ?>
                    <table>
                        <thead><tr><th>Patient ID</th><th>Name</th><th>Gender</th><th>Date of Birth</th><th>Contact</th><th>Registration Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while ($r=$report_data->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-600 text-primary"><?=htmlspecialchars($r['patient_id'])?></td>
                            <td><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></td>
                            <td><?=htmlspecialchars($r['gender'])?></td>
                            <td><?=date('M d, Y',strtotime($r['date_of_birth']))?></td>
                            <td><?=htmlspecialchars($r['contact_number']??'—')?></td>
                            <td><?=date('M d, Y',strtotime($r['registration_date']))?></td>
                            <td><span class="badge badge-<?=$status_colors[$r['status']]??'secondary'?>"><?=$r['status']?></span></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>

                <?php elseif ($report_type === 'appointments'): ?>
                    <table>
                        <thead><tr><th>Appt ID</th><th>Patient</th><th>Department</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while ($r=$report_data->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-600 text-primary"><?=htmlspecialchars($r['appointment_id'])?></td>
                            <td><?=htmlspecialchars($r['patient_name'])?> <small class="text-muted">(<?=htmlspecialchars($r['pid'])?>)</small></td>
                            <td><?=htmlspecialchars($r['dept_name'])?></td>
                            <td><?=htmlspecialchars($r['doctor_name']??'—')?></td>
                            <td><?=date('M d, Y',strtotime($r['appointment_date']))?></td>
                            <td><?=date('h:i A',strtotime($r['appointment_time']))?></td>
                            <td><span class="badge badge-<?=$status_colors[$r['status']]??'secondary'?>"><?=$r['status']?></span></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>

                <?php elseif ($report_type === 'medical_records'): ?>
                    <table>
                        <thead><tr><th>Record ID</th><th>Patient</th><th>Date</th><th>Attending Staff</th><th>Diagnosis</th></tr></thead>
                        <tbody>
                        <?php while ($r=$report_data->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-600 text-primary"><?=htmlspecialchars($r['record_id'])?></td>
                            <td><?=htmlspecialchars($r['patient_name'])?> <small class="text-muted">(<?=htmlspecialchars($r['pid'])?>)</small></td>
                            <td><?=date('M d, Y',strtotime($r['record_date']))?></td>
                            <td><?=htmlspecialchars($r['staff_name']??'—')?></td>
                            <td><?=htmlspecialchars($r['diagnosis']??'—')?></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>

                <?php elseif ($report_type === 'billing'): ?>
                    <table>
                        <thead><tr><th>Bill ID</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while ($r=$report_data->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-600 text-primary"><?=htmlspecialchars($r['bill_id'])?></td>
                            <td><?=htmlspecialchars($r['patient_name'])?> <small class="text-muted">(<?=htmlspecialchars($r['pid'])?>)</small></td>
                            <td><?=date('M d, Y',strtotime($r['bill_date']))?></td>
                            <td class="fw-600">&#8369;<?=number_format($r['total_amount'],2)?></td>
                            <td class="text-success fw-600">&#8369;<?=number_format($r['amount_paid'],2)?></td>
                            <td class="<?=$r['balance']>0?'text-danger':'text-success'?> fw-600">&#8369;<?=number_format($r['balance'],2)?></td>
                            <td><span class="badge badge-<?=$status_colors[$r['payment_status']]??'secondary'?>"><?=$r['payment_status']?></span></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>

                <?php elseif ($report_type === 'payments'): ?>
                    <table>
                        <thead><tr><th>Payment ID</th><th>Bill ID</th><th>Patient</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference #</th><th>Received By</th></tr></thead>
                        <tbody>
                        <?php while ($r=$report_data->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-600 text-primary"><?=htmlspecialchars($r['payment_id'])?></td>
                            <td><?=htmlspecialchars($r['bill_id'])?></td>
                            <td><?=htmlspecialchars($r['patient_name'])?> <small class="text-muted">(<?=htmlspecialchars($r['pid'])?>)</small></td>
                            <td><?=date('M d, Y',strtotime($r['payment_date']))?></td>
                            <td class="fw-600 text-success">&#8369;<?=number_format($r['amount_paid'],2)?></td>
                            <td><?=htmlspecialchars($r['payment_method'])?></td>
                            <td><?=htmlspecialchars($r['reference_number']??'—')?></td>
                            <td><?=htmlspecialchars($r['received_by_name']??'—')?></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
window.onbeforeprint = function(){document.getElementById('printHeader').style.display='block';};
window.onafterprint = function(){document.getElementById('printHeader').style.display='none';};
</script>
</body>
</html>
<?php $conn->close(); ?>
