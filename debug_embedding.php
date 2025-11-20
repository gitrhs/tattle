<?php
require_once 'db_config.php';

echo "<h2>Embedding Status Check</h2>";

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Check if columns exist
echo "<h3>1. Check if embedding columns exist:</h3>";
$result = $conn->query("DESCRIBE document");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check recent documents
echo "<h3>2. Recent Documents with Embedding Status:</h3>";
$stmt = $conn->prepare("SELECT id, title, file_path, embedding_status, embedding_chunks, embedding_error, created_at FROM document ORDER BY id DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='5' style='width:100%; table-layout: fixed;'>";
echo "<tr><th>ID</th><th>Title</th><th>File Path</th><th>Status</th><th>Chunks</th><th>Error</th><th>Created</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td style='word-break: break-all;'>" . htmlspecialchars($row['file_path']) . "</td>";
    echo "<td>{$row['embedding_status']}</td>";
    echo "<td>{$row['embedding_chunks']}</td>";
    echo "<td style='word-break: break-all;'>" . htmlspecialchars($row['embedding_error']) . "</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check global_setting
echo "<h3>3. Global Settings:</h3>";
$stmt = $conn->prepare("SELECT * FROM global_setting WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<table border='1' cellpadding='5'>";
    foreach ($row as $key => $value) {
        echo "<tr><th>{$key}</th><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No global_setting record found!</p>";
}

// Check session
echo "<h3>4. Session Check:</h3>";
session_start();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Key</th><th>Value</th></tr>";
echo "<tr><td>admin_logged_in</td><td>" . ($_SESSION['admin_logged_in'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>user_id</td><td>" . ($_SESSION['user_id'] ?? 'NOT SET') . "</td></tr>";
echo "<tr><td>user_hash</td><td>" . ($_SESSION['user_hash'] ?? 'NOT SET') . "</td></tr>";
echo "</table>";

// Test embedding API connection
echo "<h3>5. Test Embedding API Connection:</h3>";
$ch = curl_init('https://embedding.2ai.dev/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<p style='color: red;'>cURL Error: {$curl_error}</p>";
} else {
    echo "<p style='color: green;'>API Reachable - HTTP Code: {$http_code}</p>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
}

closeDBConnection($conn);
?>
