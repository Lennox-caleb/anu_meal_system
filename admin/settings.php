<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireSuperAdmin();

$msg      = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // All recognised keys — extend here as features grow
    $fields = [
        'org_name', 'org_email', 'timezone',
        'booking_open', 'booking_close', 'auto_reset',
        'maintenance_mode',
        'meal_alerts', 'email_reports',
        'smtp_host', 'smtp_port',
        'smtp_user', 'smtp_pass',
        'smtp_from', 'smtp_from_name',
    ];
    $stmt = $conn->prepare(
        "INSERT INTO settings (setting_key,setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value=?"
    );
    foreach ($fields as $f) {
        $val = isset($_POST[$f]) ? trim($_POST[$f]) : '0';
        $stmt->bind_param('sss', $f, $val, $val);
        $stmt->execute();
    }
    logAction($conn, 'Settings Updated', 'System settings were modified.');
    $msg = 'Settings saved successfully! Changes are now live.';
}

// ── Load all settings ─────────────────────────────────────────────
$settings_raw = $conn->query("SELECT setting_key, setting_value FROM settings");
$s = [];
while ($r = $settings_raw->fetch_assoc()) $s[$r['setting_key']] = $r['setting_value'];

// ── Compute live status ───────────────────────────────────────────
$tz_setting  = $s['timezone'] ?? 'Africa/Nairobi';
date_default_timezone_set($tz_setting);
$now_time    = date('H:i');
$open_time   = $s['booking_open']  ?? '06:00';
$close_time  = $s['booking_close'] ?? '22:00';
$window_open = ($now_time >= $open_time && $now_time <= $close_time);
$maintenance = ($s['maintenance_mode'] ?? '0') === '1';

// ── System logs ───────────────────────────────────────────────────
$logs = $conn->query(
    "SELECT l.*, u.username FROM system_logs l
     LEFT JOIN users u ON l.user_id=u.id
     ORDER BY l.created_at DESC LIMIT 40"
);

