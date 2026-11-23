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
 * Enforces company-scoped module permissions for the resolved company context.
 */
final class EnsureTenantAbility
{
    public function __construct(
        private readonly TenantRequestContextService $tenantRequestContextService
    )
    {
    }

    /**
     * Checks the resolved company's role permissions for the requested ability.
     *
     * Route middleware can pass one or more comma-separated abilities. Matching
     * any one of them is enough, which is useful for shared setup endpoints
     * such as taxes that are consumed by accounting, journal, and sales screens.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
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

        $normalizedAbilities = [];

        foreach ($abilities as $ability) {
            foreach (explode(',', $ability) as $candidate) {
                $normalized = strtolower(trim($candidate));

                if ($normalized === '') {
                    continue;
                }

                $normalizedAbilities[$normalized] = $normalized;
            }
        }

        if ($normalizedAbilities === []) {
            return response()->json([
                'message' => 'No company ability requirement was configured for this route.',
            ], 500);
        }

        $hasAnyAbility = false;

        foreach ($normalizedAbilities as $ability) {
            if ($this->tenantRequestContextService->userHasAbility($actor, $company, $ability)) {
                $hasAnyAbility = true;
                break;
            }
        }

        if (!$hasAnyAbility) {
            return response()->json([
                'message' => 'Insufficient company permissions.',
            ], 403);
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
