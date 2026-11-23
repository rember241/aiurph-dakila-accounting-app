<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Models\Tenant;
use Modules\Users\Services\Tenants\TenantRequestContextService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user can access the resolved company context.
 */
final class EnsureTenantAccess
{
    public function __construct(
        private readonly TenantRequestContextService $tenantRequestContextService
    )
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $company = $request->attributes->get('company');
        if (!$company instanceof Tenant) {
            $company = $request->attributes->get('tenant');
        }

        if (!$company instanceof Tenant) {
            return $this->notFound();
        }

        if (!$this->tenantRequestContextService->userCanAccess($actor, $company)) {
            return $this->notFound();
        }

        return $next($request);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Company context not found.',
        ], 404);
    }
}
