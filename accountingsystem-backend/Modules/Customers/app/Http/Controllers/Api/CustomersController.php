<?php

namespace Modules\Customers\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customers\Http\Requests\IndexCustomerRequest;
use Modules\Customers\Http\Requests\StoreCustomerRequest;
use Modules\Customers\Http\Requests\TransitionCustomerStatusRequest;
use Modules\Customers\Http\Requests\UpdateCustomerRequest;
use Modules\Customers\Http\Resources\CustomerResource;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerCommandService;
use Modules\Customers\Services\CustomerQueryService;
use Throwable;

/**
 * Company-scoped customer master APIs.
 */
final class CustomersController extends Controller
{
    public function __construct(
        private readonly CustomerQueryService $customerQueryService,
        private readonly CustomerCommandService $customerCommandService
    ) {
    }

    public function index(IndexCustomerRequest $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $filters = $request->validated();
        $page = $this->customerQueryService->paginate($companyId, $filters);

        return response()->json([
            'data' => CustomerResource::collection($page->items()),
            'stats' => $this->customerQueryService->stats($companyId, $filters),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerCommandService->create($request->validated());
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);

        return response()->json([
            'data' => new CustomerResource($customer),
            'message' => 'Customer created',
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);
        $customer = $this->customerCommandService->update($customer, $request->validated());
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);

        return response()->json([
            'data' => new CustomerResource($customer),
            'message' => 'Customer updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function transition(TransitionCustomerStatusRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);
        $customer = $this->customerCommandService->transition($customer, (string) $request->validated()['status']);
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);

        return response()->json([
            'data' => new CustomerResource($customer),
            'message' => 'Customer status updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $customer);
        $this->customerCommandService->delete($customer);

        return response()->json([
            'message' => 'Customer deleted',
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
