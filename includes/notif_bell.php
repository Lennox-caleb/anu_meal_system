<?php
if (empty($_SESSION['user_id'])) return;

$_nb_count  = 0;
$_nb_notifs = [];

if (function_exists('getUnreadCount') && isset($conn)) {
    try {
        $__r = @$conn->query("SELECT 1 FROM in_app_notifications LIMIT 1");
        if ($__r !== false) {
            $_nb_uid    = (int)$_SESSION['user_id'];
            $_nb_count  = getUnreadCount($conn, $_nb_uid);
            $_nb_notifs = getNotifications($conn, $_nb_uid, 12);
        }
    } catch (\Throwable $__e) { /* table not yet created */ }
}
?>
<div id="notifBellWrap" style="position:relative;display:inline-flex;align-items:center;">

    <button id="notifBellBtn" onclick="notifToggle(event)" title="Notifications"
            style="background:none;border:none;cursor:pointer;padding:6px 8px;border-radius:8px;position:relative;font-size:20px;color:#555;display:flex;align-items:center;line-height:1;transition:background .2s;">
        <i class="bi bi-bell-fill"></i>
        <span id="notifCount"
              style="position:absolute;top:2px;right:2px;background:#ff0000;color:#fff;font-size:10px;font-weight:800;min-width:17px;height:17px;border-radius:9px;display:<?= $_nb_count > 0 ? 'flex' : 'none' ?>;align-items:center;justify-content:center;padding:0 3px;pointer-events:none;line-height:1;">
            <?= min($_nb_count, 99) ?>
        </span>
    </button>

    <div id="notifDropdown"
         style="display:none;position:absolute;top:calc(100% + 10px);right:0;width:330px;max-width:calc(100vw - 20px);background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);border:1px solid #eee;z-index:99999;overflow:hidden;">

        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #f0f0f0;">
            <span style="font-size:14px;font-weight:700;">
                🔔 Notifications<?php if ($_nb_count > 0): ?> <span style="color:#ff0000;font-size:12px;">(<?= $_nb_count ?>)</span><?php endif; ?>
            </span>
            <?php if ($_nb_count > 0): ?>
            <button onclick="notifMarkAll()" style="font-size:11px;color:#ff0000;background:none;border:none;cursor:pointer;font-weight:600;">Mark all read</button>
            <?php endif; ?>
        </div>

        <div id="notifList" style="max-height:340px;overflow-y:auto;">
        <?php if (empty($_nb_notifs)): ?>
            <div style="padding:30px 16px;text-align:center;color:#bbb;font-size:13px;">
                <i class="bi bi-bell-slash" style="font-size:2rem;display:block;margin-bottom:8px;color:#ddd;"></i>
                No notifications yet
            </div>
        <?php else: ?>
            <?php foreach ($_nb_notifs as $n):
                $lnk   = !empty($n['link']) ? htmlspecialchars($n['link']) : '#';
                $ago   = function_exists('timeAgo') ? timeAgo($n['created_at']) : '';
                $bg    = empty($n['is_read']) ? '#fff8f8' : '#fff';
                $bl    = empty($n['is_read']) ? 'border-left:3px solid #ff0000;' : '';
                $col   = htmlspecialchars($n['color'] ?: '#ff0000');
                $ico   = htmlspecialchars($n['icon']  ?: 'bi-bell');
            ?>
            <a href="<?= $lnk ?>" data-id="<?= (int)$n['id'] ?>" onclick="notifMarkOne(<?= (int)$n['id'] ?>)"
               style="display:flex;gap:10px;align-items:flex-start;padding:10px 16px;border-bottom:1px solid #f8f8f8;text-decoration:none;color:#333;background:<?= $bg ?>;<?= $bl ?>">
                <div style="width:34px;height:34px;border-radius:50%;background:<?= $col ?>22;color:<?= $col ?>;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                    <i class="bi <?= $ico ?>"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:700;margin-bottom:2px;line-height:1.3;"><?= htmlspecialchars($n['title']) ?></div>
                    <div style="font-size:12px;color:#666;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars(strip_tags($n['message'])) ?></div>
                    <div style="font-size:10px;color:#aaa;margin-top:3px;"><?= $ago ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div style="padding:10px 16px;text-align:center;border-top:1px solid #f0f0f0;background:#fafafa;">
            <a href="notifications.php" style="font-size:12px;color:#ff0000;text-decoration:none;font-weight:600;">View all →</a>
        </div>
    </div>
</div>
