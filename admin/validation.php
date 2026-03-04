<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/services/NotificationService.php';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

requireAdmin();

$msg   = '';
$error = '';

// ── QR / code validation POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($code)) {
        $error = 'Please enter a booking code or scan a QR code.';
    } else {
        // Fetch booking with prepared statement
        $stmt = $conn->prepare(
            "SELECT b.id, b.code, b.date, b.status,
                    u.id user_id, u.fullname, u.email,
                    m.name meal_name, m.type meal_type
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             JOIN menus m ON b.menu_id = m.id
             WHERE b.code = ? LIMIT 1"
        );
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            $error = "Booking code <strong>{$code}</strong> not found.";
        } elseif ($booking['status'] === 'consumed') {
            $error = "This meal was already collected by <strong>{$booking['fullname']}</strong>.";
        } elseif ($booking['status'] === 'rejected') {
            $error = "Booking <strong>{$code}</strong> was rejected and cannot be validated.";
        } elseif ($booking['status'] === 'pending') {
            $error = "Booking <strong>{$code}</strong> is still <strong>pending approval</strong>. Approve it first in Booking Management.";
        } else {
            // Transaction: mark consumed + log
            $conn->begin_transaction();
            try {
                $uid = (int)$_SESSION['user_id'];
                $now = date('Y-m-d H:i:s');

                $upd = $conn->prepare(
                    "UPDATE bookings
                     SET status='consumed', validated_by=?, validated_at=?
                     WHERE code=? AND status='approved'"
                );
                $upd->bind_param('iss', $uid, $now, $code);
                $upd->execute();

                if ($upd->affected_rows > 0) {
                    logAction($conn, 'Meal Validated',
                              "Code {$code} for {$booking['fullname']} ({$booking['meal_name']})");
                    $conn->commit();

                    // Notify student after commit
                    (new NotificationService($conn))->sendBookingAlert((int)$booking['id'], 'consumed');

                    $msg = "✅ Meal validated for <strong>{$booking['fullname']}</strong> — {$booking['meal_name']}.";
                } else {
                    $conn->rollback();
                    $error = "Could not validate booking {$code}. It may have already been processed.";
                }
            } catch (\Throwable $e) {
                $conn->rollback();
                $error = "Validation error: " . htmlspecialchars($e->getMessage());
                error_log('[ANU/Validation] ' . $e->getMessage());
            }
        }
    }
}

// ── Filters for listing ───────────────────────────────────────────
$valid_statuses = ['pending','approved','rejected','consumed'];
$status_f = in_array($_GET['status'] ?? '', $valid_statuses) ? $_GET['status'] : '';

