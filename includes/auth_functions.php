<?php
require_once __DIR__ . '/../license-guard.php';

require_once __DIR__ . '/../config/database.php';

function logActivity($conn, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $user_id, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /hmsystem/auth/login.php");
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStaff() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'admin');
}

function isPatient() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'patient';
}

function getPatientId() {
    return $_SESSION['patient_id'] ?? null;
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /hmsystem/dashboard/index.php?error=access_denied");
        exit();
    }
}

function requireStaffOrAdmin() {
    requireLogin();
    if (!isStaff()) {
        header("Location: /hmsystem/dashboard/index.php?error=access_denied");
        exit();
    }
}

function generateID($prefix, $table, $column, $conn) {
    $result = $conn->query("SELECT $column FROM $table ORDER BY id DESC LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $last = intval(substr($row[$column], strlen($prefix) + 1));
        return $prefix . '-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
    return $prefix . '-0001';
}

function sendGmailSMTPDirect($to_email, $subject, $htmlBody, $full_name) {
    $smtpHost = "ssl://smtp.gmail.com";
    $smtpPort = 465;
    $username = "akatsukiharold25@gmail.com";
    $password = "jvuqwwjsvwkjhlyh";
    $fromEmail = "akatsukiharold25@gmail.com";
    $fromName = "Gerona Hospital IBMS";

    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP Socket Connection failed: $errstr ($errno)");
        return false;
    }

    $read = function() use ($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $read();
    $send("EHLO " . gethostname());
    
    $authResp = $send("AUTH LOGIN");
    if (substr($authResp, 0, 3) != "334") { fclose($socket); return false; }

    $userResp = $send(base64_encode($username));
    if (substr($userResp, 0, 3) != "334") { fclose($socket); return false; }

    $passResp = $send(base64_encode($password));
    if (substr($passResp, 0, 3) != "235") { fclose($socket); return false; }

    $send("MAIL FROM: <$fromEmail>");
    $send("RCPT TO: <$to_email>");
    $send("DATA");

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$fromEmail>\r\n";
    $headers .= "To: $full_name <$to_email>\r\n";
    $headers .= "Subject: $subject\r\n";

    $body = $headers . "\r\n" . $htmlBody . "\r\n.\r\n";
    $dataResp = $send($body);

    $send("QUIT");
    fclose($socket);

    return substr($dataResp, 0, 3) == "250";
}

function sendVerificationEmail($to_email, $code, $full_name) {
    $subject = "Your Gerona Hospital Verification Code: $code";

    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Segoe UI", Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; }
            .email-container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
            .email-header { background: #0d1726; color: #ffffff; padding: 28px; text-align: center; }
            .email-header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; }
            .email-header p { margin: 4px 0 0; font-size: 13px; color: #94a3b8; }
            .email-body { padding: 32px 28px; color: #334155; line-height: 1.6; }
            .code-box { background: #fdf2f2; border: 2px dashed #c4534f; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
            .code-number { font-size: 32px; font-weight: 800; color: #c4534f; letter-spacing: 6px; }
            .email-footer { background: #f8fafc; padding: 18px 28px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>Gerona Hospital</h1>
                <p>Information & Billing Management System</p>
            </div>
            <div class="email-body">
                <p>Hello <strong>' . htmlspecialchars($full_name) . '</strong>,</p>
                <p>Thank you for registering with Gerona Hospital Online Portal. Please use the 6-digit verification code below to complete your registration:</p>
                
                <div class="code-box">
                    <div style="font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 6px;">Verification Code</div>
                    <div class="code-number">' . htmlspecialchars($code) . '</div>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 6px;">Valid for 15 minutes</div>
                </div>

                <p>If you did not request this code, please ignore this email or contact hospital support if you have concerns.</p>
                <p>Best regards,<br><strong>Gerona Hospital Team</strong></p>
            </div>
            <div class="email-footer">
                Gerona Hospital Educational Foundation, Inc. &bull; Gerona, Tarlac, Philippines
            </div>
        </div>
    </body>
    </html>
    ';

    return sendGmailSMTPDirect($to_email, $subject, $htmlBody, $full_name);
}

function sendPasswordResetEmail($to_email, $code, $full_name) {
    $subject = "Password Reset Code: $code - Gerona Hospital";

    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Segoe UI", Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; }
            .email-container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
            .email-header { background: #0d1726; color: #ffffff; padding: 28px; text-align: center; }
            .email-header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; }
            .email-header p { margin: 4px 0 0; font-size: 13px; color: #94a3b8; }
            .email-body { padding: 32px 28px; color: #334155; line-height: 1.6; }
            .code-box { background: #fff7ed; border: 2px dashed #ea580c; border-radius: 8px; text-align: center; padding: 20px; margin: 24px 0; }
            .code-number { font-size: 32px; font-weight: 800; color: #ea580c; letter-spacing: 6px; }
            .email-footer { background: #f8fafc; padding: 18px 28px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1>Gerona Hospital</h1>
                <p>Password Reset Security Request</p>
            </div>
            <div class="email-body">
                <p>Hello <strong>' . htmlspecialchars($full_name) . '</strong>,</p>
                <p>We received a request to reset your password for your Gerona Hospital account. Please use the 6-digit verification code below to reset your password:</p>
                
                <div class="code-box">
                    <div style="font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 6px;">Password Reset Code</div>
                    <div class="code-number">' . htmlspecialchars($code) . '</div>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 6px;">Valid for 15 minutes</div>
                </div>

                <p>If you did not request a password reset, please ignore this email or notify your system administrator immediately.</p>
                <p>Best regards,<br><strong>Gerona Hospital IT Security</strong></p>
            </div>
            <div class="email-footer">
                Gerona Hospital Educational Foundation, Inc. &bull; Gerona, Tarlac, Philippines
            </div>
        </div>
    </body>
    </html>
    ';

    return sendGmailSMTPDirect($to_email, $subject, $htmlBody, $full_name);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return '&#8369;' . number_format($amount, 2);
}

function getAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}
?>
