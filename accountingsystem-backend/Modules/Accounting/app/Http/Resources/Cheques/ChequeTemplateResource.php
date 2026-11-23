<?php

namespace Modules\Accounting\Http\Resources\Cheques;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\Cheques\ChequeTemplate;

/**
 * @mixin ChequeTemplate
 */
final class ChequeTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'name' => (string) $this->name,
            'bank_name' => (string) $this->bank_name,
            'width_mm' => (float) $this->width_mm,
            'height_mm' => (float) $this->height_mm,
            'status' => (string) $this->status,
            'is_default' => (bool) $this->is_default,
            'active_version' => $this->whenLoaded('activeVersion', fn (): ?array => $this->activeVersion === null ? null : [
                'id' => (string) $this->activeVersion->getKey(),
                'version' => (int) $this->activeVersion->version,
                'date_format' => (string) $this->activeVersion->date_format,
                'layout' => $this->activeVersion->layout,
                'default_print_options' => $this->activeVersion->default_print_options,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
