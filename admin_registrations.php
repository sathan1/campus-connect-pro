<?php
// Admin: Comprehensive Event Registrations Report
session_start();
include 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>Admin privilege required.</p>");
}

// Get filters
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
$event_type = isset($_GET['event_type']) ? trim($_GET['event_type']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$today_only = isset($_GET['today']) && $_GET['today'] === '1';

// Fetch events for dropdown
$events = mysqli_query($conn, "SELECT e.id, e.event_name, e.event_date, et.name as type_name 
                               FROM events e 
                               LEFT JOIN event_types et ON e.event_type_id = et.id 
                               ORDER BY e.event_date DESC");
$events_list = [];
while ($row = mysqli_fetch_assoc($events)) {
    $events_list[] = $row;
}

// Build registration query
$where_clauses = [];
$params = [];
$types = "";

if ($event_id) {
    $where_clauses[] = "r.event_id = ?";
    $params[] = $event_id;
    $types .= "i";
}

if ($event_type) {
    $where_clauses[] = "et.name = ?";
    $params[] = $event_type;
    $types .= "s";
}

if ($today_only) {
    $where_clauses[] = "DATE(r.registration_date) = CURDATE()";
}

if ($date_filter) {
    $where_clauses[] = "DATE(r.registration_date) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query = "SELECT u.name as user_name, u.email as user_email, e.event_name, e.event_date, 
                 et.name as event_type, et.icon, r.registration_date, r.event_id
          FROM registrations r
          JOIN users u ON r.user_id = u.id
          JOIN events e ON r.event_id = e.id
          LEFT JOIN event_types et ON e.event_type_id = et.id
          $where_sql
          ORDER BY r.registration_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get stats
$total_regs = count($registrations);
$today_count_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM registrations WHERE DATE(registration_date) = CURDATE()");
$today_count = mysqli_fetch_assoc($today_count_result)['c'];

// Get registrations by date
$by_date_result = mysqli_query($conn, "SELECT DATE(registration_date) as reg_date, COUNT(*) as count 
                                        FROM registrations 
                                        GROUP BY DATE(registration_date) 
                                        ORDER BY reg_date DESC 
                                        LIMIT 7");
$by_date = [];
while ($row = mysqli_fetch_assoc($by_date_result)) {
    $by_date[] = $row;
}

// Get registrations by event
$by_event_result = mysqli_query($conn, "SELECT e.event_name, et.icon, COUNT(r.id) as count 
                                         FROM registrations r 
                                         JOIN events e ON r.event_id = e.id 
                                         LEFT JOIN event_types et ON e.event_type_id = et.id
                                         GROUP BY r.event_id 
                                         ORDER BY count DESC 
                                         LIMIT 10");
$by_event = [];
while ($row = mysqli_fetch_assoc($by_event_result)) {
    $by_event[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Registrations - Admin</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .header a {
            color: white;
            text-decoration: none;
            opacity: 0.9;
        }

        .header a:hover {
            opacity: 1;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-card .label {
            font-size: 14px;
            color: var(--muted);
            margin-top: 4px;
        }

        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .card h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters select,
        .filters input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .filters select:focus,
        .filters input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--bg);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--bg);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: var(--muted);
        }

        tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-quiz {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-photography {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-presentation {
            background: #d1fae5;
            color: #065f46;
        }

        .mini-chart {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mini-bar {
            background: var(--bg);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .mini-bar strong {
            color: var(--primary);
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
    </style>
</head>

<body>
    <div class="header">
        <div>
            <a href="admin_panel.php">← Back</a>
            <h1>Event Registrations</h1>
        </div>
        <div>
            <span style="opacity:0.8">Today: <?php echo date('M d, Y'); ?></span>
        </div>
    </div>

    <div class="container">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $total_regs; ?></div>
                <div class="label">Filtered Results</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $today_count; ?></div>
                <div class="label">Registered Today</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo count($events_list); ?></div>
                <div class="label">Total Events</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <h3>🔍 Filters</h3>
            <form method="GET" class="filters">
                <select name="event_id">
                    <option value="">All Events</option>
                    <?php foreach ($events_list as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>" <?php echo $event_id == $ev['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ev['event_name']); ?> (<?php echo $ev['event_date']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="event_type">
                    <option value="">All Types</option>
                    <option value="quiz" <?php echo $event_type === 'quiz' ? 'selected' : ''; ?>>📝 Quiz</option>
                    <option value="photography" <?php echo $event_type === 'photography' ? 'selected' : ''; ?>>📷
                        Photography</option>
                    <option value="presentation" <?php echo $event_type === 'presentation' ? 'selected' : ''; ?>>📊
                        Presentation</option>
                </select>

                <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                    placeholder="Filter by date">

                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="checkbox" name="today" value="1" <?php echo $today_only ? 'checked' : ''; ?>>
                    Today Only
                </label>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="admin_registrations.php" class="btn btn-outline">Clear</a>
            </form>
        </div>

        <!-- Charts Row -->
        <div class="two-col">
            <div class="card">
                <h3>📅 Registrations by Date (Last 7 Days)</h3>
                <div class="mini-chart">
                    <?php foreach ($by_date as $d): ?>
                        <div class="mini-bar">
                            <?php echo date('M d', strtotime($d['reg_date'])); ?>:
                            <strong><?php echo $d['count']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($by_date)): ?>
                        <span style="color:var(--muted)">No data</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h3>🏆 Top Events by Registrations</h3>
                <div class="mini-chart">
                    <?php foreach ($by_event as $ev): ?>
                        <div class="mini-bar">
                            <?php echo $ev['icon'] ?? '📅'; ?>     <?php echo htmlspecialchars($ev['event_name']); ?>:
                            <strong><?php echo $ev['count']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($by_event)): ?>
                        <span style="color:var(--muted)">No data</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Registrations Table -->
        <div class="card">
            <h3>📋 Registration Records</h3>
            <?php if (empty($registrations)): ?>
                <div class="empty-state">
                    <p>No registrations found for the selected filters.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Event</th>
                                <th>Type</th>
                                <th>Event Date</th>
                                <th>Registered On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($reg['user_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg['user_email']); ?></td>
                                    <td><?php echo htmlspecialchars($reg['event_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $reg['event_type'] ?? 'quiz'; ?>">
                                            <?php echo $reg['icon'] ?? '📅'; ?>
                                            <?php echo ucfirst($reg['event_type'] ?? 'quiz'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $reg['event_date']; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($reg['registration_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>