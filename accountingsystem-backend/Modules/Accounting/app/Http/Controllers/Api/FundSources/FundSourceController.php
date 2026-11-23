<?php

namespace Modules\Accounting\Http\Controllers\Api\FundSources;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Requests\FundSources\IndexFundSourceRequest;
use Modules\Accounting\Http\Requests\FundSources\StoreFundSourceRequest;
use Modules\Accounting\Http\Requests\FundSources\UpdateFundSourceRequest;
use Modules\Accounting\Http\Resources\FundSources\IndexFundSourceResource;
use Modules\Accounting\Http\Resources\FundSources\StoreFundSourceResource;
use Modules\Accounting\Http\Resources\FundSources\UpdateFundSourceResource;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Services\CurrentTenantContext;

class FundSourceController extends Controller
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(IndexFundSourceRequest $request)
    {
        // Ensure that no malicious URL query parameter gets picked up
        $parameters = $request->validated();
        $companyId = $this->currentTenantContext->companyId();

        /*
         * Return unpaginated data if is_paginated is set to false.
         * Because unpaginated fund source data is used for creating journal entries,
         * it should only return fund sources that have an 'active' status
         */
        if (isset($parameters['is_paginated']) && !$parameters['is_paginated']) {
            return IndexFundSourceResource::collection(
                FundSource::where('company_id', $companyId)
                    ->where('status', 'active')
                    ->orderBy('name', 'asc')
                    ->get()
            );
        }

        // Returns paginated data that is filtered based on the received parameters
        $fundSources = FundSource::where('company_id', $companyId)
            ->when($parameters['search'] ?? null, function ($query, $search) {
                $query->where('name', "ilike", "%$search%");
            })
            ->when($parameters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy($parameters['sort'] ?? 'name',$parameters['order'] ?? 'asc')
            ->paginate($parameters['per_page'] ?? 10);

        return IndexFundSourceResource::collection($fundSources);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFundSourceRequest $request)
    {
        // Ensure that the payload meets all validation rules
        $newFundSource = $request->validated();
        $companyId = $this->currentTenantContext->companyId();

        return DB::transaction(function () use ($newFundSource, $companyId)
        {
            $createdFundSource = FundSource::create([
                'company_id' => $companyId,
                'name' => $newFundSource['name'],
                'status' => $newFundSource['status'],
            ]);

            return (new StoreFundSourceResource($createdFundSource))
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFundSourceRequest $request, FundSource $fundSource)
    {
        // Ensure that no malicious payload is accepted
        $updatedValues = $request->validated();
        $companyId = $this->currentTenantContext->companyId();

        /*
         * Create a new fund source with the modified values if the
         * fund source to be edited has already been used in previous
         * entries to retain data integrity
         */
        return DB::transaction(function () use ($fundSource, $updatedValues, $companyId) {
            if ($fundSource->is_used) {
                // Soft delete the fund source before creating a new one
                $fundSource->delete();

                $createdFundSource = FundSource::create([
                    'company_id' => $companyId,
                    'name' => $updatedValues['name'],
                    'status' => $updatedValues['status'],
                ]);

                return (new UpdateFundSourceResource($createdFundSource))
                    ->response()
                    ->setStatusCode(201);
            } else {
                $fundSource->update([
                    'name' => $updatedValues['name'],
                    'status' => $updatedValues['status'],
                ]);

                return (new UpdateFundSourceResource($fundSource))
                    ->response()
                    ->setStatusCode(200);
            }
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FundSource $fundSource)
    {
        // Verify if the fund source's company ID is the same with the user's company ID
        $companyId = $this->currentTenantContext->companyId();
        if ($fundSource->company_id !== $companyId)
            abort(403, 'Unauthorized');

        // If the fund source is already used in journal entries, only apply a
        // soft delete, otherwise permanently delete it from the database
        return DB::transaction(function () use ($fundSource) {
            $fundSource->is_used
            ? $fundSource->delete()
            : $fundSource->forceDelete();

            return response()
                ->json([
                    'message' => 'Fund source deleted successfully'
                ])
                ->setStatusCode(200);
        });
    }
}
