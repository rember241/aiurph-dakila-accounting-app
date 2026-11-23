<?php

namespace Modules\Users\Services\Roles;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;

/**
 * Keeps tenant membership status aligned with inactive role assignments.
 *
 * Rule set:
 * - If any assigned role is inactive, the membership is forced to `inactive`.
 * - The original membership status is preserved once in
 *   `status_before_role_suspension`.
 * - When no assigned inactive roles remain, the preserved status is restored.
 */
final class RoleMembershipStatusService
{
    public function syncMembersForRole(Role $role): void
    {
        /** @var Collection<int, string> $membershipIds */
        $membershipIds = $role->memberships()
            ->pluck('tenant_memberships.id')
            ->map(static fn($id) => (string) $id);

        if ($membershipIds->isEmpty()) {
            return;
        }

        TenantMembership::query()
            ->whereIn('id', $membershipIds->all())
            ->with(['roles:id,status'])
            ->get()
            ->each(fn(TenantMembership $membership) => $this->syncMembership($membership));
    }

    public function syncMembership(TenantMembership $membership): TenantMembership
    {
        $membership->unsetRelation('roles');
        $membership->load('roles');

        $hasInactiveRole = $membership->roles->contains(
            fn(Role $role) => strtolower(trim((string) $role->status)) === 'inactive'
        );

        $previousStatus = (string) $membership->status;
        $baselineStatus = $membership->status_before_role_suspension;

        if ($hasInactiveRole) {
            if ($baselineStatus === null) {
                $membership->status_before_role_suspension = $previousStatus;
            }

            $membership->status = 'inactive';
        } elseif ($baselineStatus !== null) {
            $membership->status = (string) $baselineStatus;
            $membership->status_before_role_suspension = null;
        }

        if ($membership->isDirty(['status', 'status_before_role_suspension'])) {
            $membership->save();
        }

        if (
            $previousStatus !== 'inactive'
            && (string) $membership->status === 'inactive'
            && !$this->userHasAnyActiveAccess((int) $membership->user_id)
        ) {
            User::query()->find($membership->user_id)?->tokens()->delete();
        }

        return $membership;
    }

    private function userHasAnyActiveAccess(int $userId): bool
    {
        if (Tenant::query()->where('owner_user_id', $userId)->exists()) {
            return true;
        }

        return TenantMembership::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}
