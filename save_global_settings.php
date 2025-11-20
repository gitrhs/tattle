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

// Validate required fields (prompts are optional, will use defaults if not provided)
$required_fields = ['qdrant_url', 'qdrant_api_key', 'collection_name', 'api_url', 'google_api_key', 'mistral_api_key', 'provider', 'model'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Optional fields
$optional_fields = ['google_prompt', 'mistral_prompt', 'zai_api_key', 'zai_prompt'];

$qdrant_url = $input['qdrant_url'];
$qdrant_api_key = $input['qdrant_api_key'];
$collection_name = $input['collection_name'];
$api_url = $input['api_url'];
$google_api_key = $input['google_api_key'];
$mistral_api_key = $input['mistral_api_key'];
$zai_api_key = isset($input['zai_api_key']) ? $input['zai_api_key'] : null;
$provider = $input['provider'];
$model = $input['model'];
$google_prompt = isset($input['google_prompt']) ? $input['google_prompt'] : null;
$mistral_prompt = isset($input['mistral_prompt']) ? $input['mistral_prompt'] : null;
$zai_prompt = isset($input['zai_prompt']) ? $input['zai_prompt'] : null;

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if settings exist, update or insert
$result = $conn->query("SELECT id FROM global_setting WHERE id = 1");

if ($result && $result->num_rows > 0) {
    // Update existing settings
    $stmt = $conn->prepare("UPDATE global_setting SET qdrant_url = ?, qdrant_api_key = ?, collection_name = ?, api_url = ?, google_api_key = ?, mistral_api_key = ?, zai_api_key = ?, provider = ?, model = ?, google_prompt = ?, mistral_prompt = ?, zai_prompt = ? WHERE id = 1");
    $stmt->bind_param("ssssssssssss", $qdrant_url, $qdrant_api_key, $collection_name, $api_url, $google_api_key, $mistral_api_key, $zai_api_key, $provider, $model, $google_prompt, $mistral_prompt, $zai_prompt);
} else {
    // Insert new settings
    $stmt = $conn->prepare("INSERT INTO global_setting (id, qdrant_url, qdrant_api_key, collection_name, api_url, google_api_key, mistral_api_key, zai_api_key, provider, model, google_prompt, mistral_prompt, zai_prompt) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssss", $qdrant_url, $qdrant_api_key, $collection_name, $api_url, $google_api_key, $mistral_api_key, $zai_api_key, $provider, $model, $google_prompt, $mistral_prompt, $zai_prompt);
}

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Global settings saved successfully!'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $stmt->error]);
}

$stmt->close();
closeDBConnection($conn);
