<?php
session_start();
include 'config.php';

// Admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>Admin privilege required.</p><p><a href='index.php'>Go Home</a></p>");
}

// Fetch events for dropdowns
$events_result = mysqli_query($conn, "SELECT e.id, e.event_name, e.event_date, e.status, et.name as type_name, et.icon
                                       FROM events e 
                                       LEFT JOIN event_types et ON e.event_type_id = et.id 
                                       ORDER BY e.event_date DESC");
$events = [];
while ($row = mysqli_fetch_assoc($events_result)) {
    $events[] = $row;
}

// Get event types
$types_result = mysqli_query($conn, "SELECT * FROM event_types");
$event_types = [];
while ($row = mysqli_fetch_assoc($types_result)) {
    $event_types[] = $row;
}

// Stats
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_events = count($events);
$total_registrations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM registrations"))['c'];
$today_registrations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM registrations WHERE DATE(registration_date) = CURDATE()"))['c'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Campus Connect Pro</title>
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
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 24px 30px;
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-card .icon {
            font-size: 24px;
            margin-bottom: 8px;
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

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 24px 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .action-card {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .action-card .icon {
            font-size: 32px;
        }

        .action-card h4 {
            margin-bottom: 4px;
        }

        .action-card p {
            font-size: 13px;
            color: var(--muted);
        }

        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .card h3 {
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 12px 24px;
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

        .btn-danger {
            background: var(--danger);
            color: white;
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

        .event-type-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .event-type-btn {
            padding: 12px 20px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .event-type-btn:hover {
            border-color: var(--primary);
        }

        .event-type-btn.active {
            border-color: var(--primary);
            background: #eff6ff;
        }

        .event-type-btn .icon {
            font-size: 24px;
            display: block;
            text-align: center;
            margin-bottom: 4px;
        }

        .conditional-fields {
            display: none;
        }

        .conditional-fields.show {
            display: block;
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-inner">
            <div>
                <a href="index.php">← Back to Site</a>
                <h1>Admin Dashboard</h1>
            </div>
            <div>
                <a href="index.php?logout=true">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div id="message"></div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="value"><?php echo $total_users; ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="icon">📅</div>
                <div class="value"><?php echo $total_events; ?></div>
                <div class="label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="icon">📝</div>
                <div class="value"><?php echo $total_registrations; ?></div>
                <div class="label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="icon">🔥</div>
                <div class="value"><?php echo $today_registrations; ?></div>
                <div class="label">Today's Registrations</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-title">🚀 Quick Actions</div>
        <div class="quick-actions">
            <a href="admin_registrations.php" class="action-card">
                <div class="icon">📋</div>
                <div>
                    <h4>View Registrations</h4>
                    <p>Filter by event, date, type</p>
                </div>
            </a>
            <a href="admin_quiz_summary.php" class="action-card">
                <div class="icon">📝</div>
                <div>
                    <h4>Quiz Manager</h4>
                    <p>Add questions, view results</p>
                </div>
            </a>
            <a href="admin_photo_event.php" class="action-card">
                <div class="icon">📷</div>
                <div>
                    <h4>Photography Events</h4>
                    <p>View photos, select winners</p>
                </div>
            </a>
            <a href="admin_ppt_event.php" class="action-card">
                <div class="icon">📊</div>
                <div>
                    <h4>Presentation Events</h4>
                    <p>Conduct PPT presentations</p>
                </div>
            </a>
        </div>

        <!-- Create Event Form -->
        <div class="section-title">➕ Create New Event</div>
        <div class="card">
            <h3>Event Details</h3>

            <!-- Event Type Selector -->
            <div class="event-type-selector">
                <?php foreach ($event_types as $type): ?>
                    <button type="button" class="event-type-btn <?php echo $type['id'] == 1 ? 'active' : ''; ?>"
                        data-type="<?php echo $type['id']; ?>" onclick="selectEventType(<?php echo $type['id']; ?>)">
                        <?php echo ucfirst($type['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <form id="createEventForm">
                <input type="hidden" name="event_type_id" id="event_type_id" value="1">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Event Name *</label>
                        <input type="text" name="event_name" required placeholder="Enter event name">
                    </div>
                    <div class="form-group">
                        <label>Event Date *</label>
                        <input type="date" name="event_date" required>
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description"
                            placeholder="Event description visible to participants..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Last Submission Date</label>
                        <input type="date" name="last_submission_date">
                    </div>
                    <div class="form-group">
                        <label>Result Announcement Date</label>
                        <input type="date" name="result_date">
                    </div>

                    <!-- Photography specific -->
                    <div class="conditional-fields" id="fields-photography">
                        <div class="form-group">
                            <label>Photo Limit per User</label>
                            <input type="number" name="photo_limit" value="5" min="1" max="20">
                        </div>
                    </div>

                    <!-- Presentation specific -->
                    <div class="conditional-fields" id="fields-presentation">
                        <div class="form-group">
                            <label>Max Teams (Registration Limit)</label>
                            <input type="number" name="registration_limit" value="50" min="1">
                        </div>
                        <div class="form-group">
                            <label>PPT Size Limit (MB)</label>
                            <input type="number" name="ppt_size_limit" value="2" min="1" max="10">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="open">Open (accepting registrations)</option>
                            <option value="draft">Draft (not visible)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Create Event</button>
            </form>
        </div>

        <!-- Recent Events -->
        <div class="section-title">📅 Recent Events</div>
        <div class="card" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg);">
                        <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--muted);">EVENT</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--muted);">TYPE</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--muted);">DATE</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; color: var(--muted);">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($events, 0, 10) as $event): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px; font-weight: 500;">
                                <?php echo htmlspecialchars($event['event_name']); ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php echo ucfirst($event['type_name'] ?? 'quiz'); ?>
                            </td>
                            <td style="padding: 12px;"><?php echo $event['event_date']; ?></td>
                            <td style="padding: 12px;">
                                <span
                                    style="padding: 4px 10px; border-radius: 20px; font-size: 12px; 
                                    background: <?php echo $event['status'] === 'open' ? '#d1fae5' : ($event['status'] === 'finished' ? '#e5e7eb' : '#fef3c7'); ?>;
                                    color: <?php echo $event['status'] === 'open' ? '#065f46' : ($event['status'] === 'finished' ? '#374151' : '#92400e'); ?>;">
                                    <?php echo strtoupper($event['status'] ?? 'open'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const typeNames = { 1: 'quiz', 2: 'photography', 3: 'presentation' };

        function selectEventType(typeId) {
            document.querySelectorAll('.event-type-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-type="${typeId}"]`).classList.add('active');
            document.getElementById('event_type_id').value = typeId;

            // Show/hide conditional fields
            document.querySelectorAll('.conditional-fields').forEach(el => el.classList.remove('show'));
            const typeName = typeNames[typeId];
            const fields = document.getElementById(`fields-${typeName}`);
            if (fields) fields.classList.add('show');
        }

        document.getElementById('createEventForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const res = await fetch('api/create_event.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                const msgDiv = document.getElementById('message');
                if (data.status === 'success') {
                    msgDiv.innerHTML = `<div class="message success">${data.message}</div>`;
                    this.reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msgDiv.innerHTML = `<div class="message error">${data.message}</div>`;
                }
            } catch (err) {
                console.error(err);
            }
        });
    </script>
</body>

</html>