<?php

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\StoreSalesInvoicePaymentRequest;
use Modules\Sales\Http\Requests\UpdateSalesInvoicePaymentRequest;
use Modules\Sales\Http\Requests\VoidSalesInvoicePaymentRequest;
use Modules\Sales\Http\Resources\SalesInvoicePaymentResource;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Sales\Services\SalesInvoicePaymentCommandService;
use Modules\Sales\Services\SalesInvoicePaymentQueryService;
use Throwable;

final class SalesInvoicePaymentsController extends Controller
{
    public function __construct(
        private readonly SalesInvoicePaymentQueryService $paymentQueryService,
        private readonly SalesInvoicePaymentCommandService $paymentCommandService
    ) {
    }

    public function index(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $invoice = $this->paymentQueryService->findInvoiceOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $payments = $this->paymentQueryService->list($invoice);

        return response()->json([
            'data' => SalesInvoicePaymentResource::collection($payments),
        ]);
    }

    public function show(Request $request, SalesInvoice $salesInvoice, SalesInvoicePayment $salesInvoicePayment): JsonResponse
    {
        $invoice = $this->paymentQueryService->findInvoiceOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $payment = $this->paymentQueryService->findPaymentOrFail($invoice, $salesInvoicePayment);

        return response()->json([
            'data' => new SalesInvoicePaymentResource($payment),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreSalesInvoicePaymentRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $invoice = $this->paymentQueryService->findInvoiceOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $payment = $this->paymentCommandService->create($invoice, $request->validated());

        return response()->json([
            'data' => new SalesInvoicePaymentResource($payment),
            'message' => 'Invoice payment recorded',
        ], 201);
    }

    /**
     * @throws Throwable
     */
    public function update(
        UpdateSalesInvoicePaymentRequest $request,
        SalesInvoice $salesInvoice,
        SalesInvoicePayment $salesInvoicePayment
    ): JsonResponse {
        $invoice = $this->paymentQueryService->findInvoiceOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $payment = $this->paymentQueryService->findPaymentOrFail($invoice, $salesInvoicePayment);
        $payment = $this->paymentCommandService->update($invoice, $payment, $request->validated());

        return response()->json([
            'data' => new SalesInvoicePaymentResource($payment),
            'message' => 'Invoice payment updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function void(
        VoidSalesInvoicePaymentRequest $request,
        SalesInvoice $salesInvoice,
        SalesInvoicePayment $salesInvoicePayment
    ): JsonResponse {
        $invoice = $this->paymentQueryService->findInvoiceOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $payment = $this->paymentQueryService->findPaymentOrFail($invoice, $salesInvoicePayment);
        $payment = $this->paymentCommandService->void($invoice, $payment, $request->validated());

        return response()->json([
            'data' => new SalesInvoicePaymentResource($payment),
            'message' => 'Invoice payment voided',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, SalesInvoice $salesInvoice, SalesInvoicePayment $salesInvoicePayment): JsonResponse
    {
        $invoice = $this->paymentQueryService->findInvoiceOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $payment = $this->paymentQueryService->findPaymentOrFail($invoice, $salesInvoicePayment);
        $this->paymentCommandService->delete($invoice, $payment);

        return response()->json([
            'message' => 'Invoice payment deleted',
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
