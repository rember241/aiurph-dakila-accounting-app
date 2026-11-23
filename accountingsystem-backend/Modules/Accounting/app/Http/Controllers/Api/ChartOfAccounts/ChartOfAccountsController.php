<?php

namespace Modules\Accounting\Http\Controllers\Api\ChartOfAccounts;


use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Http\Requests\ChartOfAccounts\IndexAccountRequest;
use Modules\Accounting\Http\Requests\ChartOfAccounts\StoreAccountRequest;
use Modules\Accounting\Http\Requests\ChartOfAccounts\UpdateAccountRequest;
use Modules\Accounting\Http\Resources\ChartOfAccountsResource;
use Modules\Accounting\Services\ChartOfAccounts\ChartOfAccountsCommandService;
use Modules\Accounting\Services\ChartOfAccounts\ChartOfAccountsQueryService;
use RuntimeException;
use Throwable;

class ChartOfAccountsController extends Controller
{
    /**
     * Constructs a new instance of the ChartOfAccountsController class.
     *
     * @param ChartOfAccountsQueryService $accountQueryService The service that handles account queries.
     * @param ChartOfAccountsCommandService $accountCommandService The service that handles account commands.
     */
    public function __construct(
        private readonly ChartOfAccountsQueryService   $accountQueryService,
        private readonly ChartOfAccountsCommandService $accountCommandService
    )
    {
    }

    /**
     * @param IndexAccountRequest $request
     * @return JsonResponse
     */
    public function index(IndexAccountRequest $request): JsonResponse
    {
        /*
         * Disabled only for testing
         * */
//        $this->authorize('viewAny', Account::class);

        $filters = $request->validated();
        $nestedRaw = strtolower((string)($filters['nested'] ?? '0'));
        $nested = in_array($nestedRaw, ['1', 'true', 't'], true);

        if ($nested) {
            $tree = $this->accountQueryService->tree($filters);

            return response()->json([
                'data' => $tree['tree'],
                'total' => $tree['total'],
            ]);
        }

        $page = $this->accountQueryService->paginate($filters);

        return response()->json([
            'data' => ChartOfAccountsResource::collection($page->items()),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @param StoreAccountRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
//        $this->authorize('create', Account::class);

        $account = $this->accountCommandService->create($request->validated());

        return response()->json([
            'data' => new ChartOfAccountsResource($account),
            'message' => 'Account created',
        ], 201);
    }

    /**
     * Get an account by its ID.
     *
     * @param Account $account
     * @return JsonResponse
     *
     * @throws Throwable
     */
    public function show(string $account): JsonResponse
    {
        $accountModel = $this->accountQueryService->findOrFail($account);

        return response()->json([
            'data' => new ChartOfAccountsResource($accountModel),
        ]);
    }


    /**
     * Update an account.
     *
     * @param UpdateAccountRequest $request
     * @param Account $account
     * @return JsonResponse
     *
     * @throws Throwable
     */


    public function update(UpdateAccountRequest $request, string $account): JsonResponse
    {
        $accountModel = $this->accountQueryService->findOrFail($account);

        try {
            // $this->authorize('update', $account);

            $accountModel = $this->accountCommandService->update($accountModel, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => new ChartOfAccountsResource($accountModel),
            'message' => 'Account has been updated',
        ]);
    }


    /**
     * Delete an account.
     *
     * @param Account $account
     * @return JsonResponse
     * @throws Throwable
     * @throws RuntimeException if the account has sub-accounts and cannot be deleted.
     */
    public function destroy(string $account): JsonResponse
    {
        $accountModel = $this->accountQueryService->findOrFail($account);

        try {
            // $this->authorize('update', $account);

            $this->accountCommandService->delete($accountModel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Account has been deleted']);
    }


//    public function codeExists()
//    {
//        $request = request();
//        $code = $request->query('code', '');
//
//        return response()->json([
//            'exists' => $this->accountQueryService->codeExists($code),
//        ]);
//
//    }

    public function stats(): JsonResponse
    {
        //        $this->authorize('delete', $account);

        $stats = $this->accountQueryService->stats();

        return response()->json([
            'data' => $stats,
        ]);
    }

}
