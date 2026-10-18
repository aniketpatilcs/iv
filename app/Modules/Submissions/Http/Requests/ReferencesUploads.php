<?php

namespace App\Modules\Submissions\Http\Requests;

use App\Modules\Submissions\Models\Upload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * For submission requests whose files were uploaded in the background: the form
 * sends upload ids instead of files. Checks every id in one query — it must exist,
 * belong to the current user, be the right kind for its field, and be unclaimed.
 *
 * @mixin FormRequest
 */
trait ReferencesUploads
{
    /**
     * @return array<string, mixed>
     */
    protected function uploadRules(string $field, int $max, bool $required = false): array
    {
        return [
            $field => [$required ? 'required' : 'nullable', 'array', "max:{$max}"],
            "{$field}.*" => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    /**
     * @param  array<string, string>  $fields  request field => upload kind it must hold
     */
    protected function verifyUploads(Validator $validator, array $fields): void
    {
        $validator->after(function (Validator $validator) use ($fields) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = collect(array_keys($fields))->flatMap(fn (string $field) => (array) $this->input($field, []))->all();
            if ($ids === []) {
                return;
            }

            $kinds = Upload::query()
                ->where('user_id', $this->user()?->getKey())
                ->whereKey($ids)
                ->pluck('kind', 'id');

            foreach ($fields as $field => $kind) {
                foreach ((array) $this->input($field, []) as $i => $id) {
                    if (($kinds[$id] ?? null) !== $kind) {
                        $validator->errors()->add("{$field}.{$i}", 'A file is no longer available. Remove it and add it again.');
                    }
                }
            }
        });
    }

    /**
     * @param  array<int, mixed>  $details  per-file details from the payload, aligned by index
     * @return array<int, array{upload_id: string, category: string, document_type: ?string}>
     */
    protected function uploadsFor(string $field, string $category, array $details = []): array
    {
        return collect((array) $this->input($field, []))
            ->values()
            ->map(fn (string $id, int $i) => [
                'upload_id' => $id,
                'category' => $category,
                'document_type' => is_array($details[$i] ?? null) ? ($details[$i]['documentType'] ?? null) : null,
            ])
            ->all();
    }
}
