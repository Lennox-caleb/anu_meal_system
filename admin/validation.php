<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
require_once '../includes/services/NotificationService.php';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

requireAdmin();

$msg    = '';
$error  = '';
$booked = null;

// ── POST: validate booking code ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($code)) {
        $error = 'Please enter or scan a booking code.';
    } else {
        $stmt = $conn->prepare(
            "SELECT b.id, b.code, b.date, b.status,
                    u.id user_id, u.fullname, u.email,
                    m.name meal_name, m.type meal_type, m.price
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             JOIN menus m ON b.menu_id = m.id
             WHERE b.code = ? LIMIT 1"
        );
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            $error = "Code <strong>{$code}</strong> not found in the system.";
        } elseif ($booking['status'] === 'consumed') {
            $error = "Meal already collected by <strong>{$booking['fullname']}</strong>. Cannot validate again.";
        } elseif ($booking['status'] === 'rejected') {
            $error = "Booking <strong>{$code}</strong> was rejected and cannot be validated.";
        } elseif ($booking['status'] === 'pending') {
            $error = "Booking <strong>{$code}</strong> is still pending approval. Approve it in Booking Management first.";
        } else {
            $conn->begin_transaction();
            try {
                $uid = (int)$_SESSION['user_id'];
                $now = date('Y-m-d H:i:s');
                $upd = $conn->prepare(
                    "UPDATE bookings SET status='consumed', validated_by=?, validated_at=?
                     WHERE code=? AND status='approved'"
                );
                $upd->bind_param('iss', $uid, $now, $code);
                $upd->execute();

                if ($upd->affected_rows > 0) {
                    logAction($conn, 'Meal Validated',
                              "Code {$code} — {$booking['fullname']} ({$booking['meal_name']})");
                    $conn->commit();
                    notifyBookingStatus($conn, (int)$booking['id']);
                    (new NotificationService($conn))->sendBookingAlert((int)$booking['id'], 'consumed');
                    $booked = $booking;
                    $msg    = "Meal validated successfully for <strong>{$booking['fullname']}</strong>.";
                } else {
                    $conn->rollback();
                    $error = "Validation failed — booking may have already been processed.";
                }
            } catch (\Throwable $e) {
                $conn->rollback();
                $error = "Error: " . htmlspecialchars($e->getMessage());
                error_log('[ANU/Validate] ' . $e->getMessage());
            }
        }
    }
}

// ── Booking list ──────────────────────────────────────────────────
$valid_statuses = ['pending', 'approved', 'rejected', 'consumed'];
$status_f = in_array($_GET['status'] ?? '', $valid_statuses) ? $_GET['status'] : '';

if ($status_f !== '') {
    $stmt = $conn->prepare(
        "SELECT b.id, b.code, b.date, b.status, b.validated_at,
                u.fullname, u.username,
                m.name meal_name, m.type meal_type,
                v.fullname validator
         FROM bookings b
         JOIN users u ON b.user_id = u.id
         JOIN menus m ON b.menu_id = m.id
         LEFT JOIN users v ON b.validated_by = v.id
         WHERE b.status = ? ORDER BY b.created_at DESC LIMIT 80"
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
         JOIN users u ON b.user_id = u.id
         JOIN menus m ON b.menu_id = m.id
         LEFT JOIN users v ON b.validated_by = v.id
         ORDER BY b.created_at DESC LIMIT 80"
    );
}

