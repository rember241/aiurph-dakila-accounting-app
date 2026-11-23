<?php

namespace Modules\Users\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Http\Requests\Roles\IndexRoleRequest;
use Modules\Users\Http\Requests\Roles\StoreRoleRequest;
use Modules\Users\Http\Requests\Roles\TransitionRoleStatusRequest;
use Modules\Users\Http\Requests\Roles\UpdateRoleRequest;
use Modules\Users\Http\Resources\RoleResource;
use Modules\Users\Models\Role;
use Modules\Users\Services\Roles\RoleCommandService;
use Modules\Users\Services\Roles\RoleQueryService;
use RuntimeException;
use Throwable;

/**
 * RolesController
 *
 * API endpoints for role management and access governance.
 */
class RolesController extends Controller
{
    public function __construct(
        private readonly RoleQueryService   $roleQueryService,
        private readonly RoleCommandService $roleCommandService
    )
    {
    }

    public function index(IndexRoleRequest $request): JsonResponse
    {
        $page = $this->roleQueryService->paginate([
            ...$request->validated(),
            'company_id' => $this->companyIdFromRequest($request),
        ]);

        return response()->json([
            'data' => RoleResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleCommandService->create([
            ...$request->validated(),
            'company_id' => $this->companyIdFromRequest($request),
        ]);
        $role->load('orgUnit');

        return response()->json([
            'data' => new RoleResource($role),
            'message' => 'Role created',
        ], 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $role = $this->resolveTenantScopedRole($request, $role);
        $role->load(['orgUnit']);
        $role->loadCount(['users', 'memberships']);

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->resolveTenantScopedRole($request, $role);

        try {
            $role = $this->roleCommandService->update($role, [
                ...$request->validated(),
                'company_id' => $this->companyIdFromRequest($request),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
        $role->load('orgUnit');
        $role->loadCount(['users', 'memberships']);

        return response()->json([
            'data' => new RoleResource($role),
            'message' => 'Role updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function transition(TransitionRoleStatusRequest $request, Role $role): JsonResponse
    {
        $role = $this->resolveTenantScopedRole($request, $role);

        try {
            $role = $this->roleCommandService->transition($role, (string)$request->validated()['status']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
        $role->load('orgUnit');
        $role->loadCount(['users', 'memberships']);

        return response()->json([
            'data' => new RoleResource($role),
            'message' => 'Role status updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $role = $this->resolveTenantScopedRole($request, $role);

        try {
            $this->roleCommandService->delete($role);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'assigned users') ? 409 : 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(['message' => 'Role deleted']);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->roleQueryService->stats([
                'company_id' => $this->companyIdFromRequest($request),
            ]),
        ]);
    }

    /**
     * Restrict role route-model binding results to the active tenant context.
     */
    private function resolveTenantScopedRole(Request $request, Role $role): Role
    {
        $companyId = $this->companyIdFromRequest($request);

        if ($companyId !== (string) $role->tenant_id) {
            abort(404, 'Role not found.');
        }

        return $role;
    }

    private function companyIdFromRequest(Request $request): string
    {
        $companyId = $request->attributes->get('company_id') ?? $request->attributes->get('tenant_id');

        if (!is_string($companyId) || trim($companyId) === '') {
            abort(404, 'Company context not found.');
        }

        return trim($companyId);
    }
}
