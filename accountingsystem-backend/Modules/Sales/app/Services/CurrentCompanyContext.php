<?php

namespace Modules\Sales\Services;

use LogicException;

/**
 * Resolves the active company context for company-scoped sales operations.
 */
final class CurrentCompanyContext
{
    /**
     * @throws LogicException
     */
    public function companyId(): string
    {
        $companyId = request()->attributes->get('company_id')
            ?? request()->attributes->get('tenant_id');

        if (!is_string($companyId) || trim($companyId) === '') {
            throw new LogicException('Company context is required for sales invoice operations.');
        }

        return trim($companyId);
    }
}
