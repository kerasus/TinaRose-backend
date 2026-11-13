<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique()->comment('سال شمسی');
            $table->unsignedBigInteger('last_number')->default(0)->comment('آخرین شماره صادر شده در سال');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_sequences');
    }
};
