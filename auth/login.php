<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

if (isLoggedIn()) {
    header("Location: /hmsystem/dashboard/index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status, patient_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if ($user['status'] === 'inactive') {
                $error = 'Your account is inactive. Please contact the administrator.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['patient_id'] = $user['patient_id'];

                logActivity($conn, $user['id'], 'User Login', $user['full_name'] . ' logged into the system');
                $stmt->close();
                $conn->close();
                header("Location: /hmsystem/dashboard/index.php");
                exit();
            } else {
                $error = 'Invalid email/username or password.';
            }
        } else {
            $error = 'Invalid email/username or password.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Gerona Hospital IBMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/hmsystem/assets/css/style.css">
</head>
<body>
    <div class="login-split-container">
        <!-- Left Hero Section -->
        <div class="login-hero-half">
            <div class="login-hero-overlay"></div>
            
            <div class="login-hero-header">
                <div class="login-hero-logo">
                    <i class="fas fa-hospital"></i>
                </div>
                <div class="login-hero-brand">
                    <h2>Gerona Hospital</h2>
                    <p>Gerona, Tarlac</p>
                </div>
            </div>

            <div class="login-hero-content">
                <span class="login-hero-eyebrow">HOSPITAL MANAGEMENT / 2026-2027</span>
                <h1 class="login-hero-title">Make your health care seamless.</h1>
                <p class="login-hero-subtitle">
                    A unified hospital information & billing workspace for Gerona Hospital staff. Clear patient records, faster workflow, and data you can trust.
                </p>

                <div class="login-hero-features">
                    <div class="login-hero-feature">
                        <i class="fas fa-check"></i> Patient-first design
                    </div>
                    <div class="login-hero-feature">
                        <i class="fas fa-check"></i> Real-time records
                    </div>
                    <div class="login-hero-feature">
                        <i class="fas fa-check"></i> Ready when you are
                    </div>
                </div>
            </div>

            <div style="font-size: 12px; color: var(--text-light); z-index: 2;">
                Gerona Hospital management &bull; 
            </div>
        </div>

        <!-- Right Form Section -->
        <div class="login-form-half">
            <div></div>

            <div class="login-form-box">
                
                <h1 class="login-form-title">Welcome back.</h1>
                <p class="login-form-subtitle">Sign in to continue your session.</p>

                <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 24px;">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="alert alert-success" style="margin-bottom: 24px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 16px; border-radius: var(--radius-sm); font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-circle-check"></i>
                    <span>Password reset successful! You can now sign in with your new password.</span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="login-input-group">
                        <label for="username">Email Address</label>
                        <input type="email" id="username" name="username" placeholder="e.g. user@gmail.com" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="email">
                    </div>

                    <div class="login-input-group">
                        <label for="password">Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-eye password-toggle-icon" data-target="password" title="Toggle Password Visibility"></i>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-muted); font-weight: 500;">
                            <input type="checkbox" name="remember" style="accent-color: var(--primary-red);"> Remember me
                        </label>
                        <a href="/hmsystem/auth/forgot_password.php" style="color: var(--primary-red); font-weight: 600;">Forgot password?</a>
                    </div>

                    <button type="submit" class="login-submit-btn">
                        <span>Sign in</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div style="margin-top: 20px; text-align: center; font-size: 13.5px; color: var(--text-muted);">
                    Are you a patient? <a href="/hmsystem/auth/signup.php" style="color: var(--primary-red); font-weight: 700;">Sign Up &rarr;</a>
                </div>

                <div style="margin-top: 20px; padding: 14px 18px; background: var(--bg-cream-box); border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 12.5px;">
                    <div style="font-weight: 700; color: var(--navy-dark); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-key" style="color: var(--primary-red);"></i> Quick Demo Accounts
                    </div>
                    <div style="color: var(--text-muted); line-height: 1.6;">
                        Admin: <strong style="color: var(--navy-dark);">admin@geronhospital.com</strong>  / <strong style="color: var(--navy-dark);">password</strong><br>
                        Doctor/Staff: <strong style="color: var(--navy-dark);">mgarcia@geronhospital.com</strong>  / <strong style="color: var(--navy-dark);">password</strong><br>
                        Patient: <strong style="color: var(--navy-dark);">akatsukiharold25@gmail.com</strong> / <strong style="color: var(--navy-dark);">password</strong>
                    </div>
                </div>
            </div>

            <div class="login-footer-text">
                <div style="display: flex; align-items: center; gap: 6px; color: #166534; font-weight: 600;">
                    <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%;"></span>
                    SYSTEM ONLINE
                </div>
                <div>&copy; <?= date('Y') ?> Gerona Hospital</div>
            </div>
        </div>
    </div>
    <script src="/hmsystem/assets/js/main.js"></script>
</body>
</html>
