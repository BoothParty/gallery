<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAudioGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The browser decides the container on its own — WebM/Opus on Chrome and
     * Firefox, MP4/AAC on Safari — so the rule names every one of them rather
     * than a single expected type.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recording' => [
                'required',
                'file',
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,video/webm,video/mp4',
                'max:25600',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recording.required' => 'Aucun enregistrement reçu.',
            'recording.mimetypes' => "Ce fichier n'est pas un enregistrement audio.",
            'recording.max' => "L'enregistrement dépasse la taille autorisée.",
        ];
    }
}
