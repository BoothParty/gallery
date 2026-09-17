<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAudioMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Naming a message is the client's to do and always optional: an empty
     * title clears it back to the order of passage.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Le titre ne peut pas dépasser 120 caractères.',
        ];
    }
}
