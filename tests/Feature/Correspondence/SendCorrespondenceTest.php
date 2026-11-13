<?php

namespace Tests\Feature\Correspondence;

use App\Enums\Correspondence\CorrespondenceStatus;
use App\Models\Correspondence;
use App\Models\CorrespondenceAssignment;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class SendCorrespondenceTest extends CorrespondenceTestCase
{
    public function test_sending_letter_issues_reference_and_creates_root_assignments(): void
    {
        $sender = $this->createUser(['username' => 'sender']);
        $to1 = $this->createUser(['username' => 'to1']);
        $to2 = $this->createUser(['username' => 'to2']);
        $cc = $this->createUser(['username' => 'cc1']);

        Sanctum::actingAs($sender);

        $create = $this->postJson('/api/correspondences', [
            'subject' => 'موضوع تست',
            'body' => 'متن نامه',
            'priority' => 'high',
            'confidentiality' => 'internal',
            'to' => [$to1->id, $to2->id],
            'cc' => [$cc->id],
        ]);

        $create->assertCreated();
        $id = $create->json('id');

        $send = $this->postJson("/api/correspondences/{$id}/send");
        $send->assertOk();
        $send->assertJsonPath('status', CorrespondenceStatus::SENT->value);
        $this->assertNotNull($send->json('reference_number'));
        $this->assertMatchesRegularExpression('/^\d{4}\/\d{6}$/', $send->json('reference_number'));

        $correspondence = Correspondence::with('assignments')->findOrFail($id);
        $this->assertNotNull($correspondence->sent_at);
        $this->assertSame($correspondence->id, $correspondence->thread_root_id);

        $rootAssignments = $correspondence->assignments->whereNull('parent_assignment_id');
        $this->assertCount(2, $rootAssignments);
        $this->assertEqualsCanonicalizing([$to1->id, $to2->id], $rootAssignments->pluck('assigned_to_id')->all());

        $this->assertDatabaseHas('correspondence_events', [
            'correspondence_id' => $id,
            'event_type' => 'sent',
        ]);

        $this->assertSame(1, (int) DB::table('correspondence_sequences')->value('last_number'));
    }
}
