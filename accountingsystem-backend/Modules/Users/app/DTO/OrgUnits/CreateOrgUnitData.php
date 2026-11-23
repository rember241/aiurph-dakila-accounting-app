<?php

namespace Modules\Users\DTO\OrgUnits;

/**
 * Input contract for company org-unit creation commands.
 */
final readonly class CreateOrgUnitData
{
    public function __construct(
        public string $companyId,
        public string $name,
        public string $type,
        public ?string $parentOrgUnitId,
        public bool $isRoot,
        public string $status,
    ) {
    }

    public static function createRoot(string $companyId, string $name = 'Head Office'): self
    {
        $resolvedName = trim($name);

        return new self(
            companyId: $companyId,
            name: $resolvedName !== '' ? $resolvedName : 'Head Office',
            type: 'head_office',
            parentOrgUnitId: null,
            isRoot: true,
            status: 'active',
        );
    }
}
