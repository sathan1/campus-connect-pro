<?php
// index.php - Campus Connect Pro - Main Events Portal
session_start();
include 'config.php';

// Handle logout
if (isset($_GET['logout'])) {
  session_destroy();
  header("Location: index.php");
  exit;
}

// User session setup
$is_logged_in = isset($_SESSION['user_id']);
$user_name = "Guest";
$user_email = "";
$user_role = "user";
$user_id = 0;

if ($is_logged_in) {
  $user_id = $_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($row = $result->fetch_assoc()) {
    $user_name = htmlspecialchars($row['name']);
    $user_email = htmlspecialchars($row['email']);
    $user_role = $row['role'];
  }
  $stmt->close();
}
$is_admin = $user_role === 'admin';

// Auto-conclude past events: mark as finished and calculate results
$today = date('Y-m-d');

// Find events that are past date but not finished
$past_events_query = "SELECT e.id, e.event_name, et.name as type_name 
                      FROM events e 
                      LEFT JOIN event_types et ON e.event_type_id = et.id 
                      WHERE e.event_date < '$today' AND e.status != 'finished'";
$past_events = mysqli_query($conn, $past_events_query);

while ($event = mysqli_fetch_assoc($past_events)) {
    $event_id = $event['id'];
    
    // Check if results already exist
    $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM event_results WHERE event_id = $event_id"));
    
    if ($existing['c'] == 0) {
        // Calculate and insert results based on event type
        if ($event['type_name'] === 'quiz') {
            // Get top 3 quiz scores for this event
            $scores_query = "SELECT user_id, score FROM quiz_results 
                            WHERE event_id = $event_id 
                            ORDER BY score DESC, taken_at ASC 
                            LIMIT 3";
            $scores = mysqli_query($conn, $scores_query);
            $position = 1;
            while ($score = mysqli_fetch_assoc($scores)) {
                mysqli_query($conn, "INSERT INTO event_results (event_id, user_id, position, points) 
                                    VALUES ($event_id, {$score['user_id']}, $position, {$score['score']})");
                $position++;
            }
        }
        // For photography and presentation, admin manually selects winners
        // So we just mark as finished if past date, winners can be added later
    }
    
    // Mark event as finished
    mysqli_query($conn, "UPDATE events SET status = 'finished' WHERE id = $event_id");
}

// Get events with type info
$events_query = "SELECT e.*, et.name as type_name, et.icon,
                 (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as reg_count
                 FROM events e 
                 LEFT JOIN event_types et ON e.event_type_id = et.id 
                 WHERE e.status != 'draft'
                 ORDER BY e.event_date DESC";
$events_result = mysqli_query($conn, $events_query);
$events = [];
while ($row = mysqli_fetch_assoc($events_result)) {
  $events[] = $row;
}

// Get stats for hero section
$total_events = count($events);
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'] ?? 0;

// Get user's registrations
$user_registrations = [];
if ($is_logged_in) {
  $stmt = $conn->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $user_registrations[] = $row['event_id'];
  }
  $stmt->close();
}

// Get user's photo submissions count per event
$user_photos = [];
if ($is_logged_in) {
  $stmt = $conn->prepare("SELECT event_id, COUNT(*) as count FROM photo_submissions WHERE user_id = ? GROUP BY event_id");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $user_photos[$row['event_id']] = $row['count'];
  }
  $stmt->close();
}

// Get user's PPT submission
$user_ppts = [];
if ($is_logged_in) {
  $stmt = $conn->prepare("SELECT event_id, team_name, status FROM ppt_submissions WHERE user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $user_ppts[$row['event_id']] = $row;
  }
  $stmt->close();
}

// Get quizzes user has already taken
$user_quiz_taken = [];
if ($is_logged_in) {
  $stmt = $conn->prepare("SELECT event_id, score FROM quiz_results WHERE user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $user_quiz_taken[$row['event_id']] = $row['score'];
  }
  $stmt->close();
}

// Today's date for comparison
$today = date('Y-m-d');

// Get finished events with results
$results_query = "SELECT e.*, et.name as type_name, et.icon
                  FROM events e 
                  LEFT JOIN event_types et ON e.event_type_id = et.id 
                  WHERE e.status = 'finished'
                  ORDER BY e.result_date DESC, e.event_date DESC
                  LIMIT 10";
