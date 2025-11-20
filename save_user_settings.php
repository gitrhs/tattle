<?php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'generate_intro_audio.php';

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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$user_id = $_SESSION['user_id'];
$voice = $input['voice'] ?? '';
$ai_name = trim($input['ai_name'] ?? '');
$ai_role = trim($input['ai_role'] ?? '');
$ai_communication_style = trim($input['ai_communication_style'] ?? '');
$ai_introduction = trim($input['ai_introduction'] ?? '');

// Extract language from voice if provided
$language = 'en-US'; // default
if (!empty($voice) && $voice !== 'dummy-voice') {
    // Extract language from voice (usually the first part of the locale)
    // For example: en-US-JennyNeural -> en-US
    $voice_parts = explode('-', $voice);
    $language = (count($voice_parts) >= 2) ? $voice_parts[0] . '-' . $voice_parts[1] : 'en-US';
}

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get current user settings to detect changes
$old_voice = '';
$old_introduction = '';
$user_hash = '';

$stmt = $conn->prepare("SELECT voice, hash FROM user WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user_data = $result->fetch_assoc();
    $old_voice = $user_data['voice'];
    $user_hash = $user_data['hash'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT introduction FROM ai_instruction WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $ai_data = $result->fetch_assoc();
    $old_introduction = $ai_data['introduction'];
}
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Update user settings (only if voice is provided and not dummy)
    if (!empty($voice) && $voice !== 'dummy-voice') {
        $stmt = $conn->prepare("UPDATE user SET voice = ?, language = ? WHERE id = ?");
        $stmt->bind_param("ssi", $voice, $language, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Update or insert AI instruction settings
    // Check if record exists
    $stmt = $conn->prepare("SELECT id FROM ai_instruction WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    if ($exists) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE ai_instruction SET name = ?, role = ?, communication_style = ?, introduction = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $ai_name, $ai_role, $ai_communication_style, $ai_introduction, $user_id);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO ai_instruction (user_id, name, role, communication_style, introduction) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $ai_name, $ai_role, $ai_communication_style, $ai_introduction);
    }

    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Update session variables (only if voice was updated)
    if (!empty($voice) && $voice !== 'dummy-voice') {
        $_SESSION['voice'] = $voice;
        $_SESSION['language'] = $language;
    }

    // Check if voice or introduction changed and generate intro audio
    $voice_changed = (!empty($voice) && $voice !== 'dummy-voice' && $voice !== $old_voice);
    $intro_changed = ($ai_introduction !== $old_introduction);

    if (($voice_changed || $intro_changed) && !empty($ai_introduction) && !empty($user_hash)) {
        // Use the new voice if it was changed, otherwise use the old voice
        $voice_to_use = $voice_changed ? $voice : $old_voice;

        // Only generate if we have a valid voice
        if (!empty($voice_to_use) && $voice_to_use !== 'dummy-voice') {
            // Generate intro audio in the background (non-blocking)
            // This will translate if needed and save to intro_voice/{user_hash}.mp3
            $audio_result = generateIntroAudio(
                $user_id,
                $user_hash,
                $ai_introduction,
                $voice_to_use,
                $language
            );

            // Log the result but don't fail the settings save if audio generation fails
            if (!$audio_result['success']) {
                error_log("Failed to generate intro audio for user {$user_id}: " . $audio_result['message']);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Settings saved successfully!']);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
}

closeDBConnection($conn);
