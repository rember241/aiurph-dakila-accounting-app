<?php

namespace Modules\Users\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\Users\Http\Resources\CompanyResource;
use Modules\Users\Http\Resources\OrgUnitResource;
use Modules\Users\Http\Resources\TenantResource;
use Modules\Users\Http\Requests\Tenants\IndexTenantRequest;
use Modules\Users\Http\Requests\Tenants\StoreTenantCompanySetupRequest;
use Modules\Users\Http\Requests\Tenants\StoreTenantRequest;
use Modules\Users\Services\Tenants\TenantQueryService;
use Modules\Users\Services\Users\UserOnboardingCommandService;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * Legacy tenant-named bootstrap endpoints.
 *
 * These routes stay in place temporarily for compatibility, but new
 * integrations should move to the company-named surface.
 */
class TenantsController extends Controller
{
    public function __construct(
        private readonly TenantQueryService           $tenantQueryService,
        private readonly UserOnboardingCommandService $userOnboardingCommandService
    )
    {
    }

    public function index(IndexTenantRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $page = $this->tenantQueryService->paginateVisible($actor, $request->validated());

        return response()->json([
            'data' => CompanyResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * Create a new tenant and bootstrap its owner membership.
     *
     * @throws Throwable
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $result = $this->userOnboardingCommandService->createInitialTenant($actor, $request->validated());

        return response()->json([
            'data' => new CompanyResource($result['tenant']),
            'message' => 'Company created',
        ], 201);
    }

    public function show(string $tenant, IndexTenantRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $tenantModel = $this->tenantQueryService->findVisibleOrFail($actor, $tenant);

        return response()->json([
            'data' => new CompanyResource($tenantModel),
        ]);
    }

    /**
     * Create a company workspace for an already-onboarded user.
     *
     * @throws Throwable
     */
    public function storeCompanySetup(StoreTenantCompanySetupRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $result = $this->userOnboardingCommandService->createAdditionalCompanySetup($actor, $request->validated());

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
            'message' => 'Company created',
        ], 201);
    }
}
