<?php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

// Import embedding helper functions
require_once 'embedding_helpers.php';

// Basic authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$title = $_POST['title'] ?? '';
$url = $_POST['url'] ?? '';
$upload_type = $_POST['upload_type'] ?? 'file';

// Validate title
if (empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

if ($upload_type === 'text') {
    // Handle raw text input
    $raw_text = $_POST['raw_text'] ?? '';

    if (empty($raw_text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Text content is required']);
        exit;
    }

    $upload_dir = 'uploads/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Save text as a .txt file
    $filename = uniqid() . '_' . preg_replace('/[^a-z0-9]+/i', '_', $title) . '.txt';
    $filepath = $upload_dir . $filename;

    if (file_put_contents($filepath, $raw_text) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save text file']);
        exit;
    }

    // Save to database
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO document (user_id, title, url, file_path, type) VALUES (?, ?, ?, ?, 'text')");
    $stmt->bind_param("isss", $user_id, $title, $url, $filepath);

    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;

        // Close statement and connection before embedding API call
        $stmt->close();
        closeDBConnection($conn);

        // Return success immediately to the user
        echo json_encode([
            'success' => true,
            'message' => 'Text data saved successfully! Embedding in progress...',
            'data' => [
                'id' => $insert_id,
                'title' => $title,
                'url' => $url,
                'file_path' => $filepath,
                'type' => 'text'
            ]
        ]);

        // Flush output buffer to send response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            ob_flush();
            flush();
        }

        // Call embedding API after response is sent
        $user_hash = $_SESSION['user_hash'] ?? '';
        $collection_name = getCollectionName();

        if (!empty($user_hash) && !empty($collection_name)) {
            // Construct public document URL
            $document_url = 'https://tattle.2ai.dev/' . $filepath;

            // Use title as context (truncated to 200 chars in the function)
            $context = $title;

            // Call embedding API
            $embedding_result = createEmbedding($document_url, $context, $url, $user_hash, $insert_id, $collection_name);

            // Update document with embedding status
            if ($embedding_result['success']) {
                updateEmbeddingStatus($insert_id, 'success', $embedding_result['chunks'], null);
            } else {
                updateEmbeddingStatus($insert_id, 'failed', null, $embedding_result['error']);
            }
        } else {
            // Mark as failed if user_hash or collection_name is missing
            $error_msg = 'Missing ' . (empty($user_hash) ? 'user_hash' : 'collection_name');
            updateEmbeddingStatus($insert_id, 'failed', null, $error_msg);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save document metadata: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }

} else {
    // Handle file upload
    if (!isset($_FILES['document'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file was uploaded']);
        exit;
    }

    $file = $_FILES['document'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $error_message = $upload_errors[$file['error']] ?? 'Unknown upload error';
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    // Validate file size (5MB = 5 * 1024 * 1024 bytes)
    $max_file_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_file_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
        exit;
    }

    $upload_dir = 'uploads/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit;
    }

    // Save to database
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO document (user_id, title, url, file_path, type) VALUES (?, ?, ?, ?, 'file')");
    $stmt->bind_param("isss", $user_id, $title, $url, $filepath);

    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;

        // Close statement and connection before embedding API call
        $stmt->close();
        closeDBConnection($conn);

        // Return success immediately to the user
        echo json_encode([
            'success' => true,
            'message' => 'Document uploaded successfully! Embedding in progress...',
            'data' => [
                'id' => $insert_id,
                'title' => $title,
                'url' => $url,
                'file_path' => $filepath,
                'type' => 'file'
            ]
        ]);

        // Flush output buffer to send response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            ob_flush();
            flush();
        }

        // Call embedding API after response is sent
        $user_hash = $_SESSION['user_hash'] ?? '';
        $collection_name = getCollectionName();

        if (!empty($user_hash) && !empty($collection_name)) {
            // Construct public document URL
            $document_url = 'https://tattle.2ai.dev/' . $filepath;

            // Use title as context (truncated to 200 chars in the function)
            $context = $title;

            // Call embedding API
            $embedding_result = createEmbedding($document_url, $context, $url, $user_hash, $insert_id, $collection_name);

            // Update document with embedding status
            if ($embedding_result['success']) {
                updateEmbeddingStatus($insert_id, 'success', $embedding_result['chunks'], null);
            } else {
                updateEmbeddingStatus($insert_id, 'failed', null, $embedding_result['error']);
            }
        } else {
            // Mark as failed if user_hash or collection_name is missing
            $error_msg = 'Missing ' . (empty($user_hash) ? 'user_hash' : 'collection_name');
            updateEmbeddingStatus($insert_id, 'failed', null, $error_msg);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save document metadata: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }
}
