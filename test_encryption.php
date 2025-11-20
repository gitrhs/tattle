<?php
/**
 * Test script to verify encryption is working correctly
 */
require_once 'encrypt_helper.php';

// Your values from frame.php
$user_hash = '498cd6365f2b0a063c5713529488fdcca580ebcfc584ed0e7d42abdefaaf629d';
$api_key = 'YOUR_API_KEY_HERE'; // Replace with your actual API key

echo "=== Encryption Test ===\n\n";

echo "Input values:\n";
echo "  user_hash (embedding_api_key): $user_hash\n";
echo "  api_key (llm_api_key): $api_key\n\n";

// Encrypt
$auth_key = APIKeyEncryptor::encryptKeys($user_hash, $api_key);

echo "Encrypted auth_key:\n";
echo "  $auth_key\n\n";

echo "Length: " . strlen($auth_key) . " characters\n";
echo "\n";

echo "To test decryption on your Python server, run:\n";
echo "python3 test_decryption.py '$auth_key'\n";
?>
