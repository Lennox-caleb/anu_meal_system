<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

// Stats
$total_bookings = $conn->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'];
$total_menus    = $conn->query("SELECT COUNT(*) as c FROM menus")->fetch_assoc()['c'];
$total_students = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'];
$consumed       = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='consumed'")->fetch_assoc()['c'];
$pending        = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='pending'")->fetch_assoc()['c'];

// Bookings by day (last 7 days)
$chart_data = $conn->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM bookings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$chart_labels = [];
$chart_values = [];
while ($row = $chart_data->fetch_assoc()) {
    $chart_labels[] = date('D d', strtotime($row['day']));
    $chart_values[] = (int)$row['cnt'];
}

// Bookings by meal type
$meal_data = $conn->query("
    SELECT m.type, COUNT(b.id) as cnt
    FROM bookings b
    JOIN menus m ON b.menu_id = m.id
    GROUP BY m.type
");
$meal_labels = [];
$meal_values = [];
while ($row = $meal_data->fetch_assoc()) {
    $meal_labels[] = $row['type'];
    $meal_values[] = (int)$row['cnt'];
}

// Recent logs
$logs = $conn->query("
    SELECT l.*, u.username FROM system_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC LIMIT 15
");

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | ANU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="d-flex">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <!-- Topbar -->
        <div class="topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm d-md-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
                <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill text-bg-danger small"><?= date('D, d M Y') ?></span>
                <span class="text-muted small d-none d-md-inline">
                    Welcome, <strong><?= htmlspecialchars($user['fullname']) ?></strong>
                </span>
            </div>
        </div>

        <div class="p-4 fade-in-up">
            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card d-flex align-items-center gap-3">
                        <div class="icon-box"><i class="bi bi-calendar-check"></i></div>
                        <div>
                            <div class="count"><?= number_format($total_bookings) ?></div>
                            <div class="label">Total Bookings</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card d-flex align-items-center gap-3" style="border-color:#fac823;">
                        <div class="icon-box" style="background:rgba(250,200,35,0.1);color:#b8860b;">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div>
                            <div class="count" style="color:#b8860b;"><?= number_format($total_menus) ?></div>
                            <div class="label">Menu Items</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card d-flex align-items-center gap-3" style="border-color:#198754;">
                        <div class="icon-box" style="background:rgba(25,135,84,0.1);color:#198754;">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="count" style="color:#198754;"><?= number_format($total_students) ?></div>
                            <div class="label">Students</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card d-flex align-items-center gap-3" style="border-color:#0d6efd;">
                        <div class="icon-box" style="background:rgba(13,110,253,0.1);color:#0d6efd;">
                            <i class="bi bi-patch-check"></i>
                        </div>
                        <div>
                            <div class="count" style="color:#0d6efd;"><?= number_format($consumed) ?></div>
                            <div class="label">Meals Consumed</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="chart-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="section-title mb-0">Bookings – Last 7 Days</h6>
                        </div>
                        <canvas id="dailyChart" height="80"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-card h-100">
                        <h6 class="section-title">Bookings by Meal Type</h6>
                        <canvas id="mealTypeChart" height="140"></canvas>
                    </div>
                </div>
            </div>

            <!-- Logs -->
            <div class="chart-card">
                <h6 class="section-title">System Activity Logs</h6>
                <div style="max-height:240px;overflow-y:auto;">
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <div class="log-entry">
                        <span class="text-muted small"><?= date('d M H:i', strtotime($log['created_at'])) ?></span>
                        — <strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong>:
                        <?= htmlspecialchars($log['action']) ?>
                        <?php if ($log['details']): ?>
                            <span class="text-muted small">– <?= htmlspecialchars($log['details']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                    <?php if ($total_bookings == 0): ?>
                        <p class="text-muted small ps-2">No activity logs yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Daily bookings chart
const ctx1 = document.getElementById('dailyChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Bookings',
            data: <?= json_encode($chart_values) ?>,
            fill: true,
            borderColor: 'rgba(255,0,0,0.8)',
            backgroundColor: 'rgba(255,0,0,0.1)',
            tension: 0.4,
            pointBackgroundColor: '#ff0000',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Meal type chart
const ctx2 = document.getElementById('mealTypeChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($meal_labels) ?>,
        datasets: [{
            data: <?= json_encode($meal_values) ?>,
            backgroundColor: ['#ff0000cc','#fac823cc','#000000cc','#28a745cc']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } } }
    }
});

// Sidebar toggle (mobile)
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('show');
});
</script>
</body>
</html>
