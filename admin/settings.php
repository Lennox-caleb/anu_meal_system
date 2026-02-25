<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['org_name','org_email','timezone','booking_open','booking_close',
               'auto_reset','maintenance_mode','meal_alerts','email_reports'];
    $stmt = $conn->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
    foreach ($fields as $f) {
        $val = isset($_POST[$f]) ? $conn->real_escape_string($_POST[$f]) : '0';
        $stmt->bind_param("sss", $f, $val, $val);
        $stmt->execute();
    }
    logAction($conn, 'Settings Updated', 'System settings were modified.');
    $msg = 'Settings saved successfully!';
}

// Load settings
$settings_raw = $conn->query("SELECT setting_key, setting_value FROM settings");
$s = [];
while ($r = $settings_raw->fetch_assoc()) $s[$r['setting_key']] = $r['setting_value'];

// Compute live booking window status for display
$tz_setting = $s['timezone'] ?? 'Africa/Nairobi';
date_default_timezone_set($tz_setting);
$now_time    = date('H:i');
$open_time   = $s['booking_open']  ?? '06:00';
$close_time  = $s['booking_close'] ?? '22:00';
$window_open = ($now_time >= $open_time && $now_time <= $close_time);
$maintenance = ($s['maintenance_mode'] ?? '0') === '1';

// Load logs
$logs = $conn->query("
    SELECT l.*, u.username FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC LIMIT 30
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | ANU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <meta http-equiv="refresh" content="30"><!-- refresh every 30s to keep live status current -->
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content flex-grow-1">
        <div class="topbar">
            <h1><i class="bi bi-gear me-2"></i>System Settings</h1>
        </div>
        <div class="p-4 fade-in-up">
            <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="settings-card">
                            <h5><i class="bi bi-building me-2"></i>General Configuration</h5>
                            <div class="mb-3">
                                <label class="form-label">Organization Name</label>
                                <input type="text" name="org_name" class="form-control" value="<?= htmlspecialchars($s['org_name'] ?? 'Africa Nazarene University') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">System Email</label>
                                <input type="email" name="org_email" class="form-control" value="<?= htmlspecialchars($s['org_email'] ?? 'support@anu.ac.ke') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <select name="timezone" class="form-select">
                                    <option value="Africa/Nairobi" <?= ($s['timezone']??'')=='Africa/Nairobi'?'selected':'' ?>>Africa/Nairobi (EAT)</option>
                                    <option value="UTC" <?= ($s['timezone']??'')==='UTC'?'selected':'' ?>>UTC</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-card">
                            <h5><i class="bi bi-clock me-2"></i>Booking Window</h5>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Opens At</label>
                                    <input type="time" name="booking_open" class="form-control" value="<?= $s['booking_open'] ?? '06:00' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Closes At</label>
                                    <input type="time" name="booking_close" class="form-control" value="<?= $s['booking_close'] ?? '22:00' ?>">
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_reset" id="autoReset" <?= ($s['auto_reset']??'0')==='1'?'checked':'' ?> value="1">
                                <label class="form-check-label" for="autoReset">Auto-reset bookings daily</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <!-- LIVE STATUS CARD -->
                        <div class="settings-card mb-3">
                            <h5><i class="bi bi-activity me-2"></i>Live System Status</h5>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f8f9fa;">
                                    <span class="small fw-semibold"><i class="bi bi-clock me-1"></i>Current Time</span>
                                    <span class="badge bg-secondary"><?= date('H:i:s') ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f8f9fa;">
                                    <span class="small fw-semibold"><i class="bi bi-calendar-check me-1"></i>Booking Window</span>
                                    <?php if ($window_open): ?>
                                    <span class="badge bg-success"><i class="bi bi-unlock me-1"></i>OPEN (until <?= $close_time ?>)</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-lock me-1"></i>CLOSED (opens <?= $open_time ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f8f9fa;">
                                    <span class="small fw-semibold"><i class="bi bi-tools me-1"></i>Maintenance Mode</span>
                                    <?php if ($maintenance): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>ACTIVE — Students blocked</span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>OFF — System normal</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>This updates on page refresh. Changes take effect immediately on save.</p>
                        </div>

                        <div class="settings-card">
                            <h5><i class="bi bi-toggles me-2"></i>System Operations</h5>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" id="mainMode" <?= ($s['maintenance_mode']??'0')==='1'?'checked':'' ?> value="1">
                                <label class="form-check-label" for="mainMode">
                                    <strong>Maintenance Mode</strong>
                                    <div class="text-muted small">Temporarily disable student access — students will see a maintenance page</div>
                                </label>
                            </div>
                        </div>

                        <div class="settings-card">
                            <h5><i class="bi bi-bell me-2"></i>Notifications</h5>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="meal_alerts" id="mealAlerts" <?= ($s['meal_alerts']??'1')==='1'?'checked':'' ?> value="1">
                                <label class="form-check-label" for="mealAlerts">Enable meal booking alerts</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_reports" id="emailRep" <?= ($s['email_reports']??'0')==='1'?'checked':'' ?> value="1">
                                <label class="form-check-label" for="emailRep">Email daily reports to admins</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-anu px-5">
                        <i class="bi bi-save me-2"></i>Save Settings
                    </button>
                </div>
            </form>

            <!-- System Logs -->
            <div class="settings-card mt-4">
                <h5><i class="bi bi-journal-text me-2"></i>System Activity Logs</h5>
                <div style="max-height:300px;overflow-y:auto;">
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <div class="log-entry">
                        <span class="text-muted"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></span>
                        — <strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong>:
                        <?= htmlspecialchars($log['action']) ?>
                        <?php if ($log['details']): ?>
                            <span class="text-muted small"> – <?= htmlspecialchars($log['details']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
