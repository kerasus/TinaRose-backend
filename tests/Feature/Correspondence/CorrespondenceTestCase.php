<?php

namespace Tests\Feature\Correspondence;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

abstract class CorrespondenceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        $this->setUpMinimalSchema();
    }

    protected function setUpMinimalSchema(): void
    {
        Schema::dropIfExists('correspondence_attachments');
        Schema::dropIfExists('correspondence_events');
        Schema::dropIfExists('correspondence_assignments');
        Schema::dropIfExists('correspondence_recipients');
        Schema::dropIfExists('correspondences');
        Schema::dropIfExists('correspondence_sequences');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('employee_code')->nullable()->unique();
            $table->string('mobile')->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('mobile_verification_code')->nullable();
            $table->string('password');
            $table->text('description')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('correspondence_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 64)->nullable()->unique();
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('thread_root_id')->nullable()->constrained('correspondences');
            $table->foreignId('reply_to_id')->nullable()->constrained('correspondences');
            $table->string('subject', 255);
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('priority', 20)->default('normal');
            $table->string('confidentiality', 20)->default('internal');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('correspondence_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences');
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 10);
            $table->dateTime('read_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['correspondence_id', 'user_id']);
        });

        Schema::create('correspondence_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences');
            $table->foreignId('parent_assignment_id')->nullable()->constrained('correspondence_assignments');
            $table->foreignId('assigned_by_id')->constrained('users');
            $table->foreignId('assigned_to_id')->constrained('users');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('correspondence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences');
            $table->foreignId('assignment_id')->nullable()->constrained('correspondence_assignments');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event_type', 40);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('correspondence_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences');
            $table->foreignId('uploaded_by_id')->constrained('users');
            $table->string('disk', 50);
            $table->string('storage_path', 1024);
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    protected function createUser(array $attributes = []): User
    {
        static $seq = 1;
        $seq++;

        return User::create(array_merge([
            'firstname' => 'User',
            'lastname' => (string) $seq,
            'username' => 'user' . $seq,
            'mobile' => '09' . str_pad((string) $seq, 9, '0', STR_PAD_LEFT),
            'email' => 'user' . $seq . '@example.test',
            'password' => bcrypt('password'),
        ], $attributes));
    }
}
