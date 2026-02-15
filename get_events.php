<?php
// Start output buffering immediately to catch any stray output
ob_start();

include 'config.php';

// Check connection early for better error visibility
if (!$conn) {
    ob_end_clean(); // Clean buffer before outputting error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

$sql = "SELECT id, event_name, event_date, event_description FROM events ORDER BY event_date ASC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]);
    exit();
}

$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Basic category derivation (unchanged)
    $category = "General";
    if (strpos($row['event_name'], 'Hackathon') !== false || strpos($row['event_name'], 'Code') !== false) {
        $category = "Technical";
    } elseif (strpos($row['event_name'], 'Cultural') !== false || strpos($row['event_name'], 'Music') !== false) {
        $category = "Cultural";
    } elseif (strpos($row['event_name'], 'Workshop') !== false) {
        $category = "Workshop";
    }

    $events[] = [
        'id' => $row['id'],
        // Use html_entity_decode before json_encode to prevent double-encoding issues
        'title' => html_entity_decode($row['event_name']),
        'category' => $category,
        'date' => $row['event_date'],
        'desc' => html_entity_decode($row['event_description'])
    ];
}

// Clean the output buffer, preventing any accidental whitespace/errors from reaching the browser
ob_end_clean();
header('Content-Type: application/json');

// Final output
echo json_encode($events);
?>