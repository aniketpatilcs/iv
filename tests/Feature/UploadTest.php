<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Submissions\Models\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
    }

    public function test_guests_cannot_upload(): void
    {
        $this->postJson('/uploads', ['kind' => 'vehicle_image', 'file' => UploadedFile::fake()->image('a.jpg')])
            ->assertUnauthorized();
    }

    public function test_a_file_is_stored_and_returns_an_id(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)
            ->postJson('/uploads', ['kind' => 'vehicle_image', 'file' => UploadedFile::fake()->image('front.jpg', 1200, 900)])
            ->assertCreated()
            ->json('id');

        $upload = Upload::findOrFail($id);
        $this->assertSame($user->id, $upload->user_id);
        $this->assertSame('vehicle_image', $upload->kind);
        $this->assertSame('front.jpg', $upload->original_name);
        Storage::disk($upload->disk)->assertExists($upload->path);
    }

    public function test_file_types_are_checked_per_kind(): void
    {
        $user = User::factory()->create();
        $pdf = fn () => UploadedFile::fake()->create('estimate.pdf', 10, 'application/pdf');
        $sheet = fn () => UploadedFile::fake()->create('acv.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)->postJson('/uploads', ['kind' => 'vehicle_image', 'file' => $pdf()])->assertJsonValidationErrors('file');
        $this->actingAs($user)->postJson('/uploads', ['kind' => 'pickup_document', 'file' => $sheet()])->assertJsonValidationErrors('file');
        $this->actingAs($user)->postJson('/uploads', ['kind' => 'bid_document', 'file' => $sheet()])->assertCreated();
        $this->actingAs($user)->postJson('/uploads', ['kind' => 'avatar', 'file' => $pdf()])->assertJsonValidationErrors('kind');
    }

    public function test_files_over_20_mb_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/uploads', ['kind' => 'bid_document', 'file' => UploadedFile::fake()->create('big.pdf', Upload::MAX_KB + 1, 'application/pdf')])
            ->assertJsonValidationErrors(['file' => 'Each file must be 20 MB or smaller.']);
    }

    public function test_pending_uploads_per_user_are_capped(): void
    {
        $user = User::factory()->create();
        Upload::withoutEvents(function () use ($user) {
            for ($i = 0; $i < Upload::MAX_PENDING_PER_USER; $i++) {
                $user->uploads()->create(['kind' => 'vehicle_image', 'disk' => 'local', 'path' => "x/{$i}.jpg", 'original_name' => 'x.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
            }
        });

        $this->actingAs($user)
            ->postJson('/uploads', ['kind' => 'vehicle_image', 'file' => UploadedFile::fake()->image('one-more.jpg')])
            ->assertJsonValidationErrors('file');
    }

    public function test_abandoned_uploads_and_their_files_are_pruned(): void
    {
        $user = User::factory()->create();
        $post = fn (string $name) => $this->actingAs($user)
            ->postJson('/uploads', ['kind' => 'vehicle_image', 'file' => UploadedFile::fake()->image($name)])
            ->json('id');

        $abandoned = Upload::findOrFail($post('old.jpg'));
        $abandoned->forceFill(['created_at' => now()->subHours(Upload::ABANDONED_AFTER_HOURS + 1)])->save();
        $recent = Upload::findOrFail($post('new.jpg'));

        $this->artisan('model:prune', ['--model' => [Upload::class]])->assertSuccessful();

        $this->assertModelMissing($abandoned);
        Storage::disk($abandoned->disk)->assertMissing($abandoned->path);
        $this->assertModelExists($recent);
        Storage::disk($recent->disk)->assertExists($recent->path);
    }
}
