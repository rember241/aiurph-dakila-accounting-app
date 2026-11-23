<?php

namespace Modules\Customers\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customers\Http\Requests\CustomerBillingStatementRequest;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerAccountStatementService;
use Modules\Users\Models\Company;

/**
 * Returns a date-ranged account statement for a single company-scoped customer.
 *
 * GET /api/v1/customers/{customer}/billing-statement
 *     ?date_from=YYYY-MM-DD
 *     &date_to=YYYY-MM-DD
 *
 * The statement includes:
 *  - Customer and company identity headers.
 *  - Opening balance (outstanding receivable at the period start).
 *  - A chronological transaction ledger of invoices (debits) and payments (credits).
 *  - Summary totals: total_invoiced, total_paid, closing_balance.
 */
final class CustomerBillingStatementController extends Controller
{
    public function __construct(
        private readonly CustomerAccountStatementService $statementService
    ) {
    }

    public function __invoke(
        CustomerBillingStatementRequest $request,
        Customer $customer
    ): JsonResponse {
        $companyId = $this->companyIdFromRequest($request);

        // Ensure the resolved customer belongs to the authenticated company.
        if ((string) $customer->company_id !== $companyId) {
            abort(404, 'Customer not found.');
        }

        /** @var Company $company */
        $company = $request->attributes->get('company');

        $validated = $request->validated();
        $dateFrom  = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo    = Carbon::parse($validated['date_to'])->endOfDay();

        $statement = $this->statementService->generate(
            $companyId,
            $company,
            $customer,
            $dateFrom,
            $dateTo
        );

        return response()->json($statement);
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
