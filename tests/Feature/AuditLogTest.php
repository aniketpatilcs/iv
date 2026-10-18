<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assignments\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_and_sign_out_are_logged_against_the_user(): void
    {
        $user = User::factory()->create(['username' => 'adjuster1', 'password' => 'secret-pass']);

        $this->post('/login', ['username' => 'adjuster1', 'password' => 'secret-pass']);
        $this->post('/logout');

        $this->assertSame(['login', 'logout'], Activity::inLog('auth')->orderBy('id')->pluck('event')->all());
        $login = Activity::inLog('auth')->where('event', 'login')->firstOrFail();
        $this->assertTrue($login->causer->is($user));
        $this->assertSame('user', $login->causer_type, 'Morph alias, not the PHP class name, is stored.');
    }

    public function test_failed_sign_in_keeps_username_but_never_the_password(): void
    {
        User::factory()->create(['username' => 'adjuster1', 'password' => 'secret-pass']);

        $this->post('/login', ['username' => 'adjuster1', 'password' => 'wrong-guess']);

        $failed = Activity::inLog('auth')->where('event', 'failed')->firstOrFail();
        $this->assertSame('adjuster1', $failed->getExtraProperty('username'));
        $this->assertStringNotContainsString('wrong-guess', json_encode($failed->properties));
        $this->assertNull($failed->causer_id);
    }

    public function test_creating_an_assignment_logs_one_entry_with_its_stock_number(): void
    {
        Storage::fake(config('filesystems.default'));
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/assignments', ['payload' => json_encode([
            'assignmentType' => 'Standard',
            'sections' => ['assignment' => ['claimNumber' => 'CLM-9'], 'vehicle' => ['vin' => 'VIN123']],
        ])])->assertCreated();

        $entries = Activity::inLog('assignments')->get();
        $this->assertCount(1, $entries, 'The reference-number update must not add a second entry.');
        $this->assertSame('created', $entries[0]->event);
        $this->assertSame('assignment', $entries[0]->subject_type);
        $this->assertTrue($entries[0]->causer->is($user));
        $this->assertSame('CLM-9', $entries[0]->getExtraProperty('attributes')['claim_number']);
        $this->assertArrayNotHasKey('payload', $entries[0]->getExtraProperty('attributes'));
        $this->assertSame('IV0000001', Assignment::firstOrFail()->stock_number);
    }

    public function test_user_password_changes_are_not_written_to_the_log(): void
    {
        $user = User::factory()->create();

        $user->update(['password' => 'new-secret', 'name' => 'Renamed']);

        $entry = Activity::inLog('users')->where('event', 'updated')->firstOrFail();
        $this->assertSame(['name' => 'Renamed'], $entry->getExtraProperty('attributes'));
    }
}
