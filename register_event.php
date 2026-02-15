<?php
// Register for Event API
session_start();
header('Content-Type: application/json');
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = intval($_POST['event_id'] ?? 0);

if (!$event_id) {
    echo json_encode(['status' => 'error', 'message' => 'Event ID required']);
    exit;
}

// Check if event exists and is open
$stmt = $conn->prepare("SELECT id, status, registration_limit FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['status' => 'error', 'message' => 'Event not found']);
    exit;
}

if ($event['status'] !== 'open') {
    echo json_encode(['status' => 'error', 'message' => 'Event registration is closed']);
    exit;
}

// Check if already registered
$stmt = $conn->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
$stmt->bind_param("ii", $user_id, $event_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'You are already registered for this event']);
    exit;
}
$stmt->close();

// Check registration limit
if ($event['registration_limit']) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM registrations WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];

    if ($count >= $event['registration_limit']) {
        echo json_encode(['status' => 'error', 'message' => 'Registration limit reached for this event']);
        exit;
    }
    $stmt->close();
}

// Register
$stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id, registration_date) VALUES (?, ?, NOW())");
$stmt->bind_param("ii", $user_id, $event_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Successfully registered for the event!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
}
$stmt->close();
?>