<?php
/**
 * CSRF Protection Helper
 * SIA 2027 Party Agent & Command Portal
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli' && !headers_sent()) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function verify_csrf_request(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    return verify_csrf_token($token);
}

function require_csrf(): void {
    if (!verify_csrf_request()) {
        http_response_code(403);
        $is_json = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($is_json) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Security token invalid or expired. Please refresh the page and try again.',
                'error_code' => 'CSRF_INVALID'
            ]);
        } else {
            die('Security token (CSRF) verification failed. Please refresh the page and try again.');
        }
        exit;
    }
}