// ── Notification log ──────────────────────────────────────────────
$notif_exists = $conn->query("SHOW TABLES LIKE 'notifications_log'")->num_rows > 0;
$notif_rows   = null;
$notif_stats  = ['sent' => 0, 'failed' => 0];
if ($notif_exists) {
    $notif_rows = $conn->query(
        "SELECT nl.*, u.fullname, u.email, b.code booking_code
         FROM notifications_log nl
         LEFT JOIN users u    ON nl.user_id    = u.id
         LEFT JOIN bookings b ON nl.booking_id = b.id
         ORDER BY nl.sent_at DESC LIMIT 30"
    );
    $ns = $conn->query(
        "SELECT status, COUNT(*) c FROM notifications_log GROUP BY status"
    );
    while ($r = $ns->fetch_assoc()) $notif_stats[$r['status']] = (int)$r['c'];
}
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
<style>
.s-card { background:#fff; border-radius:14px; padding:22px; margin-bottom:18px; box-shadow:0 2px 12px rgba(0,0,0,.07); }
.s-card h5 { font-weight:700; margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid #f5f5f5; font-size:15px; }
.status-row { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-radius:10px; margin-bottom:8px; }
.live-clock { font-family:"Courier New",monospace; font-size:1.4rem; font-weight:800; color:#cc0000; background:#fff8f8; padding:8px 20px; border-radius:10px; border:2px solid #fde8e8; letter-spacing:3px; display:inline-block; }
.log-entry  { padding:7px 0; border-bottom:1px solid #f5f5f5; font-size:12.5px; }
.log-entry:last-child { border-bottom:none; }
.nl-sent   { background:#d1fae5;color:#065f46; }
.nl-failed { background:#fee2e2;color:#991b1b; }
.pw-wrap { position:relative; }
.pw-wrap input { padding-right:42px; }
.pw-btn { position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;padding:0; }
</style>
</head>
<body>
<div class="d-flex">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content flex-grow-1">

<div class="topbar"><h1><i class="bi bi-gear me-2"></i>System Settings</h1></div>

<div class="p-4 fade-in-up">

<?php if ($msg): ?>
<div class="alert alert-<?= $msg_type ?> alert-dismissible fade show py-2">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-0" id="sTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tGeneral">
        <i class="bi bi-sliders me-1"></i>General
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tSMTP">
        <i class="bi bi-envelope-at me-1"></i>Email &amp; SMTP
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tStatus">
        <i class="bi bi-activity me-1"></i>Live Status
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tNotif">
        <i class="bi bi-bell me-1"></i>Notification Log
        <?php if ($notif_exists): ?>
        <span class="badge bg-<?= $notif_stats['failed']>0?'danger':'success' ?> ms-1" style="font-size:10px;">
            <?= $notif_stats['failed'] > 0 ? $notif_stats['failed'].' failed' : $notif_stats['sent'].' sent' ?>
        </span>
        <?php endif; ?>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tLogs">
        <i class="bi bi-journal-text me-1"></i>Activity Logs
    </button></li>
</ul>

<form method="POST">
<div class="tab-content" style="background:#f8f9fa;border-radius:0 14px 14px 14px;padding:20px;">

<!-- ══ GENERAL ════════════════════════════════════════════ -->
<div class="tab-pane fade show active" id="tGeneral">
<div class="row g-3">
<div class="col-lg-6">

  <div class="s-card">
    <h5><i class="bi bi-building me-2 text-danger"></i>Organization</h5>
    <div class="mb-3">
        <label class="form-label fw-semibold small">Organization Name</label>
        <input type="text" name="org_name" class="form-control"
               value="<?= htmlspecialchars($s['org_name'] ?? 'Africa Nazarene University') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold small">System Email</label>
        <input type="email" name="org_email" class="form-control"
               value="<?= htmlspecialchars($s['org_email'] ?? 'support@anu.ac.ke') ?>">
    </div>
    <div>
        <label class="form-label fw-semibold small">Timezone</label>
        <select name="timezone" class="form-select">
            <option value="Africa/Nairobi" <?= ($s['timezone']??'')==='Africa/Nairobi'?'selected':'' ?>>Africa/Nairobi (EAT UTC+3)</option>
            <option value="Africa/Lagos"   <?= ($s['timezone']??'')==='Africa/Lagos'  ?'selected':'' ?>>Africa/Lagos (WAT UTC+1)</option>
            <option value="UTC"            <?= ($s['timezone']??'')==='UTC'           ?'selected':'' ?>>UTC</option>
        </select>
    </div>
  </div>

  <div class="s-card">
    <h5><i class="bi bi-clock me-2 text-danger"></i>Booking Window</h5>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label fw-semibold small"><i class="bi bi-play-circle text-success me-1"></i>Opens At</label>
            <input type="time" name="booking_open" class="form-control fw-bold"
                   value="<?= $s['booking_open'] ?? '06:00' ?>">
        </div>
        <div class="col-6">
            <label class="form-label fw-semibold small"><i class="bi bi-stop-circle text-danger me-1"></i>Closes At</label>
            <input type="time" name="booking_close" class="form-control fw-bold"
                   value="<?= $s['booking_close'] ?? '22:00' ?>">
        </div>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="auto_reset" id="autoReset"
               <?= ($s['auto_reset']??'0')==='1'?'checked':'' ?> value="1">
        <label class="form-check-label small" for="autoReset">Auto-reset pending bookings daily at midnight</label>
    </div>
  </div>

</div>
<div class="col-lg-6">

  <div class="s-card">
    <h5><i class="bi bi-tools me-2 text-danger"></i>Maintenance Mode</h5>
    <p class="text-muted small mb-3">When ON, students are immediately blocked. Admins unaffected.</p>
    <div class="d-flex justify-content-between align-items-center p-3 rounded-3"
         style="background:<?= $maintenance?'#fffbeb':'#f0fdf4' ?>;border:2px solid <?= $maintenance?'#fcd34d':'#86efac' ?>">
        <div>
            <div class="fw-bold"><?= $maintenance?'🔴 Maintenance ACTIVE':'🟢 System ONLINE' ?></div>
            <div class="small text-muted"><?= $maintenance?'Students blocked':'Operating normally' ?></div>
        </div>
        <div class="form-check form-switch mb-0" style="font-size:1.4rem;">
            <input class="form-check-input" type="checkbox" name="maintenance_mode" id="mainToggle"
                   value="1" <?= $maintenance?'checked':'' ?> onchange="this.form.submit()">
        </div>
    </div>
    <div class="small text-muted mt-2"><i class="bi bi-lightning me-1"></i>Toggle saves immediately.</div>
  </div>

  <div class="s-card">
    <h5><i class="bi bi-bell me-2 text-danger"></i>Notification Settings</h5>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="meal_alerts" id="mealAlerts"
               <?= ($s['meal_alerts']??'1')==='1'?'checked':'' ?> value="1">
        <label class="form-check-label" for="mealAlerts">
            <strong>Meal Booking Alerts</strong>
            <div class="text-muted small">Email students on approve, reject, and consume events.</div>
        </label>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="email_reports" id="emailRep"
               <?= ($s['email_reports']??'0')==='1'?'checked':'' ?> value="1">
        <label class="form-check-label" for="emailRep">
            <strong>Daily Email Reports</strong>
            <div class="text-muted small">Send daily summary + CSV to all admins at 23:59 via cron.</div>
        </label>
    </div>
    <?php if (($s['email_reports']??'0')==='1'): ?>
    <div class="alert alert-info small py-2 mt-3 mb-0">
        <i class="bi bi-terminal me-1"></i>Cron required:
        <code>59 23 * * * php /path/to/cron/cron_daily_report.php</code>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>
</div><!-- /tGeneral -->

<!-- ══ SMTP ══════════════════════════════════════════════ -->
<div class="tab-pane fade" id="tSMTP">
<div class="row g-3">
<div class="col-lg-7">
  <div class="s-card">
    <h5><i class="bi bi-envelope-at me-2 text-danger"></i>SMTP Configuration</h5>
    <p class="text-muted small mb-3">
        Used by <strong>NotificationService.php</strong> to send all emails.
        Install PHPMailer first: <code>composer require phpmailer/phpmailer</code>
    </p>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label fw-semibold small">SMTP Host</label>
            <input type="text" name="smtp_host" class="form-control"
                   placeholder="smtp.gmail.com"
                   value="<?= htmlspecialchars($s['smtp_host'] ?? 'smtp.gmail.com') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold small">Port</label>
            <select name="smtp_port" class="form-select">
                <option value="587" <?= ($s['smtp_port']??'587')==='587'?'selected':'' ?>>587 — STARTTLS</option>
                <option value="465" <?= ($s['smtp_port']??'')==='465'?'selected':'' ?>>465 — SSL</option>
                <option value="25"  <?= ($s['smtp_port']??'')==='25' ?'selected':'' ?>>25 — Plain</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold small">SMTP Username</label>
            <input type="email" name="smtp_user" class="form-control"
                   placeholder="your@gmail.com"
                   value="<?= htmlspecialchars($s['smtp_user'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold small">Password / App Password</label>
            <div class="pw-wrap">
                <input type="password" name="smtp_pass" id="smtpPwd" class="form-control"
                       placeholder="Gmail app password"
                       value="<?= htmlspecialchars($s['smtp_pass'] ?? '') ?>">
                <button type="button" class="pw-btn" onclick="
                    var i=document.getElementById('smtpPwd');
                    i.type=i.type==='password'?'text':'password';
                    this.innerHTML=i.type==='text'?'<i class=\'bi bi-eye-slash\'></i>':'<i class=\'bi bi-eye\'></i>'">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold small">From Address</label>
            <input type="email" name="smtp_from" class="form-control"
                   placeholder="noreply@anu.ac.ke"
                   value="<?= htmlspecialchars($s['smtp_from'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold small">From Name</label>
            <input type="text" name="smtp_from_name" class="form-control"
                   placeholder="ANU Meal Booking"
                   value="<?= htmlspecialchars($s['smtp_from_name'] ?? 'ANU Meal Booking System') ?>">
        </div>
    </div>
  </div>
</div>
<div class="col-lg-5">
  <div class="s-card">
    <h5><i class="bi bi-question-circle me-2 text-danger"></i>Setup Guide</h5>
    <div class="small">
        <p class="fw-semibold mb-1">1. Install PHPMailer</p>
        <code class="d-block bg-dark text-success p-2 rounded mb-3">composer require phpmailer/phpmailer</code>
        <p class="fw-semibold mb-1">2. Gmail App Password</p>
        <ol class="ps-3 text-muted mb-3">
            <li>Enable 2-Factor Auth on Google account</li>
            <li>Account → Security → App Passwords</li>
            <li>Create for "Mail" — paste above</li>
        </ol>
        <p class="fw-semibold mb-1">3. Trigger Alerts (bookings.php)</p>
        <code class="d-block bg-light p-2 rounded small mb-3" style="font-size:11px;">$n = new NotificationService($conn);<br>$n->sendBookingAlert($id, 'approved');</code>
        <p class="fw-semibold mb-1">4. Cron Job (crontab -e)</p>
        <code class="d-block bg-dark text-success p-2 rounded small" style="font-size:11px;">59 23 * * * php /path/to/cron/cron_daily_report.php</code>
    </div>
  </div>
  <div class="s-card">
    <h5><i class="bi bi-terminal me-2 text-danger"></i>Test the Cron</h5>
    <p class="text-muted small mb-2">From CLI:</p>
    <code class="d-block bg-dark text-success p-2 rounded small" style="font-size:11px;">
        # Dry-run — no emails sent<br>
        php cron_daily_report.php --test<br><br>
        # Specific date<br>
        php cron_daily_report.php --date=2025-03-01<br><br>
        # Force even if disabled<br>
        php cron_daily_report.php --force
    </code>
  </div>
</div>
</div>
</div><!-- /tSMTP -->

<!-- ══ LIVE STATUS ════════════════════════════════════════ -->
<div class="tab-pane fade" id="tStatus">
<div class="row g-3">
<div class="col-lg-5">
  <div class="s-card">
    <h5><i class="bi bi-activity me-2 text-danger"></i>Live System Status</h5>
    <div class="text-center mb-4">
        <div class="small text-muted mb-1">Server Time (<?= htmlspecialchars($tz_setting) ?>)</div>
        <div class="live-clock" id="liveClock"><?= date('H:i:s') ?></div>
    </div>

    <?php
    $rows = [
        ['Booking Window', $window_open?'OPEN':'CLOSED',
         $window_open?'bg-success':'bg-danger', "bi-".($window_open?'unlock':'lock')."-fill",
         "{$open_time} – {$close_time}"],
        ['Maintenance',   $maintenance?'ACTIVE':'OFF',
         $maintenance?'bg-warning text-dark':'bg-success', 'bi-tools',
         $maintenance?'Students blocked':'Operating normally'],
        ['Meal Alerts',   ($s['meal_alerts']??'0')==='1'?'ENABLED':'OFF',
         ($s['meal_alerts']??'0')==='1'?'bg-success':'bg-secondary', 'bi-bell',
         'Student booking notifications'],
        ['Daily Reports', ($s['email_reports']??'0')==='1'?'ENABLED':'OFF',
         ($s['email_reports']??'0')==='1'?'bg-success':'bg-secondary', 'bi-envelope',
         ($s['smtp_host']??'Not configured')],
    ];
    foreach ($rows as [$label,$badge,$cls,$ico,$sub]):
    ?>
    <div class="status-row" style="background:#f8f9fa;border:1px solid #eee;">
        <div>
            <div class="fw-semibold small"><i class="bi <?= $ico ?> me-1"></i><?= $label ?></div>
            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($sub) ?></div>
        </div>
        <span class="badge <?= $cls ?>"><?= $badge ?></span>
    </div>
    <?php endforeach; ?>

    <p class="text-muted mt-3 mb-0" style="font-size:11px;">
        <i class="bi bi-info-circle me-1"></i>Clock ticks live. Status updates on save.
    </p>
  </div>
</div>
</div>
</div><!-- /tStatus -->

<!-- ══ NOTIFICATION LOG ═══════════════════════════════════ -->
<div class="tab-pane fade" id="tNotif">
<?php if (!$notif_exists): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>notifications_log table not found.</strong>
    Run <code>notifications_schema.sql</code> in phpMyAdmin to create it.
</div>
<?php else: ?>
<div class="s-card">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-bell-fill me-2 text-danger"></i>Notification Activity Log</h5>
    <div class="d-flex gap-2 small">
        <span class="badge bg-success"><?= number_format($notif_stats['sent']) ?> sent</span>
        <?php if ($notif_stats['failed'] > 0): ?>
        <span class="badge bg-danger"><?= number_format($notif_stats['failed']) ?> failed</span>
        <?php endif; ?>
    </div>
</div>
<p class="text-muted small mb-3">
    Every email sent or attempted by NotificationService is logged here.
    Last 30 records shown.
</p>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0">
    <thead style="background:#1a1a1a;color:#fff;">
        <tr>
            <th class="small py-2">Time</th>
            <th class="small py-2">Student</th>
            <th class="small py-2">Code</th>
            <th class="small py-2">Type</th>
            <th class="small py-2">Status</th>
            <th class="small py-2">Error</th>
        </tr>
    </thead>
    <tbody style="font-size:12.5px;">
    <?php
    $type_colors = [
        'approved'=>'#28a745','rejected'=>'#dc3545','consumed'=>'#0dcaf0',
        'pending' =>'#fac823','reminder'=>'#6f42c1','cancelled'=>'#6c757d',
    ];
    $no_rows = true;
    if ($notif_rows) while ($n = $notif_rows->fetch_assoc()):
        $no_rows = false;
        $tc = $type_colors[$n['type']] ?? '#666';
    ?>
    <tr>
        <td class="text-muted"><?= date('d M H:i:s', strtotime($n['sent_at'])) ?></td>
        <td>
            <div><?= htmlspecialchars($n['fullname'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($n['email'] ?? '') ?></div>
        </td>
        <td><code style="font-size:11px;color:#cc0000;"><?= htmlspecialchars($n['booking_code'] ?? '—') ?></code></td>
        <td><span class="badge" style="background:<?= $tc ?>;font-size:11px;"><?= ucfirst($n['type']) ?></span></td>
        <td><span class="badge small <?= $n['status']==='sent'?'nl-sent':'nl-failed' ?>"><?= strtoupper($n['status']) ?></span></td>
        <td class="text-muted" style="font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= htmlspecialchars(substr($n['error_message'] ?? '', 0, 80)) ?: '—' ?>
        </td>
    </tr>
    <?php endwhile; ?>
    <?php if ($no_rows): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">No notifications logged yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>
<?php endif; ?>
</div><!-- /tNotif -->

<!-- ══ ACTIVITY LOGS ══════════════════════════════════════ -->
<div class="tab-pane fade" id="tLogs">
<div class="s-card">
    <h5><i class="bi bi-journal-text me-2 text-danger"></i>System Activity Logs</h5>
    <div style="max-height:420px;overflow-y:auto;">
    <?php
    $lc = 0;
    while ($log = $logs->fetch_assoc()):
        $lc++;
    ?>
    <div class="log-entry">
        <span class="text-muted" style="font-size:11px;"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></span>
        <span class="badge bg-dark ms-1" style="font-size:10px;"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
        <span class="ms-1 fw-semibold small"><?= htmlspecialchars($log['action']) ?></span>
        <?php if ($log['details']): ?>
        <div class="text-muted ps-2" style="font-size:11px;">↳ <?= htmlspecialchars($log['details']) ?></div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
    <?php if ($lc===0): ?><p class="text-muted text-center py-3 small">No activity yet.</p><?php endif; ?>
    </div>
</div>
</div><!-- /tLogs -->

<!-- Save button -->
<div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
    <button type="submit" class="btn btn-anu px-5">
        <i class="bi bi-save me-2"></i>Save All Settings
    </button>
    <span class="text-muted small">Changes apply immediately after saving.</span>
</div>

</div><!-- /tab-content -->
</form>

</div><!-- /p-4 -->
</div><!-- /main-content -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live server clock
(function(){
    var h=<?= (int)date('H') ?>,m=<?= (int)date('i') ?>,s=<?= (int)date('s') ?>;
    function p(n){return String(n).padStart(2,'0');}
    function tick(){s++;if(s>=60){s=0;m++;}if(m>=60){m=0;h++;}if(h>=24)h=0;
        var el=document.getElementById('liveClock');if(el)el.textContent=p(h)+':'+p(m)+':'+p(s);}
    setInterval(tick,1000);
})();
</script>
</body>
</html>
