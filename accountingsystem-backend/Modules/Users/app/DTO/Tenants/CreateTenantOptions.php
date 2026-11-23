<?php

namespace Modules\Users\DTO\Tenants;

/**
 * Options for tenant bootstrap command execution.
 */
final readonly class CreateTenantOptions
{
    public function __construct(
        public bool $createProvisioningRecord,
    )
    {
    }

    /**
     * Preserve the default tenant bootstrap behavior.
     */
    public static function defaults(): self
    {
        return new self(createProvisioningRecord: true);
    }

    /**
     * Shared-db onboarding should not depend on provisioning records.
     */
    public static function forSharedDatabaseOnboarding(): self
    {
        return new self(createProvisioningRecord: false);
    }
}
