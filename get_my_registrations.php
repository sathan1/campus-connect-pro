<?php
// get_my_registrations.php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$registrations = [];
$completed = [];

// 1. Get Registrations
$stmt = $conn->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res1 = $stmt->get_result();
while ($row = $res1->fetch_assoc()) {
    $registrations[] = (int) $row['event_id'];
}
$stmt->close();

// 2. Get Completed Quizzes
// Check if table exists/has data first to be safe, but we assume it exists
$stmt2 = $conn->prepare("SELECT event_id FROM quiz_results WHERE user_id = ?");
if ($stmt2) {
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $completed[] = (int) $row['event_id'];
    }
    $stmt2->close();
}

echo json_encode(['registrations' => $registrations, 'completed' => $completed]);
?>