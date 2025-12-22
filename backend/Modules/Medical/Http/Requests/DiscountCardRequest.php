<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscountCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('discount_card');

        return [
            'plan_id'      => 'nullable|exists:med_plans,id',
            'name'         => 'required|string|max:255',
            'code'         => 'required|string|unique:med_discount_cards,code,' . $id,
            'type'         => 'required|in:percentage,fixed',
            'value'        => 'required|numeric|min:0',
            
            // JSON Rule Validation
            'trigger_rule'          => 'required|array',
            'trigger_rule.field'    => 'required|string', // e.g., 'frequency'
            'trigger_rule.operator' => 'required|in:=,>,<,!=,IN',
            'trigger_rule.value'    => 'required|string', // e.g., 'annual'
            
            'valid_from'   => 'required|date',
            'valid_until'  => 'nullable|date|after_or_equal:valid_from',
        ];
    }
}