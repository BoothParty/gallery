<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadPicturesRequest extends FormRequest
{
    /**
     * A visitor may download from any gallery that is still published; an
     * expired one hides its pictures and must not hand them out either.
     */
    public function authorize(): bool
    {
        /** @var Party $party */
        $party = $this->route('party');

        return ! $party->hidePictures();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Party $party */
        $party = $this->route('party');

        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.config('party.download.max_pictures')],
            'ids.*' => [
                'required',
                'integer',
                Rule::exists('pictures', 'id')->where('party_id', $party->id),
            ],
        ];
    }
}
