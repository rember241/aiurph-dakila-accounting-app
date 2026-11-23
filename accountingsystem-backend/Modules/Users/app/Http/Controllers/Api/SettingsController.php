<?php

namespace Modules\Users\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\Users\Http\Requests\Settings\UpdateCompanySettingsRequest;
use Modules\Users\Http\Requests\Settings\UpdateUserSettingsRequest;
use Modules\Users\Http\Requests\Settings\UploadCompanyLogoRequest;
use Modules\Users\Http\Requests\Settings\UploadUserAvatarRequest;
use Modules\Users\Http\Resources\Settings\CompanySettingsResource;
use Modules\Users\Http\Resources\Settings\UserSettingsResource;
use Modules\Users\Services\Settings\CompanySettingsCommandService;
use Modules\Users\Services\Settings\CompanySettingsQueryService;
use Modules\Users\Services\Settings\UserSettingsCommandService;
use Modules\Users\Services\Settings\UserSettingsQueryService;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * Current authenticated user and company settings endpoints.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private readonly UserSettingsQueryService      $userSettingsQueryService,
        private readonly UserSettingsCommandService    $userSettingsCommandService,
        private readonly CompanySettingsQueryService   $companySettingsQueryService,
        private readonly CompanySettingsCommandService $companySettingsCommandService
    )
    {
    }

    public function showUser(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        return response()->json([
            'data' => new UserSettingsResource($this->userSettingsQueryService->current($actor)),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function updateUser(UpdateUserSettingsRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $user = $this->userSettingsCommandService->update($actor, $request->validated());

        return response()->json([
            'data' => new UserSettingsResource($user),
            'message' => 'User settings updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function uploadUserAvatar(UploadUserAvatarRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $avatar = $request->file('avatar');

        if (!$avatar instanceof UploadedFile) {
            return response()->json(['message' => 'Choose a profile picture to upload.'], 422);
        }

        $user = $this->userSettingsCommandService->uploadAvatar($actor, $avatar);

        return response()->json([
            'data' => new UserSettingsResource($user),
            'message' => 'Profile picture updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function deleteUserAvatar(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $user = $this->userSettingsCommandService->deleteAvatar($actor);

        return response()->json([
            'data' => new UserSettingsResource($user),
            'message' => 'Profile picture removed',
        ]);
    }

    public function showCompany(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->companySettingsQueryService->current($actor);

        if ($company === null) {
            return response()->json([
                'message' => 'Complete company setup to continue.',
            ], 409);
        }

        return response()->json([
            'data' => new CompanySettingsResource($company),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function updateCompany(UpdateCompanySettingsRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->companySettingsQueryService->current($actor);

        if ($company === null) {
            return response()->json([
                'message' => 'Complete company setup to continue.',
            ], 409);
        }

        try {
            $company = $this->companySettingsCommandService->update($actor, $company, $request->validated());
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => new CompanySettingsResource($company),
            'message' => 'Company settings updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function uploadCompanyLogo(UploadCompanyLogoRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->companySettingsQueryService->current($actor);

        if ($company === null) {
            return response()->json([
                'message' => 'Complete company setup to continue.',
            ], 409);
        }

        try {
            $logo = $request->file('logo');

            if (!$logo instanceof UploadedFile) {
                return response()->json(['message' => 'Choose a company logo to upload.'], 422);
            }

            $company = $this->companySettingsCommandService->uploadLogo($actor, $company, $logo);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => new CompanySettingsResource($company),
            'message' => 'Company logo updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function deleteCompanyLogo(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $company = $this->companySettingsQueryService->current($actor);

        if ($company === null) {
            return response()->json([
                'message' => 'Complete company setup to continue.',
            ], 409);
        }

        try {
            $company = $this->companySettingsCommandService->deleteLogo($actor, $company);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => new CompanySettingsResource($company),
            'message' => 'Company logo removed',
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        return $actor;
    }
}
