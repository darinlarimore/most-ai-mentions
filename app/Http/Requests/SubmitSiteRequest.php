<?php

namespace App\Http\Requests;

use App\Enums\SiteCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SubmitSiteRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', new Enum(SiteCategory::class)],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $host = parse_url($this->input('url'), PHP_URL_HOST);

            if ($host && \App\Models\Site::where('domain', preg_replace('/^www\./', '', $host))->exists()) {
                $validator->errors()->add('url', 'This site has already been submitted.');
            }
        });
    }
}
