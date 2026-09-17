<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartyRequest extends FormRequest
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
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::notIn(Party::RESERVED_NAMES),
                Rule::unique('parties', 'name'),
            ],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'expire_at' => ['nullable', 'date'],
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
            'name.max' => 'The party name must not exceed 255 characters.',
            'name.unique' => 'A party already uses this name.',
            'name.not_in' => 'This name is reserved by the application.',
            'user_id.exists' => 'The selected owner does not exist.',
            'expire_at.date' => 'The expiry date must be a valid date.',
        ];
    }
}
