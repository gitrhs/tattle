<?php
// Database Configuration Template
// INSTRUCTIONS:
// 1. Copy this file to db_config.php
// 2. Update the values below with your actual database credentials
// 3. Never commit db_config.php to version control

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');  // Default MySQL port, change if different
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'rafidaffa_portal');
define('DB_SOCKET', '');  // Optional: path to MySQL socket file (leave empty if not needed)


// Create database connection
function getDBConnection() {
    //$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, DB_SOCKET);

    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }

    // Set charset to utf8mb4 for full UTF-8 support
    $conn->set_charset("utf8mb4");

    return $conn;
}

// Close database connection
function closeDBConnection($conn) {
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
}

// Sanitize input to prevent XSS
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>
