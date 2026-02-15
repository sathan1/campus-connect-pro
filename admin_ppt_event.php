<?php
// Admin: PPT Presentation Event Conductor
session_start();
include 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>Admin privilege required.</p>");
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $team_id = intval($_POST['team_id'] ?? 0);

    switch ($action) {
        case 'start_event':
            $stmt = $conn->prepare("UPDATE events SET status = 'ongoing' WHERE id = ?");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $message = "Event started! Call teams to present.";
            break;

        case 'call_team':
            $stmt = $conn->prepare("UPDATE ppt_submissions SET status = 'called' WHERE id = ?");
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $message = "Team called for presentation.";
            break;

        case 'start_presenting':
            $stmt = $conn->prepare("UPDATE ppt_submissions SET status = 'presenting' WHERE id = ?");
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $message = "Team is now presenting.";
            break;

        case 'complete_team':
            $marks = intval($_POST['marks'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            $stmt = $conn->prepare("UPDATE ppt_submissions SET status = 'completed', marks = ?, notes = ? WHERE id = ?");
            $stmt->bind_param("isi", $marks, $notes, $team_id);
            $stmt->execute();
            $message = "Team marked as completed with $marks marks.";
            break;

        case 'not_attended':
            $stmt = $conn->prepare("UPDATE ppt_submissions SET status = 'not_attended' WHERE id = ?");
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $message = "Team marked as not attended.";
            break;

        case 'skipped':
            $stmt = $conn->prepare("UPDATE ppt_submissions SET status = 'skipped' WHERE id = ?");
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $message = "Team skipped. Can be recalled later.";
            break;

        case 'recall_team':
            $stmt = $conn->prepare("UPDATE ppt_submissions SET status = 'pending' WHERE id = ?");
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $message = "Team recalled to queue.";
            break;

        case 'finish_event':
            // Set winners based on marks
            $stmt = $conn->prepare("SELECT user_id, marks FROM ppt_submissions 
                                    WHERE event_id = ? AND status = 'completed' 
                                    ORDER BY marks DESC LIMIT 3");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $top_teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Clear existing results
            $conn->query("DELETE FROM event_results WHERE event_id = $event_id");

            // Insert top 3
            $position = 1;
            foreach ($top_teams as $team) {
                $points = $team['marks'];
                $stmt = $conn->prepare("INSERT INTO event_results (event_id, user_id, position, points) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiii", $event_id, $team['user_id'], $position, $points);
                $stmt->execute();
                $position++;
            }

            // Update event status
            $stmt = $conn->prepare("UPDATE events SET status = 'finished', result_date = CURDATE() WHERE id = ?");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();

            $message = "Event finished! Results announced based on marks.";
            break;
    }
}

// Get presentation events
$events_result = mysqli_query($conn, "SELECT e.id, e.event_name, e.event_date, e.status, e.registration_limit
                                       FROM events e 
                                       JOIN event_types et ON e.event_type_id = et.id 
                                       WHERE et.name = 'presentation'
                                       ORDER BY e.event_date DESC");
$events = [];
while ($row = mysqli_fetch_assoc($events_result)) {
    $events[] = $row;
}

// Get current event and teams
$event = null;
$teams = [];
$stats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'skipped' => 0];

if ($event_id) {
    $stmt = $conn->prepare("SELECT e.*, et.name as type_name FROM events e 
                            LEFT JOIN event_types et ON e.event_type_id = et.id 
                            WHERE e.id = ? AND et.name = 'presentation'");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event) {
        $stmt = $conn->prepare("SELECT ps.*, u.name as user_name, u.email 
                                FROM ppt_submissions ps 
                                JOIN users u ON ps.user_id = u.id 
                                WHERE ps.event_id = ? 
                                ORDER BY ps.presentation_order ASC");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Calculate stats
        $stats['total'] = count($teams);
        foreach ($teams as $t) {
            if ($t['status'] === 'completed')
                $stats['completed']++;
            elseif ($t['status'] === 'pending')
                $stats['pending']++;
            elseif (in_array($t['status'], ['skipped', 'not_attended']))
                $stats['skipped']++;
        }
    }
}

// Get results if finished
$winners = [];
if ($event && $event['status'] === 'finished') {
    $stmt = $conn->prepare("SELECT er.*, u.name as user_name, ps.team_name, ps.marks 
                            FROM event_results er 
                            JOIN users u ON er.user_id = u.id 
                            LEFT JOIN ppt_submissions ps ON ps.event_id = er.event_id AND ps.user_id = er.user_id
                            WHERE er.event_id = ? ORDER BY er.position");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $winners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PPT Presentation - Admin</title>
    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
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
        }

        .header {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 20px 30px;
        }

        .header h1 {
            font-size: 24px;
        }

        .header a {
            color: white;
            text-decoration: none;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
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

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        select,
        input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .stat-box {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-box .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-box .label {
            font-size: 13px;
            color: var(--muted);
        }

        .team-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .team-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid var(--border);
            transition: all 0.2s;
        }

        .team-item:hover {
            background: #f1f5f9;
        }

        .team-item.pending {
            border-left-color: var(--muted);
        }

        .team-item.called {
            border-left-color: var(--warning);
            background: #fffbeb;
        }

        .team-item.presenting {
            border-left-color: var(--info);
            background: #ecfeff;
            animation: pulse 2s infinite;
        }

        .team-item.completed {
            border-left-color: var(--success);
            background: #ecfdf5;
        }

        .team-item.skipped,
        .team-item.not_attended {
            border-left-color: var(--danger);
            background: #fef2f2;
            opacity: 0.7;
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(6, 182, 212, 0.4);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(6, 182, 212, 0);
            }
        }

        .team-info {
            flex: 1;
        }

        .team-info h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .team-info small {
            color: var(--muted);
        }

        .team-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .order-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            margin-right: 12px;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: #e5e7eb;
            color: #374151;
        }

        .status-called {
            background: #fef3c7;
            color: #92400e;
        }

        .status-presenting {
            background: #cffafe;
            color: #0e7490;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-skipped,
        .status-not_attended {
            background: #fee2e2;
            color: #991b1b;
        }

        .marks-input {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .marks-input input {
            width: 80px;
        }

        .winners-display {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .winner-card {
            padding: 20px;
            background: white;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .winner-card.gold {
            border-top: 4px solid gold;
        }

        .winner-card.silver {
            border-top: 4px solid silver;
        }

        .winner-card.bronze {
            border-top: 4px solid #cd7f32;
        }

        .winner-emoji {
            font-size: 40px;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <div class="header">
        <a href="admin_panel.php">← Back to Admin</a>
        <h1>📊 PPT Presentation Conductor</h1>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Event Selector -->
        <div class="card">
            <h3>Select Presentation Event</h3>
            <form method="GET" style="display: flex; gap: 12px; align-items: center; margin-top: 12px;">
                <select name="event_id" style="min-width: 300px;">
                    <option value="">-- Select Event --</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>" <?php echo $event_id == $ev['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ev['event_name']); ?> (
                            <?php echo $ev['event_date']; ?>)
                            [
                            <?php echo strtoupper($ev['status']); ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Load Event</button>
            </form>
        </div>

        <?php if ($event): ?>
            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="value">
                        <?php echo $stats['total']; ?>
                    </div>
                    <div class="label">Total Teams</div>
                </div>
                <div class="stat-box">
                    <div class="value" style="color: var(--success);">
                        <?php echo $stats['completed']; ?>
                    </div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-box">
                    <div class="value" style="color: var(--warning);">
                        <?php echo $stats['pending']; ?>
                    </div>
                    <div class="label">Pending</div>
                </div>
                <div class="stat-box">
                    <div class="value" style="color: var(--danger);">
                        <?php echo $stats['skipped']; ?>
                    </div>
                    <div class="label">Skipped</div>
                </div>
            </div>

            <!-- Event Controls -->
            <div class="card">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h3>
                            <?php echo htmlspecialchars($event['event_name']); ?>
                        </h3>
                        <p style="color: var(--muted); margin-top: 4px;">
                            Status: <strong>
                                <?php echo strtoupper($event['status']); ?>
                            </strong> |
                            Limit:
                            <?php echo $event['registration_limit']; ?> teams
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <?php if ($event['status'] === 'open'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="start_event">
                                <button type="submit" class="btn btn-success">▶ Start Event</button>
                            </form>
                        <?php elseif ($event['status'] === 'ongoing'): ?>
                            <form method="POST" onsubmit="return confirm('Finish event? This will calculate final rankings.');">
                                <input type="hidden" name="action" value="finish_event">
                                <button type="submit" class="btn btn-danger">✓ Finish Event</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($event['status'] === 'finished' && !empty($winners)): ?>
                <!-- Winners -->
                <div class="card">
                    <h3>🏆 Final Results</h3>
                    <div class="winners-display" style="margin-top: 16px;">
                        <?php foreach ($winners as $w):
                            $class = $w['position'] == 1 ? 'gold' : ($w['position'] == 2 ? 'silver' : 'bronze');
                            $emoji = $w['position'] == 1 ? '🥇' : ($w['position'] == 2 ? '🥈' : '🥉');
                            ?>
                            <div class="winner-card <?php echo $class; ?>">
                                <div class="winner-emoji">
                                    <?php echo $emoji; ?>
                                </div>
                                <h4>
                                    <?php echo htmlspecialchars($w['team_name']); ?>
                                </h4>
                                <p style="color: var(--muted); font-size: 14px;">
                                    <?php echo htmlspecialchars($w['user_name']); ?>
                                </p>
                                <p style="font-size: 24px; font-weight: 700; color: var(--primary); margin-top: 8px;">
                                    <?php echo $w['marks']; ?> pts
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Teams List -->
            <div class="card">
                <h3>📋 Team Queue</h3>

                <?php if (empty($teams)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 40px;">No teams registered yet.</p>
                <?php else: ?>
                    <div class="team-list" style="margin-top: 16px;">
                        <?php foreach ($teams as $team): ?>
                            <div class="team-item <?php echo $team['status']; ?>">
                                <div style="display: flex; align-items: center;">
                                    <div class="order-badge">
                                        <?php echo $team['presentation_order']; ?>
                                    </div>
                                    <div class="team-info">
                                        <h4>
                                            <?php echo htmlspecialchars($team['team_name']); ?>
                                            <span class="status-badge status-<?php echo $team['status']; ?>">
                                                <?php echo str_replace('_', ' ', $team['status']); ?>
                                            </span>
                                            <?php if ($team['marks'] > 0): ?>
                                                <span style="color: var(--primary); font-weight: 600; margin-left: 8px;">
                                                    <?php echo $team['marks']; ?> pts
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <small>
                                            <?php echo htmlspecialchars($team['user_name']); ?> •
                                            <?php echo htmlspecialchars($team['email']); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="team-actions">
                                    <?php if ($event['status'] === 'ongoing'): ?>
                                        <?php if ($team['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="call_team">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm">📢 Call</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($team['status'] === 'called'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="start_presenting">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" class="btn btn-info btn-sm">▶ Start</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="not_attended">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">✗ No Show</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($team['status'] === 'presenting'): ?>
                                            <form method="POST" class="marks-input">
                                                <input type="hidden" name="action" value="complete_team">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <input type="number" name="marks" placeholder="Marks" min="0" max="100" required>
                                                <button type="submit" class="btn btn-success btn-sm">✓ Complete</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="skipped">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" class="btn btn-outline btn-sm">Skip</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($team['status'], ['skipped', 'not_attended'])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="recall_team">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" class="btn btn-info btn-sm">↩ Recall</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Download PPT -->
                                    <a href="<?php echo htmlspecialchars($team['ppt_path']); ?>" download
                                        class="btn btn-outline btn-sm">
                                        ⬇ Download PPT
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>