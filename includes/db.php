<?php
// =====================================================
// DATABASE CONFIGURATION
// =====================================================
//define('DB_HOST', 'localhost');
//define('DB_USER', 'root');
//define('DB_PASS', '');
//define('DB_NAME', 'anu_meal_booking');
// =====================================================

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli('localhost', 'root','', 'anu_meal_booking', 3307);

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