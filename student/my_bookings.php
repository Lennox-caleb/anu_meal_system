<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();
checkMaintenance($conn);
if ($_SESSION['role'] !== 'student') { header('Location: ../admin/dashboard.php'); exit; }

$uid = $_SESSION['user_id'];
$msg = '';

// Cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $id = (int)$_POST['cancel_id'];
    $conn->query("DELETE FROM bookings WHERE id=$id AND user_id=$uid AND status='pending'");
    $msg = 'Booking cancelled.';
}

// Filters
$status_f = $_GET['status'] ?? '';
$where = "WHERE b.user_id=$uid";
if ($status_f) $where .= " AND b.status='" . $conn->real_escape_string($status_f) . "'";

$bookings = $conn->query("
    SELECT b.*, m.name as meal_name, m.type as meal_type, m.description, m.price
    FROM bookings b JOIN menus m ON b.menu_id=m.id
    $where ORDER BY b.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | ANU Student</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>
<div class="d-flex">
    <?php include '../includes/student_sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <?php $page_title = '<i class="bi bi-calendar-check me-2"></i>My Bookings'; include '../includes/topbar.php'; ?>

        <div class="p-4 fade-in-up">
            <?php if ($msg): ?><div class="alert alert-success fade show"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

            <form method="GET" class="d-flex gap-2 mb-3 align-items-center">
                <select name="status" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending"  <?= $status_f==='pending'  ?'selected':'' ?>>Pending</option>
                    <option value="approved" <?= $status_f==='approved' ?'selected':'' ?>>Approved</option>
                    <option value="consumed" <?= $status_f==='consumed' ?'selected':'' ?>>Consumed</option>
                    <option value="rejected" <?= $status_f==='rejected' ?'selected':'' ?>>Rejected</option>
                </select>
                <?php if ($status_f): ?><a href="my_bookings.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </form>

            <?php if ($bookings->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($b = $bookings->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="chart-card h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($b['meal_name']) ?></div>
                                <span class="menu-badge <?= strtolower($b['meal_type']) ?>"><?= $b['meal_type'] ?></span>
                            </div>
                            <span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                        </div>

                        <div class="small text-muted mb-2">
                            <i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y', strtotime($b['date'])) ?>
                        </div>

                        <?php if ($b['description']): ?>
                        <p class="small text-muted mb-2"><?= htmlspecialchars($b['description']) ?></p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold" style="color:var(--anu-red);">KES <?= number_format($b['price'], 2) ?></span>
                            <code class="small bg-light px-2 py-1 rounded"><?= htmlspecialchars($b['code']) ?></code>
                        </div>

                        <!-- QR Code -->
                        <?php if (in_array($b['status'], ['pending','approved'])): ?>
                        <div class="text-center mb-3">
                            <div id="qr_<?= $b['id'] ?>" class="d-inline-block p-2 bg-white rounded border"></div>
                            <div class="small text-muted mt-1">Present this QR at the cafeteria</div>
                        </div>
                        <?php endif; ?>

                        <?php if ($b['status'] === 'pending'): ?>
                        <form method="POST" onsubmit="return confirm('Cancel this booking?');">
                            <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                <i class="bi bi-x-circle me-1"></i>Cancel Booking
                            </button>
                        </form>
                        <?php elseif ($b['status'] === 'consumed'): ?>
                        <div class="alert alert-success py-1 text-center small mb-0">
                            <i class="bi bi-check-circle me-1"></i>Meal consumed on <?= $b['validated_at'] ? date('d M H:i', strtotime($b['validated_at'])) : 'N/A' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x display-1 text-muted opacity-50"></i>
                <h5 class="mt-3 text-muted">No bookings found</h5>
                <a href="menu.php" class="btn btn-anu mt-2"><i class="bi bi-plus me-1"></i>Book a Meal</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
// Generate QR codes for active bookings
<?php
$bookings->data_seek(0);
while ($b = $bookings->fetch_assoc()) {
    if (in_array($b['status'], ['pending', 'approved'])):
?>
new QRCode(document.getElementById("qr_<?= $b['id'] ?>"), {
    text: "<?= htmlspecialchars($b['code']) ?>",
    width: 120, height: 120,
    colorDark: "#ff0000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
});
<?php endif; } ?>
</script>
</body>
</html>
