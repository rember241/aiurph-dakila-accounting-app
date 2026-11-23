<?php

namespace Modules\Users\Services\Settings;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Users\Services\Companies\CompanyContextService;
use Throwable;

/**
 * Handles current-user settings updates.
 */
final class UserSettingsCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CompanyContextService $companyContextService
    ) {
    }

    /**
     * @param array{name:string,email:string,phone:?string,currency_code:string} $payload
     *
     * @throws Throwable
     */
    public function update(User $user, array $payload): User
    {
        $before = $this->snapshot($user);

        $updatedUser = DB::transaction(function () use ($user, $payload): User {
            $user->fill([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'currency_code' => (string) $payload['currency_code'],
            ]);

            if ($user->isDirty()) {
                $user->save();
            }

            return $user->fresh(['company', 'primaryOrgUnit']) ?? $user->loadMissing(['company', 'primaryOrgUnit']);
        });

        $companyId = $this->resolveCompanyId($updatedUser);
        $auditMeta = [
            'request_id' => request()->header('X-Request-ID'),
        ];

        if ($companyId !== '') {
            $auditMeta['company_id'] = $companyId;

            $this->auditLogService->recordCompanyScoped(
                'user_settings.updated',
                $updatedUser,
                $before,
                $this->snapshot($updatedUser),
                $auditMeta
            );
        } else {
            $this->auditLogService->record(
                'user_settings.updated',
                $updatedUser,
                $before,
                $this->snapshot($updatedUser),
                $auditMeta
            );
        }

        return $updatedUser;
    }

    /**
     * @throws Throwable
     */
    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'avatar' => ['The profile picture dimensions could not be read.'],
            ]);
        }

        $before = $this->snapshot($user);
        $oldPath = $user->avatar_path === null ? null : (string) $user->avatar_path;
        $extension = strtolower($file->extension() ?: 'png');
        $path = $file->storeAs(
            'user-avatars/' . (string) $user->getKey(),
            'avatar.' . $extension,
            'public'
        );

        $updatedUser = DB::transaction(function () use ($user, $file, $dimensions, $path): User {
            $user->fill([
                'avatar_url' => null,
                'avatar_path' => $path,
                'avatar_mime' => $file->getMimeType(),
                'avatar_size' => $file->getSize(),
                'avatar_width' => (int) $dimensions[0],
                'avatar_height' => (int) $dimensions[1],
                'avatar_updated_at' => now(),
            ]);
            $user->save();

            return $user->fresh(['company', 'primaryOrgUnit']) ?? $user->loadMissing(['company', 'primaryOrgUnit']);
        });

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->recordUserSettingsAudit('user_settings.avatar_uploaded', $updatedUser, $before);

        return $updatedUser;
    }

    /**
     * @throws Throwable
     */
    public function deleteAvatar(User $user): User
    {
        $before = $this->snapshot($user);
        $oldPath = $user->avatar_path === null ? null : (string) $user->avatar_path;

        $updatedUser = DB::transaction(function () use ($user): User {
            $user->fill([
                'avatar_url' => null,
                'avatar_path' => null,
                'avatar_mime' => null,
                'avatar_size' => null,
                'avatar_width' => null,
                'avatar_height' => null,
                'avatar_updated_at' => null,
            ]);
            $user->save();

            return $user->fresh(['company', 'primaryOrgUnit']) ?? $user->loadMissing(['company', 'primaryOrgUnit']);
        });

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->recordUserSettingsAudit('user_settings.avatar_deleted', $updatedUser, $before);

        return $updatedUser;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'id' => (string) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'phone' => $user->phone === null ? null : (string) $user->phone,
            'currency_code' => (string) ($user->currency_code ?: 'PHP'),
            'avatar_url' => $user->avatar_url === null ? null : (string) $user->avatar_url,
            'avatar_path' => $user->avatar_path === null ? null : (string) $user->avatar_path,
            'avatar_mime' => $user->avatar_mime === null ? null : (string) $user->avatar_mime,
            'avatar_size' => $user->avatar_size,
            'avatar_width' => $user->avatar_width,
            'avatar_height' => $user->avatar_height,
            'avatar_updated_at' => $user->avatar_updated_at?->toISOString(),
            'primary_org_unit_id' => $user->primary_org_unit_id ? (string) $user->primary_org_unit_id : null,
        ];
    }

    private function recordUserSettingsAudit(string $action, User $user, array $before): void
    {
        $companyId = $this->resolveCompanyId($user);
        $auditMeta = [
            'request_id' => request()->header('X-Request-ID'),
        ];

        if ($companyId !== '') {
            $auditMeta['company_id'] = $companyId;

            $this->auditLogService->recordCompanyScoped(
                $action,
                $user,
                $before,
                $this->snapshot($user),
                $auditMeta
            );

            return;
        }

        $this->auditLogService->record(
            $action,
            $user,
            $before,
            $this->snapshot($user),
            $auditMeta
        );
    }

    private function resolveCompanyId(User $user): string
    {
        $company = $this->companyContextService->resolveCurrentCompany($user);

        if ($company) {
            return (string) $company->getKey();
        }

        return is_string($user->company_id) ? trim($user->company_id) : '';
    }
}
