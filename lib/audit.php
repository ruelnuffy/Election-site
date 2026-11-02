<?php
/**
 * Audit Logging Helper
 * SIA 2027 Party Agent & Command Portal
 */

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // Validate IP format
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '127.0.0.1';
}

function audit_log(
    PDO $pdo,
    string $actor_type,
    ?int $actor_id,
    string $action,
    ?string $entity = null,
    ?int $entity_id = null,
    mixed $meta = null
): bool {
    try {
        $ip = get_client_ip();
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
        $meta_json = $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (actor_type, actor_id, action, entity, entity_id, ip, user_agent, meta_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        return $stmt->execute([
            $actor_type,
            $actor_id,
            $action,
            $entity,
            $entity_id,
            $ip,
            $user_agent,
            $meta_json
        ]);
    } catch (Throwable $e) {
        error_log("Audit log failure: " . $e->getMessage());
        return false;
    }
}