$results_result = mysqli_query($conn, $results_query);
$finished_events = [];
while ($row = mysqli_fetch_assoc($results_result)) {
  // Get winners
  $stmt = $conn->prepare("SELECT er.position, er.points, u.name as winner_name, u.id as winner_id
                            FROM event_results er 
                            JOIN users u ON er.user_id = u.id 
                            WHERE er.event_id = ? 
                            ORDER BY er.position");
  $stmt->bind_param("i", $row['id']);
  $stmt->execute();
  $winners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  // For photography, get winner photos
  if ($row['type_name'] === 'photography') {
    foreach ($winners as &$w) {
      $stmt = $conn->prepare("SELECT photo_path FROM photo_submissions WHERE event_id = ? AND user_id = ? LIMIT 1");
      $stmt->bind_param("ii", $row['id'], $w['winner_id']);
      $stmt->execute();
      $photo = $stmt->get_result()->fetch_assoc();
      $w['photo'] = $photo ? $photo['photo_path'] : null;
      $stmt->close();
    }
  }

  // For presentation, get team names
  if ($row['type_name'] === 'presentation') {
    foreach ($winners as &$w) {
      $stmt = $conn->prepare("SELECT team_name FROM ppt_submissions WHERE event_id = ? AND user_id = ?");
      $stmt->bind_param("ii", $row['id'], $w['winner_id']);
      $stmt->execute();
      $team = $stmt->get_result()->fetch_assoc();
      $w['team_name'] = $team ? $team['team_name'] : null;
      $stmt->close();
    }
  }

  $row['winners'] = $winners;
  $finished_events[] = $row;
}

// Stats
$total_events = count($events);
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Campus Connect Pro - Events & Quiz Platform</title>
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --primary-light: #eff6ff;
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

    /* Header */
    header {
      background: white;
      border-bottom: 1px solid var(--border);
      padding: 16px 24px;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .header-inner {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo {
      width: 40px;
      height: 40px;
      background: var(--primary);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 16px;
    }

    .brand-text h1 {
      font-size: 18px;
      font-weight: 600;
    }

    .brand-text p {
      font-size: 12px;
      color: var(--muted);
    }

    nav {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    nav a {
      padding: 8px 16px;
      text-decoration: none;
      color: var(--muted);
      font-size: 14px;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.2s;
    }

    nav a:hover {
      background: var(--primary-light);
      color: var(--primary);
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
      transition: all 0.2s;
      text-decoration: none;
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

    .btn-success {
      background: var(--success);
      color: white;
    }

    .btn-warning {
      background: var(--warning);
      color: white;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 13px;
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Main Container */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px;
    }

    /* Hero */
    .hero {
      background: linear-gradient(135deg, var(--primary), #7c3aed);
      color: white;
      padding: 48px 24px;
      border-radius: 16px;
      margin-bottom: 32px;
      text-align: center;
    }

    .hero h2 {
      font-size: 32px;
      margin-bottom: 12px;
    }

    .hero p {
      opacity: 0.9;
      margin-bottom: 24px;
    }

    .hero-stats {
      display: flex;
      justify-content: center;
      gap: 48px;
      margin-top: 24px;
    }

    .hero-stat {
      text-align: center;
    }

    .hero-stat .value {
      font-size: 36px;
      font-weight: 700;
    }

    .hero-stat .label {
      font-size: 14px;
      opacity: 0.8;
    }

    /* Tabs */
    .tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .tab {
      padding: 10px 20px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: white;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
    }

    .tab:hover {
      border-color: var(--primary);
    }

    .tab.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    /* Grid */
    .grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px;
    }

    @media (max-width: 900px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    /* Cards */
    .card {
      background: var(--card);
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
      margin-bottom: 16px;
    }

    .card h3 {
      font-size: 18px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Event Cards */
    .event-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
      transition: all 0.2s;
    }

    .event-card:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
    }

    .event-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 12px;
    }

    .event-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .event-meta {
      font-size: 13px;
      color: var(--muted);
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }

    .event-description {
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 16px;
    }

    .event-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .type-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 4px;
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

    .status-badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 500;
    }

    .status-open {
      background: #d1fae5;
      color: #065f46;
    }

    .status-ongoing {
      background: #cffafe;
      color: #0e7490;
    }

    .status-finished {
      background: #e5e7eb;
      color: #374151;
    }

    /* Sidebar */
    .sidebar {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .user-card {
      text-align: center;
      padding: 24px;
    }

    .user-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 600;
      margin: 0 auto 12px;
    }

    .user-name {
      font-weight: 600;
      margin-bottom: 4px;
    }

    .user-email {
      font-size: 13px;
      color: var(--muted);
    }

    .user-role {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      background: #d1fae5;
      color: #065f46;
      margin-top: 8px;
    }

    /* Results */
    .result-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 16px;
    }

    .result-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .result-title {
      font-weight: 600;
    }

    .winners-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .winner-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px;
      background: var(--bg);
      border-radius: 8px;
    }

    .winner-position {
      font-size: 20px;
    }

    .winner-info {
      flex: 1;
    }

    .winner-name {
      font-weight: 500;
    }

    .winner-points {
      font-size: 13px;
      color: var(--muted);
    }

    .winner-photo {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      object-fit: cover;
    }

    /* Modal */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .modal-backdrop.active {
      display: flex;
    }

    .modal {
      background: white;
      border-radius: 16px;
      padding: 24px;
      max-width: 500px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal h3 {
      margin-bottom: 20px;
    }

    .modal-close {
      float: right;
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: var(--muted);
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
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
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

    .upload-progress {
      height: 4px;
      background: var(--border);
      border-radius: 2px;
      margin-top: 8px;
      overflow: hidden;
    }

    .upload-progress-bar {
      height: 100%;
      background: var(--primary);
      width: 0%;
      transition: width 0.3s;
    }

    .photo-preview {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 8px;
    }

    .photo-preview img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
    }
  </style>
</head>

<body>
  <header>
    <div class="header-inner">
      <div class="brand">
        <div class="logo">CC</div>
        <div class="brand-text">
          <h1>Campus Connect Pro</h1>
          <p>Events • Quiz • Competitions</p>
        </div>
      </div>
      <nav>
        <a href="#events">Events</a>
        <a href="#results">Results</a>
        <?php if ($is_admin): ?>
          <a href="admin_panel.php" style="color: var(--danger);">Admin</a>
        <?php endif; ?>
        <?php if ($is_logged_in): ?>
          <span style="padding: 8px; color: var(--text); font-weight: 500;">Hi, <?php echo $user_name; ?></span>
          <a href="?logout=true">Logout</a>
        <?php else: ?>
          <button class="btn btn-primary" onclick="showAuthModal()">Sign In</button>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <div class="container">
    <!-- Hero -->
    <div class="hero">
      <h2>Welcome to Campus Connect Pro</h2>
      <p>Participate in quizzes, photography contests, and presentation competitions</p>
      <?php if (!$is_logged_in): ?>
        <button class="btn" style="background: white; color: var(--primary);" onclick="showAuthModal()">
          Get Started — Sign In
        </button>
      <?php endif; ?>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="value"><?php echo $total_events; ?></div>
          <div class="label">Total Events</div>
        </div>
        <div class="hero-stat">
          <div class="value"><?php echo $total_users; ?></div>
          <div class="label">Participants</div>
        </div>
      </div>
    </div>

    <div class="grid">
      <main>
        <!-- Events Section -->
        <section id="events">
          <h3 style="font-size: 22px; margin-bottom: 16px;">📅 Upcoming Events</h3>

          <!-- Filter Tabs -->
          <div class="tabs">
            <button class="tab active" onclick="filterEvents('all')">All Events</button>
            <button class="tab" onclick="filterEvents('quiz')">📝 Quiz</button>
            <button class="tab" onclick="filterEvents('photography')">📷 Photography</button>
            <button class="tab" onclick="filterEvents('presentation')">📊 Presentation</button>
          </div>

          <div id="eventsList">
            <?php foreach ($events as $event):
              $is_registered = in_array($event['id'], $user_registrations);
              $type = $event['type_name'] ?? 'quiz';
              $type_class = "type-$type";
              ?>
              <div class="event-card" data-type="<?php echo $type; ?>">
                <div class="event-header">
                  <div>
                    <span class="type-badge <?php echo $type_class; ?>">
                      <?php echo ucfirst($type); ?>
                    </span>
                    <span class="status-badge status-<?php echo $event['status']; ?>">
                      <?php echo strtoupper($event['status']); ?>
                    </span>
                  </div>
                </div>
                <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                <div class="event-meta">
                  <span>📅 <?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                  <span>👥 <?php echo $event['reg_count']; ?> registered</span>
                  <?php if ($event['last_submission_date']): ?>
                    <span>⏰ Deadline: <?php echo date('M d', strtotime($event['last_submission_date'])); ?></span>
                  <?php endif; ?>
                </div>

                <?php if ($event['description']): ?>
                  <div class="event-description">
                    <?php echo htmlspecialchars(substr($event['description'], 0, 150)); ?>
                    <?php if (strlen($event['description']) > 150): ?>...<?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="event-actions">
                  <?php 
                  // Date checks for this event
                  $event_date = $event['event_date'];
                  $is_past = strtotime($event_date) < strtotime($today);
                  $is_today = $event_date === $today;
                  $has_taken_quiz = isset($user_quiz_taken[$event['id']]);
                  ?>
                  
                  <?php if ($event['status'] === 'finished'): ?>
                    <button class="btn btn-outline btn-sm" onclick="viewResults(<?php echo $event['id']; ?>)">
                      🏆 View Results
                    </button>
                    
                  <?php elseif ($is_past): ?>
                    <!-- Event date has passed but not yet marked finished by admin -->
                    <span class="btn btn-outline btn-sm" style="cursor: default; background: #f3f4f6; color: #6b7280;">
                      ⏳ Event Ended - Awaiting Results
                    </span>
                    
                  <?php elseif (!$is_logged_in): ?>
                    <button class="btn btn-primary btn-sm" onclick="showAuthModal()">Sign In to Participate</button>
                    
                  <?php elseif ($type === 'quiz'): ?>
                    <?php if ($has_taken_quiz): ?>
                      <span class="btn btn-success btn-sm" style="cursor: default;">
                        ✓ Quiz Completed (Score: <?php echo $user_quiz_taken[$event['id']]; ?>)
                      </span>
                    <?php elseif ($is_registered && $is_today): ?>
                      <button class="btn btn-success btn-sm"
                        onclick="startQuiz(<?php echo $event['id']; ?>, '<?php echo addslashes($event['event_name']); ?>')">
                        ▶ Take Quiz
                      </button>
                    <?php elseif ($is_registered): ?>
                      <span class="btn btn-outline btn-sm" style="cursor: default;">
                        📅 Quiz on <?php echo date('M d', strtotime($event_date)); ?>
                      </span>
                    <?php else: ?>
                      <button class="btn btn-primary btn-sm" onclick="registerEvent(<?php echo $event['id']; ?>)">
                        Register for Quiz
                      </button>
                    <?php endif; ?>
                    
                  <?php elseif ($type === 'photography'): ?>
                    <?php
                    $photo_count = $user_photos[$event['id']] ?? 0;
                    $photo_limit = $event['photo_limit'] ?? 5;
                    $deadline = $event['last_submission_date'] ?? $event_date;
                    $can_upload = strtotime($deadline) >= strtotime($today);
                    ?>
                    <?php if (!$is_registered): ?>
                      <button class="btn btn-primary btn-sm" onclick="registerEvent(<?php echo $event['id']; ?>)">
                        Register
                      </button>
                    <?php elseif (!$can_upload): ?>
                      <span class="btn btn-outline btn-sm" style="cursor: default;">
                        ⏳ Submissions Closed (<?php echo $photo_count; ?> uploaded)
                      </span>
                    <?php elseif ($photo_count < $photo_limit): ?>
                      <button class="btn btn-warning btn-sm"
                        onclick="showPhotoUpload(<?php echo $event['id']; ?>, <?php echo $photo_limit - $photo_count; ?>)">
                        📷 Upload (<?php echo $photo_count; ?>/<?php echo $photo_limit; ?>)
                      </button>
                    <?php else: ?>
                      <span class="btn btn-success btn-sm" style="cursor: default;">✓ All Photos Uploaded</span>
                    <?php endif; ?>
                    
                  <?php elseif ($type === 'presentation'): ?>
                    <?php 
                    $deadline = $event['last_submission_date'] ?? $event_date;
                    $can_register = strtotime($deadline) >= strtotime($today);
                    ?>
                    <?php if (isset($user_ppts[$event['id']])): ?>
                      <span class="btn btn-success btn-sm" style="cursor: default;">
                        ✓ Team: <?php echo htmlspecialchars($user_ppts[$event['id']]['team_name']); ?>
                      </span>
                    <?php elseif (!$can_register): ?>
                      <span class="btn btn-outline btn-sm" style="cursor: default;">
                        ⏳ Registration Closed
                      </span>
                    <?php else: ?>
                      <button class="btn btn-primary btn-sm"
                        onclick="showPptUpload(<?php echo $event['id']; ?>, <?php echo $event['ppt_size_limit'] ?? 2; ?>)">
                        Register & Upload PPT
                      </button>
                    <?php endif; ?>
                  <?php endif; ?>

                  <button class="btn btn-outline btn-sm" onclick="showEventDetails(<?php echo $event['id']; ?>)">
                    View Details
                  </button>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($events)): ?>
              <div class="card" style="text-align: center; padding: 40px; color: var(--muted);">
                No events available at the moment.
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Results Section -->
        <section id="results" style="margin-top: 32px;">
          <h3 style="font-size: 22px; margin-bottom: 16px;">🏆 Recent Results</h3>

          <?php if (empty($finished_events)): ?>
            <div class="card" style="text-align: center; padding: 40px; color: var(--muted);">
              No results announced yet.
            </div>
          <?php else: ?>
            <?php foreach ($finished_events as $event): ?>
              <div class="result-card">
                <div class="result-header">
                  <div>
                    <span class="type-badge type-<?php echo $event['type_name']; ?>">
                      <?php echo ucfirst($event['type_name']); ?>
                    </span>
                    <span class="result-title"
                      style="margin-left: 8px;"><?php echo htmlspecialchars($event['event_name']); ?></span>
                  </div>
                  <div style="font-size: 13px; color: var(--muted);">
                    <?php echo date('M d, Y', strtotime($event['result_date'] ?? $event['event_date'])); ?>
                  </div>
                </div>

                <?php if (!empty($event['winners'])): ?>
                  <div class="winners-list">
                    <?php foreach ($event['winners'] as $w):
                      $emoji = $w['position'] == 1 ? '🥇' : ($w['position'] == 2 ? '🥈' : '🥉');
                      ?>
                      <div class="winner-row">
                        <span class="winner-position"><?php echo $emoji; ?></span>
                        <div class="winner-info">
                          <div class="winner-name">
                            <?php if (isset($w['team_name'])): ?>
                              <?php echo htmlspecialchars($w['team_name']); ?>
                              <span style="font-size: 12px; color: var(--muted);">
                                (<?php echo htmlspecialchars($w['winner_name']); ?>)
                              </span>
                            <?php else: ?>
                              <?php echo htmlspecialchars($w['winner_name']); ?>
                            <?php endif; ?>
                          </div>
                          <div class="winner-points"><?php echo $w['points']; ?> points</div>
                        </div>
                        <?php if (isset($w['photo']) && $w['photo']): ?>
                          <img src="<?php echo htmlspecialchars($w['photo']); ?>" alt="Winner photo" class="winner-photo">
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p style="color: var(--muted); font-size: 14px;">Winners not yet announced.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </main>

      <!-- Sidebar -->
      <aside class="sidebar">
        <?php if ($is_logged_in): ?>
          <div class="card user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <div class="user-name"><?php echo $user_name; ?></div>
            <div class="user-email"><?php echo $user_email; ?></div>
            <div class="user-role"><?php echo $user_role; ?></div>
          </div>

          <div class="card">
            <h3>Quick Links</h3>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <a href="my_registration.php" class="btn btn-outline" style="justify-content: center;">
                📋 My Registrations
              </a>
              <a href="certificate.php" class="btn btn-outline" style="justify-content: center;">
                🎓 Certificates
              </a>
              <?php if ($is_admin): ?>
                <a href="admin_panel.php" class="btn btn-primary" style="justify-content: center;">
                  ⚙️ Admin Panel
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="card" style="text-align: center;">
            <h3>Join Us!</h3>
            <p style="font-size: 14px; color: var(--muted); margin-bottom: 16px;">
              Sign in to participate in events and competitions.
            </p>
            <button class="btn btn-primary" onclick="showAuthModal()" style="width: 100%;">
              Sign In / Register
            </button>
          </div>
        <?php endif; ?>

        <!-- Leaderboard Preview -->
        <div class="card">
          <h3>🏆 Top Performers</h3>
          <div id="leaderboardPreview" style="font-size: 14px; color: var(--muted);">
            Loading...
          </div>
        </div>
      </aside>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal-backdrop" id="modalBackdrop" onclick="closeModal(event)">
    <div class="modal" id="modalContent" onclick="event.stopPropagation()">
      <!-- Modal content injected by JS -->
    </div>
  </div>

  <script>
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

    // Filter events
    function filterEvents(type) {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      event.target.classList.add('active');

      document.querySelectorAll('.event-card').forEach(card => {
        if (type === 'all' || card.dataset.type === type) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Modal functions
    function openModal(content) {
      document.getElementById('modalContent').innerHTML = content;
      document.getElementById('modalBackdrop').classList.add('active');
    }

    function closeModal(e) {
      if (e && e.target !== e.currentTarget) return;
      document.getElementById('modalBackdrop').classList.remove('active');
    }

    // Auth Modal
    function showAuthModal() {
      openModal(`
                <button class="modal-close" onclick="closeModal()">&times;</button>
                <h3>Sign In / Register</h3>
                <div id="authMessage"></div>
                
                <form id="loginForm" onsubmit="submitAuth(event, 'login')">
                    <p style="font-weight: 600; margin-bottom: 12px;">Sign In</p>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Sign In</button>
                </form>
                
                <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border);">
                
                <form id="registerForm" onsubmit="submitAuth(event, 'register')">
                    <p style="font-weight: 600; margin-bottom: 12px;">New User? Register</p>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-outline" style="width: 100%;">Register</button>
                </form>
            `);
    }

    async function submitAuth(e, action) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      formData.append('action', action);

      try {
        const res = await fetch('auth.php', { method: 'POST', body: formData });
        const data = await res.json();

        const msgDiv = document.getElementById('authMessage');
        if (data.status === 'success') {
          msgDiv.innerHTML = `<div class="message success">${data.message}</div>`;
          setTimeout(() => location.reload(), 1000);
        } else {
          msgDiv.innerHTML = `<div class="message error">${data.message}</div>`;
        }
      } catch (err) {
        console.error(err);
      }
    }

    // Register for event
    async function registerEvent(eventId) {
      if (!isLoggedIn) { showAuthModal(); return; }

      try {
        const formData = new FormData();
        formData.append('event_id', eventId);

        const res = await fetch('register_event.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
          alert(data.message);
          location.reload();
        } else {
          alert(data.message || 'Registration failed');
        }
      } catch (err) {
        console.error(err);
      }
    }

    // Photo upload modal
    function showPhotoUpload(eventId, remaining) {
      openModal(`
                <button class="modal-close" onclick="closeModal()">&times;</button>
                <h3>📷 Upload Photo</h3>
                <p style="font-size: 14px; color: var(--muted); margin-bottom: 16px;">
                    You can upload ${remaining} more photo(s) for this event.
                </p>
                <div id="uploadMessage"></div>
                
                <form id="photoUploadForm" onsubmit="uploadPhoto(event, ${eventId})">
                    <div class="form-group">
                        <label>Select Photo</label>
                        <input type="file" name="photo" accept="image/*" required>
                        <div class="photo-preview" id="photoPreview"></div>
                    </div>
                    <div class="form-group">
                        <label>Caption (optional)</label>
                        <input type="text" name="caption" placeholder="Describe your photo...">
                    </div>
                    <div class="upload-progress" id="uploadProgress" style="display: none;">
                        <div class="upload-progress-bar" id="uploadProgressBar"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                        Upload Photo
                    </button>
                </form>
            `);

      // Photo preview
      document.querySelector('input[name="photo"]').addEventListener('change', function (e) {
        const preview = document.getElementById('photoPreview');
        preview.innerHTML = '';
        if (e.target.files[0]) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(e.target.files[0]);
          preview.appendChild(img);
        }
      });
    }

    async function uploadPhoto(e, eventId) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      formData.append('event_id', eventId);

      const progressDiv = document.getElementById('uploadProgress');
      const progressBar = document.getElementById('uploadProgressBar');
      progressDiv.style.display = 'block';

      try {
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            progressBar.style.width = (e.loaded / e.total * 100) + '%';
          }
        };

        xhr.onload = () => {
          const data = JSON.parse(xhr.responseText);
          const msgDiv = document.getElementById('uploadMessage');

          if (data.status === 'success') {
            msgDiv.innerHTML = `<div class="message success">${data.message}</div>`;
            setTimeout(() => location.reload(), 1500);
          } else {
            msgDiv.innerHTML = `<div class="message error">${data.message}</div>`;
            progressDiv.style.display = 'none';
          }
        };

        xhr.open('POST', 'api/upload_photo.php');
        xhr.send(formData);
      } catch (err) {
        console.error(err);
      }
    }

    // PPT upload modal
    function showPptUpload(eventId, sizeLimit) {
      openModal(`
                <button class="modal-close" onclick="closeModal()">&times;</button>
                <h3>📊 Register for Presentation</h3>
                <p style="font-size: 14px; color: var(--muted); margin-bottom: 16px;">
                    Upload your presentation (max ${sizeLimit}MB). Only PPT, PPTX, or PDF files allowed.
                </p>
                <div id="uploadMessage"></div>
                
                <form id="pptUploadForm" onsubmit="uploadPpt(event, ${eventId})">
                    <div class="form-group">
                        <label>Team Name *</label>
                        <input type="text" name="team_name" required placeholder="Enter your team name">
                    </div>
                    <div class="form-group">
                        <label>Presentation File *</label>
                        <input type="file" name="ppt" accept=".ppt,.pptx,.pdf" required>
                    </div>
                    <div class="upload-progress" id="uploadProgress" style="display: none;">
                        <div class="upload-progress-bar" id="uploadProgressBar"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                        Register & Upload
                    </button>
                </form>
            `);
    }

    async function uploadPpt(e, eventId) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      formData.append('event_id', eventId);

      const progressDiv = document.getElementById('uploadProgress');
      const progressBar = document.getElementById('uploadProgressBar');
      progressDiv.style.display = 'block';

      try {
        const xhr = new XMLHttpRequest();
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            progressBar.style.width = (e.loaded / e.total * 100) + '%';
          }
        };

        xhr.onload = () => {
          const data = JSON.parse(xhr.responseText);
          const msgDiv = document.getElementById('uploadMessage');

          if (data.status === 'success') {
            msgDiv.innerHTML = `<div class="message success">${data.message}</div>`;
            setTimeout(() => location.reload(), 1500);
          } else {
            msgDiv.innerHTML = `<div class="message error">${data.message}</div>`;
            progressDiv.style.display = 'none';
          }
        };

        xhr.open('POST', 'api/upload_ppt.php');
        xhr.send(formData);
      } catch (err) {
        console.error(err);
      }
    }

    // Event details modal
    async function showEventDetails(eventId) {
      try {
        const res = await fetch(`get_event_details.php?id=${eventId}`);
        const event = await res.json();

        if (event.error) {
          alert(event.error);
          return;
        }

        openModal(`
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                    <span class="type-badge type-${event.type_name || 'quiz'}">
                        ${(event.type_name || 'quiz').charAt(0).toUpperCase() + (event.type_name || 'quiz').slice(1)}
                    </span>
                    <h3 style="margin-top: 12px;">${event.event_name}</h3>
                    
                    <div style="margin: 16px 0; font-size: 14px; color: var(--muted);">
                        <p>📅 Date: ${event.event_date}</p>
                        ${event.last_submission_date ? `<p>⏰ Submission Deadline: ${event.last_submission_date}</p>` : ''}
                        ${event.result_date ? `<p>📢 Result Date: ${event.result_date}</p>` : ''}
                        ${event.type_name === 'presentation' && event.registration_limit ? `<p>👥 Max Teams: ${event.registration_limit}</p>` : ''}
                        ${event.type_name === 'photography' && event.photo_limit ? `<p>📷 Photo Limit: ${event.photo_limit} per person</p>` : ''}
                        ${event.type_name === 'presentation' && event.ppt_size_limit ? `<p>📁 Max PPT Size: ${event.ppt_size_limit} MB</p>` : ''}
                    </div>
                    
                    ${event.description ? `
                        <div style="background: var(--bg); padding: 16px; border-radius: 8px; font-size: 14px;">
                            <strong>Description:</strong><br>
                            ${event.description}
                        </div>
                    ` : ''}
                `);
      } catch (err) {
        console.error(err);
      }
    }

    // Start Quiz
    async function startQuiz(eventId, title) {
      try {
        const res = await fetch(`get_quiz.php?event_id=${eventId}`);
        const questions = await res.json();

        if (questions.error) {
          alert(questions.error);
          return;
        }

        if (questions.length === 0) {
          alert('No questions available for this quiz yet.');
          return;
        }

        // Render quiz
        let currentQ = 0;
        let answers = {};
        let timeLeft = questions.length * 30;

        function renderQuestion() {
          const q = questions[currentQ];
          openModal(`
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>${title}</h3>
                            <div style="font-size: 20px; font-weight: 700; color: var(--danger);" id="timer">${Math.floor(timeLeft / 60)}:${String(timeLeft % 60).padStart(2, '0')}</div>
                        </div>
                        
                        <div style="font-size: 13px; color: var(--muted); margin-bottom: 16px;">
                            Question ${currentQ + 1} of ${questions.length}
                        </div>
                        
                        <div style="font-size: 16px; font-weight: 500; margin-bottom: 20px;">
                            ${q.question_text}
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            ${['A', 'B', 'C', 'D'].map(opt => `
                                <div onclick="selectAnswer('${opt}')" 
                                     class="quiz-option" id="opt-${opt}"
                                     style="padding: 14px 18px; border: 2px solid ${answers[q.id] === opt ? 'var(--primary)' : 'var(--border)'}; 
                                            border-radius: 10px; cursor: pointer; transition: all 0.2s;
                                            background: ${answers[q.id] === opt ? 'var(--primary-light)' : 'white'};">
                                    <strong>${opt}.</strong> ${q['option_' + opt.toLowerCase()]}
                                </div>
                            `).join('')}
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                            <button class="btn btn-outline" ${currentQ === 0 ? 'disabled' : ''} onclick="prevQuestion()">← Previous</button>
                            ${currentQ === questions.length - 1 ?
              `<button class="btn btn-success" onclick="submitQuiz(${eventId})">Submit Quiz</button>` :
              `<button class="btn btn-primary" onclick="nextQuestion()">Next →</button>`
            }
                        </div>
                    `);
        }

        window.selectAnswer = function (opt) {
          answers[questions[currentQ].id] = opt;
          renderQuestion();
        };

        window.nextQuestion = function () {
          if (currentQ < questions.length - 1) {
            currentQ++;
            renderQuestion();
          }
        };

        window.prevQuestion = function () {
          if (currentQ > 0) {
            currentQ--;
            renderQuestion();
          }
        };

        window.submitQuiz = async function (eventId) {
          const formData = new FormData();
          formData.append('event_id', eventId);
          formData.append('answers', JSON.stringify(answers));

          const res = await fetch('submit_quiz.php', { method: 'POST', body: formData });
          const data = await res.json();

          if (data.status === 'success') {
            openModal(`
                            <div style="text-align: center; padding: 20px;">
                                <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
                                <h3>Quiz Completed!</h3>
                                <p style="font-size: 24px; font-weight: 700; color: var(--primary); margin: 16px 0;">
                                    Score: ${data.score}/${data.total}
                                </p>
                                <button class="btn btn-primary" onclick="closeModal(); location.reload();">
                                    Continue
                                </button>
                            </div>
                        `);
          } else {
            alert(data.message || 'Error submitting quiz');
          }
        };

        // Timer
        const timerInterval = setInterval(() => {
          timeLeft--;
          const timerEl = document.getElementById('timer');
          if (timerEl) {
            timerEl.textContent = `${Math.floor(timeLeft / 60)}:${String(timeLeft % 60).padStart(2, '0')}`;
            if (timeLeft <= 30) timerEl.style.color = 'var(--danger)';
          }
          if (timeLeft <= 0) {
            clearInterval(timerInterval);
            window.submitQuiz(eventId);
          }
        }, 1000);

        renderQuestion();

      } catch (err) {
        console.error(err);
        alert('Failed to load quiz');
      }
    }

    // Load leaderboard preview
    async function loadLeaderboard() {
      try {
        const res = await fetch('get_leaderboard.php');
        const data = await res.json();

        if (data.length === 0) {
          document.getElementById('leaderboardPreview').innerHTML = 'No scores yet.';
          return;
        }

        let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
        data.slice(0, 5).forEach((item, index) => {
          const emoji = index === 0 ? '🥇' : (index === 1 ? '🥈' : (index === 2 ? '🥉' : ''));
          html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: var(--bg); border-radius: 8px;">
                            <span>${emoji} ${item.name}</span>
                            <strong style="color: var(--primary);">${item.total_score} pts</strong>
                        </div>
                    `;
        });
        html += '</div>';

        document.getElementById('leaderboardPreview').innerHTML = html;
      } catch (err) {
        document.getElementById('leaderboardPreview').innerHTML = 'Failed to load';
      }
    }

    loadLeaderboard();
  </script>
</body>

</html>