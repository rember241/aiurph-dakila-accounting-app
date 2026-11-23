<?php

namespace Modules\Users\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\Users\Http\Requests\Companies\IndexCompanyRequest;
use Modules\Users\Http\Resources\CompanyResource;
use Modules\Users\Services\Companies\CompanyQueryService;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Company read endpoints.
 *
 * These routes expose the new company-domain language while reusing the
 * current shared storage safely.
 */
final class CompaniesController extends Controller
{
    public function __construct(
        private readonly CompanyQueryService $companyQueryService
    )
    {
    }

    public function current(IndexCompanyRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $company = $this->companyQueryService->currentForUser($actor);
        if ($company === null) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new CompanyResource($company),
        ]);
    }

    public function index(IndexCompanyRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $page = $this->companyQueryService->paginateVisible($actor, $request->validated());

        return response()->json([
            'data' => CompanyResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(string $company, IndexCompanyRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Unauthenticated.');
        }

        $companyModel = $this->companyQueryService->findVisibleOrFail($actor, $company);

        return response()->json([
            'data' => new CompanyResource($companyModel),
        ]);
    }
}
