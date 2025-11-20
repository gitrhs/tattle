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

if (!$input || !isset($input['provider'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Provider is required']);
    exit;
}

$provider = $input['provider'];

// Get API keys from database
$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$result = $conn->query("SELECT google_api_key, mistral_api_key, zai_api_key FROM global_setting WHERE id = 1");
if (!$result || $result->num_rows === 0) {
    closeDBConnection($conn);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'API keys not found. Please configure them first.']);
    exit;
}

$settings = $result->fetch_assoc();
closeDBConnection($conn);

try {
    if ($provider === 'google') {
        $apiKey = $settings['google_api_key'];
        if (empty($apiKey)) {
            throw new Exception('Google API key is not configured');
        }

        // Fetch Google models
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('API request failed with status code: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }

        // Save to google_model.json
        if (file_put_contents('google_model.json', json_encode($data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Failed to write to google_model.json');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Google models refreshed successfully!',
            'count' => isset($data['models']) ? count($data['models']) : 0
        ]);

    } elseif ($provider === 'mistral') {
        $apiKey = $settings['mistral_api_key'];
        if (empty($apiKey)) {
            throw new Exception('Mistral API key is not configured');
        }

        // Fetch Mistral models
        $url = "https://api.mistral.ai/v1/models";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('API request failed with status code: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }

        // Save to mistral_model.json
        if (file_put_contents('mistral_model.json', json_encode($data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Failed to write to mistral_model.json');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Mistral models refreshed successfully!',
            'count' => isset($data['data']) ? count($data['data']) : 0
        ]);

    } elseif ($provider === 'z.ai') {
        $apiKey = $settings['zai_api_key'];
        if (empty($apiKey)) {
            throw new Exception('Z.ai API key is not configured');
        }

        // Fetch Z.ai models
        // Note: This endpoint might need to be adjusted based on Z.ai's actual API documentation
        $url = "https://api.z.ai/api/paas/v4/models";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('API request failed with status code: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }

        // Save to zai_model.json
        if (file_put_contents('zai_model.json', json_encode($data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception('Failed to write to zai_model.json');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Z.ai models refreshed successfully!',
            'count' => isset($data['data']) ? count($data['data']) : 0
        ]);

    } else {
        throw new Exception('Invalid provider. Must be "google", "mistral", or "z.ai".');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to refresh models: ' . $e->getMessage()
    ]);
}
