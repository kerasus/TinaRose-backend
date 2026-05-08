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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');

            // Polymorphic relation
            $table->unsignedBigInteger('item_id');
            $table->string('item_type');

            $table->decimal('quantity', 14, 4)->default(0);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('color_id')->references('id')->on('colors')->onDelete('set null');
            $table->unsignedBigInteger('fabric_id')->nullable();
            $table->foreign('fabric_id')->references('id')->on('fabrics')->onDelete('set null');

            $table->unique(['inventory_id', 'item_id', 'item_type', 'color_id', 'fabric_id'], 'inventory_item_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
