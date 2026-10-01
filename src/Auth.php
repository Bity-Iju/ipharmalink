<?php

declare(strict_types=1);

namespace IpharmaLink;

use PDO;
use RuntimeException;

final class Auth
{
    public static function register(PDO $db, array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $role = (string) ($input['role'] ?? 'customer');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('A valid email and password of at least 8 characters are required.');
        }
        $allowed = ['supplier', 'retail_pharmacy', 'customer'];
        if (!in_array($role, $allowed, true)) {
            throw new RuntimeException('Unsupported registration role.');
        }

        $roleQuery = $db->prepare('SELECT id FROM roles WHERE name = ?');
        $roleQuery->execute([$role]);
        $roleId = $roleQuery->fetchColumn();
        if (!$roleId) {
            throw new RuntimeException('Role is not configured.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $roleId,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                trim((string) ($input['first_name'] ?? '')),
                trim((string) ($input['last_name'] ?? '')),
                trim((string) ($input['phone'] ?? '')) ?: null,
                $role === 'customer' ? 'active' : 'pending',
            ]);
            $userId = (int) $db->lastInsertId();

            if (in_array($role, ['supplier', 'retail_pharmacy'], true)) {
                $org = $db->prepare('INSERT INTO organizations (owner_user_id, type, business_name, slug, email, phone) VALUES (?, ?, ?, ?, ?, ?)');
                $business = trim((string) ($input['business_name'] ?? ($input['first_name'] . ' Pharmacy')));
                $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $business), '-')) . '-' . $userId;
                $org->execute([$userId, $role === 'supplier' ? 'supplier' : 'retail_pharmacy', $business, $slug, $email, $input['phone'] ?? null]);
                $orgId = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO organization_users (organization_id, user_id, is_primary) VALUES (?, ?, 1)')->execute([$orgId, $userId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return self::issueToken($db, $userId);
    }

    public static function login(PDO $db, array $input): array
    {
        $stmt = $db->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1');
        $stmt->execute([strtolower(trim((string) ($input['email'] ?? '')))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify((string) ($input['password'] ?? ''), $user['password_hash'])) {
            throw new RuntimeException('Invalid email or password.');
        }
        if ($user['status'] !== 'active') {
            throw new RuntimeException('This account is awaiting approval or is unavailable.');
        }
        $db->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
        return self::issueToken($db, (int) $user['id']);
    }

    public static function userFromBearer(PDO $db, ?string $header): ?array
    {
        if (!$header || !preg_match('/Bearer\s+(.+)/i', $header, $matches)) return null;
        $stmt = $db->prepare('SELECT u.id, u.email, u.first_name, u.last_name, r.name AS role_name FROM auth_tokens t JOIN users u ON u.id = t.user_id JOIN roles r ON r.id = u.role_id WHERE t.token_hash = ? AND t.expires_at > CURRENT_TIMESTAMP AND u.status = "active"');
        $stmt->execute([hash('sha256', trim($matches[1]))]);
        return $stmt->fetch() ?: null;
    }

    private static function issueToken(PDO $db, int $userId): array
    {
        $plain = bin2hex(random_bytes(32));
        $stmt = $db->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))');
        $stmt->execute([$userId, hash('sha256', $plain)]);
        $user = $db->prepare('SELECT u.id, u.email, u.first_name, u.last_name, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $user->execute([$userId]);
        return ['token' => $plain, 'user' => $user->fetch()];
    }
}
