<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartyModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Both flags are optional: the sidebar's activation dialog sends the one
     * module it is turning on, never the pair.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'has_gallery' => ['sometimes', 'boolean'],
            'has_audio_guestbook' => ['sometimes', 'boolean'],
        ];
    }
}
