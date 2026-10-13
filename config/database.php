<?php
// Database configuration for Gerona Hospital Management System
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_NAME", "hmsystem");
define("SITE_NAME", "Gerona Hospital");
define("SITE_TAGLINE", "Information and Billing Management System");

date_default_timezone_set('Asia/Manila');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = '+08:00'");
    return $conn;
}
?>
