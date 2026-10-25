<?php

namespace App\Http\Requests;

use App\Http\Middleware\ResolvePartyHost;
use App\Models\Party;
use App\Rules\PictureBelongsToParty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReprintPicturesRequest extends FormRequest
{
    /**
     * Only the host asks for prints (the `party.host` middleware), and an
     * expired gallery no longer hands out its pictures, printed or not.
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
                'distinct',
                new PictureBelongsToParty($party, ResolvePartyHost::isHost($this, $party)),
            ],
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
            'ids.required' => 'Please select at least one picture.',
            'ids.min' => 'Please select at least one picture.',
            'ids.max' => 'You cannot reprint more than :max pictures at once.',
            'ids.*.required' => 'Please select at least one picture.',
            'ids.*.integer' => 'The selected picture is invalid.',
            'ids.*.distinct' => 'The same picture was selected twice.',
        ];
    }
}
