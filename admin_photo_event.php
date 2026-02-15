<?php
// Admin: Photography Event Management - Select Winners
session_start();
include 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>Admin privilege required.</p>");
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$message = '';
$error = '';

// Handle winner selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'set_winner') {
        $user_id = intval($_POST['user_id']);
        $position = intval($_POST['position']);
        $points = intval($_POST['points'] ?? 0);

        // Check if position already taken
        $stmt = $conn->prepare("SELECT id FROM event_results WHERE event_id = ? AND position = ?");
        $stmt->bind_param("ii", $event_id, $position);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // Update existing
            $stmt = $conn->prepare("UPDATE event_results SET user_id = ?, points = ? WHERE event_id = ? AND position = ?");
            $stmt->bind_param("iiii", $user_id, $points, $event_id, $position);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO event_results (event_id, user_id, position, points) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $event_id, $user_id, $position, $points);
        }

        if ($stmt->execute()) {
            $message = "Winner position #$position set successfully!";
        } else {
            $error = "Failed to set winner";
        }
        $stmt->close();
    }

    if ($_POST['action'] === 'finish_event') {
        $stmt = $conn->prepare("UPDATE events SET status = 'finished', result_date = CURDATE() WHERE id = ?");
        $stmt->bind_param("i", $event_id);
        if ($stmt->execute()) {
            $message = "Event marked as finished. Results are now visible to users!";
        }
        $stmt->close();
    }
}

