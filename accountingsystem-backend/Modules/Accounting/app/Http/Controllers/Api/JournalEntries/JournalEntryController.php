<?php

namespace Modules\Accounting\Http\Controllers\Api\JournalEntries;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Requests\JournalEntries\FetchEntryForEditRequest;
use Modules\Accounting\Http\Requests\JournalEntries\IndexJournalEntryRequest;
use Modules\Accounting\Http\Requests\JournalEntries\PostEntryRequest;
use Modules\Accounting\Http\Requests\JournalEntries\StoreJournalEntryRequest;
use Modules\Accounting\Http\Requests\JournalEntries\UpdateJournalEntryRequest;
use Modules\Accounting\Http\Requests\JournalEntries\VoidEntryRequest;
use Modules\Accounting\Http\Resources\JournalEntries\FetchEntryForEditResource;
use Modules\Accounting\Http\Resources\JournalEntries\IndexJournalEntryResource;
use Modules\Accounting\Http\Resources\JournalEntries\PostEntryResource;
use Modules\Accounting\Http\Resources\JournalEntries\StoreJournalEntryResource;
use Modules\Accounting\Http\Resources\JournalEntries\UpdateJournalEntryResource;
use Modules\Accounting\Http\Resources\JournalEntries\VoidEntryResource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Services\JournalEntries\JournalEntryCommandService;
use Modules\Accounting\Services\JournalEntries\JournalEntryQueryService;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Services\SalesInvoiceCommandService;

class JournalEntryController
{
    public function __construct(
        private readonly JournalEntryCommandService $journalEntryCommandService,
        private readonly JournalEntryQueryService $journalEntryQueryService,
        private readonly SalesInvoiceCommandService $salesInvoiceCommandService
    )
    {
    }

    public function index(IndexJournalEntryRequest $request): JsonResponse
    {
        $journalEntries = $this->journalEntryQueryService->paginate($request->validated());

        return IndexJournalEntryResource::collection($journalEntries)->response();
    }

    /**
     * Retrieve the specified entry for editing.
     *
     * @param FetchEntryForEditRequest $request Used to check if the user requesting the entry is authorized to edit it
     * @param JournalEntry $journalEntry
     * @return FetchEntryForEditResource
     */
    public function fetchEntryForEdit(FetchEntryForEditRequest $request, JournalEntry $journalEntry)
    {
        $journalEntry->load([
            'fundSource',
            'journalItems.account',
            'journalItems.tax',
        ]);

        return new FetchEntryForEditResource($journalEntry);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $createdJournalEntry = $this->journalEntryCommandService->create($request->validated());

        return response()->json(
            (new StoreJournalEntryResource($createdJournalEntry))->resolve(),
            201
        );
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        $updatedJournalEntry = $this->journalEntryCommandService
            ->update(
                $request->validated(),
                $journalEntry->load(['journalItems', 'journalItems.account', 'orgUnit'])
            );

        return response()
            ->json(new UpdateJournalEntryResource($updatedJournalEntry))
            ->setStatusCode(200);
    }

    /**
     * Update an entry's status to "posted"
     */
    public function postEntry(PostEntryRequest $request, JournalEntry $journalEntry)
    {
        return DB::transaction(function () use ($journalEntry) {
            $journalEntry->update(['status' => 'posted']);

            return response()
                ->json(new PostEntryResource($journalEntry))
                ->setStatusCode(200);
        });
    }

    /**
     * Update an entry's status to "void"
     */
    public function voidEntry(VoidEntryRequest $request, JournalEntry $journalEntry)
    {
        $sourceInvoice = SalesInvoice::query()
            ->where('company_id', $journalEntry->company_id)
            ->where('journal_entry_id', $journalEntry->getKey())
            ->first();

        if ($sourceInvoice instanceof SalesInvoice) {
            $this->salesInvoiceCommandService->cancel($sourceInvoice, 'Voided from linked journal entry.');
            $voidedJournalEntry = $journalEntry->fresh() ?? $journalEntry;

            return response()
                ->json(new VoidEntryResource($voidedJournalEntry))
                ->setStatusCode(200);
        }

        $legacyReversalInvoice = SalesInvoice::query()
            ->where('company_id', $journalEntry->company_id)
            ->where('reversal_journal_entry_id', $journalEntry->getKey())
            ->exists();

        if ($legacyReversalInvoice) {
            return response()->json([
                'message' => 'This journal entry is a legacy sales invoice reversal. Void the original sales invoice journal entry instead.',
            ], 409);
        }

        $voidedJournalEntry = $this->journalEntryCommandService->void($journalEntry);

        return response()
            ->json(new VoidEntryResource($voidedJournalEntry))
            ->setStatusCode(200);
    }
}
