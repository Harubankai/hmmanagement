<?php
session_start();
require_once __DIR__ . '/includes/auth_functions.php';

if (isLoggedIn()) {
    header("Location: /hmsystem/dashboard/index.php");
} else {
    header("Location: /hmsystem/auth/login.php");
}
exit();
