<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>Admin privilege required.</p>");
}

// Fetch quiz summary data, including event details
$sql = "SELECT 
            u.name, 
            u.email, 
            e.event_name,
            e.event_date,
            r.score, 
            (SELECT COUNT(q.id) FROM quiz q WHERE q.event_id = r.event_id) AS total_questions,
            r.taken_at
        FROM 
            quiz_results r
        JOIN 
            users u ON r.user_id = u.id
        LEFT JOIN events e ON r.event_id = e.id
        ORDER BY r.taken_at DESC";

$results = mysqli_query($conn, $sql);
$has_results = $results && mysqli_num_rows($results) > 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Quiz Summary - Admin</title>
    <style>
        /* (CSS from previous admin_panel.php remains) */
        :root {
            --accent: #4f46e5;
            --muted: #6b7280;
            --bg: #f8fafc
        }

        body {
            font-family: Inter, system-ui, sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        h1 {
            color: var(--accent);
            border-bottom: 2px solid #eef2ff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #f0f4f8;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #eef2ff;
            color: #1e3d59;
        }

        .score-cell {
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1><a href="admin_panel.php" style="color:var(--muted); text-decoration: none;">&larr;</a> Quiz Results Summary
        </h1>

        <table>
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>Email</th>
                    <th>Event</th>
                    <th>Score</th>
                    <th>Total Qs</th>
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($has_results):
                    while ($row = mysqli_fetch_assoc($results)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['event_name'] ?? 'N/A'); ?></td>
                            <td class="score-cell"><?php echo $row['score']; ?></td>
                            <td><?php echo $row['total_questions']; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['taken_at'])); ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">No quiz results submitted yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>