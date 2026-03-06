<?php
mysqli_report(MYSQLI_REPORT_OFF);

// Use environment variables (same as db.php)
$db_host = getenv('MYSQLHOST')     ?: 'localhost';
$db_port = (int)(getenv('MYSQLPORT') ?: 3307);
$db_user = getenv('MYSQLUSER')     ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: 'anu_meal_booking';

// Remote hosts require SSL — local dev connects normally
if (getenv('MYSQLHOST')) {
    $conn = mysqli_init();
    mysqli_ssl_set($conn, null, null, null, null, null);
    $conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, null, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
} else {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
}

if ($conn->connect_errno) {
    die('Database Error: ' . $conn->connect_error);
}

$result = $conn->query("SELECT id, username, password, role FROM users LIMIT 20");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Check Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:800px">
    <div class="card">
        <div class="card-header bg-info text-white fw-bold">Database Users Check</div>
        <div class="card-body">
            <table class="table table-striped table-sm">
                <thead>
                    <tr><th>ID</th><th>Username</th><th>Role</th><th>Password Hash (first 30 chars)</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><code><?= $row['username'] ?></code></td>
                        <td><?= $row['role'] ?></td>
                        <td><code><?= substr($row['password'], 0, 30) ?>...</code></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <?php if ($result->num_rows === 0): ?>
                <div class="alert alert-danger">❌ No users found! The database is empty.</div>
            <?php else: ?>
                <div class="alert alert-success">✅ <?= $result->num_rows ?> users found in database.</div>
                <p class="small text-muted">
                    If <code>superadmin</code>, <code>admin</code>, or <code>student</code> are NOT showing above, 
                    fix_login.php didn't insert them properly.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
