<?php
/**
 * ReportService.php — ANU Meal Booking Reporting Engine
 *
 * Responsibilities:
 *   • Filter validation and safe dynamic WHERE clause construction
 *   • All analytical queries (prepared statements only — zero raw interpolation)
 *   • Pagination, period comparison, trend aggregation
 *
 * Architecture: Service layer (MVC "Model"). Called by reports.php (View/Controller).
 */
class ReportService
{
    private mysqli $conn;

    // Whitelists — ONLY these values are accepted in filters
    private const VALID_STATUSES  = ['pending', 'approved', 'rejected', 'consumed'];
    private const VALID_TYPES     = ['Breakfast', 'Lunch', 'Dinner'];

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    // ═══════════════════════════════════════════════════════════════
    // FILTER BUILDER — validates all inputs, returns safe bind params
    // ═══════════════════════════════════════════════════════════════
    public function buildFilters(array $raw): array
    {
        $where  = ['1=1'];
        $params = [];
        $types  = '';

        // Date range — validated via DateTime, not just regex
        $from = $this->sanitizeDate($raw['from'] ?? date('Y-m-01'));
        $to   = $this->sanitizeDate($raw['to']   ?? date('Y-m-d'));

        // Swap if inverted
        if ($from > $to) [$from, $to] = [$to, $from];

        $where[]  = 'b.date BETWEEN ? AND ?';
        $params[] = $from;
        $params[] = $to;
        $types   .= 'ss';

        // Status — whitelist only
        $status = $raw['status'] ?? '';
        if ($status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            $where[]  = 'b.status = ?';
            $params[] = $status;
            $types   .= 's';
        }

        // Meal type — whitelist only
        $meal_type = $raw['meal_type'] ?? '';
        if ($meal_type !== '' && in_array($meal_type, self::VALID_TYPES, true)) {
            $where[]  = 'm.type = ?';
            $params[] = $meal_type;
            $types   .= 's';
        }

        // Department — alphanumeric + spaces only
        $dept = trim($raw['department'] ?? '');
        if ($dept !== '') {
            $dept = preg_replace('/[^a-zA-Z0-9 \-]/', '', $dept);
            if ($dept !== '') {
                $where[]  = 'u.department = ?';
                $params[] = $dept;
                $types   .= 's';
            }
        }

        // Validated only
        if (!empty($raw['validated'])) {
            $where[] = 'b.validated_at IS NOT NULL';
        }

        return [
            'where'  => implode(' AND ', $where),
            'params' => $params,
            'types'  => $types,
            'from'   => $from,
            'to'     => $to,
            'raw'    => $raw,   // preserved for audit logging
        ];
    }

    private function sanitizeDate(?string $d): string
    {
        if (!$d) return date('Y-m-d');
        $dt = DateTime::createFromFormat('Y-m-d', trim($d));
        return ($dt && $dt->format('Y-m-d') === trim($d)) ? trim($d) : date('Y-m-d');
    }

