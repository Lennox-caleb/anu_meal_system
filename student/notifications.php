<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();
checkMaintenance($conn);
if ($_SESSION['role'] !== 'student') { header('Location: ../admin/notifications.php'); exit; }

$uid = (int)$_SESSION['user_id'];

if (isset($_GET['mark_all'])) {
    markAllRead($conn, $uid);
    header('Location: notifications.php');
    exit;
}

$notifs  = getNotifications($conn, $uid, 50);
$unread  = getUnreadCount($conn, $uid);
$page_title = '<i class="bi bi-bell-fill me-2"></i>Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | ANU Student</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<style>
.notif-page-item {
    background:#fff; border-radius:14px; padding:16px 18px; margin-bottom:10px;
    box-shadow:0 2px 10px rgba(0,0,0,.07); border-left:4px solid #eee;
    display:flex; gap:14px; align-items:flex-start; transition:box-shadow .2s; text-decoration:none; color:inherit;
}
.notif-page-item:hover { box-shadow:0 4px 18px rgba(0,0,0,.12); color:inherit; }
.notif-page-item.unread { border-left-color:var(--red); background:#fff8f8; }
.npi-icon { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.npi-title { font-weight:700; font-size:14px; margin-bottom:4px; }
.npi-msg   { color:#555; font-size:13px; line-height:1.6; }
.npi-time  { color:#aaa; font-size:11.5px; margin-top:6px; }
</style>
</head>
<body>
<div class="d-flex">
<?php include '../includes/student_sidebar.php'; ?>
<div class="main-content flex-grow-1">
<?php include '../includes/topbar.php'; ?>
<div class="p-3 p-md-4 fade-in-up">

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">My Notifications</h5>
        <div class="text-muted small"><?= $unread ?> unread</div>
    </div>
    <?php if ($unread > 0): ?>
    <a href="?mark_all=1" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-check2-all me-1"></i>Mark all read
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-bell-slash" style="font-size:3rem;"></i>
    <p class="mt-3">No notifications yet. When your bookings are approved or updated, you'll see them here.</p>
</div>
<?php else: ?>
<?php foreach ($notifs as $n):
    $color = $n['color'] ?: '#ff0000';
    $icon  = $n['icon']  ?: 'bi-bell';
    $cls   = $n['is_read'] ? '' : 'unread';
    $link  = $n['link'] ?: '#';
?>
<a href="<?= htmlspecialchars($link) ?>" class="notif-page-item <?= $cls ?>"
   onclick="fetch('../includes/notif_ajax.php?action=mark_one&id=<?= $n['id'] ?>')">
    <div class="npi-icon" style="background:<?= $color ?>20;color:<?= $color ?>">
        <i class="bi <?= htmlspecialchars($icon) ?>"></i>
    </div>
    <div style="flex:1;min-width:0;">
        <div class="npi-title"><?= htmlspecialchars($n['title']) ?></div>
        <div class="npi-msg"><?= $n['message'] ?></div>
        <div class="npi-time"><i class="bi bi-clock me-1"></i><?= timeAgo($n['created_at']) ?>
            &nbsp;&middot;&nbsp; <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
        </div>
    </div>
    <?php if (!$n['is_read']): ?>
    <div style="width:9px;height:9px;border-radius:50%;background:var(--red);flex-shrink:0;margin-top:4px;"></div>
    <?php endif; ?>
</a>
<?php endforeach; ?>
<?php endif; ?>

</div>
</div>
</div>
<?php include '../includes/scripts.php'; ?>
</body>
</html>
