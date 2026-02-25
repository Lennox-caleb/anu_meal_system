<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Summary stats
$total   = $conn->query("SELECT COUNT(*) c FROM bookings")->fetch_assoc()['c'];
$consumed= $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='consumed'")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='pending'")->fetch_assoc()['c'];
$approved= $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='approved'")->fetch_assoc()['c'];
$rejected= $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='rejected'")->fetch_assoc()['c'];

// Top meal
$top_meal_row = $conn->query("
    SELECT m.name, COUNT(b.id) as c FROM bookings b
    JOIN menus m ON b.menu_id = m.id
    GROUP BY m.id ORDER BY c DESC LIMIT 1
")->fetch_assoc();
$top_meal = $top_meal_row['name'] ?? '—';

// Chart: bookings per day (last 14 days)
$daily = $conn->query("
    SELECT DATE(b.created_at) as d, COUNT(*) c FROM bookings b
    WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(b.created_at) ORDER BY d ASC
");
$daily_labels = []; $daily_vals = [];
while ($r = $daily->fetch_assoc()) {
    $daily_labels[] = date('d M', strtotime($r['d']));
    $daily_vals[]   = (int)$r['c'];
}

// Chart: bookings by meal type
$meal_dist = $conn->query("SELECT m.type, COUNT(b.id) c FROM bookings b JOIN menus m ON b.menu_id=m.id GROUP BY m.type");
$ml = []; $mv = [];
while ($r = $meal_dist->fetch_assoc()) { $ml[] = $r['type']; $mv[] = (int)$r['c']; }

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $bookings_all = $conn->query("
        SELECT b.code, u.fullname, m.name as meal, m.type, b.date, b.status,
               v.fullname as validated_by, b.validated_at
        FROM bookings b
        JOIN users u ON b.user_id=u.id
        JOIN menus m ON b.menu_id=m.id
        LEFT JOIN users v ON b.validated_by=v.id
        ORDER BY b.created_at DESC
    ");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="anu_meal_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code','Student','Meal','Type','Date','Status','Validated By','Validated At']);
    while ($b = $bookings_all->fetch_assoc()) {
        fputcsv($out, [
            $b['code'], $b['fullname'], $b['meal'], $b['type'],
            date('d/m/Y', strtotime($b['date'])), $b['status'],
            $b['validated_by'] ?? '', $b['validated_at'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Table data with date filter
$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date   = $_GET['to']   ?? date('Y-m-d');
$bookings  = $conn->query("
    SELECT b.*, u.fullname, m.name as meal_name, m.type as meal_type
    FROM bookings b
    JOIN users u ON b.user_id=u.id
    JOIN menus m ON b.menu_id=m.id
    WHERE b.date BETWEEN '$from_date' AND '$to_date'
    ORDER BY b.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | ANU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content flex-grow-1">
        <div class="topbar d-flex justify-content-between align-items-center">
            <h1><i class="bi bi-bar-chart-line me-2"></i>Reports & Analytics</h1>
            <div class="d-flex gap-2">
                <a href="?export=csv&from=<?= $from_date ?>&to=<?= $to_date ?>" class="btn btn-sm" style="background:#fac823;font-weight:600;">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
                </a>
                <button class="btn btn-sm btn-danger" id="exportPDF">
                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
            </div>
        </div>

        <div class="p-4 fade-in-up">
            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center">
                        <div class="count"><?= $total ?></div>
                        <div class="label">Total</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center" style="border-color:#17a2b8;">
                        <div class="count" style="color:#17a2b8;"><?= $consumed ?></div>
                        <div class="label">Consumed</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center" style="border-color:#ffc107;">
                        <div class="count" style="color:#856404;"><?= $pending ?></div>
                        <div class="label">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center" style="border-color:#28a745;">
                        <div class="count" style="color:#28a745;"><?= $approved ?></div>
                        <div class="label">Approved</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center" style="border-color:#dc3545;">
                        <div class="count" style="color:#dc3545;"><?= $rejected ?></div>
                        <div class="label">Rejected</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="stat-card text-center" style="border-color:#6f42c1;">
                        <div class="count" style="color:#6f42c1;font-size:1.1em;"><?= htmlspecialchars($top_meal) ?></div>
                        <div class="label">Top Meal</div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="chart-card">
                        <h6 class="section-title">Bookings per Day (Last 14 Days)</h6>
                        <canvas id="dailyChart" height="80"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h6 class="section-title">By Meal Type</h6>
                        <canvas id="mealChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <!-- Date filter -->
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label class="form-label small fw-semibold">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= $from_date ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= $to_date ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-anu btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-card" id="reportTable">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="dataTable">
                        <thead>
                            <tr><th>Code</th><th>Student</th><th>Meal</th><th>Type</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($b = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($b['code']) ?></code></td>
                                <td><?= htmlspecialchars($b['fullname']) ?></td>
                                <td><?= htmlspecialchars($b['meal_name']) ?></td>
                                <td><span class="menu-badge <?= strtolower($b['meal_type']) ?>"><?= $b['meal_type'] ?></span></td>
                                <td><?= date('d M Y', strtotime($b['date'])) ?></td>
                                <td><span class="status-badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
new Chart(document.getElementById('dailyChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($daily_labels) ?>,
        datasets: [{ label:'Bookings', data: <?= json_encode($daily_vals) ?>,
            fill:true, borderColor:'rgba(255,0,0,0.8)', backgroundColor:'rgba(255,0,0,0.1)',
            tension:0.4, pointRadius:5, pointBackgroundColor:'#ff0000' }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

new Chart(document.getElementById('mealChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($ml) ?>,
        datasets: [{ data: <?= json_encode($mv) ?>,
            backgroundColor: ['#ff0000bb','#fac823bb','#000000bb','#28a745bb'] }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}} }
});

document.getElementById('exportPDF').addEventListener('click', () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(16);
    doc.setTextColor('#ff0000');
    doc.text('ANU Meal Booking Report', 14, 15);
    doc.setTextColor('#333');
    doc.setFontSize(10);
    doc.text('Generated: ' + new Date().toLocaleString(), 14, 22);

    const rows = [];
    document.querySelectorAll('#dataTable tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        rows.push([
            cells[0].textContent.trim(),
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim(),
            cells[4].textContent.trim(),
            cells[5].textContent.trim()
        ]);
    });

    doc.autoTable({
        head: [['Code','Student','Meal','Type','Date','Status']],
        body: rows,
        startY: 28,
        headStyles: { fillColor: [255, 0, 0] },
        styles: { fontSize: 9 }
    });
    doc.save('anu_meal_report_' + new Date().toISOString().slice(0,10) + '.pdf');
});
</script>
</body>
</html>
