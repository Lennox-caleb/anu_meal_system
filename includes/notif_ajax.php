<?php
/**
 * notif_ajax.php — AJAX endpoints for notification bell
 *
 * Actions (GET/POST ?action=...):
 *   poll        — returns unread count + latest notifications (JSON)
 *   mark_all    — mark all as read for session user
 *   mark_one    — mark single notification as read (?id=N)
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error' => 'unauthenticated']); exit; }

$uid    = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'poll';

switch ($action) {
    case 'poll':
        $count  = getUnreadCount($conn, $uid);
        $notifs = getNotifications($conn, $uid, 12);
        $items  = [];
        foreach ($notifs as $n) {
            $items[] = [
                'id'       => $n['id'],
                'title'    => $n['title'],
                'message'  => strip_tags($n['message']),
                'icon'     => $n['icon'],
                'color'    => $n['color'],
                'link'     => $n['link'],
                'is_read'  => (bool)$n['is_read'],
                'time_ago' => timeAgo($n['created_at']),
            ];
        }
        echo json_encode(['count' => $count, 'notifications' => $items]);
        break;

    case 'mark_all':
        markAllRead($conn, $uid);
        echo json_encode(['ok' => true, 'count' => 0]);
        break;

    case 'mark_one':
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE in_app_notifications SET is_read=1 WHERE id=? AND user_id=?"
            );
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
        }
        $count = getUnreadCount($conn, $uid);
        echo json_encode(['ok' => true, 'count' => $count]);
        break;

    default:
        echo json_encode(['error' => 'unknown action']);
}
exit;
