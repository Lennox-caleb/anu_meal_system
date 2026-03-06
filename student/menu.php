<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();
if ($_SESSION['role'] !== 'student') { header('Location: ../admin/dashboard.php'); exit; }

// ════════════════════════════════════════════════
// SERVER-SIDE ENFORCEMENT — checked on every request
// 1. Maintenance mode  → entire page blocked for students
// 2. Booking window    → POST blocked outside datetime range
// ════════════════════════════════════════════════
checkMaintenance($conn);
$bw          = getBookingWindowStatus($conn);
$maint_on    = (getSetting($conn, 'maintenance_mode', '0') === '1');

$uid         = $_SESSION['user_id'];
$error       = '';
$new_booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Guard 1: Maintenance (double-check server-side)
    if ($maint_on) {
        $error = 'Meal booking is temporarily unavailable due to maintenance. Please try again later.';

    // Guard 2: Booking window (date + time, from DB)
    } elseif (!$bw['open']) {
        $msg = 'Booking is currently closed. Please check the booking window schedule.';
        if (!empty($bw['reason'])) {
            $msg .= ' (' . $bw['reason'] . ')';
        }
        $error = $msg;

    // Guard 3: All checks passed — process the booking
    } else {
        $menu_id = (int)($_POST['menu_id'] ?? 0);
        $date    = trim($_POST['date'] ?? '');
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'Invalid booking date.';
        } else {
            $chk = $conn->prepare("SELECT id FROM bookings WHERE user_id=? AND menu_id=? AND date=?");
            $chk->bind_param("iis", $uid, $menu_id, $date);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'You have already booked this meal for this date.';
            } else {
                $m = $conn->query("SELECT * FROM menus WHERE id=$menu_id AND available=1")->fetch_assoc();
                if (!$m) {
                    $error = 'This menu item is not available.';
                } else {
                    $code = generateBookingCode($conn);
                    $ins  = $conn->prepare("INSERT INTO bookings (code,user_id,menu_id,date,status) VALUES (?,?,?,?,'pending')");
                    $ins->bind_param("siis", $code, $uid, $menu_id, $date);
                    $ins->execute();
                    logAction($conn, 'Meal Booked', "Booked {$m['name']} on {$date}. Code: {$code}");
                    // Notify student (pending) and admins
                    $last_id = $conn->insert_id;
                    notifyBookingStatus($conn, $last_id);
                    notifyAdminsNewBooking($conn, $last_id);
                    $new_booking = [
                        'code'      => $code,
                        'meal_name' => $m['name'],
                        'meal_type' => $m['type'],
                        'date'      => $date,
                        'price'     => $m['price'],
                    ];
                }
            }
        }
    }
}

