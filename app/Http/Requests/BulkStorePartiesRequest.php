<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkStorePartiesRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parties' => ['required', 'array', 'min:1'],
            'parties.*.name' => ['required', ...Party::nameRules(), 'distinct', 'unique:parties,name'],
            'parties.*.source_event_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parties.required' => 'Please select at least one event.',
            'parties.min' => 'Please select at least one event.',
            'parties.*.name.required' => 'Please provide a party name.',
            'parties.*.name.max' => 'The party name must not exceed 63 characters.',
            'parties.*.name.regex' => Party::nameRuleMessage(),
            'parties.*.name.distinct' => 'This party name is used twice.',
            'parties.*.name.unique' => 'A party already uses this name.',
        ];
    }
}
