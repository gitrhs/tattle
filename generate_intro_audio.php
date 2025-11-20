<?php
/**
 * Helper function to generate introduction audio with language conversion
 * This file handles TTS generation for intro voice using the /tts API endpoint
 */

require_once 'db_config.php';
require_once 'encrypt_helper.php';
function getRandomKey($api_key) {
    if (str_contains($api_key, ",")){
        $keys = array_map('trim', explode(',', $api_key));
        return $keys[array_rand($keys)];
    } else{
        return $api_key;
    }
}
/**
 * Generate intro audio with language conversion if needed
 *
 * @param int $user_id User ID
 * @param string $user_hash User hash for file naming
 * @param string $introduction Introduction text
 * @param string $voice Voice to use for TTS
 * @param string $target_language Target language code (e.g., "en-US", "id-ID")
 * @return array Result array with success status and message
 */
function generateIntroAudio($user_id, $user_hash, $introduction, $voice, $target_language) {
    try {
        // Get API configuration from global settings
        $conn = getDBConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }

        $stmt = $conn->prepare("SELECT google_api_key, mistral_api_key, zai_api_key, api_url, provider, model, collection_name FROM global_setting WHERE id = 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("API configuration not found in global settings");
        }

        $settings = $result->fetch_assoc();
        $google_api_key = getRandomKey($settings['google_api_key']);
        $mistral_api_key = $settings['mistral_api_key'];
        $zai_api_key = $settings['zai_api_key'];
        $api_url = $settings['api_url'];
        $provider = $settings['provider'];
        $model = $settings['model'];
        $collection_name = $settings['collection_name'];
        $stmt->close();
        closeDBConnection($conn);

        // Select the correct API key based on provider
        if ($provider === 'mistral') {
            $api_key = $mistral_api_key;
        } elseif ($provider === 'z.ai') {
            $api_key = $zai_api_key;
        } else {
            $api_key = $google_api_key;
        }

        if (empty($api_key)) {
            throw new Exception("API key not configured for provider: {$provider}");
        }

        if (empty($api_url)) {
            throw new Exception("API URL not configured");
        }

        // Encrypt auth_key (user_hash as embedding_api_key, api_key as llm_api_key)
        $auth_key = APIKeyEncryptor::encryptKeys($user_hash, $api_key);

        // Create custom system prompt that ignores RAG and translates the introduction
        $custom_instruct = "IMPORTANT: Ignore the query and any RAG search results completely.

Your ONLY task is to process the following introduction text:
1. If the text is NOT in {$target_language} language, translate it to {$target_language}
2. If the text is already in {$target_language}, return it exactly as is
3. Return ONLY the text itself, nothing else (no explanations, no comments)

Introduction text to process:
{$introduction}

Remember: Output ONLY the translated or original text in {$target_language}. Max 60 tokens.";

        // Prepare the TTS API request
        $tts_url = rtrim($api_url, '/') . '/tts';

        $request_data = [
            'query' => 'a',  // Dummy query (will be ignored by the custom prompt)
            'user_hash' => $user_hash,
            'instruct' => $custom_instruct,
            'auth_key' => $auth_key,
            'collection_name' => $collection_name,
            'top_k' => 1,  // Minimal RAG search
            'voice' => $voice,
            'provider' => $provider,
            'model' => $model
        ];

        // Call the TTS API
        $ch = curl_init($tts_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);  // Longer timeout for TTS processing

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("TTS API error: " . $error);
        }

        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("TTS API returned status code: {$http_code}. Response: " . substr($response, 0, 200));
        }

        // Parse the JSON response
        $result_data = json_decode($response, true);

        if (!$result_data || $result_data['status'] !== 'success') {
            throw new Exception("TTS API returned error: " . ($result_data['message'] ?? 'Unknown error'));
        }

        // Get the audio URL from the response
        $audio_url = $result_data['audio_url'] ?? null;
        if (!$audio_url) {
            throw new Exception("No audio URL in TTS response");
        }

        // Download the audio file from the API
        $audio_file_url = rtrim($api_url, '/') . $audio_url;
        $audio_data = file_get_contents($audio_file_url);

        if ($audio_data === false) {
            throw new Exception("Failed to download audio file from: {$audio_file_url}");
        }

        // Save audio file
        $intro_voice_dir = __DIR__ . '/intro_voice';
        if (!is_dir($intro_voice_dir)) {
            mkdir($intro_voice_dir, 0755, true);
        }

        // Clean up old intro files for this user
        $old_files = glob($intro_voice_dir . '/' . $user_hash . '.*');
        foreach ($old_files as $old_file) {
            if (is_file($old_file)) {
                unlink($old_file);
            }
        }

        // Save new file
        $file_path = $intro_voice_dir . '/' . $user_hash . '.mp3';
        file_put_contents($file_path, $audio_data);

        return [
            'success' => true,
            'message' => 'Introduction audio generated successfully',
            'file_path' => 'intro_voice/' . $user_hash . '.mp3',
            'ai_response' => $result_data['ai_response'] ?? ''
        ];

    } catch (Exception $e) {
        error_log("Error generating intro audio: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to generate intro audio: ' . $e->getMessage()
        ];
    }
}