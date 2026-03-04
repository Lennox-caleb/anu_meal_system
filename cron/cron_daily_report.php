<?php
/**
 * cron_daily_report.php — ANU Automated Daily Email Report
 *
 * ── Schedule (Linux crontab) ─────────────────────────────────────
 *   crontab -e   then add:
 *   59 23 * * * php /var/www/html/anu_meal_system/cron/cron_daily_report.php >> /var/log/anu_cron.log 2>&1
 *
 * ── Schedule (Windows / XAMPP Task Scheduler) ────────────────────
 *   Program : C:\xampp1\php\php.exe
 *   Args    : C:\xampp1\htdocs\anu_meal_system\cron\cron_daily_report.php
 *   Trigger : Daily at 23:59
 *
 * ── Manual test ──────────────────────────────────────────────────
 *   php cron_daily_report.php
 *   php cron_daily_report.php --date=2025-03-01
 *   php cron_daily_report.php --test            (dry-run, no emails)
 *   php cron_daily_report.php --help
 */

// ── Block HTTP access ─────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden: CLI only.\n");
}

define('CRON_START', microtime(true));
define('ROOT', dirname(__DIR__));

// ── Autoloader ────────────────────────────────────────────────────
$autoload = ROOT . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    clog("FATAL: Composer autoload not found.");
    clog("Run:  composer require phpmailer/phpmailer");
    clog("From: " . ROOT);
    exit(1);
}
require_once $autoload;

// ── Bootstrap ─────────────────────────────────────────────────────
require_once ROOT . '/includes/db.php';
require_once ROOT . '/includes/services/ReportService.php';
require_once ROOT . '/includes/services/NotificationService.php';

// ── CLI arguments ─────────────────────────────────────────────────
$opts      = getopt('', ['date:', 'test', 'help', 'force']);
$test_mode = isset($opts['test']);
$force     = isset($opts['force']);        // bypass email_reports check
$report_dt = trim($opts['date'] ?? date('Y-m-d'));

if (isset($opts['help'])) {
    echo <<<HELP
ANU Meal Booking — Daily Report Cron

Usage:
  php cron_daily_report.php
  php cron_daily_report.php --date=2025-03-01
  php cron_daily_report.php --test          Dry-run: build report, skip email
  php cron_daily_report.php --force         Send even if email_reports is disabled
  php cron_daily_report.php --help          This message

HELP;
    exit(0);
}

// Validate date
$dt_check = DateTime::createFromFormat('Y-m-d', $report_dt);
if (!$dt_check || $dt_check->format('Y-m-d') !== $report_dt) {
    clog("ERROR: Invalid date '{$report_dt}'. Use --date=YYYY-MM-DD");
    exit(1);
}

// ── Load settings ─────────────────────────────────────────────────
$settings_res = $conn->query("SELECT setting_key, setting_value FROM settings");
$cfg = [];
while ($r = $settings_res->fetch_assoc()) $cfg[$r['setting_key']] = $r['setting_value'];

$tz = $cfg['timezone'] ?? 'Africa/Nairobi';
date_default_timezone_set($tz);

clog("══════════════════════════════════════════");
clog("ANU Daily Report Cron  v2.0");
clog("Report date : {$report_dt}");
clog("Timezone    : {$tz}");
clog("Test mode   : " . ($test_mode ? 'YES — no emails sent' : 'NO'));
clog("Forced      : " . ($force ? 'YES' : 'NO'));
clog("══════════════════════════════════════════");

// ── Feature guard ─────────────────────────────────────────────────
if (!$force && ($cfg['email_reports'] ?? '0') !== '1') {
    clog("SKIP: email_reports = 0 in settings.");
    clog("Enable it in Admin → Settings, or use --force to override.");
    sysLogCron($conn, 0, 'Cron Skipped', 'email_reports disabled.');
    exit(0);
}

// ── Build stats ───────────────────────────────────────────────────
clog("Building analytics...");
$svc     = new ReportService($conn);
$filters = $svc->buildFilters(['from' => $report_dt, 'to' => $report_dt]);
$stats   = $svc->getSummaryStats($filters);

// Extra fields the email template needs
$stats['top_meal']  = queryTopMeal($conn, $report_dt);
$stats['peak_hour'] = queryPeakHour($conn, $report_dt);

clog(sprintf(
    "Stats: total=%d  approved=%d  consumed=%d  rejected=%d  revenue=KES%s",
    $stats['total'], $stats['approved'], $stats['consumed'],
    $stats['rejected'], number_format($stats['revenue'], 2)
));
clog("Top meal  : " . $stats['top_meal']);
clog("Peak hour : " . $stats['peak_hour']);

// ── Generate CSV ──────────────────────────────────────────────────
$csv_path = sys_get_temp_dir()
          . DIRECTORY_SEPARATOR
          . 'anu_report_' . $report_dt . '_' . time() . '.csv';

