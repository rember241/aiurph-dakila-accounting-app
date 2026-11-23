<?php

namespace Modules\Accounting\Http\Controllers\Api\JournalEntries;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Requests\JournalEntries\DestroyJournalNumberRequest;
use Modules\Accounting\Http\Requests\JournalNumbers\IndexJournalNumberRequest;
use Modules\Accounting\Http\Requests\JournalNumbers\StoreJournalNumberRequest;
use Modules\Accounting\Http\Requests\JournalNumbers\UpdateJournalNumberRequest;
use Modules\Accounting\Http\Resources\JournalNumbers\IndexJournalNumberResource;
use Modules\Accounting\Http\Resources\JournalNumbers\StoreJournalNumberResource;
use Modules\Accounting\Http\Resources\JournalNumbers\UpdateJournalNumberResource;
use Modules\Accounting\Models\JournalEntries\JournalNumber;

class JournalNumberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexJournalNumberRequest $request)
    {
        // Ensure that no malicious URL query parameter gets picked up
        $parameters = $request->validated();
        $companyId = $request->attributes->get('company_id');

        /*
         * Return unpaginated data if is_paginated is set to false.
         * Because unpaginated journal number data is used for creating journal entries,
         * it should only return journal numbers that have an 'active' status
         */
        if (isset($parameters['is_paginated']) && !$parameters['is_paginated']) {
            return IndexJournalNumberResource::collection(
                JournalNumber::where('company_id', $companyId)
                ->where('status', 'active')
                ->orderBy('name', 'asc')->get()
            );
        }

        $journalNumbers = JournalNumber::query()
            ->where('company_id', $companyId)
            ->when($parameters['search'] ?? null, function ($query, $search) {
                $query->whereAny(['name', 'prefix'],
                    'ilike',
                    "%$search%");
            })
            ->when($parameters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($parameters['reset_interval'] ?? null, function ($query, $resetInterval) {
                $query->where('reset_interval', $resetInterval);
            })
            ->orderBy($parameters['sort'] ?? 'name', $parameters['order'] ?? 'asc')
            ->paginate($parameters['per_page'] ?? 10);

        return IndexJournalNumberResource::collection($journalNumbers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJournalNumberRequest $request) {
        // Validate incoming payload
        $payload = $request->validated();
        $companyId = $request->attributes->get('company_id');

        return DB::transaction(function () use ($payload, $companyId) {
            $createdJournalNumber = JournalNumber::create([
                'company_id' => $companyId,
                'name' => $payload['name'],
                'prefix' => $payload['prefix'],
                'reset_interval' => $payload['resetInterval'],
                'status' => $payload['status'],
            ]);

            return (new StoreJournalNumberResource($createdJournalNumber))
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJournalNumberRequest $request, JournalNumber $journalNumber) {
        // Validate incoming payload
        $payload = $request->validated();
        $companyId = $request->attributes->get('company_id');

        return DB::transaction(function () use ($payload, $journalNumber, $companyId) {
            // Soft delete the journal number if it's used in previous
            // transactions then create a new one with the received details
            if ($journalNumber->is_used) {
                $journalNumber->delete();

                $createdJournalNumber = JournalNumber::create([
                    'company_id' => $companyId,
                    'name' => $payload['name'],
                    'prefix' => $payload['prefix'],
                    'reset_interval' => $payload['resetInterval'],
                    'status' => $payload['status'],
                ]);

                return (new StoreJournalNumberResource($createdJournalNumber))
                    ->response()
                    ->setStatusCode(201);
            } else {
                $journalNumber->update([
                    'name' => $payload['name'],
                    'prefix' => $payload['prefix'],
                    'reset_interval' => $payload['resetInterval'],
                    'status' => $payload['status'],
                ]);

                return (new UpdateJournalNumberResource($journalNumber))
                    ->response()
                    ->setStatusCode(200);
            }
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DestroyJournalNumberRequest $request, JournalNumber $journalNumber) {
        // If the journal number is already used in journal entries, only apply a
        // soft delete, otherwise permanently delete it from the database
        return DB::transaction(function () use ($journalNumber) {
            $journalNumber->is_used
                ? $journalNumber->delete()
                : $journalNumber->forceDelete();

            return response()
                ->json([
                    'message' => 'Journal number deleted successfully'
                ])
                ->setStatusCode(200);
        });
    }
}
