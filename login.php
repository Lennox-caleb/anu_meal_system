<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header('Location: ' . ($role === 'student' ? 'student/dashboard.php' : 'admin/dashboard.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['email']    = $user['email'];

            logAction($conn, 'User Login', "User {$user['username']} ({$user['role']}) logged in.");

            if ($user['role'] === 'student') {
                header('Location: student/dashboard.php');
            } else {
                header('Location: admin/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANU Meal Booking – Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<div class="login-bg">
    <div class="login-card">
        <div class="row g-0">
            <!-- Branding -->
            <div class="col-md-5 login-brand">
                <img src="public/images/ANU-Logo-PNG-logo.png" alt="ANU Logo" class="mb-3" onerror="this.style.display='none'">
                <h4 class="fw-bold mb-2">ANU Meal Booking</h4>
                <p class="opacity-90 small"></p>
                <hr class="border-white opacity-50 w-75 my-3">
                <div class="small opacity-75">
                    <div><i class="bi bi-check-circle me-1"></i> Real-time menu updates</div>
                    <div><i class="bi bi-check-circle me-1"></i> Secure QR meal validation</div>
                    <div><i class="bi bi-check-circle me-1"></i> Analytics & reports</div>
                </div>
            </div>

            <!-- Form -->
            <div class="col-md-7 login-form-section">
                <h4 class="fw-bold mb-1" style="color:#ff0000;">Welcome Back</h4>
                <p class="text-muted small mb-4">Sign in to your ANU account</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

            
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Enter username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember">
                            <label class="form-check-label small" for="remember">Remember me</label>
                        </div>
                    </div>
                    <div class="register">
                        <p><small>Don't have an account? <a href="register.php" class="text-decoration-none">Register here</a></small></p>
                    </div>
                    <button type="submit" class="btn btn-anu w-100 py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                    </button>
                </form>

                <div class="text-center mt-4 small text-muted">
                    <a href="https://www.anu.ac.ke" target="_blank" style="color:#ff0000;">
                        <i class="bi bi-globe me-1"></i>Visit ANU Website
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    const inp = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
</body>
</html>
