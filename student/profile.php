<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();
checkMaintenance($conn);
if ($_SESSION['role'] !== 'student') { header('Location: ../admin/dashboard.php'); exit; }

$uid = $_SESSION['user_id'];
$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        if (!$fullname || !$email) { $error = 'Name and email are required.'; }
        else {
            $stmt = $conn->prepare("UPDATE users SET fullname=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("sssi", $fullname, $email, $phone, $uid);
            $stmt->execute();
            $_SESSION['fullname'] = $fullname;
            $_SESSION['email']    = $email;
            logAction($conn, 'Profile Updated', "Student #$uid updated their profile.");
            $msg = 'Profile updated successfully!';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $user_row = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();
        if (!password_verify($current, $user_row['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hashed' WHERE id=$uid");
            logAction($conn, 'Password Changed', "Student #$uid changed their password.");
            $msg = 'Password changed successfully!';
        }
    }
}

$profile = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$booking_count  = $conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id=$uid")->fetch_assoc()['c'];
$consumed_count = $conn->query("SELECT COUNT(*) c FROM bookings WHERE user_id=$uid AND status='consumed'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | ANU Student</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
<div class="d-flex">
    <?php include '../includes/student_sidebar.php'; ?>
    <div class="main-content flex-grow-1">
        <?php $page_title = '<i class="bi bi-person-circle me-2"></i>My Profile'; include '../includes/topbar.php'; ?>
        <div class="p-4 fade-in-up">
            <?php if ($msg):  ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="row g-3">
                <!-- Profile Card -->
                <div class="col-md-4">
                    <div class="chart-card text-center">
                        <div class="avatar-circle mx-auto mb-3" style="width:80px;height:80px;font-size:2rem;">
                            <?= strtoupper(substr($profile['fullname'], 0, 1)) ?>
                        </div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($profile['fullname']) ?></h5>
                        <div class="text-muted small mb-1">@<?= htmlspecialchars($profile['username']) ?></div>
                        <span class="badge bg-success">Student</span>
                        <?php if ($profile['student_id']): ?>
                        <div class="mt-2 small"><code><?= htmlspecialchars($profile['student_id']) ?></code></div>
                        <?php endif; ?>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6 border-end">
                                <div class="fw-bold fs-4 text-danger"><?= $booking_count ?></div>
                                <div class="small text-muted">Bookings</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold fs-4" style="color:#17a2b8;"><?= $consumed_count ?></div>
                                <div class="small text-muted">Consumed</div>
                            </div>
                        </div>
                        <div class="mt-3 small text-muted">
                            Member since <?= date('M Y', strtotime($profile['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile -->
                <div class="col-md-8">
                    <div class="chart-card mb-3">
                        <h6 class="section-title">Edit Profile</h6>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($profile['fullname']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="+254...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['student_id'] ?? '') ?>" disabled>
                                    <div class="form-text">Contact admin to change your Student ID.</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-anu mt-3"><i class="bi bi-save me-1"></i>Save Changes</button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="chart-card">
                        <h6 class="section-title">Change Password</h6>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-danger mt-3"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/scripts.php'; ?>
</body>
</html>
