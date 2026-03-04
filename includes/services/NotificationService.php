<?php
/**
 * NotificationService.php — ANU Meal Booking Email Engine
 *
 * Handles:
 *   • Booking status alerts   (approved / rejected / consumed / pending / reminder)
 *   • Daily admin digest      (called by cron_daily_report.php)
 *   • notifications_log table writes
 *   • Rate-limiting           (no duplicate sends within RATE_LIMIT_SECONDS)
 *
 * Requires PHPMailer:
 *   composer require phpmailer/phpmailer
 *
 * Usage in bookings.php / validation.php:
 *   require_once ROOT . '/includes/services/NotificationService.php';
 *   $notifier = new NotificationService($conn);
 *   $notifier->sendBookingAlert($booking_id, 'approved');
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class NotificationService
{
    private mysqli $conn;
    private array  $cfg = [];
    private string $tz  = 'Africa/Nairobi';

    /** No duplicate sends for the same booking+type within this window */
    private const RATE_LIMIT_SECONDS = 3600;

    /** Subject line templates */
    private const SUBJECTS = [
        'approved'  => '[ANU] Booking Approved — {meal} on {date}',
        'rejected'  => '[ANU] Booking Rejected — {meal} on {date}',
        'consumed'  => '[ANU] Meal Collected — {meal} on {date}',
        'pending'   => '[ANU] Booking Received — {meal} on {date}',
        'reminder'  => '[ANU] Reminder: Booking Window Closing Soon',
        'cancelled' => '[ANU] Booking Cancelled — {meal} on {date}',
    ];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->cfg  = $this->loadSettings();
        $this->tz   = $this->cfg['timezone'] ?? 'Africa/Nairobi';
        date_default_timezone_set($this->tz);
    }

    // ════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════════

    public function sendBookingAlert(int $booking_id, string $type): bool
    {
        if (($this->cfg['meal_alerts'] ?? '0') !== '1') return false;
        if (!array_key_exists($type, self::SUBJECTS)) {
            $this->writeLog($booking_id, null, $type, 'failed', 'Unknown type');
            return false;
        }
        $data = $this->fetchBookingData($booking_id);
        if (!$data) {
            $this->writeLog($booking_id, null, $type, 'failed', 'Booking not found');
            return false;
        }
        if (empty($data['email'])) {
            $this->writeLog($booking_id, (int)$data['user_id'], $type, 'failed', 'No email address');
            return false;
        }
        if ($this->isRateLimited($booking_id, $type)) return false;

        $subject = $this->interpolate(self::SUBJECTS[$type], $data);
        $html    = $this->buildAlertHTML($type, $data);
        $ok      = $this->dispatch($data['email'], $data['fullname'], $subject, $html);

        $this->writeLog(
            $booking_id, (int)$data['user_id'], $type,
            $ok ? 'sent' : 'failed',
            $ok ? null : 'SMTP delivery failed'
        );
        return $ok;
    }

    public function sendWindowReminders(): int
    {
        if (($this->cfg['meal_alerts'] ?? '0') !== '1') return 0;
        $stmt = $this->conn->prepare(
            "SELECT b.id, b.code, b.date,
                    u.id user_id, u.email, u.fullname,
                    m.name meal_name, m.type meal_type
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             JOIN menus m ON b.menu_id = m.id
             WHERE b.date = CURDATE() AND b.status = 'pending'
               AND u.email IS NOT NULL AND u.email != ''"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $sent = 0;
        foreach ($rows as $row) {
            if ($this->isRateLimited((int)$row['id'], 'reminder')) continue;
            $html = $this->buildAlertHTML('reminder', $row);
            $ok   = $this->dispatch($row['email'], $row['fullname'], self::SUBJECTS['reminder'], $html);
            $this->writeLog((int)$row['id'], (int)$row['user_id'], 'reminder',
                             $ok ? 'sent' : 'failed', $ok ? null : 'SMTP failed');
            if ($ok) $sent++;
        }
        return $sent;
    }

    public function sendDailyReport(array $stats, string $csv_path, string $date): int
    {
        if (($this->cfg['email_reports'] ?? '0') !== '1') return 0;
        $result = $this->conn->query(
            "SELECT id, email, fullname FROM users
             WHERE role IN ('admin','super_admin')
               AND email IS NOT NULL AND email != ''"
        );
        if (!$result) return 0;
        $admins  = $result->fetch_all(MYSQLI_ASSOC);
        $subject = '[ANU] Daily Meal Report — ' . date('d M Y', strtotime($date));
        $html    = $this->buildDailyReportHTML($stats, $date);
        $sent    = 0;
        foreach ($admins as $admin) {
            $ok = $this->dispatch($admin['email'], $admin['fullname'], $subject, $html, $csv_path);
            $this->sysLog((int)$admin['id'], 'Daily Report Email',
                           $ok ? "Sent to {$admin['email']}" : "FAILED for {$admin['email']}");
            if ($ok) $sent++;
        }
        return $sent;
    }

    // ════════════════════════════════════════════════════════════════
    // MAILER CORE
    // ════════════════════════════════════════════════════════════════

    private function dispatch(
        string $to_email, string $to_name,
        string $subject,  string $html,
        ?string $attachment = null
    ): bool {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log('[ANU/Notify] PHPMailer not loaded. Run: composer require phpmailer/phpmailer');
            return false;
        }
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->cfg['smtp_host'] ?? 'smtp.gmail.com';
            $mail->Port       = (int)($this->cfg['smtp_port'] ?? 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->cfg['smtp_user'] ?? '';
            $mail->Password   = $this->cfg['smtp_pass'] ?? '';
            $mail->SMTPSecure = ((int)($this->cfg['smtp_port'] ?? 587) === 465)
                                ? PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer::ENCRYPTION_STARTTLS;
            // Dev-only: disable SSL verify on localhost (remove in production)
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];

            $from      = $this->cfg['smtp_from']      ?? ($this->cfg['org_email'] ?? 'noreply@anu.ac.ke');
            $from_name = $this->cfg['smtp_from_name'] ?? ($this->cfg['org_name']  ?? 'ANU Meal Booking');
            $mail->setFrom($from, $from_name);
            $mail->addReplyTo($from, $from_name);
            $mail->addAddress($to_email, $to_name);

            $mail->isHTML(true);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = wordwrap(strip_tags(
                str_replace(['<br>', '<br/>', '</p>', '</tr>'], "\n", $html)), 100);

            if ($attachment && is_readable($attachment)) {
                $mail->addAttachment($attachment, basename($attachment));
            }
            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log('[ANU/Notify] MailerException to ' . $to_email . ': ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log('[ANU/Notify] Error to ' . $to_email . ': ' . $e->getMessage());
            return false;
        }
    }

    // ════════════════════════════════════════════════════════════════
    // EMAIL TEMPLATES
    // ════════════════════════════════════════════════════════════════

    private function buildAlertHTML(string $type, array $d): string
    {
        $org   = esc($this->cfg['org_name'] ?? 'ANU');
        $code  = esc($d['code']      ?? '—');
        $meal  = esc($d['meal_name'] ?? $d['name']  ?? '—');
        $mtype = esc($d['meal_type'] ?? $d['type']  ?? '—');
        $date  = isset($d['date']) ? date('l, d M Y', strtotime($d['date'])) : '—';
        $name  = esc($d['fullname']  ?? 'Student');
        $now   = date('d M Y H:i');

        $conf = [
            'approved'  => ['#d1fae5','#065f46','#6ee7b7','✅ Booking Approved',
                "Great news, <b>{$name}</b>! Your meal booking has been <b>approved</b>. Present your QR code at the cafeteria during serving time."],
            'rejected'  => ['#fee2e2','#991b1b','#fca5a5','❌ Booking Rejected',
                "Dear <b>{$name}</b>, your booking has been <b>rejected</b>. Please contact the cafeteria office for assistance."],
            'consumed'  => ['#dbeafe','#1e40af','#93c5fd','✔ Meal Collected',
                "Dear <b>{$name}</b>, your meal has been <b>collected and marked as consumed</b>. Enjoy!"],
            'pending'   => ['#fef3c7','#92400e','#fcd34d','⏳ Booking Received',
                "Dear <b>{$name}</b>, your booking is <b>received and awaiting approval</b>. You will be notified once reviewed."],
            'reminder'  => ['#fef3c7','#92400e','#fcd34d','⏰ Booking Reminder',
                "Dear <b>{$name}</b>, your booking is still <b>pending</b> and the booking window closes soon. Visit the cafeteria on time."],
            'cancelled' => ['#f3f4f6','#374151','#d1d5db','🚫 Booking Cancelled',
                "Dear <b>{$name}</b>, your booking has been <b>cancelled</b>."],
        ][$type] ?? ['#f3f4f6','#374151','#d1d5db','Notification',
                     "Dear <b>{$name}</b>, there is an update on your booking."];

        [$bg, $fg, $border, $badge, $message] = $conf;

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:600px;">
  <tr><td style="background:linear-gradient(135deg,#cc0000,#ff0000);padding:28px 36px;text-align:center;">
    <p style="margin:0;color:rgba(255,255,255,.65);font-size:11px;letter-spacing:2px;text-transform:uppercase;">Africa Nazarene University</p>
    <h1 style="margin:6px 0 0;color:#fff;font-size:20px;">{$org}</h1>
    <p style="margin:4px 0 0;color:rgba(255,255,255,.7);font-size:12px;">Meal Booking System</p>
  </td></tr>
  <tr><td style="padding:28px 36px 14px;text-align:center;">
    <span style="display:inline-block;background:{$bg};color:{$fg};border:1px solid {$border};
          padding:9px 28px;border-radius:40px;font-weight:700;font-size:15px;">{$badge}</span>
    <p style="margin:18px 0 0;color:#444;font-size:14px;line-height:1.8;">{$message}</p>
  </td></tr>
  <tr><td style="padding:10px 36px 26px;">
    <table width="100%" cellpadding="0" cellspacing="0"
           style="background:#fafafa;border:1px solid #eee;border-radius:10px;font-size:13px;">
      <tr><td style="padding:11px 16px;color:#999;width:38%;border-bottom:1px solid #eee;">Booking Code</td>
          <td style="padding:11px 16px;font-family:monospace;font-weight:800;color:#cc0000;font-size:15px;letter-spacing:2px;border-bottom:1px solid #eee;">{$code}</td></tr>
      <tr><td style="padding:11px 16px;color:#999;border-bottom:1px solid #eee;">Meal</td>
          <td style="padding:11px 16px;font-weight:600;color:#222;border-bottom:1px solid #eee;">{$meal}</td></tr>
      <tr><td style="padding:11px 16px;color:#999;border-bottom:1px solid #eee;">Type</td>
          <td style="padding:11px 16px;color:#444;border-bottom:1px solid #eee;">{$mtype}</td></tr>
      <tr><td style="padding:11px 16px;color:#999;">Date</td>
          <td style="padding:11px 16px;color:#444;">{$date}</td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:0 36px 22px;text-align:center;">
    <p style="color:#ccc;font-size:11px;margin:0;line-height:1.7;">
      This is an automated message — please do not reply.<br>
      Contact the {$org} cafeteria office for assistance.
    </p>
  </td></tr>
  <tr><td style="background:#1a1a1a;padding:13px 36px;text-align:center;">
    <p style="color:#555;font-size:10px;margin:0;">&copy; {$org} &nbsp;|&nbsp; Sent {$now} &nbsp;|&nbsp; ANU Meal Booking System</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }

    private function buildDailyReportHTML(array $stats, string $date): string
    {
        $org      = esc($this->cfg['org_name'] ?? 'ANU');
        $date_fmt = date('l, d M Y', strtotime($date));
        $now      = date('d M Y H:i:s');
        $tz       = $this->tz;

        $items = [
            ['Total Bookings',  number_format($stats['total']    ?? 0), '#ff0000'],
            ['Consumed',        number_format($stats['consumed'] ?? 0), '#0dcaf0'],
            ['Approved',        number_format($stats['approved'] ?? 0), '#28a745'],
            ['Pending',         number_format($stats['pending']  ?? 0), '#fac823'],
            ['Rejected',        number_format($stats['rejected'] ?? 0), '#dc3545'],
            ['Total Revenue',   'KES '.number_format($stats['revenue'] ?? 0, 2), '#6f42c1'],
            ['Approval Rate',   ($stats['approval_rate']  ?? 0).'%', '#28a745'],
            ['No-Show Rate',    ($stats['pending_rate']   ?? 0).'%', '#fac823'],
            ['Top Meal',        esc($stats['top_meal']  ?? '—'), '#cc0000'],
            ['Peak Hour',       esc($stats['peak_hour'] ?? '—'), '#0dcaf0'],
        ];

        $trs = '';
        foreach ($items as [$label, $value, $color]) {
            $trs .= "<tr style=\"border-bottom:1px solid #eee;\">
              <td style=\"padding:11px 18px;color:#777;font-size:13px;\">{$label}</td>
              <td style=\"padding:11px 18px;font-weight:700;color:{$color};font-size:14px;\">{$value}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);max-width:600px;">
  <tr><td style="background:linear-gradient(135deg,#1a1a1a,#333);padding:28px 36px;text-align:center;">
    <p style="margin:0;color:rgba(255,255,255,.45);font-size:11px;letter-spacing:2px;text-transform:uppercase;">{$org}</p>
    <h1 style="margin:8px 0 0;color:#fac823;font-size:20px;">📊 Daily Operations Report</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,.55);font-size:12px;">{$date_fmt}</p>
  </td></tr>
  <tr><td style="padding:22px 36px 10px;">
    <p style="color:#555;font-size:13px;line-height:1.8;margin:0;">
      Automated daily summary for <strong>{$date_fmt}</strong>. The full CSV is attached.
    </p>
  </td></tr>
  <tr><td style="padding:10px 36px 24px;">
    <table width="100%" cellpadding="0" cellspacing="0"
           style="background:#fafafa;border:1px solid #eee;border-radius:10px;overflow:hidden;">{$trs}</table>
  </td></tr>
  <tr><td style="padding:0 36px 22px;text-align:center;">
    <p style="color:#ccc;font-size:11px;margin:0;line-height:1.7;">
      Generated: {$now} ({$tz})<br>
      This is an automated report — do not reply.
    </p>
  </td></tr>
  <tr><td style="background:#1a1a1a;padding:13px 36px;text-align:center;">
    <p style="color:#555;font-size:10px;margin:0;">{$org} Reporting System &nbsp;|&nbsp; {$now}</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }

    // ════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════

    private function isRateLimited(int $booking_id, string $type): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::RATE_LIMIT_SECONDS);
        $stmt  = $this->conn->prepare(
            "SELECT id FROM notifications_log
             WHERE booking_id=? AND type=? AND status='sent' AND sent_at>=? LIMIT 1"
        );
        if (!$stmt) return false;
        $stmt->bind_param('iss', $booking_id, $type, $since);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function writeLog(int $booking_id, ?int $user_id, string $type, string $status, ?string $error): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO notifications_log (booking_id,user_id,type,status,error_message,sent_at)
             VALUES (?,?,?,?,?,NOW())"
        );
        if (!$stmt) { error_log('[ANU/Notify] notifications_log missing — run notifications_schema.sql'); return; }
        $stmt->bind_param('iisss', $booking_id, $user_id, $type, $status, $error);
        $stmt->execute();
    }

    private function sysLog(int $user_id, string $action, string $details): void
    {
        $ip   = (PHP_SAPI === 'cli') ? 'cron' : ($_SERVER['REMOTE_ADDR'] ?? '');
        $stmt = $this->conn->prepare("INSERT INTO system_logs (user_id,action,details,ip) VALUES (?,?,?,?)");
        if (!$stmt) return;
        $stmt->bind_param('isss', $user_id, $action, $details, $ip);
        $stmt->execute();
    }

    private function fetchBookingData(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT b.id, b.code, b.date, b.status,
                    u.id user_id, u.fullname, u.email,
                    m.name meal_name, m.type meal_type
             FROM bookings b
             JOIN users u ON b.user_id=u.id
             JOIN menus m ON b.menu_id=m.id
             WHERE b.id=? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function interpolate(string $tpl, array $d): string
    {
        return str_replace(
            ['{meal}', '{date}'],
            [$d['meal_name'] ?? '—', isset($d['date']) ? date('d M Y', strtotime($d['date'])) : '—'],
            $tpl
        );
    }

    private function loadSettings(): array
    {
        $r = $this->conn->query("SELECT setting_key, setting_value FROM settings");
        $c = [];
        if ($r) while ($row = $r->fetch_assoc()) $c[$row['setting_key']] = $row['setting_value'];
        return $c;
    }
}

/** HTML-safe shorthand used inside this file */
if (!function_exists('esc')) {
    function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
