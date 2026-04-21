<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'expire_at' => ['nullable', 'date'],
            'pictures_enabled' => ['nullable', 'boolean'],
            'audios_enabled' => ['nullable', 'boolean'],
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
            'name.required' => 'Please provide a party name.',
            'name.max' => 'The party name must not exceed 255 characters.',
            'user_id.exists' => 'The selected owner does not exist.',
            'expire_at.date' => 'The expiry date must be a valid date.',
            'pictures_enabled.boolean' => 'Pictures availability must be true or false.',
            'audios_enabled.boolean' => 'Audios availability must be true or false.',
        ];
    }
}
