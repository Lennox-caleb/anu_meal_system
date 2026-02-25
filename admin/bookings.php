<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $id       = (int)($_POST['booking_id'] ?? 0);
    if ($action === 'approve') {
        $conn->query("UPDATE bookings SET status='approved' WHERE id=$id");
        logAction($conn, 'Booking Approved', "Booking #$id approved.");
    } elseif ($action === 'reject') {
        $conn->query("UPDATE bookings SET status='rejected' WHERE id=$id");
        logAction($conn, 'Booking Rejected', "Booking #$id rejected.");
    } elseif ($action === 'reset_all') {
        $conn->query("DELETE FROM bookings");
        logAction($conn, 'All Bookings Cleared', 'Admin cleared all bookings.');
    }
    header('Location: bookings.php');
    exit;
}

// Filters
$search    = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$status_f  = $conn->real_escape_string($_GET['status'] ?? '');
$date_f    = $conn->real_escape_string($_GET['date'] ?? '');

$where = "WHERE 1=1";
if ($search)   $where .= " AND (u.fullname LIKE '%$search%' OR u.username LIKE '%$search%')";
if ($status_f) $where .= " AND b.status='$status_f'";
if ($date_f)   $where .= " AND b.date='$date_f'";

$bookings = $conn->query("
    SELECT b.*, u.fullname, u.username, m.name as meal_name, m.type as meal_type
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN menus m ON b.menu_id = m.id
    $where
    ORDER BY b.created_at DESC
");
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management | ANU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="topbar d-flex justify-content-between align-items-center">
            <h1><i class="bi bi-calendar-check me-2"></i>Booking Management</h1>
            <form method="POST" onsubmit="return confirm('Clear ALL bookings? This cannot be undone.');">
                <input type="hidden" name="action" value="reset_all">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash me-1"></i> Clear All
                </button>
            </form>
        </div>

        <div class="p-4 fade-in-up">
            <!-- Filter bar -->
            <form method="GET" class="row g-2 mb-4 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Search Student</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Name or username..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending"  <?= $status_f === 'pending'  ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_f === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_f === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="consumed" <?= $status_f === 'consumed' ? 'selected' : '' ?>>Consumed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_f) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-anu w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Student</th>
                                <th>Meal</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Booked On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; while ($b = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><code class="small"><?= htmlspecialchars($b['code']) ?></code></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($b['fullname']) ?></div>
                                    <div class="text-muted small">@<?= htmlspecialchars($b['username']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($b['meal_name']) ?></td>
                                <td><span class="menu-badge <?= strtolower($b['meal_type']) ?>"><?= $b['meal_type'] ?></span></td>
                                <td><?= date('d M Y', strtotime($b['date'])) ?></td>
                                <td>
                                    <span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
                                </td>
                                <td class="small text-muted"><?= date('d M, H:i', strtotime($b['created_at'])) ?></td>
                                <td>
                                    <?php if ($b['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success py-0 px-2" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-sm btn-danger py-0 px-2" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($bookings->num_rows === 0): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No bookings found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
