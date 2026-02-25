<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

$msg = '';
$error = '';

// Handle validation via form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code) {
        $stmt = $conn->prepare("SELECT b.*, u.fullname, m.name as meal_name FROM bookings b JOIN users u ON b.user_id=u.id JOIN menus m ON b.menu_id=m.id WHERE b.code=?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking) {
            $error = "Booking code not found: $code";
        } elseif ($booking['status'] === 'consumed') {
            $error = "This meal has already been consumed by {$booking['fullname']}.";
        } elseif ($booking['status'] === 'rejected') {
            $error = "This booking was rejected and cannot be validated.";
        } else {
            $uid = $_SESSION['user_id'];
            $now = date('Y-m-d H:i:s');
            $stmt2 = $conn->prepare("UPDATE bookings SET status='consumed', validated_by=?, validated_at=? WHERE code=?");
            $stmt2->bind_param("iss", $uid, $now, $code);
            $stmt2->execute();
            logAction($conn, 'Meal Validated', "Validated booking {$code} for {$booking['fullname']} ({$booking['meal_name']})");
            $msg = "✅ Meal validated successfully for <strong>{$booking['fullname']}</strong> ({$booking['meal_name']}).";
        }
    }
}

// Filter bookings
$status_f = $_GET['status'] ?? '';
$where = "WHERE 1=1";
if ($status_f) $where .= " AND b.status='" . $conn->real_escape_string($status_f) . "'";

$bookings = $conn->query("
    SELECT b.*, u.fullname, u.username, m.name as meal_name, m.type as meal_type,
           v.fullname as validator
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN menus m ON b.menu_id = m.id
    LEFT JOIN users v ON b.validated_by = v.id
    $where
    ORDER BY b.created_at DESC
    LIMIT 100
");
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
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="topbar d-flex justify-content-between align-items-center">
            <h1><i class="bi bi-patch-check me-2"></i>Meal Validation</h1>
        </div>

        <div class="p-4 fade-in-up">
            <?php if ($msg):  ?><div class="alert alert-success fade show"><i class="bi bi-check-circle me-1"></i><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <!-- Validation Form -->
            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <div class="chart-card">
                        <h6 class="section-title">Validate Booking Code</h6>
                        <form method="POST" class="d-flex gap-2">
                            <input type="text" name="code" id="codeInput" class="form-control form-control-lg"
                                   placeholder="Enter booking code (e.g. ANU-XXXXXXXX)" style="font-family:monospace;" required>
                            <button type="submit" class="btn btn-anu btn-lg px-4">
                                <i class="bi bi-patch-check me-1"></i> Validate
                            </button>
                        </form>
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary btn-sm" id="startScanBtn">
                                <i class="bi bi-camera me-1"></i> Scan QR Code
                            </button>
                            <button class="btn btn-outline-danger btn-sm ms-1" id="stopScanBtn" style="display:none;">
                                <i class="bi bi-x-circle me-1"></i> Stop Scanner
                            </button>
                        </div>
                        <div id="qr-reader" class="mt-3" style="display:none; max-width:320px;"></div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="chart-card h-100">
                        <h6 class="section-title">Quick Stats</h6>
                        <?php
                        $stats = $conn->query("SELECT status, COUNT(*) as c FROM bookings GROUP BY status")->fetch_all(MYSQLI_ASSOC);
                        $s = array_column($stats, 'c', 'status');
                        ?>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#fff9f0;">
                                <span>Pending</span>
                                <span class="fw-bold badge-pending status-badge"><?= $s['pending'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f0fff4;">
                                <span>Approved</span>
                                <span class="fw-bold badge-approved status-badge"><?= $s['approved'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#e8f4ff;">
                                <span>Consumed</span>
                                <span class="fw-bold badge-consumed status-badge"><?= $s['consumed'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#fff0f0;">
                                <span>Rejected</span>
                                <span class="fw-bold badge-rejected status-badge"><?= $s['rejected'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Table -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0">Booking Records</h6>
                <form method="GET" class="d-flex gap-2">
                    <select name="status" class="form-select form-select-sm" style="width:150px;">
                        <option value="">All Status</option>
                        <option value="pending"  <?= $status_f==='pending'  ?'selected':'' ?>>Pending</option>
                        <option value="approved" <?= $status_f==='approved' ?'selected':'' ?>>Approved</option>
                        <option value="consumed" <?= $status_f==='consumed' ?'selected':'' ?>>Consumed</option>
                        <option value="rejected" <?= $status_f==='rejected' ?'selected':'' ?>>Rejected</option>
                    </select>
                    <button type="submit" class="btn btn-anu btn-sm">Filter</button>
                </form>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Code</th><th>Student</th><th>Meal</th><th>Date</th><th>Status</th><th>Validated By</th><th>Validated At</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($b = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($b['code']) ?></code></td>
                                <td><?= htmlspecialchars($b['fullname']) ?></td>
                                <td>
                                    <?= htmlspecialchars($b['meal_name']) ?>
                                    <span class="menu-badge <?= strtolower($b['meal_type']) ?> ms-1"><?= $b['meal_type'] ?></span>
                                </td>
                                <td><?= date('d M Y', strtotime($b['date'])) ?></td>
                                <td><span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($b['validator'] ?? '—') ?></td>
                                <td class="small text-muted"><?= $b['validated_at'] ? date('d M H:i', strtotime($b['validated_at'])) : '—' ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let scanner;
document.getElementById('startScanBtn').addEventListener('click', function() {
    document.getElementById('qr-reader').style.display = 'block';
    this.style.display = 'none';
    document.getElementById('stopScanBtn').style.display = 'inline-block';

    scanner = new Html5Qrcode("qr-reader");
    scanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 200, height: 200 } },
        (code) => {
            document.getElementById('codeInput').value = code;
            stopScanner();
            document.querySelector('form').submit();
        },
        () => {}
    ).catch(() => alert('Camera access denied or not available.'));
});

document.getElementById('stopScanBtn').addEventListener('click', stopScanner);

function stopScanner() {
    if (scanner) scanner.stop();
    document.getElementById('qr-reader').style.display = 'none';
    document.getElementById('startScanBtn').style.display = 'inline-block';
    document.getElementById('stopScanBtn').style.display = 'none';
}
</script>
</body>
</html>
