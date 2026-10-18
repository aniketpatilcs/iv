<?php

namespace App\Modules\Submissions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $category
 * @property string|null $document_type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 */
class Attachment extends Model
{
    protected $fillable = [
        'category',
        'document_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * @return MorphTo<Model, self>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
