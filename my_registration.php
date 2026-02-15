<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: index.php");
    exit();
}
include 'config.php';

$user_id = $_SESSION['user_id'];

// Prepare SQL statement to fetch registered events
$sql = "SELECT e.event_name, e.event_date, e.event_description, r.registration_date 
        FROM registrations r 
        JOIN events e ON r.event_id = e.id 
        WHERE r.user_id = ? 
        ORDER BY e.event_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get User Name for header
$uStmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$uRes = $uStmt->get_result();
$uRow = $uRes->fetch_assoc();
$userName = $uRow['name'] ?? 'User';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Registrations</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            background: #f8fafc;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .item:last-child {
            border-bottom: none;
        }

        h1 {
            color: #4f46e5;
        }

        .date {
            color: #6b7280;
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Registrations for <?php echo htmlspecialchars($userName); ?></h1>

        <?php if ($result->num_rows > 0): ?>
            <div>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="item">
                        <h3><?php echo htmlspecialchars($row['event_name']); ?></h3>
                        <div class="date">Event Date: <?php echo $row['event_date']; ?></div>
                        <p><?php echo htmlspecialchars($row['event_description']); ?></p>
                        <div style="font-size:12px; color:#999">Registered on: <?php echo $row['registration_date']; ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>You have not registered for any events yet. <a href="index.php">Browse Events</a></p>
        <?php endif; ?>
    </div>
</body>

</html>