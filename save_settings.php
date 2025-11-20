<?php
// DEPRECATED: This file is deprecated. Use save_global_settings.php for superadmin
// or save_user_settings.php for regular users instead.

session_start();
header('Content-Type: application/json');

// Basic authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please use the new settings API endpoints']);
    exit;
}

// Redirect to appropriate endpoint
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'This endpoint is deprecated. Use save_global_settings.php (superadmin) or save_user_settings.php (users)',
    'deprecated' => true
]);
exit;

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

// Validate required fields
$required_fields = ['qdrant_url', 'qdrant_api_key', 'collection_name', 'api_url', 'url_api_key', 'voice'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Prepare settings data
$settings = [
    'qdrant' => [
        'url' => $input['qdrant_url'],
        'api_key' => $input['qdrant_api_key'],
        'collection' => $input['collection_name']
    ],
    'url' => [
        'api_url' => $input['api_url'],
        'api_key' => $input['url_api_key'],
        'voice' => $input['voice']
    ]
];

// Save to file
$settings_file = 'data/settings.json';

// Create data directory if it doesn't exist
if (!is_dir('data')) {
    mkdir('data', 0755, true);
}

if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT)) !== false) {
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
}
