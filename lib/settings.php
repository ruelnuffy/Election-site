<?php
/**
 * Application Settings Helper
 * SIA 2027 Party Agent & Command Portal
 */

function get_setting(PDO $pdo, string $key, mixed $default = null): mixed {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            $cache[$key] = $val;
            return $val;
        }
    } catch (Throwable $e) {
        // Fallback to default if table does not exist yet
    }
    return $default;
}

function set_setting(PDO $pdo, string $key, string $value): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (key, value) VALUES (?, ?)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value
        ");
        return $stmt->execute([$key, $value]);
    } catch (Throwable $e) {
        error_log("Failed to set setting {$key}: " . $e->getMessage());
        return false;
    }
}

function get_all_settings(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT key, value FROM settings");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        return [];
    }
}

