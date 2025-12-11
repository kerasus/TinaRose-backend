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
        Schema::create('product_part_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_part_id');
            $table->foreign('product_part_id')->references('id')->on('product_parts')->onDelete('cascade');

            // Polymorphic relation
            $table->unsignedBigInteger('required_item_id');
            $table->string('required_item_type'); // App\Models\ProductPart, RawMaterial, etc.

            $table->decimal('quantity', 14, 4);
            $table->string('unit'); // 'don', 'meter', ...

            $table->timestamps();

            // جلوگیری از تکراری بودن
            $table->unique(['product_part_id', 'required_item_id', 'required_item_type', 'color_id'], 'part_requirement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_part_requirements');
    }
};