    // ═══════════════════════════════════════════════════════════════
    // SUMMARY STATS — total, per-status, revenue, computed rates
    // ═══════════════════════════════════════════════════════════════
    public function getSummaryStats(array $f): array
    {
        $w = $f['where'];
        $p = $f['params'];
        $t = $f['types'];

        // Single query for all status counts + revenue
        $sql = "SELECT
                  COUNT(*)                                                    AS total,
                  SUM(b.status = 'consumed')                                 AS consumed,
                  SUM(b.status = 'pending')                                  AS pending,
                  SUM(b.status = 'approved')                                 AS approved,
                  SUM(b.status = 'rejected')                                 AS rejected,
                  COALESCE(SUM(CASE WHEN b.status IN ('approved','consumed')
                               THEN m.price ELSE 0 END), 0)                  AS revenue,
                  COALESCE(AVG(CASE WHEN b.status IN ('approved','consumed')
                               THEN m.price ELSE NULL END), 0)               AS avg_price
                FROM bookings b
                JOIN menus m ON b.menu_id = m.id
                JOIN users u ON b.user_id = u.id
                WHERE $w";

        $row = $this->runQuery($sql, $t, $p)->fetch_assoc();

        $total = max((int)$row['total'], 1); // avoid div/0
        return [
            'total'            => (int)$row['total'],
            'consumed'         => (int)$row['consumed'],
            'pending'          => (int)$row['pending'],
            'approved'         => (int)$row['approved'],
            'rejected'         => (int)$row['rejected'],
            'revenue'          => (float)$row['revenue'],
            'avg_price'        => (float)$row['avg_price'],
            'approval_rate'    => round(((int)$row['approved']  + (int)$row['consumed']) / $total * 100, 1),
            'consumption_rate' => round((int)$row['consumed']  / $total * 100, 1),
            'rejection_rate'   => round((int)$row['rejected']  / $total * 100, 1),
            'pending_rate'     => round((int)$row['pending']   / $total * 100, 1),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // PERIOD COMPARISON — current vs previous period, % change
    // ═══════════════════════════════════════════════════════════════
    public function getPeriodComparison(string $from, string $to): array
    {
        $days      = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
        $prev_to   = date('Y-m-d', strtotime($from) - 86400);
        $prev_from = date('Y-m-d', strtotime($prev_to) - ($days - 1) * 86400);

        $sql = "SELECT COUNT(*) c, COALESCE(SUM(m.price),0) rev
                FROM bookings b JOIN menus m ON b.menu_id=m.id
                WHERE b.date BETWEEN ? AND ?
                AND b.status IN ('approved','consumed')";

        $stmt_curr = $this->conn->prepare($sql);
        $stmt_curr->bind_param('ss', $from, $to);
        $stmt_curr->execute();
        $curr = $stmt_curr->get_result()->fetch_assoc();

        $stmt_prev = $this->conn->prepare($sql);
        $stmt_prev->bind_param('ss', $prev_from, $prev_to);
        $stmt_prev->execute();
        $prev = $stmt_prev->get_result()->fetch_assoc();

        $pct = fn($c, $p) => $p == 0 ? ($c > 0 ? 100.0 : 0.0) : round(($c - $p) / $p * 100, 1);

        return [
            'bookings_curr' => (int)$curr['c'],
            'bookings_prev' => (int)$prev['c'],
            'bookings_pct'  => $pct($curr['c'],  $prev['c']),
            'revenue_curr'  => (float)$curr['rev'],
            'revenue_prev'  => (float)$prev['rev'],
            'revenue_pct'   => $pct($curr['rev'], $prev['rev']),
            'prev_from'     => $prev_from,
            'prev_to'       => $prev_to,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // DAILY TREND — last N days, bookings + revenue
    // ═══════════════════════════════════════════════════════════════
    public function getDailyTrend(int $days = 14): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DATE(b.created_at) d,
                    COUNT(*) bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('approved','consumed') THEN m.price ELSE 0 END), 0) revenue
             FROM bookings b JOIN menus m ON b.menu_id=m.id
             WHERE b.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(b.created_at) ORDER BY d ASC"
        );
        $stmt->bind_param('i', $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ═══════════════════════════════════════════════════════════════
    // WEEKLY TREND — 12 weeks, with % growth
    // ═══════════════════════════════════════════════════════════════
    public function getWeeklyTrend(): array
    {
        $result = $this->conn->query(
            "SELECT YEARWEEK(b.date,1) wk,
                    MIN(b.date) week_start,
                    COUNT(*) bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('approved','consumed') THEN m.price ELSE 0 END), 0) revenue
             FROM bookings b JOIN menus m ON b.menu_id=m.id
             WHERE b.date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
             GROUP BY YEARWEEK(b.date,1) ORDER BY wk ASC"
        );
        if (!$result) return [];
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        for ($i = 1; $i < count($rows); $i++) {
            $prev = max((float)$rows[$i-1]['bookings'], 1);
            $rows[$i]['growth'] = round(((float)$rows[$i]['bookings'] - $prev) / $prev * 100, 1);
        }
        if (!empty($rows)) $rows[0]['growth'] = 0;
        return $rows;
    }

