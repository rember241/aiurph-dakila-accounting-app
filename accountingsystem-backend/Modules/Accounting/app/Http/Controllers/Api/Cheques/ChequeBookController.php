<?php

namespace Modules\Accounting\Http\Controllers\Api\Cheques;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Http\Resources\Cheques\ChequeBookResource;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\Cheques\ChequeBook;

final class ChequeBookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $books = ChequeBook::query()
            ->with('bankAccount')
            ->forCompany($this->companyIdFromRequest($request))
            ->orderBy('bank_name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => ChequeBookResource::collection($books)]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $data = $request->validate([
            'bank_account_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:160'],
            'bank_branch' => ['nullable', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'string', 'max:80'],
            'cheque_number_prefix' => ['nullable', 'string', 'max:20'],
            'start_number' => ['required', 'integer', 'min:1'],
            'end_number' => ['required', 'integer', 'gte:start_number'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $account = Account::query()->whereKey($data['bank_account_id'])->first();
        if (! $account instanceof Account || (string) $account->company_id !== $companyId || (string) $account->type !== 'asset' || ! (bool) $account->is_posting) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Select an active posting bank asset account for this cheque book.',
            ]);
        }

        $book = ChequeBook::query()->create([
            ...$data,
            'company_id' => $companyId,
            'next_number' => (int) $data['start_number'],
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json([
            'data' => new ChequeBookResource($book->load('bankAccount')),
            'message' => 'Cheque book created',
        ], 201);
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
