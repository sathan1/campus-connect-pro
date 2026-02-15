<?php
// Get Event Details API
header('Content-Type: application/json');
include 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['error' => 'Event ID required']);
    exit;
}

$stmt = $conn->prepare("SELECT e.*, et.name as type_name, et.icon 
                        FROM events e 
                        LEFT JOIN event_types et ON e.event_type_id = et.id 
                        WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['error' => 'Event not found']);
    exit;
}

echo json_encode($event);
?>