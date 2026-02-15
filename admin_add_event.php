<?php
session_start();
include 'config.php';

// ENFORCEMENT: Only admin can access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access Denied. Admin privilege required.";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['event_name']);
    $date = trim($_POST['event_date']);
    $desc = trim($_POST['event_description']);

    $limit = isset($_POST['question_limit']) ? (int) $_POST['question_limit'] : 10;

    $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, event_description, question_limit) VALUES (?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("sssi", $name, $date, $desc, $limit);
        if ($stmt->execute()) {
            echo "Event Added Successfully";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Database connection error.";
    }
}
?>