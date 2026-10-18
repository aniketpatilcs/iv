<?php

namespace App\Modules\Submissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Submissions\Http\Requests\StoreUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class UploadController extends Controller
{
    /**
     * Receives one file while the user is still filling in the form. The file is
     * written straight to its permanent location, so submitting the form later
     * only links it to the record — no copy or move.
     */
    public function store(StoreUploadRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $disk = (string) config('filesystems.default');

        $path = $file->store('uploads/'.now()->format('Y/m/d'), $disk);
        if ($path === false) {
            throw new RuntimeException('File storage failed.');
        }

        $upload = $request->user()->uploads()->create([
            'kind' => $request->string('kind')->value(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return response()->json(['id' => $upload->id], 201);
    }
}
