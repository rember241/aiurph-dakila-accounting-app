<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\IndexInventoryItemRequest;
use Modules\Inventory\Http\Requests\StoreInventoryItemRequest;
use Modules\Inventory\Http\Requests\TransitionInventoryItemStatusRequest;
use Modules\Inventory\Http\Requests\UpdateInventoryItemRequest;
use Modules\Inventory\Http\Resources\InventoryItemResource;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Services\InventoryItemCommandService;
use Modules\Inventory\Services\InventoryItemQueryService;
use RuntimeException;
use Throwable;

/**
 * Company-scoped inventory item master APIs.
 */
final class InventoryItemsController extends Controller
{
    public function __construct(
        private readonly InventoryItemQueryService   $inventoryItemQueryService,
        private readonly InventoryItemCommandService $inventoryItemCommandService
    )
    {
    }

    public function index(IndexInventoryItemRequest $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $filters = $request->validated();
        $page = $this->inventoryItemQueryService->paginate($companyId, $filters);

        return response()->json([
            'data' => InventoryItemResource::collection($page->items()),
            'stats' => $this->inventoryItemQueryService->stats($companyId, $filters),
            'page' => $page->currentPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $inventoryItem = $this->inventoryItemCommandService->create($request->validated());
        $inventoryItem = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventoryItem);

        return response()->json([
            'data' => new InventoryItemResource($inventoryItem),
            'message' => 'Inventory item created',
        ], 201);
    }

    public function show(Request $request, InventoryItem $inventory): JsonResponse
    {
        $inventory = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventory);

        return response()->json([
            'data' => new InventoryItemResource($inventory),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventory): JsonResponse
    {
        $inventory = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventory);
        $inventory = $this->inventoryItemCommandService->update($inventory, $request->validated());
        $inventory = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventory);

        return response()->json([
            'data' => new InventoryItemResource($inventory),
            'message' => 'Inventory item updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function transition(TransitionInventoryItemStatusRequest $request, InventoryItem $inventory): JsonResponse
    {
        $inventory = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventory);
        $inventory = $this->inventoryItemCommandService->transition($inventory, (string)$request->validated()['status']);
        $inventory = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventory);

        return response()->json([
            'data' => new InventoryItemResource($inventory),
            'message' => 'Inventory item status updated',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Request $request, InventoryItem $inventory): JsonResponse
    {
        $inventory = $this->inventoryItemQueryService->findVisibleOrFail($this->companyIdFromRequest($request), $inventory);

        try {
            $this->inventoryItemCommandService->delete($inventory);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Inventory item deleted',
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