// Get photography events
$events_result = mysqli_query($conn, "SELECT e.id, e.event_name, e.event_date, e.status 
                                       FROM events e 
                                       JOIN event_types et ON e.event_type_id = et.id 
                                       WHERE et.name = 'photography'
                                       ORDER BY e.event_date DESC");
$events = [];
while ($row = mysqli_fetch_assoc($events_result)) {
    $events[] = $row;
}

// Get current event details and submissions
$event = null;
$submissions = [];
$winners = [];

if ($event_id) {
    $stmt = $conn->prepare("SELECT e.*, et.name as type_name FROM events e 
                            LEFT JOIN event_types et ON e.event_type_id = et.id 
                            WHERE e.id = ? AND et.name = 'photography'");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event) {
        // Get all submissions grouped by user
        $stmt = $conn->prepare("SELECT ps.*, u.name as user_name, u.email 
                                FROM photo_submissions ps 
                                JOIN users u ON ps.user_id = u.id 
                                WHERE ps.event_id = ? 
                                ORDER BY u.name, ps.uploaded_at");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_submissions = [];
        while ($row = $result->fetch_assoc()) {
            $uid = $row['user_id'];
            if (!isset($user_submissions[$uid])) {
                $user_submissions[$uid] = [
                    'user_id' => $uid,
                    'user_name' => $row['user_name'],
                    'email' => $row['email'],
                    'photos' => []
                ];
            }
            $user_submissions[$uid]['photos'][] = $row;
        }
        $submissions = array_values($user_submissions);
        $stmt->close();

        // Get current winners
        $stmt = $conn->prepare("SELECT er.*, u.name as user_name FROM event_results er 
                                JOIN users u ON er.user_id = u.id 
                                WHERE er.event_id = ? ORDER BY er.position");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $winners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Photography Event - Admin</title>
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
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
            max-width: 1400px;
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

        .card h3 {
            margin-bottom: 16px;
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

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .participant-card {
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .participant-card:hover {
            border-color: var(--primary);
        }

        .participant-card.winner-1 {
            border-color: gold;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }

        .participant-card.winner-2 {
            border-color: silver;
        }

        .participant-card.winner-3 {
            border-color: #cd7f32;
        }

        .participant-header {
            padding: 16px;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .participant-header h4 {
            margin-bottom: 4px;
        }

        .participant-header small {
            color: var(--muted);
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 8px;
            padding: 12px;
        }

        .photo-grid img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .photo-grid img:hover {
            transform: scale(1.05);
        }

        .participant-actions {
            padding: 12px 16px;
            background: white;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .winners-display {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .winner-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .winner-badge.gold {
            border-left: 4px solid gold;
        }

        .winner-badge.silver {
            border-left: 4px solid silver;
        }

        .winner-badge.bronze {
            border-left: 4px solid #cd7f32;
        }

        .winner-position {
            font-size: 24px;
            font-weight: 700;
        }

        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .lightbox.active {
            display: flex;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 30px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="header">
        <a href="admin_panel.php">← Back to Admin</a>
        <h1>📷 Photography Event Management</h1>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Event Selector -->
        <div class="card">
            <h3>Select Photography Event</h3>
            <form method="GET" style="display: flex; gap: 12px; align-items: center;">
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
            <!-- Event Info -->
            <div class="card">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h3>
                            <?php echo htmlspecialchars($event['event_name']); ?>
                        </h3>
                        <p style="color: var(--muted);">
                            Date:
                            <?php echo $event['event_date']; ?> |
                            Status: <strong>
                                <?php echo strtoupper($event['status']); ?>
                            </strong> |
                            Participants: <strong>
                                <?php echo count($submissions); ?>
                            </strong>
                        </p>
                    </div>
                    <?php if ($event['status'] !== 'finished'): ?>
                        <form method="POST"
                            onsubmit="return confirm('Mark this event as finished? Results will be visible to users.');">
                            <input type="hidden" name="action" value="finish_event">
                            <button type="submit" class="btn btn-success">✓ Finish Event & Announce Results</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Current Winners -->
            <?php if (!empty($winners)): ?>
                <div class="card">
                    <h3>🏆 Current Winners</h3>
                    <div class="winners-display">
                        <?php foreach ($winners as $w):
                            $class = $w['position'] == 1 ? 'gold' : ($w['position'] == 2 ? 'silver' : 'bronze');
                            $emoji = $w['position'] == 1 ? '🥇' : ($w['position'] == 2 ? '🥈' : '🥉');
                            ?>
                            <div class="winner-badge <?php echo $class; ?>">
                                <span class="winner-position">
                                    <?php echo $emoji; ?>
                                </span>
                                <div>
                                    <strong>
                                        <?php echo htmlspecialchars($w['user_name']); ?>
                                    </strong>
                                    <div style="font-size: 13px; color: var(--muted);">
                                        Position #
                                        <?php echo $w['position']; ?> •
                                        <?php echo $w['points']; ?> points
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Submissions Gallery -->
            <div class="card">
                <h3>📸 Participant Submissions (
                    <?php echo count($submissions); ?> participants)
                </h3>

                <?php if (empty($submissions)): ?>
                    <p style="color: var(--muted); text-align: center; padding: 40px;">No photo submissions yet.</p>
                <?php else: ?>
                    <div class="gallery">
                        <?php foreach ($submissions as $sub):
                            $is_winner = array_filter($winners, fn($w) => $w['user_id'] == $sub['user_id']);
                            $winner_pos = $is_winner ? array_values($is_winner)[0]['position'] : 0;
                            $card_class = $winner_pos ? "winner-$winner_pos" : "";
                            ?>
                            <div class="participant-card <?php echo $card_class; ?>">
                                <div class="participant-header">
                                    <h4>
                                        <?php if ($winner_pos == 1): ?>🥇
                                        <?php elseif ($winner_pos == 2): ?>🥈
                                        <?php elseif ($winner_pos == 3): ?>🥉
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($sub['user_name']); ?>
                                    </h4>
                                    <small>
                                        <?php echo htmlspecialchars($sub['email']); ?> •
                                        <?php echo count($sub['photos']); ?> photos
                                    </small>
                                </div>
                                <div class="photo-grid">
                                    <?php foreach ($sub['photos'] as $photo): ?>
                                        <img src="<?php echo htmlspecialchars($photo['photo_path']); ?>"
                                            alt="<?php echo htmlspecialchars($photo['caption']); ?>" onclick="openLightbox(this.src)"
                                            title="<?php echo htmlspecialchars($photo['caption']); ?>">
                                    <?php endforeach; ?>
                                </div>
                                <div class="participant-actions">
                                    <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                        <input type="hidden" name="action" value="set_winner">
                                        <input type="hidden" name="user_id" value="<?php echo $sub['user_id']; ?>">
                                        <select name="position" style="padding: 6px 10px;">
                                            <option value="1">🥇 1st</option>
                                            <option value="2">🥈 2nd</option>
                                            <option value="3">🥉 3rd</option>
                                        </select>
                                        <input type="number" name="points" placeholder="Points" value="100"
                                            style="width: 80px; padding: 6px;">
                                        <button type="submit" class="btn btn-warning" style="padding: 6px 12px;">Set Winner</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img src="" id="lightboxImg">
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightbox').classList.add('active');
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLightbox(); });
    </script>
</body>

</html>