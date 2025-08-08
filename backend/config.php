<?php

/**
 * Application bootstrap and configuration
 */

// Start output buffering to prevent headers already sent issues
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Set default timezone
date_default_timezone_set('UTC');

// Error reporting
if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Include required classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Image.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/utils/EmailService.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Initialize application
try {
    // Test database connection
    $db = Database::getInstance();
    
    // Check for remember me cookie
    if (!AuthMiddleware::getUserId()) {
        AuthMiddleware::checkRememberMe();
    }
    
    // Validate session
    AuthMiddleware::validateSession();
    
} catch (Exception $e) {
    error_log("Application initialization error: " . $e->getMessage());
    
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        die("Application error: " . $e->getMessage());
    } else {
        die("Application temporarily unavailable. Please try again later.");
    }
}

// Helper functions
function redirect($url) {
    header("Location: $url");
    exit;
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

function getPost($key, $default = null) {
    return $_POST[$key] ?? $default;
}

function getGet($key, $default = null) {
    return $_GET[$key] ?? $default;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

function successResponse($data = null, $message = null) {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    jsonResponse($response);
}

function getCurrentUser() {
    return AuthMiddleware::getUser();
}

function isLoggedIn() {
    return AuthMiddleware::getUserId() !== null;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2629746) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $prev = $currentPage - 1;
        $html .= "<a href='{$baseUrl}?page={$prev}' class='btn btn-sm btn-outline-primary'>&laquo; Previous</a>";
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? 'btn-primary' : 'btn-outline-primary';
        $html .= "<a href='{$baseUrl}?page={$i}' class='btn btn-sm {$active}'>{$i}</a>";
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $next = $currentPage + 1;
        $html .= "<a href='{$baseUrl}?page={$next}' class='btn btn-sm btn-outline-primary'>Next &raquo;</a>";
    }
    
    $html .= '</nav>';
    return $html;
}
