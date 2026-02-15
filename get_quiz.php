<?php
include 'config.php';

header('Content-Type: application/json');

$event_id = (int) ($_GET['event_id'] ?? 0);

if ($event_id === 0) {
    echo json_encode(['error' => 'Event ID is required to fetch quiz questions.']);
    exit();
}

$stmt = $conn->prepare("SELECT id, question, option_a, option_b, option_c, option_d 
                        FROM quiz 
                        WHERE event_id = ?
                        ORDER BY id ASC");
if (!$stmt) {
    echo json_encode(['error' => 'Database error preparing statement.']);
    exit();
}

$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];

while ($row = $result->fetch_assoc()) {
    $questions[] = [
        'id' => $row['id'],
        'question_text' => htmlspecialchars($row['question']),
        'option_a' => htmlspecialchars($row['option_a']),
        'option_b' => htmlspecialchars($row['option_b']),
        'option_c' => htmlspecialchars($row['option_c']),
        'option_d' => htmlspecialchars($row['option_d'])
    ];
}
$stmt->close();

echo json_encode($questions);
?>