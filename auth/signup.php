<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

if (isLoggedIn()) {
    header("Location: /hmsystem/dashboard/index.php");
    exit();
}

$conn = getDBConnection();
$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$demo_code = '';

// Step 1 Submission: Register Info & Send Gmail Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_code') {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $middle_name = sanitize($_POST['middle_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $contact_number = sanitize($_POST['contact_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_number = sanitize($_POST['emergency_contact_number'] ?? '');
    $blood_type = sanitize($_POST['blood_type'] ?? 'Unknown');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($date_of_birth) || empty($contact_number)) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address / Gmail.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists in users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'An account with this email address already exists. Please sign in instead.';
            $stmt->close();
        } else {
            $stmt->close();
            // Generate 6-digit code
            $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $full_name = trim("$first_name $last_name");
            
            $payload = json_encode([
                'first_name' => $first_name,
                'middle_name' => $middle_name,
                'last_name' => $last_name,
                'date_of_birth' => $date_of_birth,
                'gender' => $gender,
                'contact_number' => $contact_number,
                'email' => $email,
                'address' => $address,
                'emergency_contact_name' => $emergency_contact_name,
                'emergency_contact_number' => $emergency_contact_number,
                'blood_type' => $blood_type,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $full_name
            ]);

            // Store in DB with explicit SQL DATE_ADD to prevent timezone skew
            $stmt = $conn->prepare("INSERT INTO email_verifications (email, code, payload, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            $stmt->bind_param("sss", $email, $code, $payload);
            $stmt->execute();
            $stmt->close();

            // Try sending email
            sendVerificationEmail($email, $code, $full_name);

            // Store in session for step 2
            $_SESSION['verify_email'] = $email;

            header("Location: /hmsystem/auth/signup.php?step=2&sent=1");
            exit();
        }
    }
}

// Step 2 Submission: Verify Code & Create Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    $email = $_SESSION['verify_email'] ?? sanitize($_POST['email'] ?? '');
    $code_input = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code_input)) {
        $error = 'Please enter the 6-digit verification code.';
        $step = 2;
    } else {
        $stmt = $conn->prepare("SELECT * FROM email_verifications WHERE email = ? AND code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $code_input);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($verification = $res->fetch_assoc()) {
            $data = json_decode($verification['payload'], true);
            $stmt->close();

            // 1. Create Patient Record
            $patient_id_str = generateID('PT', 'patients', 'patient_id', $conn);
            $reg_date = date('Y-m-d');
            
            $stmt_p = $conn->prepare("INSERT INTO patients (patient_id, first_name, middle_name, last_name, date_of_birth, gender, contact_number, email, address, emergency_contact_name, emergency_contact_number, blood_type, status, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
            $stmt_p->bind_param("sssssssssssss", 
                $patient_id_str, 
                $data['first_name'], 
                $data['middle_name'], 
                $data['last_name'], 
                $data['date_of_birth'], 
                $data['gender'], 
                $data['contact_number'], 
                $data['email'], 
                $data['address'], 
                $data['emergency_contact_name'], 
                $data['emergency_contact_number'], 
                $data['blood_type'], 
                $reg_date
            );
            $stmt_p->execute();
            $patient_pk_id = $conn->insert_id;
            $stmt_p->close();

            // 2. Create User Account
            $username = $data['email'];
            $stmt_u = $conn->prepare("INSERT INTO users (username, password, full_name, email, patient_id, role, status, email_verified) VALUES (?, ?, ?, ?, ?, 'patient', 'active', 1)");
            $stmt_u->bind_param("ssssi", $username, $data['password_hash'], $data['full_name'], $data['email'], $patient_pk_id);
            $stmt_u->execute();
            $new_user_id = $conn->insert_id;
            $stmt_u->close();

            // 3. Clean up verification records
            $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");

            // 4. Log in immediately
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $data['full_name'];
            $_SESSION['role'] = 'patient';
            $_SESSION['patient_id'] = $patient_pk_id;

            logActivity($conn, $new_user_id, 'Patient Sign Up', 'New patient signed up & verified email: ' . $data['full_name'] . ' (' . $patient_id_str . ')');
            
            unset($_SESSION['verify_email']);

            header("Location: /hmsystem/dashboard/index.php?signup=welcome");
            exit();
        } else {
            $error = 'Invalid or expired verification code. Please check your email and try again.';
            $step = 2;
        }
    }
}