clog("Generating CSV → {$csv_path}");
$record_count = generateCSV($conn, $svc, $filters, $csv_path, $cfg, $report_dt);
clog("CSV rows written: {$record_count}");

if ($record_count === 0) {
    clog("Note: No bookings found for {$report_dt} — email will still be sent.");
}

// ── Send (or skip in test mode) ───────────────────────────────────
if ($test_mode) {
    clog("[TEST] Email send skipped.");
    clog("[TEST] CSV preserved at: {$csv_path}");
    sysLogCron($conn, 0, 'Cron Test', "Test run OK. {$record_count} records. CSV: {$csv_path}");
} else {
    clog("Sending emails to admins...");
    $notifier = new NotificationService($conn);
    $sent     = $notifier->sendDailyReport($stats, $csv_path, $report_dt);
    clog("Emails sent: {$sent}");
    sysLogCron($conn, 0, 'Daily Report Cron',
               "Date: {$report_dt} | Records: {$record_count} | Sent: {$sent}");

    // Clean up temp file
    if (file_exists($csv_path)) {
        unlink($csv_path);
        clog("Temp CSV deleted.");
    }
}

$elapsed = round(microtime(true) - CRON_START, 3);
clog("Completed in {$elapsed}s");
clog("══════════════════════════════════════════");
exit(0);


// ════════════════════════════════════════════════════════════════════
// FUNCTIONS
// ════════════════════════════════════════════════════════════════════

/**
 * Stream the report to a CSV file row-by-row — never loads full
 * result set into memory, safe for large datasets.
 */
function generateCSV(
    mysqli        $conn,
    ReportService $svc,
    array         $filters,
    string        $path,
    array         $cfg,
    string        $date
): int {
    $fh  = fopen($path, 'w');
    $org = $cfg['org_name'] ?? 'ANU';
    $tz  = $cfg['timezone'] ?? 'Africa/Nairobi';
    $ts  = date('d M Y H:i:s');

    // ── Metadata block ──
    fputcsv($fh, ["{$org} — Daily Meal Booking Report"]);
    fputcsv($fh, ['Report Date',   $date]);
    fputcsv($fh, ['Generated',     $ts]);
    fputcsv($fh, ['Timezone',      $tz]);
    fputcsv($fh, ['Generated By',  'Automated Cron System']);
    fputcsv($fh, []);

    // ── Headers ──
    fputcsv($fh, [
        'Booking Code', 'Student Name', 'Student ID', 'Department',
        'Meal', 'Type', 'Price (KES)', 'Date', 'Status',
        'Booked At', 'Validated At', 'Validated By',
    ]);

    // ── Stream rows ──
    $result = $svc->getExportResult($filters);
    $count  = 0;
    while ($row = $result->fetch_assoc()) {
        fputcsv($fh, [
            $row['code'],
            $row['fullname'],
            $row['student_id']     ?? '',
            $row['department']     ?? '',
            $row['meal_name'],
            $row['meal_type'],
            number_format((float)$row['price'], 2),
            $row['date']           ? date('d/m/Y', strtotime($row['date']))              : '',
            strtoupper($row['status']),
            $row['created_at']     ? date('d/m/Y H:i', strtotime($row['created_at']))   : '',
            $row['validated_at']   ? date('d/m/Y H:i', strtotime($row['validated_at'])) : '',
            $row['validator_name'] ?? '',
        ]);
        $count++;
        // Flush every 500 rows to keep memory flat
        if ($count % 500 === 0) fflush($fh);
    }

    fputcsv($fh, []);
    fputcsv($fh, ['Total Records', $count]);
    fclose($fh);
    return $count;
}

function queryTopMeal(mysqli $conn, string $date): string
{
    $stmt = $conn->prepare(
        "SELECT m.name, COUNT(b.id) c FROM bookings b
         JOIN menus m ON b.menu_id=m.id
         WHERE b.date=? GROUP BY m.id ORDER BY c DESC LIMIT 1"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ? "{$r['name']} ({$r['c']} bookings)" : '—';
}

function queryPeakHour(mysqli $conn, string $date): string
{
    $stmt = $conn->prepare(
        "SELECT HOUR(created_at) hr, COUNT(*) c FROM bookings
         WHERE DATE(created_at)=?
         GROUP BY HOUR(created_at) ORDER BY c DESC LIMIT 1"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if (!$r) return '—';
    $h = str_pad($r['hr'], 2, '0', STR_PAD_LEFT);
    return "{$h}:00 – {$h}:59 ({$r['c']} bookings)";
}

function sysLogCron(mysqli $conn, int $uid, string $action, string $details): void
{
    $stmt = $conn->prepare(
        "INSERT INTO system_logs (user_id,action,details,ip) VALUES (?,?,?,?)"
    );
    if (!$stmt) return;
    $ip = 'cron';
    $stmt->bind_param('isss', $uid, $action, $details, $ip);
    $stmt->execute();
}

function clog(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}
