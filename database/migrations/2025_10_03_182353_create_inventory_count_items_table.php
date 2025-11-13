<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_count_id');
            $table->foreign('inventory_count_id')->references('id')->on('inventory_counts')->onDelete('cascade');

            $table->unsignedBigInteger('item_id');
            $table->string('item_type');
            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('color_id')->references('id')->on('colors')->onDelete('set null');

            $table->decimal('system_quantity', 14, 4)->default(0);
            $table->decimal('actual_quantity', 14, 4)->nullable()->default(0);
            $table->decimal('difference', 14, 4)->default(0); // actual - system

            $table->text('notes')->nullable();

            $table->unique(['inventory_count_id', 'item_id', 'item_type', 'color_id'], 'count_item_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
    }
};
