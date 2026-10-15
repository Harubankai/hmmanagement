<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$conn = getDBConnection();
$page_title = 'Dashboard';
$page_subtitle = 'Welcome back, ' . ($_SESSION['full_name'] ?? 'User');
$page_active = 'dashboard';

$today = date('Y-m-d');
$user_initial = strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1));
$user_name = $_SESSION['full_name'] ?? 'User Account';
$user_role = ucfirst($_SESSION['role'] ?? 'staff');

if (isPatient()) {
    $patient_id = getPatientId();
    $patient = null;
    if ($patient_id) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$patient && !empty($_SESSION['username'])) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE email = ? OR contact_number = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $_SESSION['username'], $_SESSION['username']);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $p_id_val = $patient['id'] ?? 0;

    // Patient Statistics
    $my_appts_cnt = $conn->query("SELECT COUNT(*) as cnt FROM appointments WHERE patient_id = $p_id_val")->fetch_assoc()['cnt'] ?? 0;
    $my_bills_cnt = $conn->query("SELECT COUNT(*) as cnt FROM bills WHERE patient_id = $p_id_val AND payment_status IN ('Unpaid','Partially Paid')")->fetch_assoc()['cnt'] ?? 0;
    $my_records_cnt = $conn->query("SELECT COUNT(*) as cnt FROM medical_records WHERE patient_id = $p_id_val")->fetch_assoc()['cnt'] ?? 0;
    $my_unpaid_sum = $conn->query("SELECT COALESCE(SUM(balance), 0) as total FROM bills WHERE patient_id = $p_id_val AND payment_status IN ('Unpaid','Partially Paid')")->fetch_assoc()['total'] ?? 0.00;

    // Recent Patient Appointments
    $my_recent_appts = $conn->query("
        SELECT a.*, d.name as dept_name
        FROM appointments a
        LEFT JOIN departments d ON a.department_id = d.id
        WHERE a.patient_id = $p_id_val
        ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5
    ");

    // Recent Patient Bills
    $my_recent_bills = $conn->query("
        SELECT * FROM bills
        WHERE patient_id = $p_id_val
        ORDER BY bill_date DESC LIMIT 5
    ");
} else {
    // Staff Statistics Queries
    $total_patients = $conn->query("SELECT COUNT(*) as cnt FROM patients WHERE status='Active'")->fetch_assoc()['cnt'];
    $today_appts = $conn->query("SELECT COUNT(*) as cnt FROM appointments WHERE appointment_date='$today'")->fetch_assoc()['cnt'];
    $pending_appts = $conn->query("SELECT COUNT(*) as cnt FROM appointments WHERE status IN ('Pending','Scheduled')")->fetch_assoc()['cnt'];
    $total_records = $conn->query("SELECT COUNT(*) as cnt FROM medical_records")->fetch_assoc()['cnt'];
    $unpaid_bills = $conn->query("SELECT COUNT(*) as cnt FROM bills WHERE payment_status IN ('Unpaid','Partially Paid')")->fetch_assoc()['cnt'];
    $today_payments = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM payments WHERE payment_date='$today'")->fetch_assoc()['total'];

    // Recent Patients
    $recent_patients = $conn->query("SELECT patient_id, CONCAT(first_name,' ',last_name) as full_name, gender, registration_date, status FROM patients ORDER BY created_at DESC LIMIT 5");

    // Recent Activity log
    $recent_activity = $conn->query("
        SELECT al.action, al.description, al.created_at, u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC LIMIT 6
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gerona Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="content-body">

                <?php if (isset($_GET['signup']) && $_GET['signup'] === 'welcome'): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-circle-check"></i>
                    <span><strong>Welcome to Gerona Hospital!</strong> Your patient account has been verified and registered. You can now book appointments, view your billing statements, and check your medical records below.</span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-shield-halved"></i>
                    <span>Access Denied: You do not have permissions to access that section.</span>
                </div>
                <?php endif; ?>

                <?php if (isPatient()): ?>
                <!-- ============================================================
                     PATIENT PORTAL DASHBOARD VIEW
                     ============================================================ -->
                
                <!-- Patient Profile Banner & Overview Stats -->
                <div class="grid-main-side">
                    <!-- Left: Patient Card -->
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span class="eyebrow">PATIENT PROFILE & STATUS</span>
                            <h2 class="card-title">Welcome back, <?= htmlspecialchars($patient['first_name'] ?? $_SESSION['full_name']) ?>!</h2>

                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                                <div style="width: 54px; height: 54px; background: var(--primary-red); color: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 700; flex-shrink: 0; box-shadow: 0 4px 14px rgba(196,83,79,0.3);">
                                    <?= $user_initial ?>
                                </div>
                                <div>
                                    <h3 style="font-family: var(--font-sans); font-size: 18px; font-weight: 700; color: var(--navy-dark); margin: 0;"><?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?></h3>
                                    <span style="font-size: 13px; color: var(--text-muted); display: block;">Patient ID: <code style="color: var(--primary-red); font-weight: 700;"><?= htmlspecialchars($patient['patient_id'] ?? 'PT-NEW') ?></code></span>
                                </div>
                            </div>
                        </div>

                        <div style="border-top: 1px solid var(--border-color); padding-top: 16px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; font-size: 13px;">
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">Gmail / Email</span>
                                <strong style="color: var(--navy-dark); word-break: break-all;"><?= htmlspecialchars($patient['email'] ?? $_SESSION['username']) ?></strong>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">Contact Phone</span>
                                <strong style="color: var(--navy-dark);"><?= htmlspecialchars($patient['contact_number'] ?? 'N/A') ?></strong>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">Blood Type</span>
                                <span class="status-badge info"><?= htmlspecialchars($patient['blood_type'] ?? 'Unknown') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Patient Balance Summary -->
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; background: linear-gradient(135deg, #111c2e 0%, #1c2b42 100%); color: #ffffff;">
                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <span class="eyebrow" style="color: #e27d78;">ACCOUNT BALANCE</span>
                                <span class="status-badge" style="background: rgba(255,255,255,0.15); color: #fff;">
                                    <i class="fas fa-wallet"></i> Patient Portal
                                </span>
                            </div>

                            <h3 style="color: var(--text-light); font-size: 13px; margin-bottom: 4px;">Total Unpaid Balance</h3>
                            <div style="font-family: var(--font-serif); font-size: 36px; color: #ffffff; line-height: 1.1; margin-bottom: 12px;">
                                <?= formatCurrency($my_unpaid_sum) ?>
                            </div>

                            <p style="font-size: 12.5px; color: #cbd5e1; line-height: 1.5;">
                                You have <strong><?= $my_bills_cnt ?></strong> pending invoice statement(s) requiring settlement.
                            </p>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <a href="/hmsystem/billing/index.php" class="btn btn-primary btn-sm" style="flex: 1;">
                                <i class="fas fa-file-invoice-dollar"></i> View & Pay Bills
                            </a>
                            <a href="/hmsystem/appointments/index.php?action=new" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2);">
                                <i class="fas fa-calendar-plus"></i> Book Appt
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Patient Stats Grid -->
                <div class="stats-grid mb-4" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="stat-value"><?= $my_appts_cnt ?></div>
                            <div class="stat-label">Total Appointments</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="color: #d97706; background: #fef3c7;"><i class="fas fa-file-invoice"></i></div>
                        <div>
                            <div class="stat-value"><?= $my_bills_cnt ?></div>
                            <div class="stat-label">Pending Invoices</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="color: #15803d; background: #dcfce7;"><i class="fas fa-user-shield"></i></div>
                        <div>
                            <div class="stat-value">Active</div>
                            <div class="stat-label">Account Status</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Boxes for Patient -->
                <div class="grid-2 mb-4">
                    <a href="/hmsystem/appointments/index.php?action=new" class="quick-action-box">
                        <div>
                            <div class="quick-action-icon"><i class="fas fa-calendar-plus"></i></div>
                            <div class="quick-action-title">Book New Appointment</div>
                            <div class="quick-action-subtext">Schedule a consultation with our hospital doctors</div>
                        </div>
                    </a>

                    <a href="/hmsystem/billing/index.php" class="quick-action-box">
                        <div>
                            <div class="quick-action-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div class="quick-action-title">See My Bills & Receipts</div>
                            <div class="quick-action-subtext">View itemized consultation, lab & medicine charges</div>
                        </div>
                    </a>
                </div>

                <!-- Patient Appointments & Billing Overview Tables -->
                <div class="grid-2">
                    <!-- My Appointments Table -->
                    <div class="card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h3 class="serif-title" style="font-size: 20px;"><i class="fas fa-calendar-alt text-muted"></i> My Appointments</h3>
                            <a href="/hmsystem/appointments/index.php?action=new" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Book Appt</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Appt ID</th>
                                        <th>Department</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($my_recent_appts->num_rows === 0): ?>
                                    <tr><td colspan="4" class="text-muted text-center py-3">No appointments scheduled yet. <a href="/hmsystem/appointments/index.php" style="color: var(--primary-red); font-weight: 700;">Book now &rarr;</a></td></tr>
                                    <?php else: ?>
                                        <?php while ($a = $my_recent_appts->fetch_assoc()): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($a['appointment_id']) ?></code></td>
                                            <td><strong><?= htmlspecialchars($a['dept_name'] ?? 'General') ?></strong></td>
                                            <td>
                                                <?= date('M d, Y', strtotime($a['appointment_date'])) ?><br>
                                                <small class="text-muted"><?= date('h:i A', strtotime($a['appointment_time'])) ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $st = strtolower($a['status']);
                                                $cls = ($st === 'completed') ? 'completed' : (($st === 'scheduled') ? 'active' : (($st === 'cancelled') ? 'danger' : 'pending'));
                                                ?>
                                                <span class="status-badge <?= $cls ?>"><?= htmlspecialchars($a['status']) ?></span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- My Bills Table -->
                    <div class="card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h3 class="serif-title" style="font-size: 20px;"><i class="fas fa-receipt text-muted"></i> Recent Hospital Bills</h3>
                            <a href="/hmsystem/billing/index.php" class="btn btn-sm btn-secondary">View All Bills</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Bill ID</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($my_recent_bills->num_rows === 0): ?>
                                    <tr><td colspan="5" class="text-muted text-center py-3">No billing records found.</td></tr>
                                    <?php else: ?>
                                        <?php while ($b = $my_recent_bills->fetch_assoc()): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($b['bill_id']) ?></code></td>
                                            <td><?= date('M d, Y', strtotime($b['bill_date'])) ?></td>
                                            <td><?= formatCurrency($b['total_amount']) ?></td>
                                            <td><strong style="color: var(--primary-red);"><?= formatCurrency($b['balance']) ?></strong></td>
                                            <td>
                                                <?php
                                                $pst = strtolower($b['payment_status']);
                                                $pcls = ($pst === 'paid') ? 'completed' : (($pst === 'partially paid') ? 'pending' : 'unpaid');
                                                ?>
                                                <span class="status-badge <?= $pcls ?>"><?= htmlspecialchars($b['payment_status']) ?></span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- ============================================================
                     STAFF / ADMIN DASHBOARD VIEW
                     ============================================================ -->
                
                <!-- Main Grid Row 1 (User Record & System Status Cards) -->
                <div class="grid-main-side">
                    <!-- Left Main Card: User Record -->
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span class="eyebrow">STAFF INFORMATION</span>
                            <h2 class="card-title">Your active hospital session</h2>

                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
                                <div style="width: 52px; height: 52px; background: var(--primary-red); color: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700; flex-shrink: 0; box-shadow: 0 4px 12px rgba(196,83,79,0.3);">
                                    <?= $user_initial ?>
                                </div>
                                <div>
                                    <h3 style="font-family: var(--font-sans); font-size: 18px; font-weight: 700; color: var(--navy-dark); margin: 0;"><?= htmlspecialchars($user_name) ?></h3>
                                    <span style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="border-top: 1px solid var(--border-color); padding-top: 16px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; font-size: 13px;">
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">Designation</span>
                                <strong style="color: var(--navy-dark);"><?= $user_role ?></strong>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">Station</span>
                                <strong style="color: var(--navy-dark);">Main Clinical Ward</strong>
                            </div>
                            <div>
                                <span style="font-size: 11px; color: var(--text-muted); display: block;">Shift Status</span>
                                <strong style="color: var(--navy-dark);">Active Duty</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side Card: Current Status -->
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <span class="eyebrow" style="margin-bottom: 0;">CURRENT STATUS</span>
                                <span class="status-badge active">
                                    <span class="status-dot"></span> System Online
                                </span>
                            </div>

                            <h2 class="card-title" style="font-size: 26px;">Hospital system is fully operational.</h2>
                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                                Operational overview: <strong><?= $total_patients ?></strong> active patient(s), <strong><?= $today_appts ?></strong> appointment(s) scheduled today, and <strong><?= $unpaid_bills ?></strong> unpaid invoice(s).
                            </p>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 14px; font-size: 12.5px; color: var(--text-muted);">
                            <span><i class="far fa-clock"></i> Last updated just now</span>
                            <a href="/hmsystem/reports/index.php" style="color: var(--primary-red); font-weight: 700;">See reports &rsaquo;</a>
                        </div>
                    </div>
                </div>

                <!-- Main Grid Row 2 (Quick Actions & Important Dates Cards) -->
                <div class="grid-main-side">
                    <!-- Left Card: Quick Actions -->
                    <div class="card">
                        <span class="eyebrow">QUICK ACTIONS</span>
                        <h2 class="card-title">Keep moving</h2>

                        <div class="grid-3">
                            <a href="/hmsystem/patients/index.php" class="quick-action-box">
                                <div>
                                    <div class="quick-action-icon"><i class="fas fa-users"></i></div>
                                    <div class="quick-action-title">Manage Patients</div>
                                    <div class="quick-action-subtext"><?= $total_patients ?> active records</div>
                                </div>
                            </a>

                            <a href="/hmsystem/appointments/index.php" class="quick-action-box">
                                <div>
                                    <div class="quick-action-icon"><i class="fas fa-calendar-check"></i></div>
                                    <div class="quick-action-title">Appointments</div>
                                    <div class="quick-action-subtext"><?= $today_appts ?> scheduled today</div>
                                </div>
                            </a>

                            <a href="/hmsystem/billing/index.php" class="quick-action-box">
                                <div>
                                    <div class="quick-action-icon"><i class="fas fa-receipt"></i></div>
                                    <div class="quick-action-title">Billing & Payments</div>
                                    <div class="quick-action-subtext">&#8369;<?= number_format($today_payments, 2) ?> paid today</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Right Card: Important Dates & Schedule -->
                    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <span class="eyebrow">IMPORTANT DATES</span>
                            
                            <div style="display: flex; align-items: center; gap: 14px; margin-top: 12px; margin-bottom: 20px;">
                                <div class="calendar-badge">
                                    <span class="day"><?= date('d') ?></span>
                                    <span class="month"><?= date('M') ?></span>
                                </div>
                                <div>
                                    <h4 style="font-family: var(--font-sans); font-size: 15px; font-weight: 700; color: var(--navy-dark); margin: 0;">Shift Audit & Reconciliation</h4>
                                    <span style="font-size: 12px; color: var(--text-muted);">Today &bull; 11:59 PM</span>
                                </div>
                            </div>
                        </div>

                        <div style="background: var(--primary-red-light); border: 1px solid rgba(196,83,79,0.2); border-radius: var(--radius-sm); padding: 14px 16px; font-size: 12.5px; color: #7f1d1d;">
                            <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-bell"></i> Daily Billing Audit Active
                            </div>
                            <div>Complete all pending patient statements and collections before end-of-shift.</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity & Patient Table Row -->
                <div class="grid-2">
                    <!-- Recent Patients Table -->
                    <div class="card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h3 class="serif-title" style="font-size: 20px;"><i class="fas fa-hospital-user text-muted"></i> Recent Patients</h3>
                            <a href="/hmsystem/patients/index.php" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Reg. Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_patients->num_rows === 0): ?>
                                    <tr><td colspan="4" class="text-muted text-center py-3">No patient records found.</td></tr>
                                    <?php else: ?>
                                        <?php while ($p = $recent_patients->fetch_assoc()): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($p['patient_id']) ?></code></td>
                                            <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($p['gender']) ?></td>
                                            <td><?= date('M d, Y', strtotime($p['registration_date'])) ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- System Activity Trail -->
                    <div class="card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h3 class="serif-title" style="font-size: 20px;"><i class="fas fa-history text-muted"></i> Activity Trail</h3>
                            <span class="status-badge info"><i class="fas fa-pulse fa-spinner"></i> Live</span>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php if ($recent_activity->num_rows === 0): ?>
                            <p class="text-muted" style="font-size: 13px;">No recent system logs.</p>
                            <?php else: ?>
                                <?php while ($log = $recent_activity->fetch_assoc()): ?>
                                <div style="display: flex; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--border-color);">
                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--bg-cream-box); color: var(--primary-red); display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; margin-top: 2px;">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div style="flex: 1; font-size: 12.5px;">
                                        <div style="font-weight: 700; color: var(--navy-dark);"><?= htmlspecialchars($log['action']) ?> &bull; <span style="font-weight: 400; color: var(--text-muted);"><?= htmlspecialchars($log['full_name'] ?? 'System') ?></span></div>
                                        <div style="color: var(--text-muted); font-size: 12px; margin-top: 1px;"><?= htmlspecialchars($log['description']) ?></div>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-light); white-space: nowrap;">
                                        <?= date('h:i A', strtotime($log['created_at'])) ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</body>
</html>
