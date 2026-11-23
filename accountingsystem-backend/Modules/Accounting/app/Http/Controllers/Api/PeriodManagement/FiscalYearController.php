<?php

namespace Modules\Accounting\Http\Controllers\Api\PeriodManagement;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Services\PeriodManagement\Commands\CreateFiscalYearHandler;
use Modules\Accounting\Services\PeriodManagement\Commands\DeleteFiscalYearHandler;
use Modules\Accounting\Services\PeriodManagement\Commands\SetCurrentFiscalYearHandler;
use Modules\Accounting\Services\PeriodManagement\Commands\UpdateFiscalYearHandler;
use Modules\Accounting\DTO\PeriodManagement\CreateFiscalYearData;
use Modules\Accounting\DTO\PeriodManagement\SetCurrentFiscalYearData;
use Modules\Accounting\DTO\PeriodManagement\UpdateFiscalYearData;
use Modules\Accounting\Services\PeriodManagement\Queries\GetCurrentFiscalYear;
use Modules\Accounting\Services\PeriodManagement\Queries\GetPeriodManagementKpi;
use Modules\Accounting\Services\PeriodManagement\Queries\PaginateFiscalYearsQuery;
use Modules\Accounting\Http\Requests\PeriodManagement\CreateFiscalYearsRequest;
use Modules\Accounting\Http\Requests\PeriodManagement\UpdateFiscalYearRequest;


final class FiscalYearController
{
    public function index(Request $request, PaginateFiscalYearsQuery $query): JsonResponse
    {
        $page = $query->run($request->all());

        return response()->json([
            'data' => $page->items(),
            'page' => $page->currentPage(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(CreateFiscalYearsRequest $request, CreateFiscalYearHandler $handler): JsonResponse
    {
        $data = CreateFiscalYearData::fromArray($request->validated());

        $fy = $handler->handle($data);

        return response()->json($fy, 201, [
            'message' => 'Fiscal year created successfully',
        ]);
    }

    public function setCurrent(string $id, SetCurrentFiscalYearHandler $handler): JsonResponse
    {
        $data = SetCurrentFiscalYearData::fromArray([], $id);

        $current = $handler->handle($data);

        return response()->json($current, 200, [
            'message' => 'Fiscal year set as current successfully',
        ]);
    }

    public function update(
        string                  $id,
        UpdateFiscalYearRequest $request,
        UpdateFiscalYearHandler $handler
    ): JsonResponse
    {
        $fiscalYear = $handler->handle(UpdateFiscalYearData::fromArray($request->validated(), $id));

        return response()->json([
            'data' => $fiscalYear,
            'message' => 'Fiscal year settings updated successfully',
        ]);
    }

    public function getCurrent(GetCurrentFiscalYear $query): JsonResponse
    {
        $current = $query->run();

        return response()->json($current, 200, [
            'message' => 'Current fiscal year retrieved successfully',
        ]);
    }

    public function kpi(GetPeriodManagementKpi $query): JsonResponse
    {
        $data = $query->run();

        return response()->json([
            'data' => $data,
            'message' => 'Period management KPI retrieved successfully',
        ], 200);
    }

    public function destroy(string $id, DeleteFiscalYearHandler $handler): JsonResponse
    {
        $handler->handle($id);

        return response()->json([
            'message' => 'Fiscal year deleted successfully',
        ]);
    }

}
