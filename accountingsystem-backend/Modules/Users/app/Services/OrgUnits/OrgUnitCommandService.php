<?php

namespace Modules\Users\Services\OrgUnits;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Users\DTO\OrgUnits\CreateOrgUnitData;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use RuntimeException;
use Throwable;

/**
 * Handles company organization unit commands.
 */
final class OrgUnitCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Create the default root org unit for a company when one does not exist yet.
     */
    public function createRootForCompany(Tenant $company, string $name = 'Head Office'): OrgUnit
    {
        $existingRoot = OrgUnit::query()
            ->where('company_id', $company->getKey())
            ->where('is_root', true)
            ->first();

        if ($existingRoot instanceof OrgUnit) {
            return $existingRoot;
        }

        $data = CreateOrgUnitData::createRoot((string) $company->getKey(), $name);

        $orgUnit = OrgUnit::create([
            'company_id' => $data->companyId,
            'parent_org_unit_id' => $data->parentOrgUnitId,
            'type' => $data->type,
            'name' => $data->name,
            'code' => $this->normalizeCodeOrNull($this->deriveCode($data->name)),
            'location' => null,
            'description' => null,
            'status' => $data->status,
            'is_root' => $data->isRoot,
        ]);

        $this->recordAudit('org_unit.created', $orgUnit, null, $this->snapshot($orgUnit));

        return $orgUnit;
    }

    /**
     * Create a company-scoped organization unit.
     *
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function create(string $companyId, array $payload): OrgUnit
    {
        $parent = $this->resolveParentOrNull($companyId, $payload['parent_org_unit_id'] ?? null);

        $orgUnit = DB::transaction(function () use ($companyId, $payload, $parent): OrgUnit {
            return OrgUnit::create([
                'company_id' => $companyId,
                'parent_org_unit_id' => $parent?->getKey(),
                'type' => (string) $payload['type'],
                'name' => (string) $payload['name'],
                'code' => $this->normalizeCodeOrNull($payload['code'] ?? null) ?? $this->deriveCode((string) $payload['name']),
                'location' => $this->normalizeNullableText($payload['location'] ?? null),
                'description' => $this->normalizeNullableText($payload['description'] ?? null),
                'status' => (string) ($payload['status'] ?? 'active'),
                'is_root' => false,
            ]);
        });

        $this->recordAudit('org_unit.created', $orgUnit, null, $this->snapshot($orgUnit));

        return $orgUnit;
    }

    /**
     * Update a company-scoped organization unit.
     *
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function update(string $companyId, OrgUnit $orgUnit, array $payload): OrgUnit
    {
        $this->ensureSameCompany($companyId, $orgUnit);
        $this->guardRootMutation($orgUnit, $payload);

        $parent = array_key_exists('parent_org_unit_id', $payload)
            ? $this->resolveParentOrNull($companyId, $payload['parent_org_unit_id'])
            : null;

        if ($parent instanceof OrgUnit) {
            $this->assertNoHierarchyCycle($orgUnit, $parent);
        }

        return DB::transaction(function () use ($orgUnit, $payload, $parent): OrgUnit {
            $before = $this->snapshot($orgUnit);

            $updatePayload = $payload;
            if (array_key_exists('code', $payload)) {
                $updatePayload['code'] = $this->normalizeCodeOrNull($payload['code'] ?? null);
            }

            if (array_key_exists('location', $payload)) {
                $updatePayload['location'] = $this->normalizeNullableText($payload['location'] ?? null);
            }

            if (array_key_exists('description', $payload)) {
                $updatePayload['description'] = $this->normalizeNullableText($payload['description'] ?? null);
            }

            if (array_key_exists('parent_org_unit_id', $payload)) {
                $updatePayload['parent_org_unit_id'] = $parent?->getKey();
            }

            $orgUnit->update($updatePayload);
            $fresh = $orgUnit->fresh(['parent']);
            $fresh?->loadCount('children');

            $this->recordAudit('org_unit.updated', $fresh, $before, $this->snapshot($fresh));

            return $fresh;
        });
    }

    /**
     * Transition org-unit status.
     *
     * @throws Throwable
     */
    public function transition(string $companyId, OrgUnit $orgUnit, string $status): OrgUnit
    {
        $this->ensureSameCompany($companyId, $orgUnit);

        $normalizedStatus = strtolower(trim($status));
        if ($normalizedStatus === '' || $normalizedStatus === (string) $orgUnit->status) {
            $orgUnit->loadMissing('parent');
            $orgUnit->loadCount('children');

            return $orgUnit;
        }

        return DB::transaction(function () use ($orgUnit, $normalizedStatus): OrgUnit {
            $before = ['status' => (string) $orgUnit->status];
            $orgUnit->update(['status' => $normalizedStatus]);
            $fresh = $orgUnit->fresh(['parent']);
            $fresh?->loadCount('children');

            $event = $normalizedStatus === 'active' ? 'org_unit.activated' : 'org_unit.deactivated';
            $this->recordAudit($event, $fresh, $before, ['status' => (string) $fresh->status]);

            return $fresh;
        });
    }

    /**
     * Delete a company-scoped org unit.
     *
     * @throws Throwable
     */
    public function delete(string $companyId, OrgUnit $orgUnit): void
    {
        $this->ensureSameCompany($companyId, $orgUnit);

        if ((bool) $orgUnit->is_root) {
            throw new RuntimeException('The root organization unit cannot be deleted.');
        }

        if ($orgUnit->children()->exists()) {
            throw new RuntimeException('Organization unit has child organization units and cannot be deleted.');
        }

        if ($orgUnit->assignedUsers()->exists() || $orgUnit->primaryUsers()->exists()) {
            throw new RuntimeException('Organization unit has assigned members and cannot be deleted.');
        }

        $before = $this->snapshot($orgUnit);
        $this->recordAudit('org_unit.deleted', $orgUnit, $before, []);

        DB::transaction(function () use ($orgUnit): void {
            Role::query()
                ->where('org_unit_id', $orgUnit->getKey())
                ->update([
                    'org_unit_id' => null,
                    'department' => null,
                ]);

            $orgUnit->delete();
        });
    }

    private function resolveParentOrNull(string $companyId, mixed $parentOrgUnitId): ?OrgUnit
    {
        $parentId = is_string($parentOrgUnitId) ? trim($parentOrgUnitId) : '';
        if ($parentId === '') {
            return null;
        }

        $parent = OrgUnit::query()->find($parentId);
        if (!$parent instanceof OrgUnit || (string) $parent->company_id !== $companyId) {
            throw ValidationException::withMessages([
                'parent_org_unit_id' => 'The selected parent organization unit is invalid for this company.',
            ]);
        }

        return $parent;
    }

    private function ensureSameCompany(string $companyId, OrgUnit $orgUnit): void
    {
        if ((string) $orgUnit->company_id === $companyId) {
            return;
        }

        throw new RuntimeException('Organization unit does not belong to the current company.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function guardRootMutation(OrgUnit $orgUnit, array $payload): void
    {
        if (!(bool) $orgUnit->is_root) {
            return;
        }

        if (array_key_exists('parent_org_unit_id', $payload) && $payload['parent_org_unit_id'] !== null) {
            throw ValidationException::withMessages([
                'parent_org_unit_id' => 'The root organization unit cannot have a parent.',
            ]);
        }

        if (array_key_exists('type', $payload) && (string) $payload['type'] !== 'head_office') {
            throw ValidationException::withMessages([
                'type' => 'The root organization unit must remain a head_office unit.',
            ]);
        }
    }

    private function assertNoHierarchyCycle(OrgUnit $orgUnit, OrgUnit $parent): void
    {
        $current = $parent;

        while ($current instanceof OrgUnit) {
            if ((string) $current->getKey() === (string) $orgUnit->getKey()) {
                throw ValidationException::withMessages([
                    'parent_org_unit_id' => 'The selected parent organization unit would create a hierarchy cycle.',
                ]);
            }

            $current = $current->parent()->first();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(OrgUnit $orgUnit): array
    {
        return [
            'company_id' => (string) $orgUnit->company_id,
            'parent_org_unit_id' => $orgUnit->parent_org_unit_id ? (string) $orgUnit->parent_org_unit_id : null,
            'type' => (string) $orgUnit->type,
            'name' => (string) $orgUnit->name,
            'code' => $this->normalizeCodeOrNull($orgUnit->getAttribute('code')) ?? $this->deriveCode((string) $orgUnit->name),
            'location' => $this->normalizeNullableText($orgUnit->getAttribute('location')),
            'description' => $this->normalizeNullableText($orgUnit->getAttribute('description')),
            'status' => (string) $orgUnit->status,
            'is_root' => (bool) $orgUnit->is_root,
        ];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    private function recordAudit(string $action, OrgUnit $orgUnit, ?array $before, ?array $after): void
    {
        $this->auditLogService->recordCompanyScoped(
            $action,
            $orgUnit,
            $before,
            $after,
            [
                'company_id' => (string) $orgUnit->company_id,
                'request_id' => request()->header('X-Request-ID'),
            ]
        );
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeCodeOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function deriveCode(string $name): string
    {
        $derived = Str::of($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '');

        return $derived->value() !== '' ? $derived->value() : 'ORG-UNIT';
    }
}
