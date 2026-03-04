<?php
/**
 * reports.php — ANU Enterprise Reporting View/Controller
 *
 * MVC Role:  View + thin controller (bootstraps services, handles exports)
 * Services:  ReportService (analytics), ExportController (file generation)
 * Security:  All SQL via prepared statements in service layer
 *            All user input validated/whitelisted before use
 */
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/services/ReportService.php';
require_once '../includes/services/ExportController.php';
requireAdmin();

// ── Bootstrap services ──────────────────────────────────────────
$svc      = new ReportService($conn);
$exporter = new ExportController($conn, $svc);

// ── Collect & validate raw filter inputs ────────────────────────
$raw = [
    'from'       => $_GET['from']       ?? date('Y-m-01'),
    'to'         => $_GET['to']         ?? date('Y-m-d'),
    'status'     => $_GET['status']     ?? '',
    'meal_type'  => $_GET['meal_type']  ?? '',
    'department' => $_GET['department'] ?? '',
    'validated'  => $_GET['validated']  ?? '',
];
$filters = $svc->buildFilters($raw);

// ── Handle export requests (must happen before any HTML output) ──
if (!empty($_GET['export'])) {
    $stats = $svc->getSummaryStats($filters);
    match ($_GET['export']) {
        'csv'         => $exporter->exportCSV($filters),
        'csv_summary' => $exporter->exportSummaryCSV($filters, $stats),
        'json'        => $exporter->exportJSON($filters),
        default       => null,
    };
}

// ── Load all analytics (wrapped so one failure doesn't crash the page) ─
$stats       = $svc->getSummaryStats($filters);
$comparison  = $svc->getPeriodComparison($filters['from'], $filters['to']);
$daily       = $svc->getDailyTrend(14);

// Graceful fallback for trend methods — returns [] if query fails
try { $weekly = $svc->getWeeklyTrend(); } catch (Throwable $e) { $weekly = []; error_log('Weekly: '.$e->getMessage()); }
try { $monthly = $svc->getMonthlyTrend(); } catch (Throwable $e) { $monthly = []; error_log('Monthly: '.$e->getMessage()); }

$peak_hours  = $svc->getPeakHours();
$rev_by_type = $svc->getRevenueByType($filters);
$top_meals   = $svc->getTopMeals(5);
$depts       = $svc->getDepartments();
$page        = max(1, (int)($_GET['page'] ?? 1));
$paged       = $svc->getBookingsPage($filters, $page, 25);

// ── Encode chart data for JavaScript ───────────────────────────
$j = fn($v) => json_encode($v);
$daily_labels    = $j(array_map(fn($r) => date('d M', strtotime($r['d'])), $daily));
$daily_bookings  = $j(array_column($daily, 'bookings'));
$daily_revenue   = $j(array_column($daily, 'revenue'));
$weekly_labels   = $j(array_map(fn($r) => date('d M', strtotime($r['week_start'])), $weekly));
$weekly_bookings = $j(array_column($weekly, 'bookings'));
$weekly_revenue  = $j(array_column($weekly, 'revenue'));
$monthly_labels  = $j(array_column($monthly, 'month_label'));
$monthly_bks     = $j(array_column($monthly, 'bookings'));
$monthly_rev     = $j(array_column($monthly, 'revenue'));
$hour_labels     = $j(array_map(fn($r) => str_pad($r['hr'],2,'0',STR_PAD_LEFT).':00', $peak_hours));
$hour_counts     = $j(array_column($peak_hours, 'cnt'));
$type_labels     = $j(array_column($rev_by_type, 'type'));
$type_bookings   = $j(array_column($rev_by_type, 'bookings'));

