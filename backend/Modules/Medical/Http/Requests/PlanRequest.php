<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan'); // For unique check on update

        return [
            'scheme_id' => 'required|exists:med_schemes,id',
            'name'      => 'required|string|max:255',
            // 'code'      => [
            //     'required', 
            //     'string', 
            //     Rule::unique('med_plans', 'code')->ignore($planId)
            // ],
            'type'      => 'required|string|in:Individual,Family,SME,Corporate',
        ];
    }
}