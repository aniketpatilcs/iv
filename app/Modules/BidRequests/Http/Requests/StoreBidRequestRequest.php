<?php

namespace App\Modules\BidRequests\Http\Requests;

use App\Modules\Submissions\Http\Requests\DecodesJsonPayload;
use App\Modules\Submissions\Http\Requests\ReferencesUploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBidRequestRequest extends FormRequest
{
    use DecodesJsonPayload, ReferencesUploads;

    /** Mirrors the document type choices in resources/js/legacy/bid/script.js. */
    public const DOCUMENT_TYPES = ['estimate', 'acv', 'other', 'combined'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'max:200000'],
            'data' => ['required', 'array'],
            'data.fields' => ['required', 'array', 'max:80'],
            'data.fields.*' => ['nullable', 'max:2000'],
            'data.fields.claimNumber' => ['required', 'string', 'max:50'],
            'data.fields.vin' => ['required', 'string', 'max:17'],
            'data.noImages' => ['boolean'],
            'data.noDocuments' => ['boolean'],
            'data.documentDetails' => ['nullable', 'array', 'max:20'],
            'data.documentDetails.*.documentType' => ['nullable', Rule::in(self::DOCUMENT_TYPES)],
            // Images and documents are required unless the user ticked "not available".
            ...$this->uploadRules('images', 30, required: ! $this->boolean('data.noImages')),
            ...$this->uploadRules('documents', 20, required: ! $this->boolean('data.noDocuments')),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->verifyUploads($validator, [
            'images' => 'vehicle_image',
            'documents' => 'bid_document',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.fields.claimNumber.required' => 'Enter the claim number.',
            'data.fields.vin.required' => 'Enter the VIN.',
            'images.required' => 'Upload vehicle images or confirm they are not available.',
            'documents.required' => 'Upload documents or confirm they are not available.',
            'images.max' => 'Attach at most 30 images.',
            'documents.max' => 'Attach at most 20 documents.',
        ];
    }

    /**
     * @return array<int, array{upload_id: string, category: string, document_type: ?string}>
     */
    public function attachments(): array
    {
        return [
            ...$this->uploadsFor('images', 'vehicle_image'),
            ...$this->uploadsFor('documents', 'document', (array) $this->input('data.documentDetails', [])),
        ];
    }
}
