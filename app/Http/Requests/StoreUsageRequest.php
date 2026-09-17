<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUsageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'feature' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.-]+$/i'],
            'delta' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'metadata' => ['nullable', 'array', 'max:10'],
            'metadata.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'feature' => mb_strtolower(trim((string) $this->input('feature'))),
        ]);
    }
}
