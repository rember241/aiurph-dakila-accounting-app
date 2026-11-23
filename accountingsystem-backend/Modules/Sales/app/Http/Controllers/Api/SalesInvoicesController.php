<?php

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\CancelSalesInvoiceRequest;
use Modules\Sales\Http\Requests\IndexSalesInvoiceRequest;
use Modules\Sales\Http\Requests\PostSalesInvoiceRequest;
use Modules\Sales\Http\Requests\StoreSalesInvoiceRequest;
use Modules\Sales\Http\Requests\UpdateSalesInvoiceRequest;
use Modules\Sales\Http\Resources\SalesInvoiceResource;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Services\SalesInvoiceCommandService;
use Modules\Sales\Services\SalesInvoiceQueryService;
use Throwable;

/**
 * Company-scoped sales invoice APIs.
 */
final class SalesInvoicesController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceQueryService $salesInvoiceQueryService,
        private readonly SalesInvoiceCommandService $salesInvoiceCommandService
    ) {
    }

    public function index(IndexSalesInvoiceRequest $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $filters = $request->validated();
        $page = $this->salesInvoiceQueryService->paginate($companyId, $filters);

        return response()->json([
            'data' => SalesInvoiceResource::collection($page->items()),
            'stats' => $this->salesInvoiceQueryService->stats($companyId, $filters),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreSalesInvoiceRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $submitAction = (string) ($payload['submit_action'] ?? 'draft');

        $salesInvoice = $this->salesInvoiceCommandService->create($payload);
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);

        return response()->json([
            'data' => new SalesInvoiceResource($salesInvoice),
            'message' => $submitAction === 'post'
                ? 'Sales invoice created and posted'
                : 'Sales invoice draft created',
        ], 201);
    }

    public function show(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);

        return response()->json([
            'data' => new SalesInvoiceResource($salesInvoice),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateSalesInvoiceRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $salesInvoice = $this->salesInvoiceCommandService->update($salesInvoice, $request->validated());
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);

        return response()->json([
            'data' => new SalesInvoiceResource($salesInvoice),
            'message' => 'Sales invoice draft updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function issue(PostSalesInvoiceRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $salesInvoice = $this->salesInvoiceCommandService->issue($salesInvoice);
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);

        return response()->json([
            'data' => new SalesInvoiceResource($salesInvoice),
            'message' => 'Sales invoice issued',
        ]);
    }

    /**
     * Compatibility alias for older clients still calling the posting route.
     *
     * @throws Throwable
     */
    public function post(PostSalesInvoiceRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        return $this->issue($request, $salesInvoice);
    }

    /**
     * @throws Throwable
     */
    public function cancel(CancelSalesInvoiceRequest $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $salesInvoice = $this->salesInvoiceCommandService->cancel(
            $salesInvoice,
            $request->validated()['cancelled_reason'] ?? null
        );
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);

        return response()->json([
            'data' => new SalesInvoiceResource($salesInvoice),
            'message' => 'Sales invoice cancelled',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice = $this->salesInvoiceQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $salesInvoice);
        $this->salesInvoiceCommandService->delete($salesInvoice);

        return response()->json([
            'message' => 'Sales invoice draft deleted',
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
