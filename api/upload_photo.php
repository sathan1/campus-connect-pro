<?php
// API: Upload Photo for Photography Event
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

if (!$event_id) {
    echo json_encode(['status' => 'error', 'message' => 'Event ID required']);
    exit;
}

// Check event exists and is photography type
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

if ($event['type_name'] !== 'photography') {
    echo json_encode(['status' => 'error', 'message' => 'This is not a photography event']);
    exit;
}

// Check submission deadline
if ($event['last_submission_date'] && strtotime($event['last_submission_date']) < strtotime('today')) {
    echo json_encode(['status' => 'error', 'message' => 'Submission deadline has passed']);
    exit;
}

// Check photo limit
$photo_limit = $event['photo_limit'] ?? 5;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM photo_submissions WHERE event_id = ? AND user_id = ?");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

if ($count >= $photo_limit) {
    echo json_encode(['status' => 'error', 'message' => "You can only upload $photo_limit photos for this event"]);
    exit;
}

// Handle file upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a photo to upload']);
    exit;
}

$file = $_FILES['photo'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, GIF, WEBP files allowed']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['status' => 'error', 'message' => 'File size must be under 5MB']);
    exit;
}

// Create upload directory
$upload_dir = "../uploads/photos/event_$event_id/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = "user_{$user_id}_" . time() . "_" . uniqid() . ".$ext";
$filepath = $upload_dir . $filename;
$db_path = "uploads/photos/event_$event_id/$filename";

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $caption = trim($_POST['caption'] ?? '');
    $stmt = $conn->prepare("INSERT INTO photo_submissions (event_id, user_id, photo_path, caption) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $event_id, $user_id, $db_path, $caption);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Photo uploaded successfully!',
            'photo_id' => $stmt->insert_id,
            'photos_remaining' => $photo_limit - $count - 1
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
}
?>