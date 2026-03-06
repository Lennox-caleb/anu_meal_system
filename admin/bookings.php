<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
require_once '../includes/services/NotificationService.php';

// Load PHPMailer if available
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

requireAdmin();

$flash      = '';
$flash_type = 'success';

// ── POST: status actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['booking_id'] ?? 0);

    if ($id > 0) {
        $conn->begin_transaction();
        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE bookings SET status='approved' WHERE id=? AND status='pending'");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    logAction($conn, 'Booking Approved', "Booking #{$id} approved.");
                    $conn->commit();
                    // In-app notification
                    notifyBookingStatus($conn, $id);
                    // Email notification (fails gracefully)
                    (new NotificationService($conn))->sendBookingAlert($id, 'approved');
                    $flash = "Booking #{$id} approved — student notified.";
                } else {
                    $conn->rollback();
                    $flash = "Booking #{$id} could not be approved (already processed?).";
                    $flash_type = 'warning';
                }
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE bookings SET status='rejected' WHERE id=? AND status='pending'");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    logAction($conn, 'Booking Rejected', "Booking #{$id} rejected.");
                    $conn->commit();
                    notifyBookingStatus($conn, $id);
                    (new NotificationService($conn))->sendBookingAlert($id, 'rejected');
                    $flash = "Booking #{$id} rejected — student notified.";
                } else {
                    $conn->rollback();
                    $flash = "Booking #{$id} could not be rejected (already processed?).";
                    $flash_type = 'warning';
                }
            } else {
                $conn->rollback();
            }
        } catch (\Throwable $e) {
            $conn->rollback();
            $flash = "Error processing action: " . htmlspecialchars($e->getMessage());
            $flash_type = 'danger';
            error_log('[ANU/Bookings] ' . $e->getMessage());
        }
    }

    // Preserve filters on redirect
    $qs = http_build_query([
        'search' => $_POST['_search'] ?? '',
        'status' => $_POST['_status'] ?? '',
        'date'   => $_POST['_date']   ?? '',
    ]);
    session_start_if_needed();
    $_SESSION['_flash']      = $flash;
    $_SESSION['_flash_type'] = $flash_type;
    header("Location: bookings.php?{$qs}");
    exit;
}

// Pick up flash from redirect
if (!isset($_SESSION)) session_start();
$flash      = $_SESSION['_flash']      ?? '';
$flash_type = $_SESSION['_flash_type'] ?? 'success';
unset($_SESSION['_flash'], $_SESSION['_flash_type']);

