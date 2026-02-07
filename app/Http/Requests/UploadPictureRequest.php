<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadPictureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow anyone to upload to a party (you can add authorization logic here)
        // For example, check if party is expired:
        $party = Party::query()->find($this->route('party'))->first();

        if (! $party) {
            return false;
        }

        // Check if party has expired
        if ($party->expiration_date && $party->expiration_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                File::image()
                    ->min(10) // 10 KB minimum
                    ->max(10 * 1024), // 10 MB maximum
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
            'image.required' => 'An image file is required.',
            'image.image' => 'The file must be an image (jpg, jpeg, png, bmp, gif, or webp).',
            'image.min' => 'The image must be at least 10 KB.',
            'image.max' => 'The image must not exceed 10 MB.',
        ];
    }
}
