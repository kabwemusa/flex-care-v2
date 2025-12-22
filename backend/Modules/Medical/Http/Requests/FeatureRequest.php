<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255|unique:med_features,name,' . $this->route('feature'),
            'category' => 'required|string|in:Clinical,In-Patient,Out-Patient,Specialist,Dental,Optical',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'The selected category is not a valid medical benefit group.',
            'name.unique' => 'This benefit feature already exists in the library.',
        ];
    }
}