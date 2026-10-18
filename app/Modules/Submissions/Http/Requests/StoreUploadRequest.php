<?php

namespace App\Modules\Submissions\Http\Requests;

use App\Modules\Submissions\Models\Upload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = Upload::KINDS[$this->input('kind')] ?? [];

        return [
            'kind' => ['required', Rule::in(array_keys(Upload::KINDS))],
            'file' => ['required', 'file', 'max:'.Upload::MAX_KB, ...($extensions ? ['mimes:'.implode(',', $extensions)] : [])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'Each file must be 20 MB or smaller.',
            'file.mimes' => 'This file type is not accepted here.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pending = Upload::query()->where('user_id', $this->user()?->getKey())->count();

            if ($pending >= Upload::MAX_PENDING_PER_USER) {
                $validator->errors()->add('file', 'Too many files are waiting to be submitted. Submit or clear a form first.');
            }
        });
    }
}
