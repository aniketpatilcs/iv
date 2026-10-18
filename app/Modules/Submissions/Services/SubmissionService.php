<?php

namespace App\Modules\Submissions\Services;

use App\Models\User;
use App\Modules\Submissions\Contracts\Submission;
use App\Modules\Submissions\Events\SubmissionCreated;
use App\Modules\Submissions\Models\Upload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubmissionService
{
    /**
     * Persist a submission, assign its reference number and attach its uploaded files
     * in one transaction. Files were already stored by UploadController, so this only
     * writes rows — it stays fast no matter how many or how large the files are.
     *
     * @template TModel of Model&Submission
     *
     * @param  HasMany<TModel>  $relation  the owning user's relation, e.g. $user->assignments()
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{upload_id: string, category: string, document_type: ?string}>  $files
     * @return TModel
     *
     * @throws ValidationException when an upload was claimed or pruned in the meantime
     */
    public function create(HasMany $relation, array $attributes, array $files): Model
    {
        $user = $relation->getParent();
        if (! $user instanceof User) {
            throw new RuntimeException('Submissions must be created through a user relation.');
        }

        /** @var TModel $submission */
        $submission = DB::transaction(function () use ($relation, $attributes, $files, $user): Model {
            $submission = $relation->create($attributes);

            // Derived from the auto-increment id: unique and gap-tolerant without a lock.
            // Saved quietly so the audit log records one "created" entry, not a second "updated".
            $submission->forceFill([
                $submission->referenceColumn() => $submission->referencePrefix().str_pad((string) $submission->getKey(), 7, '0', STR_PAD_LEFT),
            ])->saveQuietly();

            if ($files !== []) {
                $this->claimUploads($submission, $user, $files);
            }

            return $submission;
        });

        SubmissionCreated::dispatch($submission, $user);

        return $submission;
    }

    /**
     * Turn the user's pending uploads into attachments. The row lock makes a double
     * submit wait here and then fail cleanly instead of attaching the same file twice.
     *
     * @param  array<int, array{upload_id: string, category: string, document_type: ?string}>  $files
     */
    private function claimUploads(Model&Submission $submission, User $user, array $files): void
    {
        $uploads = Upload::query()
            ->where('user_id', $user->getKey())
            ->whereKey(array_column($files, 'upload_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($files as $item) {
            $upload = $uploads->get($item['upload_id']);
            if (! $upload) {
                throw ValidationException::withMessages([
                    'files' => 'A file is no longer available. Remove it and add it again.',
                ]);
            }

            $rows[] = [
                'category' => $item['category'],
                'document_type' => $item['document_type'],
                'disk' => $upload->disk,
                'path' => $upload->path,
                'original_name' => $upload->original_name,
                'mime_type' => $upload->mime_type,
                'size' => $upload->size,
            ];
        }

        $submission->attachments()->createMany($rows);

        // The files now belong to the attachments; drop the pending rows so pruning leaves them alone.
        Upload::query()->whereKey($uploads->keys())->delete();
    }
}
