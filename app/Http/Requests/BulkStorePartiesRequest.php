<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkStorePartiesRequest extends FormRequest
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
            'parties' => ['required', 'array', 'min:1'],
            'parties.*.name' => [
                'nullable',
                'string',
                'max:255',
                'distinct',
                Rule::notIn(Party::RESERVED_NAMES),
                'unique:parties,name',
            ],
            'parties.*.source_event_id' => ['nullable', 'string', 'max:255'],
            'parties.*.client_email' => ['nullable', 'email', 'max:255'],
            'parties.*.event_at' => ['nullable', 'date'],
            'parties.*.has_gallery' => ['sometimes', 'boolean'],
            'parties.*.has_audio_guestbook' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A party's name is its subdomain, so it is required exactly when the
     * gallery is sold and meaningless otherwise.
     *
     * Expressed here rather than as a `required_if` rule because the sibling
     * lives under the same wildcard index, which reads far worse than the
     * explicit loop.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('parties', []) as $index => $party) {
                    $wantsGallery = filter_var($party['has_gallery'] ?? true, FILTER_VALIDATE_BOOLEAN);

                    if ($wantsGallery && blank($party['name'] ?? null)) {
                        $validator->errors()->add(
                            "parties.{$index}.name",
                            'A gallery needs a name: it is the subdomain it is published under.',
                        );
                    }
                }
            },
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
            'parties.required' => 'Please select at least one event.',
            'parties.min' => 'Please select at least one event.',
            'parties.*.name.max' => 'The party name must not exceed 255 characters.',
            'parties.*.name.distinct' => 'This party name is used twice.',
            'parties.*.name.unique' => 'A party already uses this name.',
            'parties.*.name.not_in' => 'This name is reserved by the application.',
            'parties.*.client_email.email' => 'The client address is not a valid email.',
        ];
    }
}
