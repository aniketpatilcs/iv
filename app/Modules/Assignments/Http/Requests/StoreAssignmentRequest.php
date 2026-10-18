<?php

namespace App\Modules\Assignments\Http\Requests;

use App\Modules\Submissions\Http\Requests\DecodesJsonPayload;
use App\Modules\Submissions\Http\Requests\ReferencesUploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
{
    use DecodesJsonPayload, ReferencesUploads;

    /** Mirrors `pickupDocumentTypes` in resources/js/legacy/assignment/script.js. */
    public const DOCUMENT_TYPES = [
        'Pickup Release Document', 'Title', 'Hold Harmless', 'Miscellaneous Document', 'Police Release',
    ];

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
            'data.assignmentType' => ['required', Rule::in(['Standard', 'Transport Only'])],
            'data.sections' => ['required', 'array', 'max:20'],
            'data.sections.*' => ['array', 'max:60'],
            'data.sections.*.*' => ['nullable', 'string', 'max:2000'],
            'data.sections.assignment.claimNumber' => ['required', 'string', 'max:50'],
            'data.sections.vehicle.vin' => ['required', 'string', 'max:17'],
            'data.documentAssistance' => ['nullable', 'array', 'max:5'],
            'data.pickupDocumentDetails' => ['nullable', 'array', 'max:20'],
            'data.pickupDocumentDetails.*.documentType' => ['nullable', Rule::in(self::DOCUMENT_TYPES)],
            ...$this->uploadRules('images', 30),
            ...$this->uploadRules('pickup_documents', 20),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->verifyUploads($validator, [
            'images' => 'vehicle_image',
            'pickup_documents' => 'pickup_document',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.sections.assignment.claimNumber.required' => 'Enter the claim number.',
            'data.sections.vehicle.vin.required' => 'Enter the VIN.',
            'images.max' => 'Attach at most 30 images.',
            'pickup_documents.max' => 'Attach at most 20 documents.',
        ];
    }

    /**
     * @return array<int, array{upload_id: string, category: string, document_type: ?string}>
     */
    public function attachments(): array
    {
        return [
            ...$this->uploadsFor('images', 'vehicle_image'),
            ...$this->uploadsFor('pickup_documents', 'pickup_document', (array) $this->input('data.pickupDocumentDetails', [])),
        ];
    }
}
