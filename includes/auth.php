<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBasePath() . 'login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
        header('Location: ' . getBasePath() . 'student/dashboard.php');
        exit;
    }
}

function requireSuperAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'super_admin') {
        header('Location: ' . getBasePath() . 'admin/dashboard.php');
        exit;
    }
}

function getBasePath() {
    $script = $_SERVER['SCRIPT_NAME'];
    if (strpos($script, '/admin/') !== false || strpos($script, '/student/') !== false) {
        return '../';
    }
    return '';
}

function currentUser() {
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'fullname' => $_SESSION['fullname'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
        'email'    => $_SESSION['email'] ?? '',
    ];
}

function logAction($conn, $action, $details = '') {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, ip) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
}

function generateBookingCode($conn = null) {
    $attempts = 0;
    do {
        $code = 'ANU-' . strtoupper(bin2hex(random_bytes(4)));
        $attempts++;
        if ($conn) {
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE code = ? LIMIT 1");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
        } else {
            $exists = false;
        }
    } while ($exists && $attempts < 10);
    return $code;
}

function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? $res['setting_value'] : $default;
}

// ============================================================
// MAINTENANCE MODE
// Blocks students from all pages when maintenance is active.
// ============================================================
function checkMaintenance($conn) {
    if (getSetting($conn, 'maintenance_mode', '0') !== '1') return;
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'student') return; // admins unaffected

    $tz = getSetting($conn, 'timezone', 'Africa/Nairobi');
    date_default_timezone_set($tz);
    $now = date('D, d M Y H:i:s');
    die('<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>System Maintenance | ANU</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: linear-gradient(135deg,#fff5f5,#fffde7); }
.maint-card { background:#fff; border-radius:20px; padding:50px 40px; box-shadow:0 10px 40px rgba(0,0,0,.1); max-width:500px; }
.maint-icon { font-size:5rem; animation: spin 4s linear infinite; }
@keyframes spin { 0%,100%{transform:rotate(-10deg)} 50%{transform:rotate(10deg)} }
</style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="maint-card text-center">
  <div class="maint-icon">🔧</div>
  <h2 class="fw-bold mt-3" style="color:#ff0000;">System Maintenance</h2>
  <p class="text-muted mt-2">Meal booking is temporarily unavailable due to scheduled maintenance.</p>
  <p class="text-muted">Please check back later.</p>
  <div class="alert alert-warning py-2 small mt-3">
    <i class="bi bi-clock me-1"></i><strong>Current time:</strong> ' . $now . '
  </div>
  <a href="../logout.php" class="btn btn-outline-danger mt-2">
    <i class="bi bi-box-arrow-left me-1"></i>Logout
  </a>
</div>
</body></html>');
}

// ============================================================
// BOOKING WINDOW CHECK (date + time based)
// Returns array with open status and all window details.
// ============================================================
function getBookingWindowStatus($conn) {
    $tz = getSetting($conn, 'timezone', 'Africa/Nairobi');
    date_default_timezone_set($tz);

    // Support both date+time and time-only formats
    $start_raw = getSetting($conn, 'booking_start', '');
    $end_raw   = getSetting($conn, 'booking_end',   '');

    // Fall back to old time-only settings if new ones not set
    if (empty($start_raw)) {
        $start_raw = date('Y-m-d') . ' ' . getSetting($conn, 'booking_open',  '06:00') . ':00';
    }
    if (empty($end_raw)) {
        $end_raw   = date('Y-m-d') . ' ' . getSetting($conn, 'booking_close', '22:00') . ':00';
    }

    $now_ts   = time();
    $start_ts = strtotime($start_raw);
    $end_ts   = strtotime($end_raw);

    // Handle invalid dates
    if (!$start_ts || !$end_ts) {
        return [
            'open'      => false,
            'start'     => $start_raw,
            'end'       => $end_raw,
            'now'       => date('Y-m-d H:i:s'),
            'reason'    => 'Booking window not configured.',
        ];
    }

    $is_open = ($now_ts >= $start_ts && $now_ts <= $end_ts);

    return [
        'open'        => $is_open,
        'start'       => $start_raw,
        'end'         => $end_raw,
        'start_fmt'   => date('D, d M Y H:i', $start_ts),
        'end_fmt'     => date('D, d M Y H:i', $end_ts),
        'now'         => date('Y-m-d H:i:s'),
        'now_fmt'     => date('D, d M Y H:i:s'),
        'reason'      => $is_open ? '' : ($now_ts < $start_ts
            ? 'Booking opens on ' . date('D, d M Y \a\t H:i', $start_ts)
            : 'Booking closed on ' . date('D, d M Y \a\t H:i', $end_ts)),
    ];
}

function isBookingOpen($conn) {
    return getBookingWindowStatus($conn)['open'];
}
