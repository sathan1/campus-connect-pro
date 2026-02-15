<?php
// Authentication API
session_start();
header('Content-Type: application/json');
include 'config.php';

$action = $_POST['action'] ?? '';
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$name = trim($_POST['name'] ?? '');

if ($action === 'login') {
    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password required']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];

    echo json_encode(['status' => 'success', 'message' => 'Login successful!']);
    exit;
}

if ($action === 'register') {
    if (empty($email) || empty($password) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields required']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
        exit;
    }
    $stmt->close();

    // Create user
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $name, $email, $hashed);

    if ($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_role'] = 'user';
        echo json_encode(['status' => 'success', 'message' => 'Registration successful!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>