// Resend Code Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_code') {
    $email = $_SESSION['verify_email'] ?? sanitize($_POST['email'] ?? '');
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT payload FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $data = json_decode($row['payload'], true);
            $full_name = $data['full_name'] ?? 'User';
            $stmt->close();

            $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

            $stmt_ins = $conn->prepare("INSERT INTO email_verifications (email, code, payload, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            $stmt_ins->bind_param("sss", $email, $code, $row['payload']);
            $stmt_ins->execute();
            $stmt_ins->close();

            sendVerificationEmail($email, $code, $full_name);
            $success = 'A new 6-digit verification code has been sent to ' . $email . '.';
        } else {
            $error = 'Verification record not found. Please try again.';
        }
    } else {
        $error = 'Session expired. Please fill in your registration details again.';
    }
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Sign Up - Gerona Hospital IBMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
    <style>
        .signup-container {
            max-width: 720px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .signup-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            padding: 36px;
        }
        .step-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            margin-bottom: 32px;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-light);
        }
        .step-item.active {
            color: var(--primary-red);
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-cream-box);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
        }
        .step-item.active .step-circle {
            background: var(--primary-red);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(196, 83, 79, 0.3);
        }
        .verification-code-input {
            letter-spacing: 12px;
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            padding: 16px;
            max-width: 280px;
            margin: 0 auto 24px;
            display: block;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-cream-input);
        }
        .verification-code-input:focus {
            border-color: var(--primary-red);
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px var(--primary-red-light);
        }
        .code-preview-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            margin-bottom: 24px;
            text-align: center;
        }
    </style>
