<?php

namespace Modules\Accounting\Http\Controllers\Api\Taxes;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Requests\Taxes\DestroyTaxRequest;
use Modules\Accounting\Http\Requests\Taxes\IndexTaxRequest;
use Modules\Accounting\Http\Requests\Taxes\StoreTaxRequest;
use Modules\Accounting\Http\Requests\Taxes\UpdateTaxRequest;
use Modules\Accounting\Http\Resources\Taxes\IndexTaxResource;
use Modules\Accounting\Http\Resources\Taxes\StoreTaxResource;
use Modules\Accounting\Http\Resources\Taxes\UpdateTaxResource;
use Modules\Accounting\Models\Taxes\Tax;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexTaxRequest $request)
    {
        // Ensure that the URL query parameters are valid
        $parameters = $request->validated();
        $companyId = $request->attributes->get('company_id');

        /*
         * Return unpaginated data if is_paginated is set to false.
         * Because unpaginated tax data is used for creating journal entries,
         * it should only return taxes that have an 'active' status
         */
        if (isset($parameters['is_paginated']) && !$parameters['is_paginated']) {
            return IndexTaxResource::collection(
                Tax::where('company_id', $companyId)
                    ->where('status', 'active')
                    ->orderBy('name', 'asc')
                    ->get()
            );
        }

        // Returns paginated data that is filtered based on the received parameters
        $taxes = Tax::query()
            ->where('company_id', $companyId)
            ->when($parameters['search'] ?? null, function ($query, $search) {
                $query->where('name', "ilike", "%$search%");
            })
            ->when($parameters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy($parameters['sort'] ?? 'name',$parameters['order'] ?? 'asc')
            ->paginate($parameters['per_page'] ?? 10);

        return IndexTaxResource::collection($taxes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaxRequest $request)
    {
        // Ensure that the payload meets set validation rules
        $newTaxRate = $request->validated();
        $companyId = $request->attributes->get('company_id');

        return DB::transaction(function () use ($newTaxRate, $companyId) {
            $createdTaxRate = Tax::create([
                'company_id' => $companyId,
                'name' => $newTaxRate['name'],
                'rate' => $newTaxRate['rate'],
                'status' => $newTaxRate['status']
            ]);

            return (new StoreTaxResource($createdTaxRate))
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaxRequest $request, Tax $tax) {
        // Ensure that no malicious payload is accepted
        $updatedValues = $request->validated();
        $companyId = $request->attributes->get('company_id');

        /*
         * Create a new tax with the modified values if the tax to
         * be edited has already been used in previous entries to
         * retain data integrity
         */
        return DB::transaction(function () use ($tax, $updatedValues, $companyId) {
            if ($tax->is_used) {
                // Soft delete the tax before creating a new one
                $tax->delete();

                $createdTax = Tax::create([
                    'company_id' => $companyId,
                    'name' => $updatedValues['name'],
                    'rate' => $updatedValues['rate'],
                    'status' => $updatedValues['status']
                ]);

                return (new StoreTaxResource($createdTax))
                    ->response()
                    ->setStatusCode(201);
            } else {
                $tax->update([
                    'name' => $updatedValues['name'],
                    'rate' => $updatedValues['rate'],
                    'status' => $updatedValues['status']
                ]);

                return (new UpdateTaxResource($tax))
                    ->response()
                    ->setStatusCode(200);
            }
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DestroyTaxRequest $request, Tax $tax) {
        // If the tax is already used in journal entries, only apply a
        // soft delete, otherwise permanently delete it from the database
        return DB::transaction(function () use ($tax) {
            $tax->is_used
                ? $tax->delete()
                : $tax->forceDelete();

            return response()
                ->json([
                    'message' => 'Tax deleted successfully'
                ])
                ->setStatusCode(200);
        });
    }
}
