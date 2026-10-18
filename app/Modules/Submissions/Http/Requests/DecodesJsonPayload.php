<?php

namespace App\Modules\Submissions\Http\Requests;

/**
 * Multipart submissions carry the form fields as one JSON string (`payload`)
 * next to the binary files. Decode it into `data` so it validates like normal input.
 */
trait DecodesJsonPayload
{
    protected function prepareForValidation(): void
    {
        $raw = $this->input('payload');
        $decoded = is_string($raw) && strlen($raw) <= 200_000 ? json_decode($raw, true, 8) : null;

        $this->merge(['data' => is_array($decoded) ? $decoded : null]);
    }
}
