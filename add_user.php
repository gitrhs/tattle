<?php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

// Check if user is superadmin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['user_type'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Superadmin only.']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$type = $input['type'] ?? 'user';

// Validate input
if (empty($username)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

if (!in_array($type, ['user', 'superadmin'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user type']);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM user WHERE user_name = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username already exists']);
    $stmt->close();
    closeDBConnection($conn);
    exit;
}
$stmt->close();

// Generate hash for user (SHA-256 of username + timestamp for uniqueness)
$hash = hash('sha256', $username . time());

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$stmt = $conn->prepare("INSERT INTO user (user_name, password, hash, type) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $username, $password_hash, $hash, $type);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'User added successfully!',
        'user_id' => $conn->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add user: ' . $stmt->error]);
}

$stmt->close();
closeDBConnection($conn);