// Build export query string (preserves active filters)
$export_qs = fn($fmt) => '?' . http_build_query(array_merge($raw, ['export' => $fmt]));
$page_qs   = fn($p)   => '?' . http_build_query(array_merge($raw, ['page'   => $p]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports &amp; Analytics | ANU Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../public/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── Enterprise Report Styles ────────────────────────────── */
:root { --red:#ff0000; --gold:#fac823; --dark:#1a1a1a; --mid:#555; --light:#f8f9fa; }

.metric-card {
    background:#fff; border-radius:14px; padding:20px 18px;
    box-shadow:0 2px 12px rgba(0,0,0,.07);
    border-left:4px solid var(--red);
    position:relative; overflow:hidden; transition:transform .18s,box-shadow .18s;
}
.metric-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.12); }
.metric-card .val { font-size:1.85rem; font-weight:800; line-height:1.1; color:var(--dark); }
.metric-card .lbl { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.6px; margin-top:5px; }
.metric-card .ico { position:absolute; right:14px; top:14px; font-size:1.5rem; opacity:.12; }

.pct-pill { font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; margin-top:6px; display:inline-block; }
.pct-up   { background:#d1fae5; color:#065f46; }
.pct-dn   { background:#fee2e2; color:#991b1b; }
.pct-flat { background:#f3f4f6; color:#6b7280; }

.rate-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.rate-track { flex:1; height:6px; background:#f0f0f0; border-radius:3px; margin:0 10px; }
.rate-fill  { height:100%; border-radius:3px; transition:width .7s ease; }

.panel { background:#fff; border-radius:14px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.07); height:100%; }
.panel-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--mid); margin-bottom:16px; display:flex; align-items:center; gap:6px; }
.panel-title i { color:var(--red); font-size:14px; }

.filter-bar { background:#fff; border-radius:14px; padding:14px 18px; box-shadow:0 2px 12px rgba(0,0,0,.07); }

.report-table thead th { background:var(--dark); color:#fff; font-size:11px; text-transform:uppercase; letter-spacing:.5px; padding:11px 14px; border:none; white-space:nowrap; }
.report-table tbody td { padding:10px 14px; font-size:13px; vertical-align:middle; border-color:#f5f5f5; }
.report-table tbody tr:hover { background:#fafafa; }

.meal-bar { height:4px; border-radius:2px; background:linear-gradient(90deg,var(--red),var(--gold)); margin-top:5px; }

.loading-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:9999; align-items:center; justify-content:center; }
.loading-overlay.show { display:flex; }
.spinner-ring { width:54px; height:54px; border:5px solid rgba(255,255,255,.2); border-top-color:#fff; border-radius:50%; animation:spin .8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

.audit-entry { padding:8px 0; border-bottom:1px solid #f5f5f5; font-size:12.5px; }
.audit-entry:last-child { border-bottom:none; }

.tab-content .panel { border-radius:0 0 14px 14px; }
.nav-tabs .nav-link.active { border-bottom-color:#fff; font-weight:600; color:var(--red); }
</style>
</head>
<body>
<div class="d-flex">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content flex-grow-1">

<!-- ── Topbar ─────────────────────────────────────────────── -->
<div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1><i class="bi bi-bar-chart-line me-2"></i>Reports &amp; Analytics</h1>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
            <?= date('d M Y') ?> &nbsp;|&nbsp;
            <?= htmlspecialchars($filters['from']) ?> → <?= htmlspecialchars($filters['to']) ?>
        </span>
        <!-- Export Dropdown -->
        <div class="dropdown">
            <button class="btn btn-anu btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="exportDropdown">
                <i class="bi bi-download me-1"></i>Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3" style="min-width:230px;">
                <li><div class="dropdown-header small fw-bold text-muted px-3 py-2">📊 Choose Export Format</div></li>
                <li>
                    <a class="dropdown-item export-link py-2" href="<?= $export_qs('csv') ?>">
                        <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>
                        <strong>Full CSV</strong><br>
                        <span class="text-muted small ms-4">All records with current filters</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item export-link py-2" href="<?= $export_qs('csv_summary') ?>">
                        <i class="bi bi-file-earmark-text me-2 text-warning"></i>
                        <strong>Summary CSV</strong><br>
                        <span class="text-muted small ms-4">Stats only, no row data</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item export-link py-2" href="<?= $export_qs('json') ?>">
                        <i class="bi bi-braces me-2 text-info"></i>
                        <strong>JSON Export</strong><br>
                        <span class="text-muted small ms-4">API-ready with metadata</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider mx-2"></li>
                <li>
                    <button class="dropdown-item py-2" id="exportPDF">
                        <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>
                        <strong>PDF (Current View)</strong><br>
                        <span class="text-muted small ms-4">Visible table only</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center text-white">
        <div class="spinner-ring mb-3"></div>
        <div class="fw-semibold">Preparing export…</div>
    </div>
</div>

<div class="p-4 fade-in-up">

<!-- ── FILTER BAR ───────────────────────────────────────── -->
<div class="filter-bar mb-4">
<form method="GET" id="filterForm">
<div class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
        <label class="form-label fw-semibold small mb-1"><i class="bi bi-calendar me-1"></i>From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($raw['from']) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label fw-semibold small mb-1"><i class="bi bi-calendar-check me-1"></i>To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($raw['to']) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label fw-semibold small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <?php foreach (['pending','approved','rejected','consumed'] as $s): ?>
            <option value="<?= $s ?>" <?= $raw['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label fw-semibold small mb-1">Meal Type</label>
        <select name="meal_type" class="form-select form-select-sm">
            <option value="">All Types</option>
            <?php foreach (['Breakfast','Lunch','Dinner'] as $t): ?>
            <option value="<?= $t ?>" <?= $raw['meal_type']===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label fw-semibold small mb-1">Department</label>
        <select name="department" class="form-select form-select-sm">
            <option value="">All Depts</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $raw['department']===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label fw-semibold small mb-1">Validation</label>
        <select name="validated" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="1" <?= $raw['validated']==='1'?'selected':'' ?>>Validated Only</option>
        </select>
    </div>
    <div class="col-12 col-md-12 d-flex gap-2 mt-1">
        <button type="submit" class="btn btn-anu btn-sm px-4">
            <i class="bi bi-funnel me-1"></i>Apply Filters
        </button>
        <a href="reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1"></i>Reset
        </a>
        <?php if (array_filter($raw, fn($v) => $v !== '' && $v !== date('Y-m-01') && $v !== date('Y-m-d'))): ?>
        <span class="badge bg-warning text-dark align-self-center">
            <i class="bi bi-funnel-fill me-1"></i>Filters active
        </span>
        <?php endif; ?>
    </div>
</div>
</form>
</div>

<!-- ── SUMMARY METRIC CARDS ─────────────────────────────── -->
<div class="row g-3 mb-4">
<?php
$metrics = [
    ['v' => $stats['total'],    'l' => 'Total Bookings', 'c' => '#ff0000',
     'pct' => $comparison['bookings_pct'],  'i' => 'bi-calendar-check-fill'],
    ['v' => $stats['consumed'], 'l' => 'Consumed',       'c' => '#0dcaf0',
     'pct' => null, 'i' => 'bi-check2-circle'],
    ['v' => $stats['approved'], 'l' => 'Approved',       'c' => '#28a745',
     'pct' => null, 'i' => 'bi-patch-check-fill'],
    ['v' => $stats['pending'],  'l' => 'Pending',        'c' => '#fac823',
     'pct' => null, 'i' => 'bi-hourglass-split'],
    ['v' => $stats['rejected'], 'l' => 'Rejected',       'c' => '#dc3545',
     'pct' => null, 'i' => 'bi-x-circle-fill'],
    ['v' => 'KES '.number_format($stats['revenue'],0), 'l' => 'Revenue',
     'c' => '#6f42c1', 'pct' => $comparison['revenue_pct'], 'i' => 'bi-cash-stack'],
];
foreach ($metrics as $m):
    $pct = $m['pct'];
    $pc  = $pct === null ? '' : ($pct > 0 ? 'pct-up' : ($pct < 0 ? 'pct-dn' : 'pct-flat'));
    $pi  = $pct !== null ? ($pct >= 0 ? '↑' : '↓') . abs($pct) . '% vs prev' : '';
?>
<div class="col-6 col-md-4 col-lg-2">
    <div class="metric-card" style="border-left-color:<?= $m['c'] ?>">
        <i class="bi <?= $m['i'] ?> ico" style="color:<?= $m['c'] ?>"></i>
        <div class="val" style="color:<?= $m['c'] ?>"><?= $m['v'] ?></div>
        <div class="lbl"><?= $m['l'] ?></div>
        <?php if ($pi): ?>
        <div class="pct-pill <?= $pc ?>"><?= $pi ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── RATE INDICATORS ──────────────────────────────────── -->
<div class="panel mb-4">
    <div class="panel-title"><i class="bi bi-speedometer2"></i>Performance Rates — <?= htmlspecialchars($filters['from']) ?> to <?= htmlspecialchars($filters['to']) ?></div>
    <div class="row g-3">
    <?php
    $rates = [
        ['l'=>'Approval Rate',    'v'=>$stats['approval_rate'],    'c'=>'#28a745'],
        ['l'=>'Consumption Rate', 'v'=>$stats['consumption_rate'], 'c'=>'#0dcaf0'],
        ['l'=>'Rejection Rate',   'v'=>$stats['rejection_rate'],   'c'=>'#dc3545'],
        ['l'=>'Pending Rate',     'v'=>$stats['pending_rate'],     'c'=>'#fac823'],
    ];
    foreach ($rates as $r):
    ?>
    <div class="col-6 col-md-3">
        <div class="rate-row mb-1">
            <span style="font-size:12px;color:#666;"><?= $r['l'] ?></span>
            <span style="font-size:13px;font-weight:700;color:<?= $r['c'] ?>"><?= $r['v'] ?>%</span>
        </div>
        <div class="rate-track">
            <div class="rate-fill" style="width:<?= $r['v'] ?>%;background:<?= $r['c'] ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- ── TABS: Charts / Table / Audit ─────────────────────── -->
<ul class="nav nav-tabs mb-0" id="reportTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabCharts">
        <i class="bi bi-graph-up me-1"></i>Charts
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTable">
        <i class="bi bi-table me-1"></i>Booking Records
        <span class="badge bg-secondary ms-1" style="font-size:10px;"><?= number_format($paged['total']) ?></span>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAudit">
        <i class="bi bi-shield-check me-1"></i>Export Audit
    </button></li>
</ul>

<div class="tab-content">

<!-- ── CHARTS TAB ───────────────────────────────────────── -->
<div class="tab-pane fade show active" id="tabCharts">
<div class="panel" style="border-radius:0 14px 14px 14px;">

    <!-- Row 1: Daily trend + Meal type donut -->
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-graph-up-arrow"></i>Daily Bookings &amp; Revenue (14 Days)</div>
                <canvas id="chartDaily" height="85"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-pie-chart-fill"></i>Meal Type Distribution</div>
                <canvas id="chartType" height="160"></canvas>
            </div>
        </div>
    </div>

    <!-- Row 2: Peak hours + Weekly trend -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-clock-history"></i>Peak Booking Hours (30 Days)</div>
                <canvas id="chartHours" height="100"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-calendar-week"></i>Weekly Trend + Revenue (12 Weeks)</div>
                <canvas id="chartWeekly" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Row 3: Monthly trend + Top meals + Revenue by type -->
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-bar-chart-line"></i>Monthly Trend (6 Months)</div>
                <canvas id="chartMonthly" height="130"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-trophy-fill"></i>Top 5 Meals</div>
                <?php $max_b = max(1, (int)($top_meals[0]['bookings'] ?? 1)); ?>
                <?php foreach ($top_meals as $i => $meal): ?>
                <div style="padding:7px 0;border-bottom:1px solid #f9f9f9;">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($meal['name']) ?></span>
                        <span class="text-muted" style="font-size:12px;"><?= $meal['bookings'] ?> bks</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <div class="meal-bar flex-grow-1 me-2" style="width:<?= round($meal['bookings']/$max_b*100) ?>%"></div>
                        <span class="fw-bold" style="font-size:12px;color:#6f42c1;white-space:nowrap;">KES <?= number_format($meal['revenue'],0) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($top_meals)): ?><p class="text-muted small text-center py-3">No data</p><?php endif; ?>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="panel" style="box-shadow:none;border:1px solid #f0f0f0;">
                <div class="panel-title"><i class="bi bi-cash-stack"></i>Revenue by Type</div>
                <?php foreach ($rev_by_type as $r): ?>
                <div style="padding:10px 0;border-bottom:1px solid #f9f9f9;">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold small"><?= $r['type'] ?></span>
                        <span class="fw-bold" style="color:var(--red);font-size:13px;">KES <?= number_format($r['revenue'],0) ?></span>
                    </div>
                    <div class="text-muted" style="font-size:11px;"><?= $r['bookings'] ?> bookings</div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($rev_by_type)): ?><p class="text-muted small text-center py-3">No revenue data</p><?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /panel -->
</div><!-- /tab-pane charts -->

<!-- ── TABLE TAB ─────────────────────────────────────────── -->
<div class="tab-pane fade" id="tabTable">
<div class="panel" style="border-radius:0 14px 14px 14px;padding:0;overflow:hidden;">

    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
        <div>
            <span class="fw-bold">Booking Records</span>
            <span class="text-muted small ms-2">
                Showing <?= number_format(($page-1)*25+1) ?>–<?= number_format(min($page*25,$paged['total'])) ?>
                of <?= number_format($paged['total']) ?>
            </span>
        </div>
        <span class="text-muted small"><?= $paged['total_pages'] ?> page<?= $paged['total_pages']!==1?'s':'' ?></span>
    </div>

    <div class="table-responsive">
        <table class="table mb-0 report-table" id="dataTable">
            <thead>
                <tr>
                    <th>Code</th><th>Student</th><th>Department</th>
                    <th>Meal</th><th>Type</th><th>Price</th>
                    <th>Date</th><th>Status</th><th>Validated</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paged['rows'] as $b): ?>
            <tr>
                <td><code style="font-size:11px;color:var(--red);"><?= htmlspecialchars($b['code']) ?></code></td>
                <td>
                    <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($b['fullname']) ?></div>
                    <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($b['student_id'] ?? '') ?></div>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($b['department'] ?? '—') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($b['meal_name']) ?></td>
                <td><span class="menu-badge <?= strtolower($b['meal_type']) ?>"><?= htmlspecialchars($b['meal_type']) ?></span></td>
                <td class="fw-semibold" style="color:var(--red);font-size:13px;">KES <?= number_format($b['price'],0) ?></td>
                <td style="font-size:13px;"><?= date('d M Y', strtotime($b['date'])) ?></td>
                <td><span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                <td class="text-muted" style="font-size:12px;">
                    <?= $b['validated_at'] ? date('d M H:i', strtotime($b['validated_at'])) : '—' ?>
                    <?php if ($b['validator_name']): ?>
                    <div style="font-size:10px;"><?= htmlspecialchars($b['validator_name']) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($paged['rows'])): ?>
            <tr><td colspan="9" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>No records match these filters.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paged['total_pages'] > 1): ?>
    <div class="d-flex justify-content-center py-3 border-top">
        <nav>
        <ul class="pagination pagination-sm mb-0 flex-wrap">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= $page_qs($page-1) ?>">‹</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $paged['total_pages']; $i++):
                if ($i===1||$i===$paged['total_pages']||abs($i-$page)<=2): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="<?= $page_qs($i) ?>"><?= $i ?></a>
            </li>
            <?php elseif (abs($i-$page)===3): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; endfor; ?>
            <?php if ($page < $paged['total_pages']): ?>
            <li class="page-item"><a class="page-link" href="<?= $page_qs($page+1) ?>">›</a></li>
            <?php endif; ?>
        </ul>
        </nav>
    </div>
    <?php endif; ?>

</div>
</div><!-- /tab-pane table -->

<!-- ── AUDIT TAB ─────────────────────────────────────────── -->
<div class="tab-pane fade" id="tabAudit">
<div class="panel" style="border-radius:0 14px 14px 14px;">
    <div class="panel-title"><i class="bi bi-shield-lock-fill"></i>Export Audit Log</div>
    <p class="text-muted small mb-3">Every export action is logged here with the admin's identity, IP address, format, and filters used.</p>
    <?php
    $audit_result = $conn->query(
        "SELECT ra.*, u.fullname, u.username
         FROM reports_audit ra
         LEFT JOIN users u ON ra.user_id=u.id
         ORDER BY ra.created_at DESC LIMIT 50"
    );
    $audit_rows = $audit_result ? $audit_result->fetch_all(MYSQLI_ASSOC) : [];
    ?>
    <?php if (empty($audit_rows)): ?>
    <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        No export audit records yet. Run <code>reports_schema_patch.sql</code> first if this is unexpected.
    </div>
    <?php else: ?>
    <?php foreach ($audit_rows as $a): ?>
    <div class="audit-entry">
        <div class="d-flex justify-content-between flex-wrap gap-1">
            <div>
                <span class="badge bg-dark me-1"><?= strtoupper(htmlspecialchars($a['export_format'])) ?></span>
                <strong><?= htmlspecialchars($a['fullname'] ?? 'Unknown') ?></strong>
                <span class="text-muted"> (<?= htmlspecialchars($a['username'] ?? '') ?>)</span>
            </div>
            <span class="text-muted" style="font-size:11px;"><?= date('d M Y H:i:s', strtotime($a['created_at'])) ?></span>
        </div>
        <div class="text-muted mt-1" style="font-size:11px;">
            <i class="bi bi-geo me-1"></i><?= htmlspecialchars($a['ip_address']) ?>
            &nbsp;|&nbsp;
            <i class="bi bi-table me-1"></i><?= number_format($a['record_count']) ?> records
            &nbsp;|&nbsp;
            <span>Filters: <?= htmlspecialchars(substr($a['filters_json'] ?? '{}', 0, 120)) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</div><!-- /tab-pane audit -->

</div><!-- /tab-content -->
</div><!-- /p-4 -->
</div><!-- /main-content -->
</div><!-- /d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
const R = '#ff0000', G = '#fac823', B = '#1a1a1a', T = '#0dcaf0';
const gridColor = '#f0f0f0';

const chartDefs = {
    // Daily: dual-axis bar + line
    daily: {
        el: 'chartDaily', type: 'bar',
        data: {
            labels: <?= $daily_labels ?>,
            datasets: [
                { label:'Bookings', type:'bar', data:<?= $daily_bookings ?>,
                  backgroundColor:'rgba(255,0,0,.15)', borderColor:R, borderWidth:2,
                  borderRadius:5, yAxisID:'y' },
                { label:'Revenue (KES)', type:'line', data:<?= $daily_revenue ?>,
                  borderColor:G, backgroundColor:'rgba(250,200,35,.08)',
                  tension:.4, fill:true, pointRadius:4, pointBackgroundColor:G, yAxisID:'y1' }
            ]
        },
        options: {
            responsive:true, interaction:{mode:'index'},
            plugins:{ legend:{ labels:{boxWidth:12,font:{size:11}} } },
            scales:{
                y:  { beginAtZero:true, grid:{color:gridColor}, ticks:{stepSize:1} },
                y1: { position:'right', beginAtZero:true, grid:{display:false},
                      ticks:{ callback: v=>'KES '+v.toLocaleString() } }
            }
        }
    },
    // Meal type donut
    type: {
        el: 'chartType', type: 'doughnut',
        data: {
            labels: <?= $type_labels ?>,
            datasets:[{ data:<?= $type_bookings ?>,
                backgroundColor:['rgba(255,0,0,.8)','rgba(250,200,35,.8)','rgba(26,26,26,.8)'],
                borderWidth:0, hoverOffset:8 }]
        },
        options:{ responsive:true, cutout:'58%',
            plugins:{ legend:{ position:'bottom', labels:{boxWidth:10,font:{size:11}} } } }
    },
    // Peak hours
    hours: {
        el: 'chartHours', type: 'bar',
        data: {
            labels: <?= $hour_labels ?>,
            datasets:[{ label:'Bookings', data:<?= $hour_counts ?>,
                backgroundColor: (ctx) => {
                    const data = <?= $hour_counts ?>;
                    const max  = Math.max(...data);
                    return ctx.parsed.y === max ? R : 'rgba(255,0,0,.22)';
                },
                borderRadius:4 }]
        },
        options:{ responsive:true,
            plugins:{ legend:{display:false} },
            scales:{ y:{ beginAtZero:true,grid:{color:gridColor},ticks:{stepSize:1} } }
        }
    },
    // Weekly dual
    weekly: {
        el: 'chartWeekly', type: 'line',
        data: {
            labels: <?= $weekly_labels ?>,
            datasets:[
                { label:'Bookings', data:<?= $weekly_bookings ?>,
                  borderColor:R, backgroundColor:'rgba(255,0,0,.07)',
                  fill:true, tension:.4, pointRadius:4, pointBackgroundColor:R, yAxisID:'y' },
                { label:'Revenue', data:<?= $weekly_revenue ?>,
                  borderColor:G, backgroundColor:'rgba(250,200,35,.07)',
                  fill:true, tension:.4, pointRadius:4, pointBackgroundColor:G, yAxisID:'y1' }
            ]
        },
        options:{
            responsive:true, interaction:{mode:'index'},
            plugins:{ legend:{ labels:{boxWidth:12,font:{size:11}} } },
            scales:{
                y:  { beginAtZero:true, grid:{color:gridColor} },
                y1: { position:'right', beginAtZero:true, grid:{display:false},
                      ticks:{ callback:v=>'KES '+v.toLocaleString() } }
            }
        }
    },
    // Monthly
    monthly: {
        el: 'chartMonthly', type: 'bar',
        data: {
            labels: <?= $monthly_labels ?>,
            datasets:[
                { label:'Bookings', data:<?= $monthly_bks ?>,
                  backgroundColor:'rgba(255,0,0,.7)', borderRadius:5, yAxisID:'y' },
                { label:'Revenue', type:'line', data:<?= $monthly_rev ?>,
                  borderColor:G, tension:.4, pointRadius:5, pointBackgroundColor:G, yAxisID:'y1' }
            ]
        },
        options:{
            responsive:true, interaction:{mode:'index'},
            plugins:{ legend:{ labels:{boxWidth:12,font:{size:11}} } },
            scales:{
                y:  { beginAtZero:true, grid:{color:gridColor} },
                y1: { position:'right', beginAtZero:true, grid:{display:false},
                      ticks:{ callback:v=>'KES '+v.toLocaleString() } }
            }
        }
    }
};

// Render all charts
Object.values(chartDefs).forEach(def => {
    new Chart(document.getElementById(def.el), {
        type:    def.type,
        data:    def.data,
        options: { ...def.options, responsive:true }
    });
});

// ── Rate bar animation ──────────────────────────────────────
document.querySelectorAll('.rate-fill').forEach(el => {
    const w = el.style.width; el.style.width='0';
    setTimeout(() => el.style.width = w, 200);
});

// ── Export loading overlay ──────────────────────────────────
document.querySelectorAll('.export-link').forEach(a => {
    a.addEventListener('click', () => {
        document.getElementById('loadingOverlay').classList.add('show');
        setTimeout(() => document.getElementById('loadingOverlay').classList.remove('show'), 5000);
    });
});

// ── PDF Export (visible table rows only) ────────────────────
document.getElementById('exportPDF').addEventListener('click', () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l','mm','a4');

    doc.setFontSize(18); doc.setTextColor(255,0,0);
    doc.text('ANU Meal Booking — Analytics Report', 14, 16);

    doc.setFontSize(9); doc.setTextColor(100);
    doc.text([
        'Period: <?= htmlspecialchars($filters['from']) ?> to <?= htmlspecialchars($filters['to']) ?>',
        'Generated: ' + new Date().toLocaleString() + '   |   By: <?= addslashes(htmlspecialchars($_SESSION['fullname'] ?? 'Admin')) ?>'
    ], 14, 23);

    // Summary block
    doc.setFontSize(10); doc.setTextColor(0);
    doc.text(
        'Total: <?= $stats['total'] ?>  |  Consumed: <?= $stats['consumed'] ?>  |  Approved: <?= $stats['approved'] ?>  |  Revenue: KES <?= number_format($stats['revenue'],0) ?>  |  Approval: <?= $stats['approval_rate'] ?>%',
        14, 30
    );

    const rows = [];
    document.querySelectorAll('#dataTable tbody tr').forEach(tr => {
        const td = [...tr.querySelectorAll('td')];
        if (td.length > 1) rows.push([
            td[0].textContent.trim(),
            td[1].querySelector('.fw-semibold')?.textContent.trim() || td[1].textContent.trim(),
            td[2].textContent.trim(),
            td[3].textContent.trim(),
            td[4].textContent.trim(),
            td[5].textContent.trim(),
            td[6].textContent.trim(),
            td[7].textContent.trim(),
        ]);
    });

    doc.autoTable({
        head:[['Code','Student','Dept','Meal','Type','Price','Date','Status']],
        body: rows, startY:35,
        headStyles:{ fillColor:[26,26,26], textColor:255, fontSize:8 },
        bodyStyles:{ fontSize:8 },
        alternateRowStyles:{ fillColor:[250,250,250] },
        columnStyles:{ 0:{cellWidth:30}, 5:{halign:'right'} }
    });

    const finalY = doc.lastAutoTable.finalY + 6;
    doc.setFontSize(8); doc.setTextColor(150);
    doc.text(`Page ${doc.internal.getNumberOfPages()} — ANU Meal Booking System`, 14, finalY);
    doc.save('anu_report_<?= date('Y-m-d') ?>.pdf');
});
</script>
</body>
</html>
