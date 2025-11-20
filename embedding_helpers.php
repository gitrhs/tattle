<?php
/**
 * Embedding Helper Functions
 * Shared functions for embedding API operations
 */

require_once 'db_config.php';

/**
 * Get collection name from global_setting table
 */
function getCollectionName() {
    $conn = getDBConnection();
    if (!$conn) {
        return null;
    }

    $stmt = $conn->prepare("SELECT collection_name FROM global_setting WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $collection_name = null;

    if ($row = $result->fetch_assoc()) {
        $collection_name = $row['collection_name'];
    }

    $stmt->close();
    closeDBConnection($conn);

    return $collection_name;
}

/**
 * Get API key from global_setting table
 */
function getApiKey() {
    $conn = getDBConnection();
    if (!$conn) {
        return null;
    }

    $stmt = $conn->prepare("SELECT qdrant_api_key FROM global_setting WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $api_key = null;

    if ($row = $result->fetch_assoc()) {
        $api_key = $row['qdrant_api_key'];
    }

    $stmt->close();
    closeDBConnection($conn);

    return $api_key;
}

/**
 * Call the embedding API to create embeddings for uploaded document
 *
 * @param string $document_url Public URL to the document
 * @param string $context Brief description (max 200 chars)
 * @param string $url Source URL (optional)
 * @param string $user_hash User's unique hash
 * @param int $document_id Document ID from database
 * @param string $collection_name Qdrant collection name
 * @return array ['success' => bool, 'chunks' => int|null, 'error' => string|null]
 */
function createEmbedding($document_url, $context, $url, $user_hash, $document_id, $collection_name) {
    $api_url = 'https://embedding.2ai.dev/create';

    // Get API key from database
    $api_key = getApiKey();
    if (!$api_key) {
        return [
            'success' => false,
            'chunks' => null,
            'error' => 'API key not found in database'
        ];
    }

    // Truncate context to max 200 characters
    $context = substr($context, 0, 200);

    $payload = [
        'api_key' => $api_key,
        'document_url' => $document_url,
        'context' => $context,
        'url' => $url,
        'user_hash' => $user_hash,
        'document_id' => (string)$document_id,
        'collection_name' => $collection_name
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return [
            'success' => false,
            'chunks' => null,
            'error' => 'cURL error: ' . $curl_error
        ];
    }

    if ($http_code !== 200) {
        return [
            'success' => false,
            'chunks' => null,
            'error' => 'API returned HTTP ' . $http_code . ': ' . $response
        ];
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result['status'])) {
        return [
            'success' => false,
            'chunks' => null,
            'error' => 'Invalid API response format'
        ];
    }

    if ($result['status'] === 'ok') {
        return [
            'success' => true,
            'chunks' => $result['inserted_chunks'] ?? null,
            'error' => null
        ];
    } else {
        return [
            'success' => false,
            'chunks' => null,
            'error' => $result['detail'] ?? 'Unknown error from API'
        ];
    }
}

/**
 * Update document table with embedding status
 */
function updateEmbeddingStatus($document_id, $status, $chunks = null, $error = null) {
    $conn = getDBConnection();
    if (!$conn) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE document SET embedding_status = ?, embedding_chunks = ?, embedding_error = ? WHERE id = ?");
    $stmt->bind_param("sisi", $status, $chunks, $error, $document_id);
    $result = $stmt->execute();

    $stmt->close();
    closeDBConnection($conn);

    return $result;
}

/**
 * Call the embedding API to delete embeddings for a document
 *
 * @param string $user_hash User's unique hash
 * @param int $document_id Document ID from database
 * @param string $collection_name Qdrant collection name
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteEmbedding($user_hash, $document_id, $collection_name) {
    $api_url = 'https://embedding.2ai.dev/delete';

    // Get API key from database
    $api_key = getApiKey();
    if (!$api_key) {
        return [
            'success' => false,
            'error' => 'API key not found in database'
        ];
    }

    $payload = [
        'api_key' => $api_key,
        'user_hash' => $user_hash,
        'document_id' => (string)$document_id,
        'collection_name' => $collection_name
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $curl_error
        ];
    }

    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'API returned HTTP ' . $http_code . ': ' . $response
        ];
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result['status'])) {
        return [
            'success' => false,
            'error' => 'Invalid API response format'
        ];
    }

    if ($result['status'] === 'ok') {
        return [
            'success' => true,
            'error' => null
        ];
    } else {
        return [
            'success' => false,
            'error' => $result['message'] ?? 'Unknown error from API'
        ];
    }
}
