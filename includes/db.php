<?php
// =====================================================
// DATABASE CONFIGURATION
// Railway env vars: MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
// Local fallbacks for XAMPP/WAMP development
// =====================================================

mysqli_report(MYSQLI_REPORT_OFF);

$db_host = getenv('MYSQLHOST')     ?: 'localhost';
$db_port = (int)(getenv('MYSQLPORT') ?: 3307);
$db_user = getenv('MYSQLUSER')     ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: 'anu_meal_booking';

// Remote hosts (Aiven, etc.) require SSL — local dev connects normally
if (getenv('MYSQLHOST')) {
    $conn = mysqli_init();
    mysqli_ssl_set($conn, null, null, null, null, null);
    $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, null, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT); //
} else {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
}

if ($conn->connect_errno) {
    $err = $conn->connect_error;
    $is_password_issue = strpos($err, 'Access denied') !== false;
    $is_no_db          = strpos($err, 'Unknown database') !== false;

    die('<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>DB Error</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head><body class="bg-light">
<div class="container py-5" style="max-width:640px">
  <div class="card border-danger border-2 shadow-sm">
    <div class="card-header bg-danger text-white fw-bold fs-5">&#10060; Database Connection Failed</div>
    <div class="card-body">
      <p><strong>Error:</strong> <code>' . htmlspecialchars($err) . '</code></p>
      ' . ($is_password_issue ? '
      <div class="alert alert-warning">
        <strong>Password Issue:</strong> Edit <code>includes/db.php</code> line 6 and set the correct password.<br>
        If your MySQL has no password, make sure line 6 is exactly:<br>
        <code>define(\'DB_PASS\', \'\');</code>
      </div>' : '') . '
      ' . ($is_no_db ? '
      <div class="alert alert-info">
        <strong>Database not found:</strong> Visit
        <a href="../install.php"><strong>install.php</strong></a> to create it.
      </div>' : '') . '
    </div>
  </div>
</div></body></html>');
}

$conn->set_charset('utf8mb4');

// Tell MySQL to return TIMESTAMP columns in EAT (UTC+3)
$conn->query("SET time_zone = '+03:00'");

// Keep PHP's clock in sync with the DB timezone.
date_default_timezone_set('Africa/Nairobi');