<?php
/**
 * API Key Encryption Helper for TTS API
 *
 * This file demonstrates how to encrypt API keys before sending to the TTS API.
 * The encrypted data will be decrypted by the Python backend.
 */

class APIKeyEncryptor {
    private const CIPHER_METHOD = 'aes-256-cbc';

    /**
     * Get the secret key from environment variable
     * @throws Exception if ENCRYPTION_SECRET_KEY is not set
     */
    private static function getSecretKey(): string {
        $key = getenv('ENCRYPTION_SECRET_KEY');
        if ($key === false || empty($key)) {
            throw new Exception("ENCRYPTION_SECRET_KEY environment variable is required");
        }
        if (strlen($key) !== 32) {
            throw new Exception("ENCRYPTION_SECRET_KEY must be exactly 32 characters for AES-256");
        }
        return $key;
    }

    /**
     * Encrypt API keys into a single auth_key
     *
     * @param string $embedding_api_key The API key for embedding search
     * @param string $llm_api_key The API key for LLM provider
     * @return string Base64 encoded encrypted data
     */
    public static function encryptKeys(string $embedding_api_key, string $llm_api_key): string {
        // Create JSON payload with both keys
        $payload = json_encode([
            'embedding_api_key' => $embedding_api_key,
            'llm_api_key' => $llm_api_key
        ]);

        // Generate a random IV (Initialization Vector)
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt the payload
        $encrypted = openssl_encrypt(
            $payload,
            self::CIPHER_METHOD,
            self::getSecretKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        // Combine IV and encrypted data, then base64 encode
        $result = base64_encode($iv . $encrypted);

        return $result;
    }

    /**
     * Decrypt auth_key to get API keys
     *
     * @param string $encrypted_data Base64 encoded encrypted data
     * @return string JSON string containing embedding_api_key and llm_api_key
     * @throws Exception If decryption fails
     */
    public static function decryptKeys(string $encrypted_data): string {
        try {
            // Decode base64
            $encrypted_bytes = base64_decode($encrypted_data);

            if ($encrypted_bytes === false) {
                throw new Exception("Failed to decode base64 data");
            }

            // Extract IV (first 16 bytes for AES)
            $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $iv = substr($encrypted_bytes, 0, $iv_length);
            $encrypted_payload = substr($encrypted_bytes, $iv_length);

            // Decrypt the payload
            $decrypted = openssl_decrypt(
                $encrypted_payload,
                self::CIPHER_METHOD,
                self::getSecretKey(),
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new Exception("Failed to decrypt data");
            }

            // Validate JSON
            $keys = json_decode($decrypted, true);
            if ($keys === null || !isset($keys['embedding_api_key']) || !isset($keys['llm_api_key'])) {
                throw new Exception("Invalid decrypted data format");
            }

            return $decrypted;

        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            throw new Exception("Failed to decrypt auth_key: " . $e->getMessage());
        }
    }
}

// Example usage:
// $auth_key = APIKeyEncryptor::encryptKeys("embedding-key-123", "llm-key-456");
//
// Then send this $auth_key to your API:
// $request_data = [
//     "query" => "your query",
//     "user_hash" => "user123",
//     "instruct" => "You are a helpful assistant",
//     "auth_key" => $auth_key,  // Encrypted keys
//     "collection_name" => "your-collection",
//     "top_k" => 5,
//     "voice" => "en-HK-SamNeural",
//     "provider" => "google",
//     "model" => "gemma-2-9b-it"
// ];

?>