    // ═══════════════════════════════════════════════════════════════
    // MONTHLY TREND — 6 months, for forecast grouping
    // ═══════════════════════════════════════════════════════════════
    public function getMonthlyTrend(): array
    {
        $result = $this->conn->query(
            "SELECT DATE_FORMAT(b.date,'%Y-%m') month_key,
                    DATE_FORMAT(b.date,'%b %Y') month_label,
                    COUNT(*) bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('approved','consumed') THEN m.price ELSE 0 END),0) revenue
             FROM bookings b JOIN menus m ON b.menu_id=m.id
             WHERE b.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(b.date,'%Y-%m')
             ORDER BY month_key ASC"
        );
        if (!$result) return [];
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        for ($i = 1; $i < count($rows); $i++) {
            $prev = max((float)$rows[$i-1]['bookings'], 1);
            $rows[$i]['growth'] = round(((float)$rows[$i]['bookings'] - $prev) / $prev * 100, 1);
        }
        if (!empty($rows)) $rows[0]['growth'] = 0;
        return $rows;
    }

    // ═══════════════════════════════════════════════════════════════
    // PEAK BOOKING HOURS — last 30 days
    // ═══════════════════════════════════════════════════════════════
    public function getPeakHours(): array
    {
        // Fill all 24 hours with 0 first, then merge real data
        $hours = array_fill(0, 24, ['hr' => 0, 'cnt' => 0]);
        for ($i = 0; $i < 24; $i++) $hours[$i]['hr'] = $i;

        $result = $this->conn->query(
            "SELECT HOUR(created_at) hr, COUNT(*) cnt
             FROM bookings
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY HOUR(created_at)"
        );
        if (!$result) return $hours;
        while ($r = $result->fetch_assoc()) {
            $hours[(int)$r['hr']]['cnt'] = (int)$r['cnt'];
        }
        return $hours;
    }

    // ═══════════════════════════════════════════════════════════════
    // REVENUE BY MEAL TYPE
    // ═══════════════════════════════════════════════════════════════
    public function getRevenueByType(array $f): array
    {
        $sql = "SELECT m.type,
                       COUNT(b.id) bookings,
                       COALESCE(SUM(CASE WHEN b.status IN ('approved','consumed') THEN m.price ELSE 0 END),0) revenue
                FROM bookings b
                JOIN menus m ON b.menu_id=m.id
                JOIN users u ON b.user_id=u.id
                WHERE {$f['where']}
                GROUP BY m.type ORDER BY revenue DESC";
        return $this->runQuery($sql, $f['types'], $f['params'])->fetch_all(MYSQLI_ASSOC);
    }

    // ═══════════════════════════════════════════════════════════════
    // TOP MEALS
    // ═══════════════════════════════════════════════════════════════
    public function getTopMeals(int $limit = 5): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.name, m.type,
                    COUNT(b.id) bookings,
                    COALESCE(SUM(CASE WHEN b.status IN ('approved','consumed') THEN m.price ELSE 0 END),0) revenue
             FROM bookings b JOIN menus m ON b.menu_id=m.id
             GROUP BY m.id, m.name, m.type
             ORDER BY bookings DESC LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ═══════════════════════════════════════════════════════════════
    // PAGINATED TABLE — server-side, uses LIMIT/OFFSET
    // ═══════════════════════════════════════════════════════════════
    public function getBookingsPage(array $f, int $page = 1, int $per_page = 25): array
    {
        $page     = max(1, $page);
        $per_page = min(100, max(10, $per_page));
        $offset   = ($page - 1) * $per_page;

        // Count query
        $count_sql = "SELECT COUNT(*) c FROM bookings b
                      JOIN menus m ON b.menu_id=m.id
                      JOIN users u ON b.user_id=u.id
                      WHERE {$f['where']}";
        $total = (int)$this->runQuery($count_sql, $f['types'], $f['params'])->fetch_assoc()['c'];

        // Data query — add pagination params
        $data_sql = "SELECT b.code, u.fullname, u.student_id, u.department,
                            m.name meal_name, m.type meal_type, m.price,
                            b.date, b.status, b.created_at, b.validated_at,
                            v.fullname validator_name
                     FROM bookings b
                     JOIN menus m ON b.menu_id=m.id
                     JOIN users u ON b.user_id=u.id
                     LEFT JOIN users v ON b.validated_by=v.id
                     WHERE {$f['where']}
                     ORDER BY b.created_at DESC
                     LIMIT ? OFFSET ?";

        $all_params = array_merge($f['params'], [$per_page, $offset]);
        $all_types  = $f['types'] . 'ii';
        $rows = $this->runQuery($data_sql, $all_types, $all_params)->fetch_all(MYSQLI_ASSOC);

        return [
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int)ceil($total / $per_page),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // EXPORT RESULT — unbuffered for large datasets
    // ═══════════════════════════════════════════════════════════════
    public function getExportResult(array $f): mysqli_result
    {
        $sql = "SELECT b.code, u.fullname, u.student_id, u.department,
                       m.name meal_name, m.type meal_type, m.price,
                       b.date, b.status, b.created_at, b.validated_at,
                       v.fullname validator_name
                FROM bookings b
                JOIN menus m ON b.menu_id=m.id
                JOIN users u ON b.user_id=u.id
                LEFT JOIN users v ON b.validated_by=v.id
                WHERE {$f['where']}
                ORDER BY b.created_at DESC";
        return $this->runQuery($sql, $f['types'], $f['params']);
    }

    // ═══════════════════════════════════════════════════════════════
    // DEPARTMENTS LIST — for filter dropdown
    // ═══════════════════════════════════════════════════════════════
    public function getDepartments(): array
    {
        $result = $this->conn->query(
            "SELECT DISTINCT department FROM users
             WHERE department IS NOT NULL AND department != ''
             ORDER BY department ASC"
        );
        return array_column($result->fetch_all(MYSQLI_ASSOC), 'department');
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPER — run any prepared query generically
    // ═══════════════════════════════════════════════════════════════
    private function runQuery(string $sql, string $types, array $params): mysqli_result
    {
        if (empty($params)) {
            $result = $this->conn->query($sql);
            if (!$result) {
                throw new RuntimeException("Query failed: " . $this->conn->error . " | SQL: " . substr($sql, 0, 200));
            }
            return $result;
        }
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $this->conn->error . " | SQL: " . substr($sql, 0, 200));
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            throw new RuntimeException("get_result failed: " . $stmt->error);
        }
        return $result;
    }
}
