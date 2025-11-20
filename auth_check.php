<?php
/**
 * Authentication Check
 * Include this file at the top of any page that requires authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Not logged in - redirect to login page
    header('Location: login.php');
    exit;
}

// Optionally check if required session variables exist
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Session corrupted - force re-login
    session_destroy();
    header('Location: login.php');
    exit;
}
