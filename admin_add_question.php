<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access Denied. Admin privilege required.";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_id = (int) ($_POST['event_id'] ?? 0);
    $q = trim($_POST['question']);
    $a = trim($_POST['option_a']);
    $b = trim($_POST['option_b']);
    $c = trim($_POST['option_c']);
    $d = trim($_POST['option_d']);
    $ans = strtoupper(trim($_POST['answer']));

    if ($event_id === 0) {
        echo "Error: Event ID is required.";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO quiz (event_id, question, option_a, option_b, option_c, option_d, answer)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        // 'issssss' means: integer, six strings
        $stmt->bind_param("issssss", $event_id, $q, $a, $b, $c, $d, $ans);

        if ($stmt->execute()) {
            echo "Question Added Successfully!!";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Database connection error.";
    }
}
?>