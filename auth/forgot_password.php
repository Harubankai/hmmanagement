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

// Step 1 Submission: Request Password Reset Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset_code') {
    $email_or_username = sanitize($_POST['email_or_username'] ?? '');

    if (empty($email_or_username)) {
        $error = 'Please enter your registered Email Address.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, full_name FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param("ss", $email_or_username, $email_or_username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            $stmt->close();
            $target_email = $user['email'];
            $full_name = $user['full_name'];
            $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

            $payload = json_encode([
                'type' => 'password_reset',
                'user_id' => $user['id'],
                'email' => $target_email,
                'full_name' => $full_name
            ]);

            $stmt_ins = $conn->prepare("INSERT INTO email_verifications (email, code, payload, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            $stmt_ins->bind_param("sss", $target_email, $code, $payload);
            $stmt_ins->execute();
            $stmt_ins->close();

            sendPasswordResetEmail($target_email, $code, $full_name);

            $_SESSION['reset_email'] = $target_email;
            unset($_SESSION['reset_code_verified']);

            header("Location: /hmsystem/auth/forgot_password.php?step=2&sent=1");
            exit();
        } else {
            $stmt->close();
            $error = 'No registered account found matching that email address.';
        }
    }
}

// Step 2 Submission: Verify 6-Digit Code ONLY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code_only') {
    $email = $_SESSION['reset_email'] ?? sanitize($_POST['email'] ?? '');
    $code_input = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code_input)) {
        $error = 'Please enter the 6-digit verification code sent to your email.';
        $step = 2;
    } else {
        $stmt = $conn->prepare("SELECT * FROM email_verifications WHERE email = ? AND code = ? AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $code_input);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($verification = $res->fetch_assoc()) {
            $stmt->close();
            $_SESSION['reset_code_verified'] = true;
            header("Location: /hmsystem/auth/forgot_password.php?step=3");
            exit();
        } else {
            $stmt->close();
            $error = 'Invalid or expired 6-digit reset code. Please check your email and try again.';
            $step = 2;
        }
    }
}

// Step 3 Submission: Update New Password (only allowed if code was verified in Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_new_password') {
    $email = $_SESSION['reset_email'] ?? '';
    $is_verified = $_SESSION['reset_code_verified'] ?? false;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$is_verified || empty($email)) {
        $error = 'Session expired or verification incomplete. Please request a new code.';
        $step = 1;
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
        $step = 3;
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
        $step = 3;
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
        $step = 3;
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_u = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt_u->bind_param("ss", $hash, $email);
        $stmt_u->execute();
        $stmt_u->close();

        // Clean up verification records
        $conn->query("DELETE FROM email_verifications WHERE email = '" . $conn->real_escape_string($email) . "'");

        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_code_verified']);

        header("Location: /hmsystem/auth/login.php?reset=success");
        exit();
    }
}

// Resend Code Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_reset_code') {
    $email = $_SESSION['reset_email'] ?? sanitize($_POST['email'] ?? '');
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT full_name FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            $stmt->close();
            $full_name = $user['full_name'];
            $code = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

            $payload = json_encode(['type' => 'password_reset', 'email' => $email, 'full_name' => $full_name]);
            $stmt_ins = $conn->prepare("INSERT INTO email_verifications (email, code, payload, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            $stmt_ins->bind_param("sss", $email, $code, $payload);
            $stmt_ins->execute();
            $stmt_ins->close();

            sendPasswordResetEmail($email, $code, $full_name);
            $success = 'A new 6-digit reset code has been sent to ' . htmlspecialchars($email) . '.';
        } else {
            $error = 'User account not found.';
        }
    } else {
        $error = 'Session expired. Please enter your email address again.';
    }
    $step = 2;
}

