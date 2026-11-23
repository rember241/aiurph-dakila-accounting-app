<?php

namespace Modules\Users\Services\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Users\Models\OrgUnit;

/**
 * Manages the user's authoritative company and org-unit assignment state.
 *
 * Membership rows remain in place for role and lifecycle handling, but the
 * single-company runtime model uses `users.company_id` and the explicit org
 * unit assignment table as the authoritative assignment source.
 */
final class UserCompanyAssignmentService
{
    /**
     * Attach the user to the given company or confirm the existing assignment.
     */
    public function assignUserToCompany(User $user, string $companyId): void
    {
        $currentCompanyId = is_string($user->company_id) ? trim($user->company_id) : '';

        if ($currentCompanyId !== '' && $currentCompanyId !== $companyId) {
            throw ValidationException::withMessages([
                'email' => 'This user is already assigned to another company.',
            ]);
        }

        if ($currentCompanyId === $companyId) {
            return;
        }

        $user->company_id = $companyId;
        $user->save();
    }

    /**
     * Clear the user's company and org-unit assignment state.
     */
    public function clearUserCompanyContext(User $user): void
    {
        $user->orgUnits()->detach();
        $user->forceFill([
            'company_id' => null,
            'primary_org_unit_id' => null,
        ])->save();
    }

    /**
     * Sync the user's primary and additional org-unit assignments.
     *
     * @param array<int, string> $orgUnitIds
     */
    public function syncOrgUnits(User $user, string $companyId, ?string $primaryOrgUnitId, array $orgUnitIds = []): void
    {
        $normalizedPrimaryOrgUnitId = $this->normalizeOptionalId($primaryOrgUnitId);
        $normalizedOrgUnitIds = $this->normalizeIds($orgUnitIds);

        if ($normalizedPrimaryOrgUnitId !== null && !in_array($normalizedPrimaryOrgUnitId, $normalizedOrgUnitIds, true)) {
            $normalizedOrgUnitIds[] = $normalizedPrimaryOrgUnitId;
        }

        if ($normalizedPrimaryOrgUnitId === null && $normalizedOrgUnitIds !== []) {
            throw ValidationException::withMessages([
                'primary_org_unit_id' => 'A primary org unit is required when org unit assignments are provided.',
            ]);
        }

        if ($normalizedOrgUnitIds === []) {
            $user->orgUnits()->detach();
            if ($user->primary_org_unit_id !== null) {
                $user->primary_org_unit_id = null;
                $user->save();
            }

            return;
        }

        $resolvedOrgUnitIds = OrgUnit::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $normalizedOrgUnitIds)
            ->pluck('id')
            ->map(static fn (string $id): string => (string) $id)
            ->values()
            ->all();

        sort($normalizedOrgUnitIds);
        $sortedResolvedOrgUnitIds = $resolvedOrgUnitIds;
        sort($sortedResolvedOrgUnitIds);

        if ($normalizedOrgUnitIds !== $sortedResolvedOrgUnitIds) {
            throw ValidationException::withMessages([
                'org_unit_ids' => 'One or more org unit assignments are outside the current company.',
            ]);
        }

        if ($normalizedPrimaryOrgUnitId !== null && !in_array($normalizedPrimaryOrgUnitId, $resolvedOrgUnitIds, true)) {
            throw ValidationException::withMessages([
                'primary_org_unit_id' => 'Primary org unit must belong to the current company.',
            ]);
        }

        $syncPayload = [];
        foreach ($resolvedOrgUnitIds as $orgUnitId) {
            $syncPayload[$orgUnitId] = [
                'company_id' => $companyId,
            ];
        }

        $user->orgUnits()->sync($syncPayload);

        if ((string) $user->primary_org_unit_id !== (string) $normalizedPrimaryOrgUnitId) {
            $user->primary_org_unit_id = $normalizedPrimaryOrgUnitId;
            $user->save();
        }
    }

    private function normalizeOptionalId(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function normalizeIds(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $candidate = trim($value);
            if ($candidate === '') {
                continue;
            }

            $normalized[$candidate] = $candidate;
        }

        return array_values($normalized);
    }
}
