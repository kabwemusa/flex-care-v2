<?php
namespace Modules\Medical\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $addonId = $this->route('addon');

        return [
            'name'         => 'required|string|max:255|unique:med_addons,name,' . $addonId,
            'description'  => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'is_mandatory'    => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'price.min' => 'The premium amount cannot be less than zero.',
            'name.unique'      => 'An add-on with this name already exists in the system.',
        ];
    }
}