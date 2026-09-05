<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminPartyRequest extends FormRequest
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
        /** @var Party $party */
        $party = $this->route('party');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('parties', 'name')->ignore($party->id)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'expire_at' => ['nullable', 'date'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'banner' => ['nullable', 'image', 'max:10240'],
            'remove_banner' => ['nullable', 'boolean'],
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
            'name.required' => 'Please provide a party name.',
            'name.max' => 'The party name must not exceed 255 characters.',
            'name.unique' => 'A party already uses this name.',
            'user_id.exists' => 'The selected owner does not exist.',
            'expire_at.date' => 'The expiry date must be a valid date.',
            'display_name.max' => 'The display name must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 255 characters.',
            'banner.image' => 'The banner must be an image.',
            'banner.max' => 'The banner must not exceed 10 MB.',
        ];
    }
}