$view_date    = $conn->real_escape_string($_GET['date'] ?? date('Y-m-d'));
$type_f       = $conn->real_escape_string($_GET['type'] ?? '');
$where        = "WHERE date='$view_date' AND available=1";
if ($type_f)  $where .= " AND type='$type_f'";
$menus        = $conn->query("SELECT * FROM menus $where ORDER BY FIELD(type,'Breakfast','Lunch','Dinner')");
$date_options = $conn->query("SELECT DISTINCT date FROM menus WHERE date>=CURDATE() AND date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND available=1 ORDER BY date ASC");
$booked_res   = $conn->query("SELECT menu_id FROM bookings WHERE user_id=$uid AND date='$view_date'");
$booked_ids   = [];
while ($b = $booked_res->fetch_assoc()) $booked_ids[] = $b['menu_id'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Menu | ANU Student</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
.menu-card{border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.08);transition:all .25s;}
.menu-card:hover{transform:translateY(-5px);box-shadow:0 8px 24px rgba(0,0,0,.14);}
.menu-card-top{background:linear-gradient(135deg,#ff0000,#fac823);padding:14px 16px;color:#fff;}
.code-display{font-family:'Courier New',monospace;font-size:1.6rem;font-weight:800;letter-spacing:5px;
  background:linear-gradient(135deg,#fff8e1,#fff3cd);border:3px dashed #fac823;
  border-radius:12px;padding:14px 20px;color:#111;text-align:center;word-break:break-all;}
.qr-box{background:#fff;border-radius:16px;padding:18px;display:inline-block;
  box-shadow:0 4px 20px rgba(0,0,0,.12);border:2px solid #f0f0f0;}
.info-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f5f5f5;font-size:14px;}
.info-row:last-child{border-bottom:none;}
</style>
</head>
<body>
<div class="d-flex">
<?php include '../includes/student_sidebar.php'; ?>
<div class="main-content flex-grow-1">

<?php $page_title = '<i class="bi bi-journal-text me-2"></i>Daily Menu'; include '../includes/topbar.php'; ?>

<div class="p-4 fade-in-up">

<!-- ── Booking Status Banner (server-rendered, always accurate) ── -->
<?php
$maint_on = (getSetting($conn, 'maintenance_mode', '0') === '1');
?>
<?php if ($maint_on): ?>
<div class="alert alert-danger d-flex align-items-center gap-3 mb-3">
  <i class="bi bi-tools fs-4"></i>
  <div>
    <strong>System Maintenance</strong><br>
    <span class="small">Meal booking is temporarily unavailable due to maintenance. Please check back later.</span>
  </div>
</div>
<?php elseif (!$bw['open']): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-3">
  <i class="bi bi-lock-fill fs-4"></i>
  <div>
    <strong>Booking is Currently Closed</strong><br>
    <span class="small">
      <?= !empty($bw['reason']) ? htmlspecialchars($bw['reason']) : 'Outside the booking window.' ?>
      Please check the booking window schedule.
    </span>
    <div class="mt-1 small text-muted">
      Window: <strong><?= htmlspecialchars($bw['start_fmt'] ?? '—') ?></strong>
      to <strong><?= htmlspecialchars($bw['end_fmt'] ?? '—') ?></strong>
      &nbsp;|&nbsp; Now: <strong><?= htmlspecialchars($bw['now_fmt'] ?? '—') ?></strong>
    </div>
  </div>
</div>
<?php else: ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-3 py-2">
  <i class="bi bi-check-circle-fill"></i>
  <span class="small">
    Booking is <strong>OPEN</strong> until <strong><?= htmlspecialchars($bw['end_fmt'] ?? '—') ?></strong>
    &nbsp;|&nbsp; Now: <strong><?= htmlspecialchars($bw['now_fmt'] ?? '—') ?></strong>
  </span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
  <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="row g-2 align-items-end mb-4">
  <div class="col-md-4">
    <label class="form-label fw-semibold small">Select Date</label>
    <select name="date" class="form-select" onchange="this.form.submit()">
      <?php
      $today = date('Y-m-d');
      if ($date_options && $date_options->num_rows > 0):
        $date_options->data_seek(0);
        while ($d = $date_options->fetch_assoc()):
          $lbl = ($d['date']==$today)
            ? date('D, d M Y', strtotime($d['date'])).' (Today)'
            : date('D, d M Y', strtotime($d['date']));
      ?>
      <option value="<?= $d['date'] ?>" <?= ($view_date==$d['date'])?'selected':'' ?>><?= $lbl ?></option>
      <?php endwhile; else: ?>
      <option value="<?= $today ?>"><?= date('D, d M Y').' (Today)' ?></option>
      <?php endif; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold small">Meal Type</label>
    <select name="type" class="form-select" onchange="this.form.submit()">
      <option value="">All Types</option>
      <option value="Breakfast" <?= ($_GET['type']??'')==='Breakfast'?'selected':'' ?>>Breakfast</option>
      <option value="Lunch"     <?= ($_GET['type']??'')==='Lunch'    ?'selected':'' ?>>Lunch</option>
      <option value="Dinner"    <?= ($_GET['type']??'')==='Dinner'   ?'selected':'' ?>>Dinner</option>
    </select>
  </div>
</form>

<!-- Menu Grid -->
<?php if ($menus && $menus->num_rows > 0): ?>
<div class="row g-3">
  <?php while ($m = $menus->fetch_assoc()):
    $already = in_array($m['id'], $booked_ids); ?>
  <div class="col-sm-6 col-lg-4">
    <div class="menu-card h-100 <?= $already?'opacity-75':'' ?>">
      <div class="menu-card-top">
        <div class="d-flex justify-content-between align-items-start">
          <span class="fw-bold"><?= htmlspecialchars($m['name']) ?></span>
          <span class="badge ms-2 bg-white text-dark"><?= $m['type'] ?></span>
        </div>
      </div>
      <div class="p-3 d-flex flex-column" style="min-height:160px;">
        <p class="small text-muted flex-grow-1"><?= htmlspecialchars($m['description'] ?: 'Freshly prepared meal.') ?></p>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <span class="fw-bold" style="color:var(--anu-red);">KES <?= number_format($m['price'],2) ?></span>
          <?php if ($already): ?>
            <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Booked</span>
          <?php else: ?>
            <form method="POST">
              <input type="hidden" name="menu_id" value="<?= $m['id'] ?>">
              <input type="hidden" name="date" value="<?= $view_date ?>">
              <?php
              $can_book = $bw['open'] && !$maint_on;
              ?>
              <?php if ($can_book): ?>
              <button type="submit" class="btn btn-anu btn-sm"
                onclick="return confirm('Book <?= htmlspecialchars(addslashes($m['name'])) ?> for <?= date('d M Y', strtotime($view_date)) ?>?')">
                <i class="bi bi-calendar-plus me-1"></i>Book
              </button>
              <?php elseif ($maint_on): ?>
              <button type="button" class="btn btn-secondary btn-sm" disabled title="System under maintenance">
                <i class="bi bi-tools me-1"></i>Maintenance
              </button>
              <?php else: ?>
              <button type="button" class="btn btn-secondary btn-sm" disabled title="Booking window closed">
                <i class="bi bi-lock me-1"></i>Closed
              </button>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endwhile; ?>
</div>
<?php else: ?>
<div class="text-center py-5">
  <i class="bi bi-journal-x display-1 text-muted opacity-50"></i>
  <h5 class="mt-3 text-muted">No menu available for <?= date('D, d M Y', strtotime($view_date)) ?></h5>
  <p class="small text-muted">Check back later or select a different date.</p>
</div>
<?php endif; ?>

</div><!-- /p-4 -->
</div><!-- /main-content -->
</div><!-- /d-flex -->

<!-- ===== BOOKING SUCCESS MODAL ===== -->
<?php if ($new_booking): ?>
<div class="modal fade" id="bookingModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header text-white border-0 py-3" style="background:linear-gradient(135deg,#ff0000,#fac823);">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-check-circle-fill me-2"></i>Booking Confirmed!
        </h5>
      </div>
      <div class="modal-body text-center p-4">
        <p class="text-muted small mb-3">Show this QR code or booking code at the cafeteria counter</p>

        <!-- QR Code -->
        <div class="qr-box mb-3">
          <div id="modalQR"></div>
        </div>

        <!-- Booking Code -->
        <div class="code-display mb-3"><?= htmlspecialchars($new_booking['code']) ?></div>

        <!-- Meal Info -->
        <div class="text-start mt-3">
          <div class="info-row">
            <span class="text-muted">Meal</span>
            <span class="fw-semibold"><?= htmlspecialchars($new_booking['meal_name']) ?></span>
          </div>
          <div class="info-row">
            <span class="text-muted">Type</span>
            <span class="fw-semibold"><?= htmlspecialchars($new_booking['meal_type']) ?></span>
          </div>
          <div class="info-row">
            <span class="text-muted">Date</span>
            <span class="fw-semibold"><?= date('D, d M Y', strtotime($new_booking['date'])) ?></span>
          </div>
          <div class="info-row">
            <span class="text-muted">Price</span>
            <span class="fw-bold" style="color:#ff0000;">KES <?= number_format($new_booking['price'],2) ?></span>
          </div>
          <div class="info-row">
            <span class="text-muted">Status</span>
            <span class="badge bg-warning text-dark">Pending Approval</span>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex gap-2">
        <a href="my_bookings.php" class="btn btn-anu flex-grow-1">
          <i class="bi bi-calendar-check me-1"></i>View All Bookings
        </a>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x me-1"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  new QRCode(document.getElementById('modalQR'), {
    text: "<?= htmlspecialchars($new_booking['code']) ?>",
    width: 180,
    height: 180,
    colorDark: "#cc0000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
  });
  var modal = new bootstrap.Modal(document.getElementById('bookingModal'));
  modal.show();
});
</script>
<?php endif; ?>

<?php include '../includes/scripts.php'; ?>
</body>
</html>
