<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAudioMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The handset writes MP3 on its card, so the files arrive ready to serve.
     *
     * `recorded_at` comes alongside them because a multipart upload carries no
     * modification time: the browser reads it from the file and sends it by
     * index. Missing entries are tolerated — an unknown time is better than a
     * refused upload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1'],
            'messages.*' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3', 'max:51200'],
            'recorded_at' => ['nullable', 'array'],
            'recorded_at.*' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'messages.required' => 'Aucun fichier reçu.',
            'messages.*.mimetypes' => 'Le combiné enregistre en MP3 : seuls des MP3 sont acceptés.',
            'messages.*.max' => 'Un des fichiers dépasse 50 Mo.',
        ];
    }
}
