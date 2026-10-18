<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assignments\Models\Assignment;
use App\Modules\Submissions\Notifications\SubmissionReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        Notification::fake();
    }

    private function assignmentPayload(array $overrides = []): string
    {
        return json_encode(array_replace_recursive([
            'assignmentType' => 'Transport Only',
            'insuranceCompany' => 'Spoofed Carrier',
            'sections' => [
                'assignment' => ['claimNumber' => 'CLM-1001', 'Date of Loss' => '04/17/2026'],
                'people' => ['ownerFirst' => 'Jane', 'ownerLast' => 'Doe'],
                'vehicle' => ['vin' => '1hgcm82633a004352', 'vehicleYear' => '2020', 'vehicleMake' => 'Honda', 'vehicleModel' => 'Accord'],
                'location' => ['pickupState' => 'California'],
            ],
            'documentAssistance' => null,
            'pickupDocumentDetails' => [['name' => 'release.pdf', 'documentType' => 'Title']],
        ], $overrides));
    }

    public function test_form_pages_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/assignments/create?type=transport')
            ->assertInertia(fn (Assert $page) => $page->component('Assignments/Create')->where('initialType', 'Transport Only'));

        $this->actingAs($user)->get('/bid-requests/create')
            ->assertInertia(fn (Assert $page) => $page->component('BidRequests/Create'));
    }

    /**
     * Upload a file the way the form does in the background, returning its id.
     */
    private function upload(User $user, string $kind, UploadedFile $file): string
    {
        return $this->actingAs($user)
            ->postJson('/uploads', ['kind' => $kind, 'file' => $file])
            ->assertCreated()
            ->json('id');
    }

    public function test_assignment_is_stored_with_files(): void
    {
        $user = User::factory()->create(['insurance_company' => 'Real Carrier']);
        $image = $this->upload($user, 'vehicle_image', UploadedFile::fake()->image('front.jpg'));
        $document = $this->upload($user, 'pickup_document', UploadedFile::fake()->create('release.pdf', 100, 'application/pdf'));

        $response = $this->actingAs($user)->postJson('/assignments', [
            'payload' => $this->assignmentPayload(),
            'images' => [$image],
            'pickup_documents' => [$document],
        ]);

        $response->assertCreated()->assertJson(['stockNumber' => 'IV0000001']);

        $assignment = Assignment::firstOrFail();
        $this->assertSame(Assignment::TYPE_TRANSPORT, $assignment->type);
        $this->assertSame('CLM-1001', $assignment->claim_number);
        $this->assertSame('1HGCM82633A004352', $assignment->vin);
        $this->assertSame('Jane Doe', $assignment->owner_name);
        $this->assertSame('2020 Honda Accord', $assignment->vehicle_description);
        $this->assertSame('2026-04-17', $assignment->loss_date->toDateString());
        // Account data wins over anything the browser sends.
        $this->assertSame('Real Carrier', $assignment->payload['insuranceCompany']);

        $this->assertCount(2, $assignment->attachments);
        $attachment = $assignment->attachments->firstWhere('category', 'pickup_document');
        $this->assertSame('Title', $attachment->document_type);
        $this->assertSame('release.pdf', $attachment->original_name);
        Storage::disk($attachment->disk)->assertExists($attachment->path);

        // Claimed uploads become attachments and leave the pending table.
        $this->assertDatabaseCount('uploads', 0);

        Notification::assertSentTo($user, SubmissionReceived::class);
    }

    public function test_assignment_requires_claim_and_vin(): void
    {
        $payload = $this->assignmentPayload(['sections' => ['assignment' => ['claimNumber' => ''], 'vehicle' => ['vin' => '']]]);

        $this->actingAs(User::factory()->create())
            ->postJson('/assignments', ['payload' => $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.sections.assignment.claimNumber', 'data.sections.vehicle.vin']);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_an_upload_cannot_be_attached_to_the_wrong_field(): void
    {
        $user = User::factory()->create();
        $document = $this->upload($user, 'pickup_document', UploadedFile::fake()->create('release.pdf', 10, 'application/pdf'));

        $this->actingAs($user)
            ->postJson('/assignments', ['payload' => $this->assignmentPayload(), 'images' => [$document]])
            ->assertJsonValidationErrors('images.0');
    }

    public function test_another_users_upload_cannot_be_attached(): void
    {
        $owner = User::factory()->create();
        $image = $this->upload($owner, 'vehicle_image', UploadedFile::fake()->image('front.jpg'));

        $this->actingAs(User::factory()->create())
            ->postJson('/assignments', ['payload' => $this->assignmentPayload(), 'images' => [$image]])
            ->assertJsonValidationErrors('images.0');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_an_upload_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $image = $this->upload($user, 'vehicle_image', UploadedFile::fake()->image('front.jpg'));
        $submit = fn () => $this->actingAs($user)->postJson('/assignments', ['payload' => $this->assignmentPayload(), 'images' => [$image]]);

        $submit()->assertCreated();
        $submit()->assertJsonValidationErrors('images.0');

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseCount('attachments', 1);
    }

    public function test_malformed_payload_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/assignments', ['payload' => '{not json'])
            ->assertJsonValidationErrors('data');
    }

    public function test_bid_request_is_stored(): void
    {
        $user = User::factory()->create();
        $document = $this->upload($user, 'bid_document', UploadedFile::fake()->create('estimate.pdf', 50, 'application/pdf'));

        $this->actingAs($user)->postJson('/bid-requests', [
            'payload' => json_encode([
                'fields' => [
                    'claimNumber' => 'CLM-2002', 'vin' => '1HGCM82633A004352', 'lossDate' => '02/30/2026',
                    'vehicleYear' => '2019', 'vehicleMake' => 'Ford', 'vehicleModel' => 'F-150',
                    'vehicleLocationState' => 'Texas', 'acv' => '$12,500.00', 'damageAmount' => '4,000', 'manual' => false,
                ],
                'noImages' => true,
                'noDocuments' => false,
                'documentDetails' => [['name' => 'estimate.pdf', 'documentType' => 'estimate']],
            ]),
            'documents' => [$document],
        ])->assertCreated()->assertJson(['bidNumber' => 'BID0000001']);

        $bid = $user->bidRequests()->firstOrFail();
        $this->assertSame('12500.00', $bid->acv);
        $this->assertSame('4000.00', $bid->damage_amount);
        $this->assertNull($bid->loss_date, 'Impossible dates are dropped, not stored.');
        $this->assertSame('2019 Ford F-150', $bid->vehicle_description);
        $this->assertSame('estimate', $bid->attachments()->first()->document_type);
    }

    public function test_bid_request_needs_files_unless_marked_unavailable(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/bid-requests', ['payload' => json_encode([
                'fields' => ['claimNumber' => 'CLM-1', 'vin' => 'ABC'],
                'noImages' => false,
                'noDocuments' => false,
            ])])
            ->assertJsonValidationErrors(['images', 'documents']);
    }

    public function test_new_submission_refreshes_cached_dashboard_counts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats.assignments', 0));

        $this->actingAs($user)->post('/assignments', ['payload' => $this->assignmentPayload()])->assertCreated();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats.assignments', 1));
    }
}
