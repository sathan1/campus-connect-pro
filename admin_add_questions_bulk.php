<?php
// admin_add_questions_bulk.php
session_start();
include 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_id = (int) ($_POST['event_id'] ?? 0);
    $questions = $_POST['questions'] ?? []; // Array of questions
    $count = 0;

    if ($event_id === 0) {
        echo "Error: Event ID is required.";
        exit();
    }

    // ENFORCEMENT: Check limit before adding
    $limitCheck = $conn->prepare("SELECT question_limit FROM events WHERE id = ?");
    $limitCheck->bind_param("i", $event_id);
    $limitCheck->execute();
    $res = $limitCheck->get_result();
    $row = $res->fetch_assoc();
    $limit = $row['question_limit'] ?? 10;
    $limitCheck->close();

    $countCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM quiz WHERE event_id = $event_id");
    $countRow = mysqli_fetch_assoc($countCheck);
    $currentCount = $countRow['c'];

    $remaining = $limit - $currentCount;

    if ($remaining <= 0) {
        echo "Error: Question limit ($limit) reached. Cannot add more.";
        exit();
    }

    // Only process up to reasonable batch size, but here we strictly check count
    if (count($questions) > $remaining) {
        echo "Error: You are trying to add " . count($questions) . " questions but only $remaining slots are available.";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO quiz (event_id, question, option_a, option_b, option_c, option_d, answer) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($questions as $qData) {
        $q = trim($qData['question']);
        $a = trim($qData['option_a']);
        $b = trim($qData['option_b']);
        $c = trim($qData['option_c']);
        $d = trim($qData['option_d']);
        $ans = strtoupper(trim($qData['answer']));

        if (!empty($q) && !empty($a) && !empty($b) && !empty($c) && !empty($d) && !empty($ans)) {
            $stmt->bind_param("issssss", $event_id, $q, $a, $b, $c, $d, $ans);
            if ($stmt->execute()) {
                $count++;
            }
        }
    }

    // Optional: Update the limit if requested (could be passed in POST)
    if (isset($_POST['update_limit']) && is_numeric($_POST['update_limit'])) {
        $limit = (int) $_POST['update_limit'];
        $updateLimit = $conn->prepare("UPDATE events SET question_limit = ? WHERE id = ?");
        $updateLimit->bind_param("ii", $limit, $event_id);
        $updateLimit->execute();
    }

    echo "Successfully added $count questions!";
    $stmt->close();
}
?>