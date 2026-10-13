<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$report_type = $_GET['report'] ?? 'patients';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$filter_pid = intval($_GET['patient_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_dept = intval($_GET['dept'] ?? 0);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

switch ($report_type) {
    case 'patients':
        fputcsv($output, ['Patient ID','First Name','Last Name','Gender','Date of Birth','Contact Number','Email','Blood Type','Status','Registration Date']);
        $where = "WHERE registration_date BETWEEN ? AND ?"; $params=[$date_from,$date_to]; $types='ss';
        if ($filter_status) { $where .= " AND status=?"; $params[]=$filter_status; $types.='s'; }
        $stmt=$conn->prepare("SELECT * FROM patients $where ORDER BY registration_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $rows=$stmt->get_result();
        while ($r=$rows->fetch_assoc()) {
            fputcsv($output,[$r['patient_id'],$r['first_name'],$r['last_name'],$r['gender'],$r['date_of_birth'],$r['contact_number'],$r['email'],$r['blood_type'],$r['status'],$r['registration_date']]);
        }
        break;

    case 'appointments':
        fputcsv($output, ['Appointment ID','Patient','Patient ID','Department','Doctor','Date','Time','Purpose','Status']);
        $where = "WHERE a.appointment_date BETWEEN ? AND ?"; $params=[$date_from,$date_to]; $types='ss';
        if ($filter_status) { $where.=" AND a.status=?"; $params[]=$filter_status; $types.='s'; }
        if ($filter_dept) { $where.=" AND a.department_id=?"; $params[]=$filter_dept; $types.='i'; }
        if ($filter_pid) { $where.=" AND a.patient_id=?"; $params[]=$filter_pid; $types.='i'; }
        $stmt=$conn->prepare("SELECT a.appointment_id,CONCAT(p.first_name,' ',p.last_name) as pn,p.patient_id as pid,d.name as dname,a.doctor_name,a.appointment_date,a.appointment_time,a.purpose,a.status FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN departments d ON a.department_id=d.id $where ORDER BY a.appointment_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $rows=$stmt->get_result();
        while ($r=$rows->fetch_assoc()) {
            fputcsv($output,[$r['appointment_id'],$r['pn'],$r['pid'],$r['dname'],$r['doctor_name'],$r['appointment_date'],$r['appointment_time'],$r['purpose'],$r['status']]);
        }
        break;

    case 'billing':
        fputcsv($output, ['Bill ID','Patient','Patient ID','Bill Date','Total Amount','Amount Paid','Balance','Status']);
        $where = "WHERE b.bill_date BETWEEN ? AND ?"; $params=[$date_from,$date_to]; $types='ss';
        if ($filter_status) { $where.=" AND b.payment_status=?"; $params[]=$filter_status; $types.='s'; }
        $stmt=$conn->prepare("SELECT b.bill_id,CONCAT(p.first_name,' ',p.last_name) as pn,p.patient_id as pid,b.bill_date,b.total_amount,b.amount_paid,b.balance,b.payment_status FROM bills b JOIN patients p ON b.patient_id=p.id $where ORDER BY b.bill_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $rows=$stmt->get_result();
        while ($r=$rows->fetch_assoc()) {
            fputcsv($output,[$r['bill_id'],$r['pn'],$r['pid'],$r['bill_date'],$r['total_amount'],$r['amount_paid'],$r['balance'],$r['payment_status']]);
        }
        break;

    case 'payments':
        fputcsv($output, ['Payment ID','Bill ID','Patient','Patient ID','Date','Amount','Method','Reference #','Received By']);
        $where = "WHERE py.payment_date BETWEEN ? AND ?"; $params=[$date_from,$date_to]; $types='ss';
        $stmt=$conn->prepare("SELECT py.payment_id,b.bill_id,CONCAT(p.first_name,' ',p.last_name) as pn,p.patient_id as pid,py.payment_date,py.amount_paid,py.payment_method,py.reference_number,u.full_name as rby FROM payments py JOIN patients p ON py.patient_id=p.id JOIN bills b ON py.bill_id=b.id LEFT JOIN users u ON py.received_by=u.id $where ORDER BY py.payment_date DESC");
        $stmt->bind_param($types,...$params); $stmt->execute();
        $rows=$stmt->get_result();
        while ($r=$rows->fetch_assoc()) {
            fputcsv($output,[$r['payment_id'],$r['bill_id'],$r['pn'],$r['pid'],$r['payment_date'],$r['amount_paid'],$r['payment_method'],$r['reference_number']??'',$r['rby']??'']);
        }
        break;
}

fclose($output);
$conn->close();
exit();
?>
