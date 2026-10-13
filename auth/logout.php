<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

if (isLoggedIn()) {
    $conn = getDBConnection();
    logActivity($conn, $_SESSION['user_id'], 'User Logout', $_SESSION['full_name'] . ' logged out of the system');
    $conn->close();
}

session_destroy();
header("Location: /hmsystem/auth/login.php?logged_out=1");
exit();
?>
