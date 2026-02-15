<?php
session_start();
include 'config.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = "Please log in to submit the quiz.";
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = (int) ($_POST['event_id'] ?? 0);
$answers = json_decode($_POST['answers'] ?? '{}', true);

if ($event_id === 0) {
    $response['message'] = "Event ID is missing from submission.";
    echo json_encode($response);
    exit();
}
if (empty($answers) || !is_array($answers)) {
    $response['message'] = "Invalid or empty answers submitted.";
    echo json_encode($response);
    exit();
}

// 1. Check if the event date is today (Basic Time Constraint)
$stmt_event = $conn->prepare("SELECT event_date FROM events WHERE id = ?");
$stmt_event->bind_param("i", $event_id);
$stmt_event->execute();
$event_result = $stmt_event->get_result();
$event_row = $event_result->fetch_assoc();
$stmt_event->close();

if ($event_row && $event_row['event_date'] !== date('Y-m-d')) {
    $response['message'] = "Quiz is only available on the event date (" . $event_row['event_date'] . ").";
    echo json_encode($response);
    exit();
}


// 2. Fetch correct answers for submitted IDs linked to the event
$question_ids = array_keys($answers);
$placeholders = implode(',', array_fill(0, count($question_ids), '?'));
$types = str_repeat('i', count($question_ids)); // IDs are integers

// Use event_id in the WHERE clause for security and filtering
$sql = "SELECT id, answer FROM quiz WHERE event_id = ? AND id IN ($placeholders)";

// Correct type definition: 'i' for event_id, then more 'i's for question IDs
$types = 'i' . str_repeat('i', count($question_ids));

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $response['message'] = "Database error preparing quiz data.";
    echo json_encode($response);
    exit();
}

// Params array: [types, event_id, ...question_ids]
$bind_params = [];
$bind_params[] = $types;
$bind_params[] = $event_id;
foreach ($question_ids as $qid) {
    $bind_params[] = $qid;
}

// Call bind_param dynamically
$stmt->bind_param(...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

$correct_answers = [];
while ($row = $result->fetch_assoc()) {
    $correct_answers[$row['id']] = strtoupper($row['answer']);
}
$stmt->close();

// 3. Calculate score
$score = 0;
foreach ($answers as $qid => $selected_ans) {
    if (isset($correct_answers[$qid]) && $correct_answers[$qid] === strtoupper(trim($selected_ans))) {
        $score++;
    }
}

// 1.5. Check if already submitted for this event
$checkSub = $conn->prepare("SELECT id FROM quiz_results WHERE user_id = ? AND event_id = ?");
$checkSub->bind_param("ii", $user_id, $event_id);
$checkSub->execute();
$checkSub->store_result();
if ($checkSub->num_rows > 0) {
    $response['message'] = "You have already taken the quiz for this event.";
    echo json_encode($response);
    exit();
}
$checkSub->close();

// ... existing code ...

// 4. Store score
$stmt2 = $conn->prepare("INSERT INTO quiz_results (user_id, event_id, score) VALUES (?, ?, ?)");
if ($stmt2) {
    $stmt2->bind_param("iii", $user_id, $event_id, $score);
    if ($stmt2->execute()) {
        $response = [
            'status' => 'success',
            'message' => "Quiz submitted successfully!",
            'score' => $score,
            'total' => count($correct_answers)
        ];
    } else {
        $response['message'] = "Error storing score: " . $stmt2->error;
    }
    $stmt2->close();
} else {
    $response['message'] = "Database error storing score.";
}

echo json_encode($response);
?>