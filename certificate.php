<?php
// certificate.php - Certificate Download Page
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get all events user has registered for (finished events only for certificates)
$stmt = $conn->prepare("
    SELECT e.id, e.event_name, e.event_date, e.status, et.name as type_name
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE r.user_id = ? AND e.status = 'finished'
    ORDER BY e.event_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$participated_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user's placements (1st, 2nd, 3rd positions)
$stmt = $conn->prepare("
    SELECT er.event_id, er.position, er.points, e.event_name, e.event_date, et.name as type_name
    FROM event_results er
    JOIN events e ON er.event_id = e.id
    LEFT JOIN event_types et ON e.event_type_id = et.id
    WHERE er.user_id = ?
    ORDER BY er.position ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$placements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Create lookup for placements
$placement_lookup = [];
foreach ($placements as $p) {
    $placement_lookup[$p['event_id']] = $p;
}

// Handle certificate download
if (isset($_GET['download']) && isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    $cert_type = $_GET['type'] ?? 'participation'; // 'participation' or 'achievement'

    // Check if user participated
    $stmt = $conn->prepare("SELECT e.event_name, e.event_date, et.name as type_name 
                            FROM registrations r 
                            JOIN events e ON r.event_id = e.id 
                            LEFT JOIN event_types et ON e.event_type_id = et.id
                            WHERE r.user_id = ? AND r.event_id = ? AND e.status = 'finished'");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event) {
        $placement = $placement_lookup[$event_id] ?? null;

        // For achievement certificate, only allow if user got 1st place
        if ($cert_type === 'achievement' && (!$placement || $placement['position'] != 1)) {
            $cert_type = 'participation'; // Fallback to participation
        }

        generateCertificate($user['name'], $event['event_name'], $event['event_date'], $event['type_name'], $placement, $cert_type);
        exit;
    }
}

// Generate certificate as PDF-like HTML (downloadable)
function generateCertificate($name, $event_name, $event_date, $type_name, $placement = null, $cert_type = 'participation')
{
    $positions = [1 => '1st', 2 => '2nd', 3 => '3rd'];
    $position_ordinal = $placement ? ($positions[$placement['position']] ?? '') : '';

    // Determine certificate content based on type
    if ($cert_type === 'achievement' && $placement && $placement['position'] == 1) {
        $certificate_type = "CERTIFICATE OF ACHIEVEMENT";
        $award_line = "and secured <strong>1st Place</strong>";
    } else {
        $certificate_type = "CERTIFICATE OF PARTICIPATION";
        $award_line = "";  // Participation certificate - no position text
    }

    $event_type_display = ucfirst($type_name ?? 'event');

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="certificate_' . preg_replace('/[^a-z0-9]/i', '_', $event_name) . '.html"');

    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate - ' . htmlspecialchars($event_name) . '</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Open+Sans:wght@400;600&display=swap");
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Open Sans", sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .certificate {
            width: 900px;
            min-height: 600px;
            background: white;
            border: 8px solid #1e3a5f;
            padding: 50px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .certificate::before {
            content: "";
            position: absolute;
            top: 15px; left: 15px; right: 15px; bottom: 15px;
            border: 2px solid #c9a227;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .org-name {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            color: #1e3a5f;
            letter-spacing: 3px;
        }
        
        .certificate-type {
            font-family: "Playfair Display", serif;
            font-size: 32px;
            color: #c9a227;
            margin: 30px 0 20px;
            letter-spacing: 3px;
        }
        
        .subtitle {
            font-size: 15px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .recipient {
            font-family: "Playfair Display", serif;
            font-size: 40px;
            color: #1e3a5f;
            margin: 15px 0;
            padding: 8px 20px;
            border-bottom: 2px solid #c9a227;
            display: inline-block;
        }
        
        .award-text {
            font-size: 17px;
            color: #333;
            margin: 20px 0;
            line-height: 1.8;
        }
        
        .event-name {
            font-weight: 600;
            color: #2563eb;
            font-size: 20px;
        }
        
        .event-type {
            display: inline-block;
            padding: 4px 14px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 20px;
            font-size: 13px;
            margin: 8px 0;
        }
        
        .date {
            margin-top: 25px;
            font-size: 14px;
            color: #666;
        }
        
        .footer {
            display: flex;
            justify-content: space-around;
            margin-top: 50px;
            padding-top: 20px;
        }
        
        .signature {
            text-align: center;
            width: 180px;
        }
        
        .signature-line {
            border-top: 2px solid #333;
            padding-top: 8px;
            font-size: 12px;
            color: #666;
        }
        
        @media print {
            body { background: white; }
            .certificate { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="logo">CC</div>
            <div class="org-name">CAMPUS CONNECT PRO</div>
        </div>
        
        <div style="text-align: center;">
            <div class="certificate-type">' . $certificate_type . '</div>
            <div class="subtitle">This is to certify that</div>
            
            <div class="recipient">' . htmlspecialchars($name) . '</div>
            
            <div class="award-text">
                has successfully participated in the<br>
                <span class="event-type">' . $event_type_display . '</span><br>
                <span class="event-name">' . htmlspecialchars($event_name) . '</span>
                ' . ($award_line ? '<br>' . $award_line : '') . '
            </div>
            
            <p class="date">Dated: ' . date('F d, Y', strtotime($event_date)) . '</p>
        </div>
        
        <div class="footer">
            <div class="signature">
                <div class="signature-line">Event Coordinator</div>
            </div>
            <div class="signature">
                <div class="signature-line">Director</div>
            </div>
        </div>
    </div>
</body>
</html>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Certificates - Campus Connect Pro</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc;
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

        header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
        }

        .header-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 24px;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted);
            margin-bottom: 24px;
        }

        .filters {
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters label {
            font-size: 14px;
            font-weight: 500;
            color: var(--muted);
        }

        .filters select,
        .filters input {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        .filters select:focus,
        .filters input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--muted);
        }

        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .cert-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }

        .cert-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        .cert-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-quiz {
            background: #dbeafe;
            color: #1e40af;
        }

        .type-photography {
            background: #fef3c7;
            color: #92400e;
        }

        .type-presentation {
            background: #d1fae5;
            color: #065f46;
        }

        .position-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: linear-gradient(135deg, #c9a227, #d4af37);
            color: white;
        }

        .position-1 {
            background: linear-gradient(135deg, #ffd700, #ffb700);
            color: #7c5d00;
        }

        .position-2 {
            background: linear-gradient(135deg, #c0c0c0, #a0a0a0);
            color: #333;
        }

        .position-3 {
            background: linear-gradient(135deg, #cd7f32, #a0522d);
            color: white;
        }

        .cert-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .cert-meta {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 16px;
        }

        .cert-type {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .cert-achievement {
            background: #fef3c7;
            color: #92400e;
        }

        .cert-participation {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--bg);
        }

        .stats {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
        }
    </style>
</head>

<body>
    <header>
        <div class="header-inner">
            <a href="index.php" class="back-link">← Back to Events</a>
            <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
        </div>
    </header>

    <div class="container">
        <h1>🎓 My Certificates</h1>
        <p class="subtitle">Download certificates for events you've participated in</p>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($participated_events); ?></div>
                <div class="stat-label">Events Participated</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($placements); ?></div>
                <div class="stat-label">Achievements Won</div>
            </div>
        </div>

        <?php if (empty($participated_events)): ?>
            <div class="empty-state">
                <div class="icon">📜</div>
                <h3>No Certificates Available</h3>
                <p>Participate in events and wait for them to finish to get certificates.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top: 16px;">Browse Events</a>
            </div>
        <?php else: ?>
            <!-- Filters -->
            <div class="filters">
                <div>
                    <label>Filter by Type</label><br>
                    <select id="filterType" onchange="filterCertificates()">
                        <option value="all">All Types</option>
                        <option value="quiz">Quiz</option>
                        <option value="photography">Photography</option>
                        <option value="presentation">Presentation</option>
                    </select>
                </div>
                <div>
                    <label>Filter by Date</label><br>
                    <input type="date" id="filterDate" onchange="filterCertificates()">
                </div>
                <div>
                    <label>Search Event</label><br>
                    <input type="text" id="searchEvent" placeholder="Event name..." oninput="filterCertificates()">
                </div>
                <div>
                    <label>Show Only</label><br>
                    <select id="filterAchievement" onchange="filterCertificates()">
                        <option value="all">All Certificates</option>
                        <option value="achievement">Achievements Only</option>
                        <option value="participation">Participation Only</option>
                    </select>
                </div>
            </div>

            <!-- Certificates Grid -->
            <div class="certificates-grid" id="certificatesGrid">
                <?php foreach ($participated_events as $event):
                    $placement = $placement_lookup[$event['id']] ?? null;
                    $positions = [1 => '1st', 2 => '2nd', 3 => '3rd'];
                    $position_ordinal = $placement ? ($positions[$placement['position']] ?? '') : '';
                    ?>
                    <div class="cert-card" data-type="<?php echo $event['type_name']; ?>"
                        data-date="<?php echo $event['event_date']; ?>"
                        data-name="<?php echo strtolower($event['event_name']); ?>"
                        data-achievement="<?php echo ($placement && $placement['position'] == 1) ? 'achievement' : 'participation'; ?>">
                        <div class="cert-header">
                            <span class="type-badge type-<?php echo $event['type_name']; ?>">
                                <?php echo ucfirst($event['type_name']); ?>
                            </span>
                            <?php if ($placement): ?>
                                <span class="position-badge position-<?php echo $placement['position']; ?>">
                                    <?php echo $position_ordinal; ?> Place
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="cert-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                        <div class="cert-meta">
                            📅 <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px;">
                            <!-- Participation Certificate - ALWAYS available -->
                            <a href="certificate.php?download=1&event_id=<?php echo $event['id']; ?>&type=participation"
                                class="btn btn-outline" style="width: 100%; justify-content: center;">
                                📜 Download Participation Certificate
                            </a>

                            <?php if ($placement && $placement['position'] == 1): ?>
                                <!-- Achievement Certificate - Only for 1st place -->
                                <a href="certificate.php?download=1&event_id=<?php echo $event['id']; ?>&type=achievement"
                                    class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    🏆 Download Achievement Certificate
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="noResults" style="display: none;">
                <div class="empty-state">
                    <div class="icon">🔍</div>
                    <h3>No Matching Certificates</h3>
                    <p>Try adjusting your filters.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filterCertificates() {
            const type = document.getElementById('filterType').value;
            const date = document.getElementById('filterDate').value;
            const search = document.getElementById('searchEvent').value.toLowerCase();
            const achievement = document.getElementById('filterAchievement').value;

            const cards = document.querySelectorAll('.cert-card');
            let visibleCount = 0;

            cards.forEach(card => {
                let show = true;

                if (type !== 'all' && card.dataset.type !== type) show = false;
                if (date && card.dataset.date !== date) show = false;
                if (search && !card.dataset.name.includes(search)) show = false;
                if (achievement !== 'all' && card.dataset.achievement !== achievement) show = false;

                card.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('noResults').style.display = visibleCount === 0 ? 'block' : 'none';
        }
    </script>
</body>

</html>