// ── Filters + pagination ──────────────────────────────────────────
$valid_statuses = ['pending','approved','rejected','consumed'];
$search   = trim($_GET['search'] ?? '');
$status_f = in_array($_GET['status'] ?? '', $valid_statuses) ? $_GET['status'] : '';
$date_f   = $_GET['date'] ?? '';
if ($date_f) {
    $dt = DateTime::createFromFormat('Y-m-d', $date_f);
    if (!$dt) $date_f = '';
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

// Build safe WHERE
$where   = "WHERE 1=1";
$params  = [];
$types   = "";

if ($search !== '') {
    $where  .= " AND (u.fullname LIKE ? OR u.username LIKE ? OR b.code LIKE ?)";
    $like    = "%{$search}%";
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= "sss";
}
if ($status_f !== '') {
    $where  .= " AND b.status = ?";
    $params[] = $status_f;
    $types   .= "s";
}
if ($date_f !== '') {
    $where  .= " AND b.date = ?";
    $params[] = $date_f;
    $types   .= "s";
}

// Count
$count_sql = "SELECT COUNT(*) c FROM bookings b JOIN users u ON b.user_id=u.id JOIN menus m ON b.menu_id=m.id $where";
if ($params) {
    $cs = $conn->prepare($count_sql);
    $cs->bind_param($types, ...$params);
    $cs->execute();
    $total = (int)$cs->get_result()->fetch_assoc()['c'];
} else {
    $total = (int)$conn->query($count_sql)->fetch_assoc()['c'];
}
$total_pages = (int)ceil($total / $per_page);

// Data
$data_sql = "SELECT b.id, b.code, b.date, b.status, b.created_at,
                    u.fullname, u.username,
                    m.name meal_name, m.type meal_type
             FROM bookings b
             JOIN users u ON b.user_id=u.id
             JOIN menus m ON b.menu_id=m.id
             $where
             ORDER BY b.created_at DESC
             LIMIT ? OFFSET ?";
$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . "ii";
$stmt = $conn->prepare($data_sql);
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$bookings = $stmt->get_result();

// Quick stats (always full, no filters)
$qs_row = $conn->query(
    "SELECT SUM(status='pending') pend, SUM(status='approved') appr,
            SUM(status='rejected') rej,  SUM(status='consumed') cons
     FROM bookings"
)->fetch_assoc();

// Check notification table
$notif_enabled = $conn->query("SHOW TABLES LIKE 'notifications_log'")->num_rows > 0;

function session_start_if_needed(): void { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bookings | ANU Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<style>
.mini-stat { background:#fff; border-radius:12px; padding:14px 18px; box-shadow:0 2px 10px rgba(0,0,0,.07); border-left:4px solid #ff0000; }
.mini-stat .val { font-size:1.6rem; font-weight:800; line-height:1; }
.mini-stat .lbl { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#999; margin-top:3px; }
.bk-table thead th { background:#1a1a1a; color:#fff; font-size:11px; text-transform:uppercase; letter-spacing:.5px; padding:11px 14px; border:none; }
.bk-table tbody td { padding:10px 14px; font-size:13px; vertical-align:middle; border-color:#f5f5f5; }
.bk-table tbody tr:hover { background:#fafafa; }
.notif-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
</style>
</head>
<body>
<div class="d-flex">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php $page_title = '<i class="bi bi-calendar-check me-2"></i>Booking Management';
include '../includes/topbar.php'; ?>

<div class="p-4 fade-in-up">

<?php if ($flash): ?>
<div class="alert alert-<?= $flash_type ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flash_type==='success'?'check-circle':'exclamation-triangle' ?>-fill me-2"></i>
    <?= $flash ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Quick stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Pending',  $qs_row['pend'], '#fac823', 'hourglass-split'],
        ['Approved', $qs_row['appr'], '#28a745', 'patch-check-fill'],
        ['Consumed', $qs_row['cons'], '#0dcaf0', 'check2-circle'],
        ['Rejected', $qs_row['rej'],  '#dc3545', 'x-circle-fill'],
    ] as [$lbl, $val, $col, $ico]): ?>
    <div class="col-6 col-md-3">
        <div class="mini-stat" style="border-left-color:<?= $col ?>">
            <div class="val" style="color:<?= $col ?>"><?= number_format((int)$val) ?></div>
            <div class="lbl"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-4 rounded-3">
<div class="card-body py-3">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
        <label class="form-label small fw-semibold mb-1">Search</label>
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Name, username or code…"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <?php foreach ($valid_statuses as $s): ?>
            <option value="<?= $s ?>" <?= $status_f===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Date</label>
        <input type="date" name="date" class="form-control form-control-sm"
               value="<?= htmlspecialchars($date_f) ?>">
    </div>
    <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-anu btn-sm flex-grow-1">
            <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <a href="bookings.php" class="btn btn-outline-secondary btn-sm" title="Reset">
            <i class="bi bi-x"></i>
        </a>
    </div>
</form>
</div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm rounded-3 overflow-hidden">
<div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
    <div class="fw-bold">
        Bookings
        <span class="text-muted small ms-2">
            <?= number_format(($page-1)*$per_page+1) ?>–<?= number_format(min($page*$per_page,$total)) ?>
            of <?= number_format($total) ?>
        </span>
    </div>
    <?php if ($notif_enabled): ?>
    <div class="d-flex align-items-center gap-2 small text-muted">
        <span class="notif-dot bg-success"></span>Approve/Reject sends email to student
    </div>
    <?php endif; ?>
</div>
<div class="table-responsive">
<table class="table mb-0 bk-table">
    <thead>
        <tr>
            <th>#</th><th>Code</th><th>Student</th>
            <th>Meal</th><th>Type</th><th>Date</th>
            <th>Status</th><th>Booked</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php $i = ($page-1)*$per_page+1; while ($b = $bookings->fetch_assoc()): ?>
    <tr>
        <td class="text-muted small"><?= $i++ ?></td>
        <td><code style="font-size:11px;color:#cc0000;"><?= htmlspecialchars($b['code']) ?></code></td>
        <td>
            <div class="fw-semibold"><?= htmlspecialchars($b['fullname']) ?></div>
            <div class="text-muted" style="font-size:11px;">@<?= htmlspecialchars($b['username']) ?></div>
        </td>
        <td style="font-size:13px;"><?= htmlspecialchars($b['meal_name']) ?></td>
        <td><span class="menu-badge <?= strtolower($b['meal_type']) ?>"><?= $b['meal_type'] ?></span></td>
        <td style="font-size:13px;"><?= date('d M Y', strtotime($b['date'])) ?></td>
        <td><span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
        <td class="text-muted" style="font-size:12px;"><?= date('d M H:i', strtotime($b['created_at'])) ?></td>
        <td>
            <?php if ($b['status'] === 'pending'): ?>
            <!-- Approve -->
            <form method="POST" class="d-inline">
                <input type="hidden" name="action"     value="approve">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="_search"    value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="_status"    value="<?= htmlspecialchars($status_f) ?>">
                <input type="hidden" name="_date"      value="<?= htmlspecialchars($date_f) ?>">
                <button class="btn btn-sm btn-success py-0 px-2" title="Approve"
                        onclick="return confirm('Approve this booking? Student will be notified.')">
                    <i class="bi bi-check-lg"></i>
                </button>
            </form>
            <!-- Reject -->
            <form method="POST" class="d-inline ms-1">
                <input type="hidden" name="action"     value="reject">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="_search"    value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="_status"    value="<?= htmlspecialchars($status_f) ?>">
                <input type="hidden" name="_date"      value="<?= htmlspecialchars($date_f) ?>">
                <button class="btn btn-sm btn-danger py-0 px-2" title="Reject"
                        onclick="return confirm('Reject this booking? Student will be notified.')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </form>
            <?php else: ?>
            <span class="text-muted small">—</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
    <?php if ($total === 0): ?>
    <tr><td colspan="9" class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>No bookings match these filters.
    </td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-center py-3 border-top">
<nav><ul class="pagination pagination-sm mb-0">
    <?php
    $pqs = fn($p) => '?' . http_build_query(['search'=>$search,'status'=>$status_f,'date'=>$date_f,'page'=>$p]);
    if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= $pqs($page-1) ?>">‹</a></li><?php endif;
    for ($p=1; $p<=$total_pages; $p++):
        if ($p===1||$p===$total_pages||abs($p-$page)<=2): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= $pqs($p) ?>"><?= $p ?></a></li>
        <?php elseif(abs($p-$page)===3): ?><li class="page-item disabled"><span class="page-link">…</span></li>
    <?php endif; endfor;
    if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="<?= $pqs($page+1) ?>">›</a></li><?php endif; ?>
</ul></nav>
</div>
<?php endif; ?>
</div><!-- /card -->

</div><!-- /p-4 -->
</div><!-- /main-content -->
</div>

<?php include '../includes/scripts.php'; ?>
</body>
</html>
