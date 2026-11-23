<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountsResource extends JsonResource
{
    private static function statementGroupFromType(string $type): string
    {
        return in_array($type, ['asset', 'liability', 'equity'], true)
            ? 'balance_sheet'
            : 'income_statement';
    }

    /**
     * Transform the resource into an array.
     *
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id ?? $this->tenant_id,
            'tenant_id' => $this->tenant_id ?? $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'account_type_id' => $this->account_type_id,
            'account_type_name' => $this->account_type_name,
            'detail_type_id' => $this->detail_type_id,
            'detail_type_name' => $this->detail_type_name,
            'parent_id' => $this->parent_id,
            'is_posting' => (bool)$this->is_posting,
            'status' => $this->status,
            'normal_balance' => $this->normal_balance,
            'is_contra' => (bool)$this->is_contra,
            'is_system' => (bool)$this->is_system,
            'statement_group' => self::statementGroupFromType((string)$this->type),
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
