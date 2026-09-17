<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectAudioGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The reason is mandatory: a greeting sent back without one leaves the
     * client guessing, and they will record the same take again.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Indiquez au client ce qui ne va pas.',
            'rejection_reason.max' => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
