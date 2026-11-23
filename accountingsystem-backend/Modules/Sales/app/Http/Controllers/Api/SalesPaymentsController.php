<?php

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\IndexSalesPaymentRequest;
use Modules\Sales\Http\Resources\SalesInvoicePaymentResource;
use Modules\Sales\Services\SalesInvoicePaymentQueryService;

/**
 * Company-scoped, top-level listing of sales invoice payments for the
 * standalone Payments tab. Reuses {@see SalesInvoicePaymentResource} so the
 * shape matches the nested /sales-invoices/{id}/payments endpoints.
 */
final class SalesPaymentsController extends Controller
{
    public function __construct(
        private readonly SalesInvoicePaymentQueryService $paymentQueryService
    ) {
    }

    public function index(IndexSalesPaymentRequest $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $page = $this->paymentQueryService->paginate($companyId, $request->validated());

        return response()->json([
            'data' => SalesInvoicePaymentResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
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
