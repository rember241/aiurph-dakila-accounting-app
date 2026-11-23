<?php

namespace Modules\Users\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Http\Requests\OrgUnits\IndexOrgUnitRequest;
use Modules\Users\Http\Requests\OrgUnits\StoreOrgUnitRequest;
use Modules\Users\Http\Requests\OrgUnits\TransitionOrgUnitStatusRequest;
use Modules\Users\Http\Requests\OrgUnits\UpdateOrgUnitRequest;
use Modules\Users\Http\Resources\OrgUnitResource;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Services\OrgUnits\OrgUnitCommandService;
use Modules\Users\Services\OrgUnits\OrgUnitQueryService;
use RuntimeException;
use Throwable;

/**
 * Company organization unit APIs.
 */
final class OrgUnitsController extends Controller
{
    public function __construct(
        private readonly OrgUnitQueryService   $orgUnitQueryService,
        private readonly OrgUnitCommandService $orgUnitCommandService,
    )
    {
    }

    public function index(IndexOrgUnitRequest $request): JsonResponse
    {
        $page = $this->orgUnitQueryService->paginate($this->companyIdFromRequest($request), $request->validated());

        return response()->json([
            'data' => OrgUnitResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreOrgUnitRequest $request): JsonResponse
    {
        $orgUnit = $this->orgUnitCommandService->create($this->companyIdFromRequest($request), $request->validated());
        $orgUnit = $this->orgUnitQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $orgUnit);

        return response()->json([
            'data' => new OrgUnitResource($orgUnit),
            'message' => 'Organization unit created',
        ], 201);
    }

    public function show(Request $request, OrgUnit $orgUnit): JsonResponse
    {
        $orgUnit = $this->resolveCompanyScopedOrgUnit($request, $orgUnit);

        return response()->json([
            'data' => new OrgUnitResource($orgUnit),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateOrgUnitRequest $request, OrgUnit $orgUnit): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $orgUnit = $this->resolveCompanyScopedOrgUnit($request, $orgUnit);

        try {
            $orgUnit = $this->orgUnitCommandService->update($companyId, $orgUnit, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $orgUnit = $this->orgUnitQueryService->findVisibleOrFail($companyId, $orgUnit);

        return response()->json([
            'data' => new OrgUnitResource($orgUnit),
            'message' => 'Organization unit updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function transition(TransitionOrgUnitStatusRequest $request, OrgUnit $orgUnit): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $orgUnit = $this->resolveCompanyScopedOrgUnit($request, $orgUnit);

        try {
            $orgUnit = $this->orgUnitCommandService->transition($companyId, $orgUnit, (string)$request->validated()['status']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $orgUnit = $this->orgUnitQueryService->findVisibleOrFail($companyId, $orgUnit);

        return response()->json([
            'data' => new OrgUnitResource($orgUnit),
            'message' => 'Organization unit status updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, OrgUnit $orgUnit): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $orgUnit = $this->resolveCompanyScopedOrgUnit($request, $orgUnit);

        try {
            $this->orgUnitCommandService->delete($companyId, $orgUnit);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'child organization units')
            || str_contains($e->getMessage(), 'assigned members')
                ? 409
                : 403;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Organization unit deleted',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->orgUnitQueryService->stats($this->companyIdFromRequest($request)),
        ]);
    }

    private function resolveCompanyScopedOrgUnit(Request $request, OrgUnit $orgUnit): OrgUnit
    {
        return $this->orgUnitQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $orgUnit);
    }

    private function companyIdFromRequest(Request $request): string
    {
        $companyId = $request->attributes->get('company_id')
            ?? $request->attributes->get('tenant_id');

        if (!is_string($companyId) || trim($companyId) === '') {
            abort(404, 'Company context not found.');
        }

        return trim($companyId);
    }
}
