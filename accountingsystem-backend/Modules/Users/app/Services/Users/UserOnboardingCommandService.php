<?php

namespace Modules\Users\Services\Users;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\DTO\PeriodManagement\CreateFiscalYearData;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Services\ChartOfAccounts\BootstrapStarterChartOfAccountsService;
use Modules\Accounting\Services\PeriodManagement\Commands\CreateFiscalYearHandler;
use Modules\Users\DTO\Tenants\CreateTenantData;
use Modules\Users\DTO\Tenants\CreateTenantOptions;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Tenant;
use Modules\Users\Services\OrgUnits\OrgUnitCommandService;
use Modules\Users\Services\Tenants\InitialTenantEligibilityService;
use Modules\Users\Services\Tenants\TenantCommandService;
use RuntimeException;
use Throwable;

/**
 * Builds the initial company workspace for a new account.
 *
 * This service is the source of truth for onboarding side effects: company
 * creation, owner assignment, root org unit setup, starter fiscal year, and
 * starter chart of accounts.
 */
final class UserOnboardingCommandService
{
    public function __construct(
        private readonly InitialTenantEligibilityService $initialTenantEligibilityService,
        private readonly UserOnboardingQueryService $userOnboardingQueryService,
        private readonly TenantCommandService $tenantCommandService,
        private readonly OrgUnitCommandService $orgUnitCommandService,
        private readonly UserCompanyAssignmentService $userCompanyAssignmentService,
        private readonly CreateFiscalYearHandler $createFiscalYearHandler,
        private readonly BootstrapStarterChartOfAccountsService $bootstrapStarterChartOfAccountsService,
        private readonly Request $request
    )
    {
    }

    /**
     * Creates the one allowed company context for the authenticated user.
     *
     * New onboarding changes should be added here instead of duplicating setup
     * in controllers or front-end route handlers.
     *
     * @return array{
     *     tenant: Tenant,
     *     organization: array{root_org_unit: OrgUnit|null},
     *     fiscal_year: FiscalYear,
     *     accounts_setup: array{mode: string, created_count: int}
     * }
     *
     * @throws Throwable
     */
    public function completeCompanySetup(User $user, array $payload): array
    {
        return $this->createCompanySetup($user, $payload, true);
    }

    /**
     * @return array{
     *     tenant: Tenant,
     *     organization: array{root_org_unit: OrgUnit|null},
     *     fiscal_year: FiscalYear,
     *     accounts_setup: array{mode: string, created_count: int}
     * }
     *
     * @throws Throwable
     */
    public function createAdditionalCompanySetup(User $user, array $payload): array
    {
        unset($payload);

        throw ValidationException::withMessages([
            'user' => 'This account is already assigned to a company. Creating additional companies is no longer supported.',
        ]);
    }

    /**
     * @return array{
     *     tenant: Tenant,
     *     organization: array{root_org_unit: OrgUnit|null},
     *     fiscal_year: FiscalYear,
     *     accounts_setup: array{mode: string, created_count: int}
     * }
     *
     * @throws Throwable
     */
    private function createCompanySetup(User $user, array $payload, bool $enforceInitialEligibility): array
    {
        if ($enforceInitialEligibility) {
            $this->initialTenantEligibilityService->ensureCanCreateInitialTenant($user);
        }

        return DB::transaction(function () use ($user, $payload) {
            $tenant = $this->tenantCommandService->create(
                CreateTenantData::fromArray((array)($payload['company'] ?? []), (int)$user->getKey()),
                CreateTenantOptions::forSharedDatabaseOnboarding()
            );
            $organization = $this->bootstrapOrganizationStructure(
                $tenant,
                (array) ($payload['organization'] ?? [])
            );
            $this->assignOwnerOrganizationContext($user, $tenant, $organization['root_org_unit']);

            $fiscalYear = $this->createInitialFiscalYearForTenant(
                $tenant,
                (array)($payload['fiscal_year'] ?? [])
            );
            $accountsSetupSummary = $this->bootstrapInitialAccountsForTenant(
                $tenant,
                (array)($payload['accounts_setup'] ?? [])
            );

            return [
                'tenant' => $tenant,
                'organization' => $organization,
                'fiscal_year' => $fiscalYear,
                'accounts_setup' => $accountsSetupSummary,
            ];
        });
    }

    /**
     * Bootstrap the initial tenant through the legacy tenant-create endpoint.
     *
     * This path keeps the old response contract while ensuring the user cannot
     * bypass the initial fiscal-year bootstrap.
     *
     * @return array{
     *     tenant: Tenant,
     *     organization: array{root_org_unit: OrgUnit|null},
     *     fiscal_year: FiscalYear
     * }
     *
     * @throws Throwable
     */
    public function createInitialTenant(User $user, array $companyPayload): array
    {
        $this->initialTenantEligibilityService->ensureCanCreateInitialTenant($user);

        return DB::transaction(function () use ($user, $companyPayload) {
            $tenant = $this->tenantCommandService->create(
                CreateTenantData::fromArray($companyPayload, (int)$user->getKey()),
                CreateTenantOptions::forSharedDatabaseOnboarding()
            );
            $organization = $this->bootstrapOrganizationStructure($tenant, [
                'create_root_org_unit' => true,
                'root_org_unit_name' => 'Head Office',
            ]);
            $this->assignOwnerOrganizationContext($user, $tenant, $organization['root_org_unit']);

            $timezone = trim((string)($companyPayload['timezone'] ?? 'UTC'));
            $fiscalYear = $this->createInitialFiscalYearForTenant(
                $tenant,
                $this->defaultInitialFiscalYearPayload($timezone !== '' ? $timezone : 'UTC')
            );

            return [
                'tenant' => $tenant,
                'organization' => $organization,
                'fiscal_year' => $fiscalYear,
            ];
        });
    }

