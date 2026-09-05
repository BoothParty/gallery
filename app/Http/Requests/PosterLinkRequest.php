<?php

namespace App\Http\Requests;

use App\Models\Party;
use App\Services\PosterBatchLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PosterLinkRequest extends FormRequest
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
            'parties' => ['required', 'string'],
        ];
    }

    /**
     * The selection is a comma separated list, so the identifiers are checked
     * once parsed rather than through an array rule.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->partyIds() === []) {
                    $validator->errors()->add('parties', 'Please select at least one party.');
                }
            },
        ];
    }

    /**
     * The selected party identifiers that actually exist.
     *
     * @return array<int, int>
     */
    public function partyIds(): array
    {
        $ids = app(PosterBatchLink::class)->parseIds($this->string('parties')->toString());

        $existing = Party::query()->whereIn('id', $ids)->pluck('id')->all();

        return array_values(array_filter(
            $ids,
            fn (int $id): bool => in_array($id, $existing, true),
        ));
    }
}
