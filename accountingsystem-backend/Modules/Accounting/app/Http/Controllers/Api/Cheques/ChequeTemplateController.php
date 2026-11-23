<?php

namespace Modules\Accounting\Http\Controllers\Api\Cheques;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Http\Resources\Cheques\ChequeTemplateResource;
use Modules\Accounting\Models\Cheques\ChequeTemplate;

final class ChequeTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = ChequeTemplate::query()
            ->forCompany($this->companyIdFromRequest($request))
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->orderBy('name')
            ->get()
            ->load('activeVersion');

        return response()->json(['data' => ChequeTemplateResource::collection($templates)]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyIdFromRequest($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:160'],
            'width_mm' => ['nullable', 'numeric', 'min:50', 'max:300'],
            'height_mm' => ['nullable', 'numeric', 'min:30', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
            'date_format' => ['nullable', 'string', 'max:40'],
            'layout' => ['nullable', 'array'],
            'default_print_options' => ['nullable', 'array'],
        ]);

        $template = DB::transaction(function () use ($companyId, $data): ChequeTemplate {
            if ((bool) ($data['is_default'] ?? false)) {
                ChequeTemplate::query()->forCompany($companyId)->update(['is_default' => false]);
            }

            $template = ChequeTemplate::query()->create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'bank_name' => $data['bank_name'],
                'width_mm' => $data['width_mm'] ?? 203.20,
                'height_mm' => $data['height_mm'] ?? 76.20,
                'status' => 'active',
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            $template->versions()->create([
                'company_id' => $companyId,
                'version' => 1,
                'date_format' => $data['date_format'] ?? 'MM-DD-YYYY',
                'layout' => $data['layout'] ?? $this->defaultLayout(),
                'default_print_options' => $data['default_print_options'] ?? ['x_offset_mm' => 0, 'y_offset_mm' => 0],
                'is_active' => true,
            ]);

            return $template;
        });

        return response()->json([
            'data' => new ChequeTemplateResource($template->load('activeVersion')),
            'message' => 'Cheque template created',
        ], 201);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultLayout(): array
    {
        return [
            'date' => ['x_mm' => 169, 'y_mm' => 18, 'font_size' => 10, 'format' => 'MM-DD-YYYY', 'letter_spacing_mm' => 2],
            'payee' => ['x_mm' => 42, 'y_mm' => 37, 'font_size' => 10, 'max_width_mm' => 118],
            'amount_number' => ['x_mm' => 166, 'y_mm' => 37, 'font_size' => 11, 'align' => 'right', 'max_width_mm' => 31],
            'amount_words' => ['x_mm' => 42, 'y_mm' => 48, 'font_size' => 9, 'max_width_mm' => 150],
            'crossing' => ['x_mm' => 12, 'y_mm' => 11, 'font_size' => 8],
            'bearer_strike' => ['x_mm' => 22, 'y_mm' => 38, 'width_mm' => 18],
        ];
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
