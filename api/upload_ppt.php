<?php
// API: Upload PPT for Presentation Event
session_start();
header('Content-Type: application/json');
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = intval($_POST['event_id'] ?? 0);
$team_name = trim($_POST['team_name'] ?? '');

if (!$event_id) {
    echo json_encode(['status' => 'error', 'message' => 'Event ID required']);
    exit;
}

if (empty($team_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Team name required']);
    exit;
}

// Check event exists and is presentation type
$stmt = $conn->prepare("SELECT e.*, et.name as type_name FROM events e 
                        LEFT JOIN event_types et ON e.event_type_id = et.id 
                        WHERE e.id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['status' => 'error', 'message' => 'Event not found']);
    exit;
}

if ($event['type_name'] !== 'presentation') {
    echo json_encode(['status' => 'error', 'message' => 'This is not a presentation event']);
    exit;
}

if ($event['status'] === 'finished' || $event['status'] === 'ongoing') {
    echo json_encode(['status' => 'error', 'message' => 'Event registration is closed']);
    exit;
}

// Check submission deadline
if ($event['last_submission_date'] && strtotime($event['last_submission_date']) < strtotime('today')) {
    echo json_encode(['status' => 'error', 'message' => 'Submission deadline has passed']);
    exit;
}

// Check registration limit
$reg_limit = $event['registration_limit'] ?? 100;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM ppt_submissions WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

if ($count >= $reg_limit) {
    echo json_encode(['status' => 'error', 'message' => "Registration limit ($reg_limit teams) reached"]);
    exit;
}

// Check if user already submitted
$stmt = $conn->prepare("SELECT id FROM ppt_submissions WHERE event_id = ? AND user_id = ?");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'You have already registered for this event']);
    exit;
}
$stmt->close();

// Handle file upload
if (!isset($_FILES['ppt']) || $_FILES['ppt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a PPT file to upload']);
    exit;
}

$file = $_FILES['ppt'];
$allowed_types = [
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/pdf'
];
$allowed_ext = ['ppt', 'pptx', 'pdf'];
$max_size = ($event['ppt_size_limit'] ?? 2) * 1024 * 1024;

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    echo json_encode(['status' => 'error', 'message' => 'Only PPT, PPTX, PDF files allowed']);
    exit;
}

if ($file['size'] > $max_size) {
    $limit_mb = $event['ppt_size_limit'] ?? 2;
    echo json_encode(['status' => 'error', 'message' => "File size must be under {$limit_mb}MB"]);
    exit;
}

// Create upload directory
$upload_dir = "../uploads/ppts/event_$event_id/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$safe_team = preg_replace('/[^a-zA-Z0-9]/', '_', $team_name);
$filename = "team_{$safe_team}_" . time() . ".$ext";
$filepath = $upload_dir . $filename;
$db_path = "uploads/ppts/event_$event_id/$filename";

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Get next presentation order
    $stmt = $conn->prepare("SELECT COALESCE(MAX(presentation_order), 0) + 1 as next_order FROM ppt_submissions WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $next_order = $stmt->get_result()->fetch_assoc()['next_order'];
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO ppt_submissions (event_id, user_id, team_name, ppt_path, presentation_order) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $event_id, $user_id, $team_name, $db_path, $next_order);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful! Your presentation order: #' . $next_order,
            'order' => $next_order
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
}
?>