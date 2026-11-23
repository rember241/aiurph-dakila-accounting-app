<?php

namespace Modules\Accounting\Http\Controllers\Api\PeriodManagement;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Services\PeriodManagement\Commands\TransitionPeriodStatusHandler;
use Modules\Accounting\DTO\PeriodManagement\TransitionPeriodStatusData;
use Modules\Accounting\Services\PeriodManagement\Queries\GetAllAccountingPeriodsQuery;
use Modules\Accounting\Services\PeriodManagement\Queries\PaginateAccountingPeriodsQuery;
use Modules\Accounting\Http\Requests\PeriodManagement\TransitionPeriodStatusRequest;

final class AccountingPeriodController
{
    public function index(
        Request $request,
        PaginateAccountingPeriodsQuery $paginatedQuery,
        GetAllAccountingPeriodsQuery $unpaginatedQuery
    ): JsonResponse
    {
        $params = $request->all();

        // Return an unpaginated response if isPaginated is set to false
        if (isset($params['isPaginated']) && !filter_var($params['isPaginated'], FILTER_VALIDATE_BOOLEAN)) {
            $periods = $unpaginatedQuery->run($params);
            return response()->json($periods);
        }

        $page = $paginatedQuery->run($params);
        return response()->json([
            'data' => $page->items(),
            'page' => $page->currentPage(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
        ]);
    }


    /**
     * Transition an accounting period status.
     *
     * This controller method transitions the status of an accounting period to the
     * target status specified in the request. The target status can be one of `OPEN`,
     * `SOFT_CLOSED`, or `CLOSED`.
     *
     * @param string $id The ID of the accounting period to transition.
     * @param TransitionPeriodStatusRequest $request The HTTP request containing the target status.
     * @param TransitionPeriodStatusHandler $handler The handler for transitioning the period status.
     * @return JsonResponse The HTTP response containing the updated accounting period and a success message.
     */
    public function transition(
        string                        $id,
        TransitionPeriodStatusRequest $request,
        TransitionPeriodStatusHandler $handler
    ): JsonResponse
    {
        $period = $handler->handle(TransitionPeriodStatusData::fromArray($request->validated(), $id));

        return response()->json([
            'data' => $period,
            'message' => 'Period status updated successfully',
        ]);
    }

}
