<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * جدول نامه‌های داخلی
         * هر رکورد یک نامه، پیش‌نویس یا پاسخ است.
         */
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();

            $table->string('reference_number', 64)
                ->nullable()
                ->unique()
                ->comment('شماره اداری نامه؛ هنگام ارسال صادر می‌شود و پیش‌نویس شماره ندارد');

            $table->foreignId('sender_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('کاربر فرستنده نامه');

            $table->foreignId('thread_root_id')
                ->nullable()
                ->constrained('correspondences')
                ->restrictOnDelete()
                ->comment('شناسه نامه اصلی رشته مکاتبه؛ در نامه اصلی خالی است');

            $table->foreignId('reply_to_id')
                ->nullable()
                ->constrained('correspondences')
                ->restrictOnDelete()
                ->comment('شناسه نامه‌ای که این پاسخ مستقیماً به آن داده شده است');

            $table->string('subject', 255)
                ->comment('موضوع نامه');

            $table->longText('body')
                ->nullable()
                ->comment('متن نامه؛ در پیش‌نویس می‌تواند خالی باشد');

            $table->string('status', 20)
                ->default('draft')
                ->comment('وضعیت نامه: draft پیش‌نویس، sent ارسال‌شده، cancelled لغوشده');

            $table->string('priority', 20)
                ->default('normal')
                ->comment('اولویت: low کم، normal معمولی، high زیاد، urgent فوری');

            $table->string('confidentiality', 20)
                ->default('internal')
                ->comment('سطح محرمانگی: internal داخلی، confidential محرمانه');

            $table->dateTime('due_at')
                ->nullable()
                ->comment('مهلت کلی نامه');

            $table->dateTime('sent_at')
                ->nullable()
                ->comment('تاریخ و ساعت ارسال نامه');

            $table->dateTime('closed_at')
                ->nullable()
                ->comment('تاریخ بستن کلی پرونده؛ بایگانی هر کاربر جداگانه است');

            $table->timestamps();
            // created_at: تاریخ ایجاد | updated_at: تاریخ آخرین ویرایش

            $table->index(['sender_id', 'sent_at'], 'corr_sender_sent_idx');
            $table->index(['thread_root_id', 'sent_at'], 'corr_thread_sent_idx');
        });

        /*
         * جدول گیرندگان اولیه نامه (to و cc)
         * وضعیت خواندن و بایگانی برای هر کاربر مستقل است.
         */
        Schema::create('correspondence_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('correspondence_id')
                ->constrained('correspondences')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('شناسه کاربر گیرنده');

            $table->string('type', 10)
                ->comment('نوع گیرنده: to گیرنده اصلی، cc رونوشت');

            $table->dateTime('read_at')
                ->nullable()
                ->comment('تاریخ و ساعت اولین مطالعه نامه توسط این گیرنده');

            $table->dateTime('archived_at')
                ->nullable()
                ->comment('تاریخ بایگانی نامه در صندوق شخصی این گیرنده');

            $table->timestamps();
            // created_at: تاریخ ایجاد | updated_at: تاریخ آخرین ویرایش

            $table->unique(['correspondence_id', 'user_id'], 'corr_recipient_user_unique');
            $table->index(['user_id', 'archived_at'], 'corr_recipients_inbox_idx');
        });

        /*
         * جدول ارجاع‌های مستقل و چندمرحله‌ای
         * هر ارجاع یک مسئولیت جداگانه است و می‌تواند فرزند ارجاع قبلی باشد.
         */
        Schema::create('correspondence_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('correspondence_id')
                ->constrained('correspondences')
                ->restrictOnDelete()
                ->comment('شناسه نامه‌ای که این ارجاع به آن مربوط است');

            $table->foreignId('parent_assignment_id')
                ->nullable()
                ->constrained('correspondence_assignments')
                ->restrictOnDelete()
                ->comment('شناسه ارجاع مرحله قبل؛ در ارجاع اولیه خالی است');

            $table->foreignId('assigned_by_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('کاربری که این ارجاع را انجام داده است');

            $table->foreignId('assigned_to_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('کاربری که مسئول انجام این ارجاع است');

            $table->text('note')
                ->nullable()
                ->comment('توضیح یا دستور همراه ارجاع');

            $table->string('status', 20)
                ->default('pending')
                ->comment('وضعیت ارجاع: pending در انتظار، in_progress در حال انجام، completed انجام‌شده، rejected ردشده، cancelled لغوشده');

            $table->dateTime('due_at')
                ->nullable()
                ->comment('مهلت انجام این ارجاع');

            $table->dateTime('read_at')
                ->nullable()
                ->comment('تاریخ و ساعت مطالعه ارجاع توسط مسئول آن');

            $table->dateTime('started_at')
                ->nullable()
                ->comment('تاریخ و ساعت شروع انجام کار');

            $table->dateTime('resolved_at')
                ->nullable()
                ->comment('تاریخ و ساعت نهایی‌شدن ارجاع (انجام یا رد)');

            $table->text('resolution_note')
                ->nullable()
                ->comment('توضیح نتیجه کار یا دلیل رد ارجاع');

            $table->dateTime('archived_at')
                ->nullable()
                ->comment('تاریخ بایگانی ارجاع در صندوق شخصی مسئول');

            $table->timestamps();
            // created_at: تاریخ ایجاد | updated_at: تاریخ آخرین ویرایش

            $table->index(['assigned_to_id', 'status', 'due_at'], 'corr_assignments_inbox_idx');
            $table->index(['correspondence_id', 'parent_assignment_id'], 'corr_assignments_tree_idx');
        });

        /*
         * جدول تاریخچه رویدادها
         * فقط ثبت می‌شود و پس از ایجاد نباید ویرایش شود.
         */
        Schema::create('correspondence_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('correspondence_id')
                ->constrained('correspondences')
                ->restrictOnDelete()
                ->comment('شناسه نامه مربوط به این رویداد');

            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('correspondence_assignments')
                ->restrictOnDelete()
                ->comment('شناسه ارجاع مربوط؛ برای رویدادهای کلی نامه خالی است');

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('کاربری که این رویداد را انجام داده است');

            $table->string('event_type', 40)
                ->comment('نوع رویداد: sent ارسال، read مطالعه، assigned ارجاع، started شروع، replied پاسخ، completed انجام، rejected رد، cancelled لغو، closed بستن');

            $table->json('metadata')
                ->nullable()
                ->comment('اطلاعات تکمیلی رویداد به صورت JSON');

            $table->timestamp('created_at')
                ->useCurrent()
                ->comment('تاریخ و ساعت ثبت رویداد');

            $table->index(['correspondence_id', 'created_at'], 'corr_events_timeline_idx');
        });

        /*
         * جدول پیوست‌های نامه
         * فایل‌ها باید در فضای ذخیره‌سازی خصوصی نگهداری شوند.
         */
        Schema::create('correspondence_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('correspondence_id')
                ->constrained('correspondences')
                ->restrictOnDelete()
                ->comment('شناسه نامه‌ای که فایل به آن پیوست شده است');

            $table->foreignId('uploaded_by_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('کاربری که فایل را بارگذاری کرده است');

            $table->string('disk', 50)
                ->comment('نام دیسک ذخیره‌سازی لاراول؛ معمولاً local یا private');

            $table->string('storage_path', 1024)
                ->comment('مسیر نسبی فایل در دیسک ذخیره‌سازی');

            $table->string('original_name')
                ->comment('نام اصلی فایل هنگام بارگذاری');

            $table->string('mime_type', 150)
                ->nullable()
                ->comment('نوع MIME فایل');

            $table->unsignedBigInteger('size_bytes')
                ->comment('حجم فایل بر حسب بایت');

            $table->timestamps();
            // created_at: تاریخ ایجاد | updated_at: تاریخ آخرین ویرایش

            $table->index(['correspondence_id', 'created_at'], 'corr_attachments_letter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_attachments');
        Schema::dropIfExists('correspondence_events');
        Schema::dropIfExists('correspondence_assignments');
        Schema::dropIfExists('correspondence_recipients');
        Schema::dropIfExists('correspondences');
    }
};
