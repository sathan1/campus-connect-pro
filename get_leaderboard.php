<?php
include 'config.php';

header('Content-Type: application/json');

// PROFESSIONALISM: Join tables and limit for performance and relevance
$event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'simple';

if ($mode === 'global_agg') {
    // Aggregated Global Leaderboard (Sum of all scores per user)
    // "visible just total of user which events are particapted total points from all events participated"
    $sql = "SELECT u.name, SUM(r.score) as total_score, COUNT(r.event_id) as events_played
            FROM quiz_results r
            JOIN users u ON r.user_id = u.id
            GROUP BY r.user_id
            ORDER BY total_score DESC, events_played DESC
            LIMIT 20";

    $result = mysqli_query($conn, $sql);
    $leaderboard = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $leaderboard[] = [
                'name' => htmlspecialchars($row['name']),
                'score' => (int) $row['total_score'],
                'events_played' => (int) $row['events_played']
            ];
        }
    }
    echo json_encode($leaderboard);
    exit();

} elseif ($event_id > 0) {
    // Event specific
    $sql = "SELECT u.name, r.score, r.taken_at 
            FROM quiz_results r
            JOIN users u ON r.user_id = u.id
            WHERE r.event_id = $event_id
            ORDER BY r.score DESC, r.taken_at ASC
            LIMIT 50";
} else {
    // Default: Aggregated scores for sidebar (same as global_agg but for default)
    $sql = "SELECT u.name, SUM(r.score) as total_score
            FROM quiz_results r
            JOIN users u ON r.user_id = u.id
            GROUP BY r.user_id
            ORDER BY total_score DESC
            LIMIT 10";
}

$result = mysqli_query($conn, $sql);

$leaderboard = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Handle different response formats
        if (isset($row['total_score'])) {
            // Aggregated query
            $entry = [
                'name' => htmlspecialchars($row['name']),
                'total_score' => (int) $row['total_score']
            ];
        } else {
            // Event-specific query
            $entry = [
                'name' => htmlspecialchars($row['name']),
                'total_score' => (int) $row['score'],
                'time' => $row['taken_at'] ?? null
            ];
        }
        if (isset($row['event_name'])) {
            $entry['event'] = htmlspecialchars($row['event_name']);
        }
        $leaderboard[] = $entry;
    }
}
echo json_encode($leaderboard);
?>