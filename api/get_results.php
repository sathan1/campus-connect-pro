<?php
// API: Get Results for Events
header('Content-Type: application/json');
include '../config.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
$event_type = isset($_GET['type']) ? trim($_GET['type']) : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : 'finished';

$results = [];

// Build query based on filters
$where = ["e.status = 'finished'"];
$params = [];
$types = "";

if ($event_id) {
    $where[] = "e.id = ?";
    $params[] = $event_id;
    $types .= "i";
}

if ($event_type) {
    $where[] = "et.name = ?";
    $params[] = $event_type;
    $types .= "s";
}

$where_sql = implode(" AND ", $where);

$sql = "SELECT e.id, e.event_name, e.event_date, e.result_date, et.name as event_type, et.icon
        FROM events e
        LEFT JOIN event_types et ON e.event_type_id = et.id
        WHERE $where_sql
        ORDER BY e.result_date DESC, e.event_date DESC
        LIMIT 20";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($events as $event) {
    $event_data = [
        'id' => $event['id'],
        'name' => $event['event_name'],
        'date' => $event['event_date'],
        'result_date' => $event['result_date'],
        'type' => $event['event_type'],
        'icon' => $event['icon'],
        'winners' => []
    ];

    // Get winners from event_results
    $stmt = $conn->prepare("SELECT er.position, er.points, er.prize_description, u.name as user_name, u.id as user_id
                            FROM event_results er
                            JOIN users u ON er.user_id = u.id
                            WHERE er.event_id = ?
                            ORDER BY er.position ASC");
    $stmt->bind_param("i", $event['id']);
    $stmt->execute();
    $winners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // For photography events, get winner's first photo
    if ($event['event_type'] === 'photography') {
        foreach ($winners as &$winner) {
            $stmt = $conn->prepare("SELECT photo_path FROM photo_submissions 
                                    WHERE event_id = ? AND user_id = ? 
                                    ORDER BY uploaded_at ASC LIMIT 1");
            $stmt->bind_param("ii", $event['id'], $winner['user_id']);
            $stmt->execute();
            $photo = $stmt->get_result()->fetch_assoc();
            $winner['photo'] = $photo ? $photo['photo_path'] : null;
            $stmt->close();
        }
    }

    // For presentation events, get team names
    if ($event['event_type'] === 'presentation') {
        foreach ($winners as &$winner) {
            $stmt = $conn->prepare("SELECT team_name, marks FROM ppt_submissions 
                                    WHERE event_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $event['id'], $winner['user_id']);
            $stmt->execute();
            $team = $stmt->get_result()->fetch_assoc();
            $winner['team_name'] = $team ? $team['team_name'] : null;
            $winner['marks'] = $team ? $team['marks'] : 0;
            $stmt->close();
        }
    }

    $event_data['winners'] = $winners;
    $results[] = $event_data;
}

echo json_encode(['status' => 'success', 'results' => $results]);
?>