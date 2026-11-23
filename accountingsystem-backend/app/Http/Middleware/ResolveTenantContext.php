<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Users\Services\Tenants\TenantRequestContextService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current company context for legacy tenant-scoped API requests.
 *
 * The runtime boundary is now company-centric and derived from the
 * authenticated user. Legacy `X-Tenant-ID` or `X-Company-ID` headers are
 * accepted only as compatibility hints and must match the resolved company.
 */
final class ResolveTenantContext
{
    public function __construct(
        private readonly TenantRequestContextService $tenantRequestContextService
    )
    {
    }

    /**
     * Resolves the single company context for the authenticated user.
     *
     * Older clients may still send `X-Tenant-ID`, but the header is treated as
     * a compatibility assertion only. It must match the user's resolved company;
     * this backend no longer switches between companies per request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usedLegacyTenantHeader = trim((string) $request->header(TenantRequestContextService::HEADER_NAME, '')) !== '';
        $actor = $request->user();
        if (!$actor instanceof User) {
            return $this->decorateCompatibilityResponse(response()->json([
                'message' => 'Unauthenticated.',
            ], 401), $usedLegacyTenantHeader);
        }

        $requestedCompanyId = trim((string)(
            $request->header(TenantRequestContextService::COMPANY_HEADER_NAME)
            ?? $request->header(TenantRequestContextService::HEADER_NAME, '')
        ));

        if ($requestedCompanyId !== '' && !Str::isUuid($requestedCompanyId)) {
            return $this->decorateCompatibilityResponse(
                $this->badRequest('Company context header must be a valid UUID.'),
                $usedLegacyTenantHeader
            );
        }

        $company = $this->tenantRequestContextService->resolveCurrentForUser($actor);
        if ($company === null) {
            return $this->decorateCompatibilityResponse(response()->json([
                'message' => 'Company context not found.',
            ], 404), $usedLegacyTenantHeader);
        }

        if ($requestedCompanyId !== '' && $requestedCompanyId !== (string) $company->getKey()) {
            return $this->decorateCompatibilityResponse(response()->json([
                'message' => 'Company switching is no longer supported for this account.',
            ], 409), $usedLegacyTenantHeader);
        }

        $request->attributes->set('company_id', (string) $company->getKey());
        $request->attributes->set('company', $company);

        // Dual-write the legacy tenant attributes only as a temporary
        // compatibility bridge for older controllers, resources, and tests.
        $request->attributes->set('tenant_id', (string) $company->getKey());
        $request->attributes->set('tenant', $company);

        return $this->decorateCompatibilityResponse($next($request), $usedLegacyTenantHeader);
    }

    private function badRequest(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 400);
    }

    private function decorateCompatibilityResponse(Response $response, bool $usedLegacyTenantHeader): Response
    {
        if (!$usedLegacyTenantHeader) {
            return $response;
        }

        $response->headers->set('X-Deprecated-Header', TenantRequestContextService::HEADER_NAME);
        $response->headers->set('X-Canonical-Company-Header', TenantRequestContextService::COMPANY_HEADER_NAME);
        $response->headers->set(
            'Warning',
            '299 - "X-Tenant-ID is deprecated; use X-Company-ID for company context."'
        );

        return $response;
    }
}
