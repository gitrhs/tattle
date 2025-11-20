<?php
header('Content-Type: application/json');

require_once 'db_config.php';
require_once 'template_helper.php';

// Static user hash for now
$user_hash = '498cd6365f2b0a063c5713529488fdcca580ebcfc584ed0e7d42abdefaaf629d';

$response = [
    'api_key' => '',
    'collection_name' => '',
    'api_url' => '',
    'voice' => 'alloy',
    'language' => 'English',
    'instruct' => ''
];

$conn = getDBConnection();

if ($conn) {
    // Get user info by hash
    $stmt = $conn->prepare("SELECT id, voice, language FROM user WHERE hash = ?");
    $stmt->bind_param("s", $user_hash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $user_id = $user_data['id'];
        $response['voice'] = $user_data['voice'];
        $response['language'] = $user_data['language'];

        // Get global settings
        $global_result = $conn->query("SELECT google_api_key, mistral_api_key, zai_api_key, collection_name, api_url, provider, google_prompt, mistral_prompt, zai_prompt FROM global_setting WHERE id = 1");
        if ($global_result && $global_result->num_rows === 1) {
            $settings_data = $global_result->fetch_assoc();
            $provider = $settings_data['provider'];

            // Select API key based on provider
            if ($provider === 'mistral') {
                $response['api_key'] = $settings_data['mistral_api_key'];
            } elseif ($provider === 'z.ai') {
                $response['api_key'] = $settings_data['zai_api_key'];
            } else {
                $response['api_key'] = $settings_data['google_api_key'];
            }

            $response['collection_name'] = $settings_data['collection_name'];
            $response['api_url'] = $settings_data['api_url'];

            // Get appropriate template based on provider
            if ($provider === 'mistral') {
                $prompt_template = $settings_data['mistral_prompt'];
            } elseif ($provider === 'z.ai') {
                $prompt_template = $settings_data['zai_prompt'];
            } else {
                $prompt_template = $settings_data['google_prompt'];
            }
        }

        // Get AI instruction settings
        $ai_stmt = $conn->prepare("SELECT name, role, communication_style, introduction FROM ai_instruction WHERE user_id = ?");
        $ai_stmt->bind_param("i", $user_id);
        $ai_stmt->execute();
        $ai_result = $ai_stmt->get_result();

        if ($ai_result->num_rows === 1) {
            $ai_data = $ai_result->fetch_assoc();
            $ai_name = $ai_data['name'];
            $ai_role = $ai_data['role'];
            $ai_communication_style = $ai_data['communication_style'];
            $ai_introduction = $ai_data['introduction'];

            // Render template with actual values
            $response['instruct'] = renderTemplate($prompt_template, [
                'language' => $response['language'],
                'ai_name' => $ai_name,
                'ai_role' => $ai_role,
                'ai_communication_style' => $ai_communication_style,
                'ai_introduction' => $ai_introduction
            ]);
        }

        $ai_stmt->close();
    }

    $stmt->close();
    closeDBConnection($conn);
}

echo json_encode($response);
?>