</head>
<body style="background: var(--bg-main);">
    <div class="signup-container">
        <!-- Header Brand -->
        <div style="text-align: center; margin-bottom: 28px;">
            <div style="width: 48px; height: 48px; background: var(--primary-red); color: #fff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 12px; box-shadow: 0 4px 14px rgba(196,83,79,0.3);">
                <i class="fas fa-hospital"></i>
            </div>
            <h1 style="font-family: var(--font-serif); font-size: 32px; color: var(--navy-dark); margin: 0;">Gerona Hospital</h1>
            <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 4px;">Patient Registration & Online Services Portal</p>
        </div>

        <div class="signup-card">
            <!-- Step Progress Indicator -->
            <div class="step-progress">
                <div class="step-item <?= $step === 1 ? 'active' : '' ?>">
                    <div class="step-circle">1</div>
                    <span>Personal Info & Gmail</span>
                </div>
                <i class="fas fa-chevron-right" style="font-size: 11px; color: var(--text-light);"></i>
                <div class="step-item <?= $step === 2 ? 'active' : '' ?>">
                    <div class="step-circle">2</div>
                    <span>Gmail Code Verification</span>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success mb-4" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <!-- STEP 1: PATIENT REGISTRATION FORM -->
            <form method="POST" action="/hmsystem/auth/signup.php?step=1">
                <input type="hidden" name="action" value="request_code">

                <span class="eyebrow">PATIENT DEMOGRAPHICS</span>
                <h2 style="font-family: var(--font-serif); font-size: 22px; color: var(--navy-dark); margin-bottom: 20px;">Create your Patient Account</h2>

                <div class="grid-3 mb-3" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="login-input-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required placeholder="e.g. Juan" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="login-input-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" placeholder="e.g. Santos" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                    </div>
                    <div class="login-input-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required placeholder="e.g. Dela Cruz" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid-2 mb-3">
                    <div class="login-input-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="date_of_birth" required value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="login-input-group">
                        <label>Gender *</label>
                        <select name="gender" class="form-control" style="width: 100%; height: 45px;">
                            <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2 mb-3">
                    <div class="login-input-group">
                        <label>Contact Phone Number *</label>
                        <input type="text" name="contact_number" required placeholder="e.g. 09171234567" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                    </div>
                    <div class="login-input-group">
                        <label>Gmail / Email Address *</label>
                        <input type="email" name="email" required placeholder="e.g. user@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="login-input-group mb-3">
                    <label>Home Address *</label>
                    <input type="text" name="address" required placeholder="Street, Barangay, City, Province" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>

                <div class="grid-3 mb-3">
                    <div class="login-input-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" placeholder="Full name of contact" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>">
                    </div>
                    <div class="login-input-group">
                        <label>Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_number" placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($_POST['emergency_contact_number'] ?? '') ?>">
                    </div>
                    <div class="login-input-group">
                        <label>Blood Type</label>
                        <select name="blood_type" class="form-control" style="width: 100%; height: 45px;">
                            <option value="Unknown">Unknown</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>

                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;">

                <span class="eyebrow">ACCOUNT SECURITY</span>
                <h2 style="font-family: var(--font-serif); font-size: 20px; color: var(--navy-dark); margin-bottom: 16px;">Set Account Password</h2>

                <div class="grid-2 mb-4">
                    <div class="login-input-group">
                        <label>Password *</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="signup_password" name="password" required placeholder="At least 6 characters">
                            <i class="fas fa-eye password-toggle-icon" data-target="signup_password" title="Toggle Password Visibility"></i>
                        </div>
                    </div>
                    <div class="login-input-group">
                        <label>Confirm Password *</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="signup_confirm_password" name="confirm_password" required placeholder="Re-enter password">
                            <i class="fas fa-eye password-toggle-icon" data-target="signup_confirm_password" title="Toggle Password Visibility"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="login-submit-btn" style="margin-top: 0;">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Verification Code to Gmail</span>
                </button>
            </form>

            <div style="margin-top: 24px; text-align: center; font-size: 13.5px; color: var(--text-muted);">
                Already registered? <a href="/hmsystem/auth/login.php" style="color: var(--primary-red); font-weight: 700;">Sign in here</a>
            </div>

            <?php else: ?>
            <!-- STEP 2: ENTER VERIFICATION CODE -->
            <form method="POST" action="/hmsystem/auth/signup.php?step=2">
                <input type="hidden" name="action" value="verify_code">
                <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['verify_email'] ?? '') ?>">

                <div style="text-align: center;">
                    <div style="width: 56px; height: 56px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <span class="eyebrow">GMAIL VERIFICATION</span>
                    <h2 style="font-family: var(--font-serif); font-size: 26px; color: var(--navy-dark); margin-bottom: 8px;">Check your Gmail Inbox</h2>
                    <p style="color: var(--text-muted); font-size: 14px; max-width: 440px; margin: 0 auto 24px;">
                        We have sent a 6-digit verification code to <strong style="color: var(--navy-dark);"><?= htmlspecialchars($_SESSION['verify_email'] ?? 'your email') ?></strong>.
                    </p>

                    <label class="form-label" style="text-align: center; margin-bottom: 12px;">Enter 6-Digit Code</label>
                    <input type="text" name="code" class="verification-code-input" maxlength="6" placeholder="123456" required autofocus autocomplete="off">

                    <button type="submit" class="login-submit-btn" style="max-width: 320px; margin: 0 auto 16px;">
                        <i class="fas fa-check-circle"></i>
                        <span>Verify & Complete Registration</span>
                    </button>
                </div>
            </form>

            <form method="POST" action="/hmsystem/auth/signup.php?step=2" style="text-align: center; margin-top: 12px;">
                <input type="hidden" name="action" value="resend_code">
                <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['verify_email'] ?? '') ?>">
                <button type="submit" class="btn-resend-code" style="background: #f8fafc; border: 1px solid var(--border-color); padding: 10px 22px; border-radius: var(--radius-pill); font-size: 13.5px; font-weight: 700; color: var(--navy-dark); cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-rotate-right" style="color: var(--primary-red);"></i>
                    <span>Resend Verification Code</span>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="/hmsystem/assets/js/main.js"></script>
</body>
</html>