$page_title = '<i class="bi bi-qr-code-scan me-2"></i>Meal Validation';
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
        .val-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,.09);
            border-top: 4px solid var(--red);
        }
        .val-input {
            font-size: 1.15rem;
            font-family: monospace;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
            font-weight: 700;
        }
        .val-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border-radius: 12px;
            padding: 16px;
            border-left: 4px solid #059669;
        }
        .val-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-radius: 12px;
            padding: 12px 16px;
            border-left: 4px solid #dc3545;
        }
        .val-table thead th {
            background: #1a1a1a;
            color: #fff;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 11px 13px;
            border: none;
            white-space: nowrap;
        }
        .val-table tbody td {
            padding: 10px 13px;
            font-size: 13px;
            vertical-align: middle;
            border-color: #f5f5f5;
        }
        #qr-result-flash {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a1a;
            color: #fff;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 13px;
            z-index: 9999;
            display: none;
            white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0,0,0,.3);
        }
        /* QR video wrapper */
        #qr-video-wrap {
            position: relative;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            border: 3px solid var(--red);
            background: #000;
        }
        #qr-video { width: 100%; display: block; }
        #qr-crosshair {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none;
        }
        #qr-crosshair::before {
            content: '';
            width: 55%; height: 55%;
            border: 3px solid #fac823;
            border-radius: 8px;
            box-shadow: 0 0 0 1000px rgba(0,0,0,.35);
            animation: scanPulse 2s ease-in-out infinite;
        }
        @keyframes scanPulse {
            0%,100% { box-shadow: 0 0 0 1000px rgba(0,0,0,.35); }
            50%      { box-shadow: 0 0 0 1000px rgba(0,0,0,.35), 0 0 8px 4px rgba(250,200,35,.4); }
        }
        #qr-scan-line {
            position: absolute;
            width: 55%; height: 2px;
            background: linear-gradient(90deg, transparent, #fac823, transparent);
            animation: scanLine 2.5s linear infinite;
            top: 22.5%; left: 22.5%;
        }
        @keyframes scanLine {
            0%   { top: 22.5%; }
            100% { top: 77.5%; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <?php include '../includes/topbar.php'; ?>

        <div id="qr-result-flash"></div>

        <div class="p-3 p-md-4 fade-in-up">
            <div class="row g-3 g-md-4">

                <!-- ── Left: Scan Panel ─────────────────────────── -->
                <div class="col-12 col-lg-4">
                    <div class="val-card">
                        <h6 class="fw-bold mb-1" style="color:var(--red);">
                            <i class="bi bi-qr-code-scan me-2"></i>Scan / Enter Code
                        </h6>
                        <p class="text-muted small mb-3">
                            Use camera, barcode scanner, or type the booking code manually.
                        </p>

                        <!-- Success result -->
                        <?php if ($msg && $booked): ?>
                        <div class="val-success mb-3">
                            <div class="fw-bold mb-2" style="color:#059669;font-size:15px;">
                                <i class="bi bi-check-circle-fill me-1"></i> Validated!
                            </div>
                            <table class="w-100 small">
                                <tr>
                                    <td class="text-muted pe-3" style="width:40%;">Student</td>
                                    <td class="fw-bold"><?= htmlspecialchars($booked['fullname']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted pe-3">Meal</td>
                                    <td><?= htmlspecialchars($booked['meal_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted pe-3">Type</td>
                                    <td><?= htmlspecialchars($booked['meal_type']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted pe-3">Date</td>
                                    <td><?= date('d M Y', strtotime($booked['date'])) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted pe-3">Price</td>
                                    <td class="fw-bold" style="color:var(--red);">
                                        KES <?= number_format($booked['price'], 2) ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Error result -->
                        <?php elseif ($error): ?>
                        <div class="val-error mb-3 small">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                        </div>
                        <?php endif; ?>

                        <!-- Code input form -->
                        <form method="POST" id="valForm" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Booking Code</label>
                                <input type="text" name="code" id="codeInput"
                                       class="form-control val-input"
                                       placeholder="ANU-XXXXXXXX"
                                       value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                                       autocomplete="off" required autofocus>
                                <div class="form-text small mt-1">
                                    <i class="bi bi-usb me-1"></i>Compatible with USB barcode scanners.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-anu w-100 py-2">
                                <i class="bi bi-check-circle me-2"></i>Validate Meal
                            </button>
                        </form>

                        <hr class="my-3">

                        <!-- Camera Scanner -->
                        <button class="btn btn-outline-secondary w-100" id="startCamBtn"
                                onclick="startQRScanner()"
                                style="border-radius:10px; font-weight:600; padding:10px;">
                            <i class="bi bi-camera-fill me-2"></i>Open Camera Scanner
                        </button>

                        <div id="qrScannerUI" style="display:none; margin-top:12px;">
                            <div id="qr-video-wrap">
                                <video id="qr-video" autoplay muted playsinline></video>
                                <div id="qr-crosshair"></div>
                                <div id="qr-scan-line"></div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-outline-danger btn-sm flex-grow-1"
                                        onclick="stopQRScanner()">
                                    <i class="bi bi-x-circle me-1"></i>Stop Camera
                                </button>
                                <button class="btn btn-outline-secondary btn-sm flex-grow-1"
                                        id="switchCamBtn" onclick="switchCamera()" style="display:none;">
                                    <i class="bi bi-arrow-repeat me-1"></i>Switch
                                </button>
                            </div>
                            <p class="text-muted text-center mt-2 mb-0" style="font-size:11px;">
                                Point camera at the QR code on the student's booking slip
                            </p>
                        </div>

                    </div>
                </div>

                <!-- ── Right: Booking List ──────────────────────── -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-bottom flex-wrap gap-2">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-list-ul me-2 text-danger"></i>Recent Bookings
                            </h6>
                            <form method="GET" class="d-flex align-items-center gap-2">
                                <select name="status" class="form-select form-select-sm"
                                        style="width:auto;" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <?php foreach ($valid_statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $status_f === $s ? 'selected' : '' ?>>
                                        <?= ucfirst($s) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table mb-0 val-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Student</th>
                                        <th>Meal</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Validated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($b = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <code style="font-size:11px;color:#cc0000;">
                                            <?= htmlspecialchars($b['code']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <div class="fw-semibold" style="font-size:13px;">
                                            <?= htmlspecialchars($b['fullname']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;">
                                            @<?= htmlspecialchars($b['username']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:13px;">
                                            <?= htmlspecialchars($b['meal_name']) ?>
                                        </div>
                                        <span class="menu-badge <?= strtolower($b['meal_type']) ?>">
                                            <?= $b['meal_type'] ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;white-space:nowrap;">
                                        <?= date('d M Y', strtotime($b['date'])) ?>
                                    </td>
                                    <td>
                                        <?php if ($b['status'] === 'approved'): ?>
                                        <button class="btn btn-success btn-sm py-0 px-2"
                                                onclick="fillCode('<?= htmlspecialchars($b['code']) ?>')"
                                                title="Click to validate this booking">
                                            <i class="bi bi-check-lg me-1"></i>Validate
                                        </button>
                                        <?php else: ?>
                                        <span class="status-badge badge-<?= $b['status'] ?>">
                                            <?= ucfirst($b['status']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted" style="font-size:11px;">
                                        <?= $b['validated_at']
                                            ? date('d M, H:i', strtotime($b['validated_at']))
                                            : '—' ?>
                                        <?php if ($b['validator']): ?>
                                        <div style="font-size:10px;">
                                            <?= htmlspecialchars($b['validator']) ?>
                                        </div>
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
    </div><!-- /main-content -->
</div>

<?php include '../includes/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
var qrStream = null, qrRAF = null, cameras = [], camIdx = 0;

// Auto-uppercase as user types
document.getElementById('codeInput').addEventListener('input', function () {
    var p = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(p, p);
});

// Click a row → fill code into input and scroll up
function fillCode(code) {
    document.getElementById('codeInput').value = code;
    document.getElementById('codeInput').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function startQRScanner() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Camera not supported on this browser.');
        return;
    }
    document.getElementById('startCamBtn').style.display = 'none';
    document.getElementById('qrScannerUI').style.display = 'block';
    try {
        var devices = await navigator.mediaDevices.enumerateDevices();
        cameras = devices.filter(function (d) { return d.kind === 'videoinput'; });
        if (cameras.length > 1) document.getElementById('switchCamBtn').style.display = '';
    } catch (e) {}
    openCamera();
}

async function openCamera() {
    try {
        qrStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: camIdx === 0 ? 'environment' : 'user', width: { ideal: 640 }, height: { ideal: 480 } }
        });
        var vid = document.getElementById('qr-video');
        vid.srcObject = qrStream;
        vid.play();
        vid.addEventListener('loadedmetadata', scanFrame, { once: true });
    } catch (err) {
        stopQRScanner();
        alert('Camera access denied: ' + err.message);
    }
}

function switchCamera() {
    camIdx = (camIdx + 1) % Math.max(cameras.length, 2);
    stopStream(); openCamera();
}

function stopStream() {
    cancelAnimationFrame(qrRAF);
    if (qrStream) qrStream.getTracks().forEach(function (t) { t.stop(); });
    qrStream = null;
}

function stopQRScanner() {
    stopStream();
    document.getElementById('qrScannerUI').style.display = 'none';
    document.getElementById('startCamBtn').style.display = '';
}

function scanFrame() {
    var vid = document.getElementById('qr-video');
    var canvas = document.createElement('canvas');
    canvas.width = vid.videoWidth || 640;
    canvas.height = vid.videoHeight || 480;
    var ctx = canvas.getContext('2d');

    (function tick() {
        if (!qrStream) return;
        if (vid.readyState === vid.HAVE_ENOUGH_DATA) {
            ctx.drawImage(vid, 0, 0, canvas.width, canvas.height);
            var img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data) {
                var val = code.data.trim().toUpperCase();
                stopQRScanner();
                document.getElementById('codeInput').value = val;
                var flash = document.getElementById('qr-result-flash');
                flash.textContent = '✅ Scanned: ' + val;
                flash.style.display = 'block';
                setTimeout(function () { flash.style.display = 'none'; }, 2500);
                setTimeout(function () { document.getElementById('valForm').submit(); }, 700);
                return;
            }
        }
        qrRAF = requestAnimationFrame(tick);
    })();
}
</script>
</body>
</html>
