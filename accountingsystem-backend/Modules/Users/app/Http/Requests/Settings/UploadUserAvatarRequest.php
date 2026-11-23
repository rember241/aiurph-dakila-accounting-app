<?php

namespace Modules\Users\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class UploadUserAvatarRequest extends FormRequest
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
            'avatar' => [
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
            'avatar.required' => 'Choose a profile picture to upload.',
            'avatar.image' => 'The profile picture must be an image file.',
            'avatar.mimes' => 'The profile picture must be a JPG, PNG, or WebP image.',
            'avatar.max' => 'The profile picture must be 2 MB or smaller.',
            'avatar.dimensions' => 'The profile picture must be between 64x64 and 1024x1024 pixels.',
        ];
    }
}
