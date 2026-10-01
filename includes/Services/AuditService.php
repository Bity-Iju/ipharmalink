<?php

/**
 * iPharmaLink :: Audit log service
 * ---------------------------------------------------------------------------
 * Records who did what, to which entity, from where. Every sensitive action
 * (approvals, status changes, price edits, payments, logins) funnels through
 * here so the trail cannot be silently skipped.
 */

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\Request;

final class AuditService
{
    private ?Request $request = null;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * @param array<string,mixed>|null $oldValue
     * @param array<string,mixed>|null $newValue
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValue = null,
        ?array $newValue = null
    ): int {
        $user  = Auth::user();
        $audit = [
            'user_id'     => $user['id'] ?? null,
            'actor_name'  => $user['full_name'] ?? 'System',
            'actor_role'  => $user['role'] ?? 'system',
            'action'      => substr($action, 0, 80),
            'entity_type' => $entityType !== null ? substr($entityType, 0, 40) : null,
            'entity_id'   => $entityId,
            'description' => $description !== null ? substr($description, 0, 500) : null,
            'old_value'   => $this->encode($oldValue),
            'new_value'   => $this->encode($newValue),
            'ip_address'  => $this->ip(),
            'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ];

        try {
            return Database::instance()->insert('audit_logs', $audit);
        } catch (\Throwable $e) {
            // Auditing must never break the business operation it describes.
            \App\Logger::error('Audit write failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Convenience for "field(s) changed on row X".
     *
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    public function logChange(string $action, string $entityType, int $entityId, array $before, array $after): int
    {
        $changed = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before) || (string) $before[$key] !== (string) $value) {
                $changed[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }
        if ($changed === []) {
            return 0;
        }
        return $this->log(
            $action,
            $entityType,
            $entityId,
            'Changed: ' . implode(', ', array_keys($changed)),
            null,
            $changed
        );
    }

    /**
     * Admin view with filters. Returns a paginated result set.
     *
     * @param  array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>, total:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'a.action LIKE :action';
            $params['action'] = $filters['action'] . '%';
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'a.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $where[] = 'a.entity_id = :entity_id';
            $params['entity_id'] = (int) $filters['entity_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'a.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'a.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.action LIKE :q OR a.description LIKE :q OR a.actor_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Database::instance()->value("SELECT COUNT(*) FROM audit_logs a{$whereSql}", $params);
        $page   = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::instance()->all(
            "SELECT a.* FROM audit_logs a{$whereSql} ORDER BY a.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @param array<string,mixed>|null $value */
    private function encode(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // Never store secrets or raw password material in the audit trail.
        foreach (['password', 'password_hash', 'remember_token', 'two_factor_secret', 'credentials'] as $secret) {
            unset($value[$secret]);
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function ip(): string
    {
        return $this->request?->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
