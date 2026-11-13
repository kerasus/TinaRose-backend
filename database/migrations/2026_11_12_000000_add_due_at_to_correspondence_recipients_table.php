<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence_recipients', function (Blueprint $table) {
            $table->dateTime('due_at')
                ->nullable()
                ->after('type')
                ->comment('مهلت انجام این گیرنده؛ در زمان ارجاع ریشه به ارجاع منتقل می‌شود');
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_recipients', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};