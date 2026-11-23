<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use LogicException;


/**
 * AuditLogService class.
 *
 * This service is responsible for recording audit logs of changes made to the application.
 */
class AuditLogService
{
    /**
     * Record an audit log.
     *
     * @param string $action The action performed on the entity (e.g. created, updated, deleted).
     * @param Model $entity The entity that was acted upon. (Example Account model)
     * @param array|null $before The state of the entity before the action was performed.
     * @param array|null $after The state of the entity after the action was performed.
     * @param array $meta Additional metadata to be stored with the audit log.
     *
     * @return void
     */
    public function record(
        string $action,
        Model  $entity,
        ?array $before = null,
        ?array $after = null,
        array  $meta = []
    ): void
    {
        [$tenantId, $resolvedMeta] = $this->resolveContext($meta);

        $this->persist($action, $entity, $before, $after, $tenantId, $resolvedMeta);
    }

    /**
     * Record an audit log that must include tenant scope.
     *
     * @throws LogicException
     */
    public function recordTenantScoped(
        string $action,
        Model $entity,
        ?array $before = null,
        ?array $after = null,
        array $meta = []
    ): void
    {
        $this->recordCompanyScoped($action, $entity, $before, $after, $meta);
    }

    /**
     * Record an audit log that must include company scope.
     *
     * Shared storage remains in the legacy `tenant_id` audit column for
     * compatibility, but the resolved context is now company-centric.
     *
     * @throws LogicException
     */
    public function recordCompanyScoped(
        string $action,
        Model $entity,
        ?array $before = null,
        ?array $after = null,
        array $meta = []
    ): void
    {
        [$tenantId, $resolvedMeta] = $this->resolveContext($meta);

        if ($tenantId === null || $tenantId === '') {
            throw new LogicException("Company-scoped audit action [{$action}] requires company context.");
        }

        $this->persist($action, $entity, $before, $after, $tenantId, $resolvedMeta);
    }

    /**
     * Resolves audit scope from explicit meta first, then the current request.
     *
     * The audit table still stores the scope in `tenant_id`, but callers should
     * pass `company_id` for new code. Both values end up in the same company
     * context while the tenant naming compatibility layer is still alive.
     *
     * @param array<string, mixed> $meta
     * @return array{0: mixed, 1: array<string, mixed>}
     */
    private function resolveContext(array $meta): array
    {
        $tenantId = null;
        if (array_key_exists('company_id', $meta)) {
            $tenantId = $meta['company_id'];
            unset($meta['company_id']);
        }

        if (array_key_exists('tenant_id', $meta)) {
            $tenantId = $meta['tenant_id'];
            unset($meta['tenant_id']);
        }

        $defaultMeta = [];
        if (app()->bound('request')) {
            $req = request();
            $defaultMeta = [
                'request_id' => $req->header('X-Request-ID'),
                'ip' => $req->ip(),
                'user_agent' => $req->userAgent(),
            ];

            $tenantId = $tenantId
                ?? $req->attributes->get('company_id')
                ?? $req->attributes->get('tenant_id')
                ?? $req->header('X-Company-ID')
                ?? $req->header('X-Tenant-ID');
        }

        if ($tenantId !== null && $tenantId !== '' && !array_key_exists('company_id', $meta)) {
            $meta['company_id'] = (string) $tenantId;
        }

        return [$tenantId, array_merge($defaultMeta, $meta)];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed> $meta
     */
    private function persist(
        string $action,
        Model $entity,
        ?array $before,
        ?array $after,
        mixed $tenantId,
        array $meta
    ): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => $tenantId,
            'action' => $action,
            'auditable_type' => $entity->getMorphClass(),
            'auditable_id' => (string)$entity->getKey(),
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ]);
    }
}
