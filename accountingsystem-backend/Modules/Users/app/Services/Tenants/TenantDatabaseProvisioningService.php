<?php

namespace Modules\Users\Services\Tenants;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantDatabase;

/**
 * Creates the control-plane record used to track tenant database provisioning.
 *
 * The actual physical database provisioning is intentionally deferred. This
 * service only persists the initial "provisioning" record so the workflow can
 * move to an async job later without changing the tenant bootstrap contract.
 */
class TenantDatabaseProvisioningService
{
    private const DEFAULT_DRIVER = 'pgsql';
    private const DEFAULT_HOST = 'pending-provisioning';
    private const DEFAULT_PORT = 5432;

    public function __construct(
        private readonly AuditLogService $auditLogService
    )
    {
    }

    /**
     * Create the initial tenant database provisioning record.
     */
    public function createProvisioningRecord(Tenant $tenant): TenantDatabase
    {
        $database = TenantDatabase::create([
            'tenant_id' => (string)$tenant->getKey(),
            'driver' => self::DEFAULT_DRIVER,
            'host' => self::DEFAULT_HOST,
            'port' => self::DEFAULT_PORT,
            'database_name' => $this->plannedDatabaseName($tenant),
            'database_username' => $this->plannedDatabaseUsername($tenant),
            'database_password_encrypted' => Crypt::encryptString(Str::random(40)),
            'database_schema' => null,
            'status' => 'provisioning',
            'provisioned_at' => null,
            'last_checked_at' => null,
            'meta' => [
                'provisioning_mode' => 'deferred',
                'requested_at' => now()->toISOString(),
                'requested_via' => 'tenant.create',
                'provisioning_job' => null,
            ],
        ]);

        $this->auditLogService->recordTenantScoped(
            'tenant_database.provisioning_requested',
            $database,
            null,
            [
                'tenant_id' => (string)$database->tenant_id,
                'driver' => (string)$database->driver,
                'host' => (string)$database->host,
                'port' => (int)$database->port,
                'database_name' => (string)$database->database_name,
                'database_username' => (string)$database->database_username,
                'status' => (string)$database->status,
                'meta' => $database->meta ?? [],
            ],
            [
                'request_id' => request()->header('X-Request-ID'),
                'tenant_id' => $tenant->getKey(),
            ]
        );

        return $database;
    }

    /**
     * Build the planned database name stored before the actual provisioner runs.
     */
    private function plannedDatabaseName(Tenant $tenant): string
    {
        $base = 'tenant_'.$tenant->slug;

        return $this->truncateWithSuffix($base, 120, substr((string)$tenant->getKey(), 0, 8));
    }

    /**
     * Build the planned database username stored before the actual provisioner runs.
     */
    private function plannedDatabaseUsername(Tenant $tenant): string
    {
        $base = 'tenant_'.$tenant->slug.'_app';

        return $this->truncateWithSuffix($base, 120, substr((string)$tenant->getKey(), 0, 8));
    }

    /**
     * Truncate a value while keeping a deterministic suffix for later traceability.
     */
    private function truncateWithSuffix(string $value, int $maxLength, string $suffix): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        $normalizedSuffix = trim($suffix, '_');
        $headLength = max(1, $maxLength - strlen($normalizedSuffix) - 1);

        return substr($value, 0, $headLength).'_'.$normalizedSuffix;
    }
}
