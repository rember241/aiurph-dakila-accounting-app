<?php

namespace Modules\Users\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ModuleAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Http\Requests\Users\CompleteCompanyOnboardingRequest;
use Modules\Users\Http\Requests\Users\IndexUserRequest;
use Modules\Users\Http\Requests\Users\StoreUserRequest;
use Modules\Users\Http\Requests\Users\TransitionUserStatusRequest;
use Modules\Users\Http\Requests\Users\UpdateUserRequest;
use Modules\Users\Http\Resources\CompanyResource;
use Modules\Users\Http\Resources\OrgUnitResource;
use Modules\Users\Http\Resources\TenantResource;
use Modules\Users\Http\Resources\UserResource;
use Modules\Users\Models\Tenant;
use Modules\Users\Services\Companies\CompanyContextService;
use Modules\Users\Services\Users\UserCommandService;
use Modules\Users\Services\Users\UserOnboardingCommandService;
use Modules\Users\Services\Users\UserOnboardingQueryService;
use Modules\Users\Services\Users\UserQueryService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * UsersController
 *
 * API endpoints for enterprise user management.
 *
 * Key design points:
 * - Input is validated via FormRequest.
 * - Writes are routed through a command service (transaction + audit).
 * - Reads are routed through a query service (filters + pagination).
 */
class UsersController extends Controller
{
    public function __construct(
        private readonly UserQueryService $userQueryService,
        private readonly UserCommandService $userCommandService,
        private readonly UserOnboardingQueryService $userOnboardingQueryService,
        private readonly UserOnboardingCommandService $userOnboardingCommandService
    ) {}

    public function onboardingStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        return response()->json([
            'data' => $this->userOnboardingQueryService->status($user),
        ]);
    }

    public function currentAccess(Request $request, CompanyContextService $companyContextService): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $tenant = $request->attributes->get('company')
            ?? $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(404, 'Company context not found.');
        }

        return response()->json([
            'data' => [
                'company_id' => (string) $tenant->getKey(),
                'tenant_id' => (string) $tenant->getKey(),
                'full_access' => $companyContextService->userHasFullAccess($user, $tenant),
                'abilities' => $companyContextService->userAbilities($user, $tenant),
                'disabled_modules' => ModuleAvailability::disabledModules(),
            ],
        ]);
    }

    /**
     * @throws Throwable
     */
    public function completeCompanySetup(CompleteCompanyOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $result = $this->userOnboardingCommandService->completeCompanySetup($user, $request->validated());

        return response()->json([
            'data' => [
                'tenant' => new TenantResource($result['tenant']),
                'company' => new CompanyResource($result['tenant']),
                'organization' => [
                    'root_org_unit' => $result['organization']['root_org_unit'] === null
                        ? null
                        : new OrgUnitResource($result['organization']['root_org_unit']),
                ],
                'fiscal_year' => $result['fiscal_year'],
                'accounts_setup' => $result['accounts_setup'],
            ],
            'message' => 'Company setup completed',
        ], 201);
    }

    public function index(IndexUserRequest $request): JsonResponse
    {
        $page = $this->userQueryService->paginate($this->tenantIdFromRequest($request), $request->validated());

        return response()->json([
            'data' => UserResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userCommandService->create($this->tenantIdFromRequest($request), $request->validated());

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'User created',
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user = $this->resolveTenantScopedUser($request, $user);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->resolveTenantScopedUser($request, $user);

        try {
            $user = $this->userCommandService->update($this->tenantIdFromRequest($request), $user, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'User updated',
        ]);
    }

    /**
     * Activate/deactivate a user.
     *
     * @throws Throwable
     */
    public function transition(TransitionUserStatusRequest $request, User $user): JsonResponse
    {
        $user = $this->resolveTenantScopedUser($request, $user);

        try {
            $user = $this->userCommandService->transition(
                $this->tenantIdFromRequest($request),
                $user,
                (string) $request->validated()['status']
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'User status updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $tenantId = $this->tenantIdFromRequest($request);
        $user = $this->userQueryService->findVisibleOrFail($tenantId, $user);

        try {
            $this->userCommandService->delete($tenantId, $user);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'User removed from company']);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->userQueryService->stats($this->tenantIdFromRequest($request)),
        ]);
    }

    private function resolveTenantScopedUser(Request $request, User $user): User
    {
        return $this->userQueryService->findVisibleOrFail($this->tenantIdFromRequest($request), $user);
    }

    private function tenantIdFromRequest(Request $request): string
    {
        $tenantId = $request->attributes->get('company_id')
            ?? $request->attributes->get('tenant_id');

        if (! is_string($tenantId) || trim($tenantId) === '') {
            abort(404, 'Company context not found.');
        }

        return $tenantId;
    }
}
