<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartyMetadataRequest extends FormRequest
{
    /**
     * The right to edit is settled by the `party.host` middleware, not here,
     * as in the neighbouring requests.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The 60 and 90 character caps are tighter than the 255 the administration
     * allows: past them the serif title and its small-caps subtitle overflow
     * the hero. The administration keeps its headroom because it knows what it
     * is doing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:90'],
            'banner' => ['nullable', 'image', 'max:10240'],
            'remove_banner' => ['nullable', 'boolean'],
            'banner_position_x' => ['required', 'numeric', 'between:0,100'],
            'banner_position_y' => ['required', 'numeric', 'between:0,100'],
            'banner_zoom' => ['required', 'numeric', 'between:1,3'],
        ];
    }

    /**
     * In French, unlike the administration's requests: these are read by the
     * host, inside an interface that is French throughout.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.max' => 'Le nom de la galerie ne peut pas dépasser 60 caractères.',
            'description.max' => 'La description ne peut pas dépasser 90 caractères.',
            'banner.image' => 'La photo de couverture doit être une image.',
            'banner.max' => 'La photo de couverture ne peut pas dépasser 10 Mo.',
            'banner_position_x.between' => 'Le cadrage horizontal est hors limites.',
            'banner_position_y.between' => 'Le cadrage vertical est hors limites.',
            'banner_zoom.between' => 'Le zoom doit être compris entre 1 et 3.',
        ];
    }
}
