<?php
// API: Create Event (Admin Only)
session_start();
header('Content-Type: application/json');
include '../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$event_name = trim($_POST['event_name'] ?? '');
$event_date = trim($_POST['event_date'] ?? '');
$event_type_id = intval($_POST['event_type_id'] ?? 1);
$description = trim($_POST['description'] ?? '');
$photo_limit = intval($_POST['photo_limit'] ?? 5);
$ppt_size_limit = intval($_POST['ppt_size_limit'] ?? 2);
$registration_limit = intval($_POST['registration_limit'] ?? 100);
$last_submission_date = trim($_POST['last_submission_date'] ?? '');
$result_date = trim($_POST['result_date'] ?? '');
$status = trim($_POST['status'] ?? 'open');

if (empty($event_name) || empty($event_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Event name and date are required']);
    exit;
}

// Validate event type
$stmt = $conn->prepare("SELECT id FROM event_types WHERE id = ?");
$stmt->bind_param("i", $event_type_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid event type']);
    exit;
}
$stmt->close();

// Insert event
$sql = "INSERT INTO events (event_name, event_date, event_type_id, description, photo_limit, ppt_size_limit, registration_limit, last_submission_date, result_date, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

$last_sub = !empty($last_submission_date) ? $last_submission_date : null;
$res_date = !empty($result_date) ? $result_date : null;

$stmt->bind_param("ssissiisss", $event_name, $event_date, $event_type_id, $description, $photo_limit, $ppt_size_limit, $registration_limit, $last_sub, $res_date, $status);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Event created successfully!',
        'event_id' => $stmt->insert_id
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create event: ' . $stmt->error]);
}
$stmt->close();
?>