// Safe query
if ($status_f !== '') {
    $stmt = $conn->prepare(
        "SELECT b.id, b.code, b.date, b.status, b.validated_at,
                u.fullname, u.username,
                m.name meal_name, m.type meal_type,
                v.fullname validator
         FROM bookings b
         JOIN users u ON b.user_id=u.id
         JOIN menus m ON b.menu_id=m.id
         LEFT JOIN users v ON b.validated_by=v.id
         WHERE b.status=?
         ORDER BY b.created_at DESC LIMIT 100"
    );
    $stmt->bind_param('s', $status_f);
    $stmt->execute();
    $bookings = $stmt->get_result();
} else {
    $bookings = $conn->query(
        "SELECT b.id, b.code, b.date, b.status, b.validated_at,
                u.fullname, u.username,
                m.name meal_name, m.type meal_type,
                v.fullname validator
         FROM bookings b
         JOIN users u ON b.user_id=u.id
         JOIN menus m ON b.menu_id=m.id
         LEFT JOIN users v ON b.validated_by=v.id
         ORDER BY b.created_at DESC LIMIT 100"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meal Validation | ANU Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<style>
.val-box { background:#fff; border-radius:16px; padding:28px; box-shadow:0 4px 20px rgba(0,0,0,.1); border-top:4px solid #ff0000; }
.val-input { font-size:1.3rem; font-family:monospace; letter-spacing:3px; text-transform:uppercase; text-align:center; font-weight:700; }
.val-table thead th { background:#1a1a1a; color:#fff; font-size:11px; text-transform:uppercase; letter-spacing:.5px; padding:11px 14px; border:none; }
.val-table tbody td { padding:10px 14px; font-size:13px; vertical-align:middle; border-color:#f5f5f5; }
</style>
</head>
<body>
<div class="d-flex">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content flex-grow-1">

<div class="topbar"><h1><i class="bi bi-qr-code-scan me-2"></i>Meal Validation</h1></div>

<div class="p-4 fade-in-up">
<div class="row g-4">

<!-- Validation panel -->
<div class="col-lg-4">
    <div class="val-box">
        <h5 class="fw-bold mb-1"><i class="bi bi-qr-code-scan me-2 text-danger"></i>Scan / Enter Code</h5>
        <p class="text-muted small mb-4">Scan QR or manually enter booking code to mark meal as consumed.</p>

        <?php if ($msg): ?>
        <div class="alert alert-success py-2 small"><i class="bi bi-check-circle-fill me-1"></i><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Booking Code</label>
                <input type="text" name="code" id="codeInput"
                       class="form-control val-input"
                       placeholder="e.g. BK-2025-001"
                       value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                       autofocus required>
                <div class="form-text">Auto-uppercased. Works with barcode scanners.</div>
            </div>
            <button type="submit" class="btn btn-anu w-100 py-2">
                <i class="bi bi-check-circle me-2"></i>Validate Meal
            </button>
        </form>

        <hr class="my-4">
        <div class="small text-muted">
            <p class="fw-semibold mb-1 text-dark"><i class="bi bi-bell me-1"></i>On successful validation:</p>
            <ul class="ps-3 mb-0">
                <li>Status set to <strong>Consumed</strong></li>
                <li>Validator + timestamp recorded</li>
                <li>Student emailed confirmation</li>
                <li>Action logged in System Logs</li>
            </ul>
        </div>
    </div>
</div>

<!-- Booking list -->
<div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
            <h6 class="mb-0 fw-bold">Recent Bookings</h6>
            <form method="GET" class="d-flex gap-2 align-items-center">
                <select name="status" class="form-select form-select-sm" style="width:auto;"
                        onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <?php foreach ($valid_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $status_f===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="table-responsive">
        <table class="table mb-0 val-table">
            <thead>
                <tr><th>Code</th><th>Student</th><th>Meal</th><th>Date</th><th>Status</th><th>Validated</th></tr>
            </thead>
            <tbody>
            <?php while ($b = $bookings->fetch_assoc()): ?>
            <tr>
                <td><code style="font-size:11px;color:#cc0000;"><?= htmlspecialchars($b['code']) ?></code></td>
                <td>
                    <div class="fw-semibold"><?= htmlspecialchars($b['fullname']) ?></div>
                    <div class="text-muted" style="font-size:11px;">@<?= htmlspecialchars($b['username']) ?></div>
                </td>
                <td>
                    <div><?= htmlspecialchars($b['meal_name']) ?></div>
                    <span class="menu-badge <?= strtolower($b['meal_type']) ?>"><?= $b['meal_type'] ?></span>
                </td>
                <td style="font-size:12px;"><?= date('d M Y', strtotime($b['date'])) ?></td>
                <td><span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                <td class="text-muted" style="font-size:11px;">
                    <?= $b['validated_at'] ? date('d M H:i', strtotime($b['validated_at'])) : '—' ?>
                    <?php if ($b['validator']): ?>
                    <div style="font-size:10px;"><?= htmlspecialchars($b['validator']) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

</div><!-- /row -->
</div><!-- /p-4 -->
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-uppercase the code input as user types
document.getElementById('codeInput').addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});
</script>
</body>
</html>