// Enforce Step 3 protection
if ($step === 3 && empty($_SESSION['reset_code_verified'])) {
    $step = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Gerona Hospital IBMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
    <style>
        .forgot-container {
            max-width: 520px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .forgot-card {
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
            gap: 16px;
            margin-bottom: 28px;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-light);
        }
        .step-item.active {
            color: var(--primary-red);
        }
        .step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--bg-cream-box);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
        }
        .step-item.active .step-circle {
            background: var(--primary-red);
            color: #ffffff;
        }
        .verification-code-input {
            letter-spacing: 12px;
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            padding: 14px;
            max-width: 280px;
            margin: 0 auto 20px;
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
    </style>
</head>
<body style="background: var(--bg-main);">
    <div class="forgot-container">
        <!-- Header Brand -->
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: var(--primary-red); color: #fff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 12px; box-shadow: 0 4px 14px rgba(196,83,79,0.3);">
                <i class="fas fa-hospital"></i>
            </div>
            <h1 style="font-family: var(--font-serif); font-size: 30px; color: var(--navy-dark); margin: 0;">Gerona Hospital</h1>
            <p style="color: var(--text-muted); font-size: 13.5px; margin-top: 4px;">Account Password Recovery System</p>
        </div>

        <div class="forgot-card">
            <!-- Step Indicator -->
            <div class="step-progress">
                <div class="step-item <?= $step === 1 ? 'active' : '' ?>">
                    <div class="step-circle">1</div>
                    <span>Email</span>
                </div>
                <i class="fas fa-chevron-right" style="font-size: 10px; color: var(--text-light);"></i>
                <div class="step-item <?= $step === 2 ? 'active' : '' ?>">
                    <div class="step-circle">2</div>
                    <span>Verify Code</span>
                </div>
                <i class="fas fa-chevron-right" style="font-size: 10px; color: var(--text-light);"></i>
                <div class="step-item <?= $step === 3 ? 'active' : '' ?>">
                    <div class="step-circle">3</div>
                    <span>New Password</span>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-4" style="padding: 12px 16px; font-size: 13.5px;">
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
            <!-- STEP 1: REQUEST CODE -->
            <form method="POST" action="/hmsystem/auth/forgot_password.php?step=1">
                <input type="hidden" name="action" value="request_reset_code">

                <span class="eyebrow" style="color: var(--primary-red); font-size: 11px; font-weight: 800; letter-spacing: 1px;">STEP 1: EMAIL ADDRESS</span>
                <h2 style="font-family: var(--font-serif); font-size: 24px; color: var(--navy-dark); margin-bottom: 8px;">Forgot your password?</h2>
                <p style="color: var(--text-muted); font-size: 13.5px; margin-bottom: 24px; line-height: 1.5;">
                    Enter your registered Gmail / Email address below. We will send a 6-digit verification code to your inbox.
                </p>

                <div class="login-input-group mb-4">
                    <label>Gmail / Email Address *</label>
                    <input type="email" name="email_or_username" required placeholder="e.g. user@gmail.com" value="<?= htmlspecialchars($_POST['email_or_username'] ?? '') ?>" autofocus>
                </div>

                <button type="submit" class="login-submit-btn" style="margin-top: 0;">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Verification Code</span>
                </button>
            </form>

            <div style="margin-top: 24px; text-align: center; font-size: 13.5px; color: var(--text-muted);">
                Remembered your password? <a href="/hmsystem/auth/login.php" style="color: var(--primary-red); font-weight: 700;">Back to Sign In</a>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- STEP 2: VERIFY CODE ONLY -->
            <form method="POST" action="/hmsystem/auth/forgot_password.php?step=2">
                <input type="hidden" name="action" value="verify_code_only">
                <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?>">

                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 52px; height: 52px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 12px;">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <span class="eyebrow" style="color: var(--primary-red); font-size: 11px; font-weight: 800; letter-spacing: 1px;">STEP 2: CODE VERIFICATION</span>
                    <h2 style="font-family: var(--font-serif); font-size: 24px; color: var(--navy-dark); margin-bottom: 6px;">Verify 6-Digit Code</h2>
                    <p style="color: var(--text-muted); font-size: 13.5px; margin: 0 0 20px;">
                        We sent a 6-digit code to <strong style="color: var(--navy-dark);"><?= htmlspecialchars($_SESSION['reset_email'] ?? 'your email') ?></strong>.
                    </p>
                </div>

                <div class="mb-4">
                    <label class="form-label" style="text-align: center; display: block; margin-bottom: 8px; font-weight: 700;">Enter 6-Digit Verification Code *</label>
                    <input type="text" name="code" class="verification-code-input" maxlength="6" placeholder="123456" required autofocus autocomplete="off">
                </div>

                <button type="submit" class="login-submit-btn">
                    <i class="fas fa-check-circle"></i>
                    <span>Verify Code & Continue &rarr;</span>
                </button>
            </form>

            <form method="POST" action="/hmsystem/auth/forgot_password.php?step=2" style="text-align: center; margin-top: 16px;">
                <input type="hidden" name="action" value="resend_reset_code">
                <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?>">
                <button type="submit" class="btn-resend-code" style="background: #f8fafc; border: 1px solid var(--border-color); padding: 9px 20px; border-radius: var(--radius-pill); font-size: 13px; font-weight: 700; color: var(--navy-dark); cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-rotate-right" style="color: var(--primary-red);"></i>
                    <span>Resend Code</span>
                </button>
            </form>

            <?php elseif ($step === 3): ?>
            <!-- STEP 3: SET NEW PASSWORD -->
            <form method="POST" action="/hmsystem/auth/forgot_password.php?step=3">
                <input type="hidden" name="action" value="update_new_password">

                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 52px; height: 52px; background: #dcfce7; color: #15803d; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 12px;">
                        <i class="fas fa-lock"></i>
                    </div>
                    <span class="eyebrow" style="color: var(--primary-red); font-size: 11px; font-weight: 800; letter-spacing: 1px;">STEP 3: NEW PASSWORD</span>
                    <h2 style="font-family: var(--font-serif); font-size: 24px; color: var(--navy-dark); margin-bottom: 6px;">Set New Password</h2>
                    <p style="color: var(--text-muted); font-size: 13.5px; margin: 0 0 20px;">
                        Code verified for <strong style="color: var(--navy-dark);"><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>. Create your new password.
                    </p>
                </div>

                <div class="login-input-group mb-3">
                    <label>New Password *</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="reset_password" name="new_password" required placeholder="Min. 6 characters" autofocus>
                        <i class="fas fa-eye password-toggle-icon" data-target="reset_password" title="Toggle Password Visibility"></i>
                    </div>
                </div>

                <div class="login-input-group mb-4">
                    <label>Confirm New Password *</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="reset_confirm_password" name="confirm_password" required placeholder="Re-enter new password">
                        <i class="fas fa-eye password-toggle-icon" data-target="reset_confirm_password" title="Toggle Password Visibility"></i>
                    </div>
                </div>

                <button type="submit" class="login-submit-btn">
                    <i class="fas fa-shield-alt"></i>
                    <span>Save New Password & Sign In</span>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="/hmsystem/assets/js/main.js"></script>
</body>
</html>
