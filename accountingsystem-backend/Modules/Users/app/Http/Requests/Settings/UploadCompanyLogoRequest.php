<?php

namespace Modules\Users\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class UploadCompanyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:min_width=64,min_height=64,max_width=1024,max_height=1024',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Choose a company logo to upload.',
            'logo.image' => 'The company logo must be an image file.',
            'logo.mimes' => 'The company logo must be a JPG, PNG, or WebP image.',
            'logo.max' => 'The company logo must be 2 MB or smaller.',
            'logo.dimensions' => 'The company logo must be between 64x64 and 1024x1024 pixels.',
        ];
    }
}
