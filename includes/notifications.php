<?php
/**
 * notifications.php — In-App Notification Bell Helper
 *
 * Functions used by sidebars + AJAX endpoint.
 * Safe: uses prepared statements only.
 */

/**
 * Create a notification for a user.
 */
function createNotification(
    mysqli $conn,
    int    $user_id,
    string $type,      // approved|rejected|consumed|pending|info|alert
    string $title,
    string $message,
    string $icon  = 'bi-bell',
    string $color = '#ff0000',
    string $link  = ''
): bool {
    $stmt = $conn->prepare(
        "INSERT INTO in_app_notifications
         (user_id, type, title, message, icon, color, link)
         VALUES (?,?,?,?,?,?,?)"
    );
    if (!$stmt) return false;
    $stmt->bind_param('issssss',
        $user_id, $type, $title, $message, $icon, $color, $link
    );
    return $stmt->execute();
}

/**
 * Create booking status notification for student.
 */
function notifyBookingStatus(mysqli $conn, int $booking_id): void
{
    $stmt = $conn->prepare(
        "SELECT b.status, b.user_id, b.code, m.name meal_name, m.type meal_type, b.date
         FROM bookings b JOIN menus m ON b.menu_id=m.id
         WHERE b.id=? LIMIT 1"
    );
    if (!$stmt) return;
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    if (!$b) return;

    $status = $b['status'];
    $code   = $b['code'];
    $meal   = $b['meal_name'];
    $date   = date('d M Y', strtotime($b['date']));

    $map = [
        'approved' => [
            'title'   => 'Booking Approved ✅',
            'message' => "Your booking <strong>{$code}</strong> for {$meal} on {$date} has been approved. Show your QR code at the cafeteria.",
            'icon'    => 'bi-patch-check-fill',
            'color'   => '#28a745',
        ],
        'rejected' => [
            'title'   => 'Booking Rejected ❌',
            'message' => "Your booking <strong>{$code}</strong> for {$meal} on {$date} was rejected. Contact the cafeteria for details.",
            'icon'    => 'bi-x-circle-fill',
            'color'   => '#dc3545',
        ],
        'consumed' => [
            'title'   => 'Meal Collected ✔',
            'message' => "Your meal <strong>{$meal}</strong> on {$date} has been collected and marked as consumed.",
            'icon'    => 'bi-check2-circle',
            'color'   => '#0dcaf0',
        ],
        'pending' => [
            'title'   => 'Booking Received ⏳',
            'message' => "Your booking <strong>{$code}</strong> for {$meal} on {$date} is pending approval.",
            'icon'    => 'bi-hourglass-split',
            'color'   => '#fac823',
        ],
    ];

    $n = $map[$status] ?? null;
    if (!$n) return;

    createNotification(
        $conn, (int)$b['user_id'], $status,
        $n['title'], $n['message'], $n['icon'], $n['color'],
        'my_bookings.php'
    );
}

/**
 * Create admin notification for new booking.
 */
function notifyAdminsNewBooking(mysqli $conn, int $booking_id): void
{
    $stmt = $conn->prepare(
        "SELECT b.code, b.date, u.fullname, m.name meal_name
         FROM bookings b
         JOIN users u ON b.user_id=u.id
         JOIN menus m ON b.menu_id=m.id
         WHERE b.id=? LIMIT 1"
    );
    if (!$stmt) return;
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    if (!$b) return;

    // Get all admin IDs
    $admins = $conn->query(
        "SELECT id FROM users WHERE role IN ('admin','super_admin')"
    );
    if (!$admins) return;

    $date = date('d M Y', strtotime($b['date']));
    while ($a = $admins->fetch_assoc()) {
        createNotification(
            $conn, (int)$a['id'], 'info',
            'New Booking Received',
            "Student <strong>{$b['fullname']}</strong> booked <strong>{$b['meal_name']}</strong> for {$date}. Code: {$b['code']}",
            'bi-calendar-plus',
            '#ff0000',
            'bookings.php'
        );
    }
}

/**
 * Get unread count for a user.
 */
function getUnreadCount(mysqli $conn, int $user_id): int
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) c FROM in_app_notifications WHERE user_id=? AND is_read=0"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

/**
 * Get recent notifications for a user (last 15).
 */
function getNotifications(mysqli $conn, int $user_id, int $limit = 15): array
{
    $stmt = $conn->prepare(
        "SELECT * FROM in_app_notifications
         WHERE user_id=?
         ORDER BY created_at DESC LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Mark all as read for a user.
 */
function markAllRead(mysqli $conn, int $user_id): void
{
    $stmt = $conn->prepare(
        "UPDATE in_app_notifications SET is_read=1 WHERE user_id=? AND is_read=0"
    );
    if (!$stmt) return;
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

/**
 * Time-ago helper.
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff/60) . 'm ago';
    if ($diff < 86400)   return floor($diff/3600) . 'h ago';
    if ($diff < 604800)  return floor($diff/86400) . 'd ago';
    return date('d M', strtotime($datetime));
}
