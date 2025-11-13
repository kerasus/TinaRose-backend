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
        Schema::create('transfer_package_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_package_id');
            $table->foreign('transfer_package_id')->references('id')->on('transfer_packages')->onDelete('cascade');

            // Polymorphic relation
            $table->unsignedBigInteger('item_id');
            $table->string('item_type');
            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('color_id')->references('id')->on('colors')->onDelete('set null');

            $table->decimal('quantity', 12, 2);
            $table->text('notes')->nullable();

            $table->unique(['transfer_package_id', 'item_id', 'item_type', 'color_id'], 'package_item_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_package_items');
    }
};
