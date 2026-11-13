<?php

namespace Tests\Feature\Correspondence;

use App\Models\Correspondence;
use App\Models\CorrespondenceAssignment;
use Laravel\Sanctum\Sanctum;

class AssignmentHierarchyTest extends CorrespondenceTestCase
{
    public function test_only_parent_assignee_can_create_child_assignment(): void
    {
        $sender = $this->createUser(['username' => 'sender2']);
        $to = $this->createUser(['username' => 'assignee']);
        $other = $this->createUser(['username' => 'other']);
        $childUser = $this->createUser(['username' => 'childuser']);

        Sanctum::actingAs($sender);
        $create = $this->postJson('/api/correspondences', [
            'subject' => 'ارجاع تست',
            'body' => 'بدنه',
            'to' => [$to->id],
        ])->assertCreated();

        $id = $create->json('id');
        $this->postJson("/api/correspondences/{$id}/send")->assertOk();

        $parent = CorrespondenceAssignment::where('correspondence_id', $id)->whereNull('parent_assignment_id')->firstOrFail();

        Sanctum::actingAs($other);
        $forbidden = $this->postJson("/api/correspondences/assignments/{$parent->id}/children", [
            'assigned_to_id' => $childUser->id,
            'note' => 'ارجاع نامجاز',
        ]);
        $forbidden->assertStatus(403);
        $this->assertDatabaseMissing('correspondence_assignments', [
            'parent_assignment_id' => $parent->id,
            'assigned_to_id' => $childUser->id,
        ]);

        Sanctum::actingAs($to);
        $ok = $this->postJson("/api/correspondences/assignments/{$parent->id}/children", [
            'assigned_to_id' => $childUser->id,
            'note' => 'لطفا پیگیری شود',
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('parent_assignment_id', $parent->id);
        $ok->assertJsonPath('assigned_to_id', $childUser->id);
        $ok->assertJsonPath('correspondence_id', $id);

        $this->assertDatabaseHas('correspondence_assignments', [
            'correspondence_id' => $id,
            'parent_assignment_id' => $parent->id,
            'assigned_to_id' => $childUser->id,
            'assigned_by_id' => $to->id,
        ]);
    }

    public function test_confidential_correspondence_not_accessible_to_manager_without_participation(): void
    {
        $sender = $this->createUser(['username' => 'sender3']);
        $to = $this->createUser(['username' => 'to3']);
        $manager = $this->createUser(['username' => 'manager3']);

        Sanctum::actingAs($sender);
        $id = $this->postJson('/api/correspondences', [
            'subject' => 'محرمانه',
            'body' => 'بدنه محرمانه',
            'confidentiality' => 'confidential',
            'to' => [$to->id],
        ])->assertCreated()->json('id');

        $this->postJson("/api/correspondences/{$id}/send")->assertOk();

        // manager has permission notionally, but is not participant -> must be forbidden by policy view
        Sanctum::actingAs($manager);
        $this->getJson("/api/correspondences/{$id}")->assertStatus(403);
    }
}
