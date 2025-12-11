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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');

            $table->unsignedBigInteger('product_part_id')->nullable();
            $table->foreign('product_part_id')->references('id')->on('product_parts')->onDelete('cascade');

            $table->unsignedBigInteger('fabric_id')->nullable(); // فقط برای برش
            $table->foreign('fabric_id')->references('id')->on('fabrics')->onDelete('set null');

            $table->unsignedBigInteger('color_id')->nullable(); // فقط برای رنگ
            $table->foreign('color_id')->references('id')->on('colors')->onDelete('set null');

            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            $table->date('production_date');
            $table->decimal('bunch_count', 8, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
