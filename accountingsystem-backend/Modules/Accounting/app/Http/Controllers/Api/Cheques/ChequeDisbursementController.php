<?php

namespace Modules\Accounting\Http\Controllers\Api\Cheques;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Accounting\Http\Resources\Cheques\ChequeDisbursementResource;
use Modules\Accounting\Models\Cheques\ChequeDisbursement;
use Modules\Accounting\Services\Cheques\ChequeDisbursementCommandService;
use Throwable;

final class ChequeDisbursementController extends Controller
{
    public function __construct(
        private readonly ChequeDisbursementCommandService $commandService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $query = ChequeDisbursement::query()
            ->with(['chequeBook', 'bankAccount', 'lines.account'])
            ->forCompany($companyId)
            ->latest('cheque_date');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->query('q')).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('payee_name', 'like', $needle)
                    ->orWhere('cheque_number', 'like', $needle)
                    ->orWhere('reference_no', 'like', $needle);
            });
        }

        $perPage = max(1, min(100, (int) $request->query('perPage', 15)));
        $cheques = $query->paginate($perPage);

        return response()->json([
            'data' => ChequeDisbursementResource::collection($cheques->items()),
            'current_page' => $cheques->currentPage(),
            'per_page' => $cheques->perPage(),
            'total' => $cheques->total(),
        ]);
    }

    public function show(Request $request, ChequeDisbursement $cheque): JsonResponse
    {
        $cheque = $this->resolveCompanyCheque($request, $cheque);

        return response()->json(['data' => new ChequeDisbursementResource($cheque)]);
    }

    /**
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cheque_book_id' => ['required', 'uuid'],
            'cheque_template_version_id' => ['nullable', 'uuid'],
            'cheque_date' => ['required', 'date'],
            'payee_name' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'crossing_type' => ['nullable', Rule::in(['none', 'account_payee_only'])],
            'bearer_action' => ['nullable', Rule::in(['none', 'strike_bearer'])],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'memo' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'uuid'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $cheque = $this->commandService->create($this->companyIdFromRequest($request), $data);

        return response()->json([
            'data' => new ChequeDisbursementResource($cheque),
            'message' => 'Cheque disbursement created',
        ], 201);
    }

    public function approve(Request $request, ChequeDisbursement $cheque): JsonResponse
    {
        $cheque = $this->resolveCompanyCheque($request, $cheque);
        $cheque = $this->commandService->approve($cheque, $request->user()?->getAuthIdentifier());

        return response()->json(['data' => new ChequeDisbursementResource($cheque), 'message' => 'Cheque approved']);
    }

    public function print(Request $request, ChequeDisbursement $cheque): JsonResponse
    {
        $cheque = $this->resolveCompanyCheque($request, $cheque);
        $data = $request->validate([
            'print_type' => ['required', Rule::in(['test', 'final', 'reprint'])],
            'cheque_template_version_id' => ['nullable', 'uuid'],
            'x_offset_mm' => ['nullable', 'numeric', 'between:-25,25'],
            'y_offset_mm' => ['nullable', 'numeric', 'between:-25,25'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $cheque = $this->commandService->print($cheque, $data, $request->user()?->getAuthIdentifier());

        return response()->json(['data' => new ChequeDisbursementResource($cheque), 'message' => 'Cheque print recorded']);
    }

    public function post(Request $request, ChequeDisbursement $cheque): JsonResponse
    {
        $cheque = $this->resolveCompanyCheque($request, $cheque);
        $cheque = $this->commandService->post($cheque);

        return response()->json(['data' => new ChequeDisbursementResource($cheque), 'message' => 'Cheque posted']);
    }

    public function spoil(Request $request, ChequeDisbursement $cheque): JsonResponse
    {
        $cheque = $this->resolveCompanyCheque($request, $cheque);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $cheque = $this->commandService->spoil($cheque, $data['reason']);

        return response()->json(['data' => new ChequeDisbursementResource($cheque), 'message' => 'Cheque marked spoiled']);
    }

    public function void(Request $request, ChequeDisbursement $cheque): JsonResponse
    {
        $cheque = $this->resolveCompanyCheque($request, $cheque);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $cheque = $this->commandService->void($cheque, $data['reason']);

        return response()->json(['data' => new ChequeDisbursementResource($cheque), 'message' => 'Cheque voided']);
    }

    private function resolveCompanyCheque(Request $request, ChequeDisbursement $cheque): ChequeDisbursement
    {
        $companyId = $this->companyIdFromRequest($request);

        return ChequeDisbursement::query()
            ->with(['chequeBook.bankAccount', 'templateVersion.template', 'bankAccount', 'lines.account', 'printLogs', 'journalEntry', 'reversalJournalEntry'])
            ->forCompany($companyId)
            ->whereKey($cheque->getKey())
            ->firstOrFail();
    }

    private function companyIdFromRequest(Request $request): string
    {
        $companyId = $request->attributes->get('company_id') ?? $request->attributes->get('tenant_id');

        if (! is_string($companyId) || trim($companyId) === '') {
            abort(404, 'Company context not found.');
        }

        return trim($companyId);
    }
}
