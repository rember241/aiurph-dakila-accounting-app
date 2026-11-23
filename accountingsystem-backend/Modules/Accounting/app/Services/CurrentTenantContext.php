<?php

namespace Modules\Accounting\Services;

use LogicException;

/**
 * Resolves the active company context for shared-db Accounting operations.
 */
final class CurrentTenantContext
{
    /**
     * Get the current company identifier from the request attributes.
     *
     * @throws LogicException
     */
    public function companyId(): string
    {
        $tenantId = request()->attributes->get('company_id')
            ?? request()->attributes->get('tenant_id');

        if (!is_string($tenantId) || trim($tenantId) === '') {
            throw new LogicException('Company context is required for accounting operations.');
        }

        return trim($tenantId);
    }

    /**
     * Backward-compatible alias for existing tenant-named services.
     *
     * @throws LogicException
     */
    public function id(): string
    {
        return $this->companyId();
    }
}