    /**
     * @return array{root_org_unit: OrgUnit|null}
     */
    private function bootstrapOrganizationStructure(Tenant $tenant, array $payload): array
    {
        $createRootOrgUnit = array_key_exists('create_root_org_unit', $payload)
            ? (bool) $payload['create_root_org_unit']
            : true;

        if (!$createRootOrgUnit) {
            return [
                'root_org_unit' => null,
            ];
        }

        $rootOrgUnitName = trim((string) ($payload['root_org_unit_name'] ?? 'Head Office'));
        $rootOrgUnit = $this->orgUnitCommandService->createRootForCompany(
            $tenant,
            $rootOrgUnitName !== '' ? $rootOrgUnitName : 'Head Office'
        );

        return [
            'root_org_unit' => $rootOrgUnit,
        ];
    }

    private function assignOwnerOrganizationContext(User $user, Tenant $tenant, ?OrgUnit $rootOrgUnit): void
    {
        if (!$rootOrgUnit instanceof OrgUnit) {
            return;
        }

        $this->userCompanyAssignmentService->syncOrgUnits(
            $user,
            (string) $tenant->getKey(),
            (string) $rootOrgUnit->getKey(),
            [(string) $rootOrgUnit->getKey()]
        );
    }

    /**
     * Create the initial shared-db fiscal year and periods for the new tenant.
     */
    private function createInitialFiscalYearForTenant(Tenant $tenant, array $payload): FiscalYear
    {
        $fiscalYear = $this->runInTenantContext(
            $tenant,
            fn() => $this->createFiscalYearHandler->handle(
                CreateFiscalYearData::fromArray([
                    ...$payload,
                    'is_current' => true,
                ])
            )
        );

        $tenantId = (string)$tenant->getKey();
        if ((string)$fiscalYear->tenant_id !== $tenantId) {
            throw new RuntimeException('Initial fiscal year tenant scope does not match the bootstrapped tenant.');
        }

        if ($fiscalYear->periods->count() !== 12) {
            throw new RuntimeException('Initial fiscal year setup did not generate the expected accounting periods.');
        }

        $invalidPeriodCount = AccountingPeriod::query()
            ->where('fiscal_year_id', $fiscalYear->getKey())
            ->where(function ($query) use ($tenantId): void {
                $query
                    ->where('tenant_id', '!=', $tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->count();

        if ($invalidPeriodCount > 0) {
            throw new RuntimeException('Initial accounting periods were created with an invalid tenant scope.');
        }

        return $fiscalYear;
    }

    /**
     * @return array{mode: string, created_count: int}
     */
    private function bootstrapInitialAccountsForTenant(Tenant $tenant, array $payload): array
    {
        $mode = strtolower(trim((string)($payload['mode'] ?? 'empty')));

        if ($mode !== 'generated') {
            return [
                'mode' => 'empty',
                'created_count' => 0,
            ];
        }

        $createdCount = $this->runInTenantContext(
            $tenant,
            fn() => $this->bootstrapStarterChartOfAccountsService->createStarterAccounts()
        );

        return [
            'mode' => 'generated',
            'created_count' => $createdCount,
        ];
    }

    /**
     * Build the default current-year fiscal-year payload used by the legacy tenant create endpoint.
     *
     * @return array{code: string, name: string, start_date: string, end_date: string}
     */
    private function defaultInitialFiscalYearPayload(string $timezone): array
    {
        $today = CarbonImmutable::now($timezone);
        $year = $today->year;

        return [
            'code' => sprintf('FY%d', $year),
            'name' => sprintf('Fiscal Year %d', $year),
            'start_date' => $today->startOfYear()->toDateString(),
            'end_date' => $today->endOfYear()->toDateString(),
        ];
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     */
    private function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $previousCompanyId = $this->request->attributes->get('company_id');
        $previousCompany = $this->request->attributes->get('company');
        $previousTenantId = $this->request->attributes->get('tenant_id');
        $previousTenant = $this->request->attributes->get('tenant');

        $this->request->attributes->set('company_id', (string)$tenant->getKey());
        $this->request->attributes->set('company', $tenant);
        $this->request->attributes->set('tenant_id', (string)$tenant->getKey());
        $this->request->attributes->set('tenant', $tenant);

        try {
            return $callback();
        } finally {
            $this->request->attributes->set('company_id', $previousCompanyId);
            $this->request->attributes->set('company', $previousCompany);
            $this->request->attributes->set('tenant_id', $previousTenantId);
            $this->request->attributes->set('tenant', $previousTenant);
        }
    }
}
