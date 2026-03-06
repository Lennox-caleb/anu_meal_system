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
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Generate fresh correct bcrypt hashes
$hash_admin   = password_hash('admin123',   PASSWORD_BCRYPT);
$hash_student = password_hash('student123', PASSWORD_BCRYPT);

// Delete old broken users first
$conn->query("DELETE FROM users WHERE username IN ('superadmin','admin','student')");

// Use direct SQL with proper escaping (more reliable on TiDB than prepared statements)
// Include explicit ID values since TiDB doesn't auto-increment without being set initially
$inserts = [
    "INSERT INTO users (id, username, password, fullname, email, role, student_id) VALUES (1, 'superadmin', '$hash_admin', 'Super Administrator', 'superadmin@anu.ac.ke', 'super_admin', 'SA001')",
    "INSERT INTO users (id, username, password, fullname, email, role, student_id) VALUES (2, 'admin', '$hash_admin', 'Cafeteria Manager', 'manager@anu.ac.ke', 'admin', 'AD001')",
    "INSERT INTO users (id, username, password, fullname, email, role, student_id) VALUES (4, 'student', '$hash_student', 'John Doe', 'john.doe@anu.ac.ke', 'student', 'ANU/2024/001')",
];

$errors = [];
foreach ($inserts as $sql) {
    if (!$conn->query($sql)) {
        $errors[] = "Query error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:500px">
    <div class="card border-0 shadow rounded-4 overflow-hidden">
        <div class="card-header py-3 text-white fw-bold"
             style="background:linear-gradient(135deg,#ff0000,#fac823)">
            ✅ Login Passwords Fixed!
        </div>
        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>❌ Errors occurred:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="text-success fw-semibold">✅ All default users have been reset successfully.</p>

                <table class="table table-bordered small">
                    <thead class="table-danger">
                        <tr><th>Role</th><th>Username</th><th>Password</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Super Admin</td><td><code>superadmin</code></td><td><code>admin123</code></td></tr>
                        <tr><td>Admin</td>      <td><code>admin</code></td>      <td><code>admin123</code></td></tr>
                        <tr><td>Student</td>    <td><code>student</code></td>    <td><code>student123</code></td></tr>
                    </tbody>
                </table>

                <div class="alert alert-warning small py-2">
                    ⚠️ Delete <code>fix_login.php</code> after logging in successfully!
                </div>
            <?php endif; ?>

            <a href="login.php"
               class="btn w-100 fw-bold text-white"
               style="background:linear-gradient(135deg,#ff0000,#fac823)">
                → Go to Login
            </a>
        </div>
    </div>
</div>
</body>
</html>