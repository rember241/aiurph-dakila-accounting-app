<?php

namespace Modules\Accounting\Http\Requests\PeriodManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Accounting\Enums\PeriodManagement\PeriodStatus;


/**
 * A request for transitioning the status of an accounting period.
 */
final class TransitionPeriodStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * It validates that the 'status' field is required and must be a valid value of the PeriodStatus Enum.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(PeriodStatus::class)],
        ];
    }

}
