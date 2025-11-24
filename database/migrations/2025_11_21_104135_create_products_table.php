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
        Schema::create('products', function (Blueprint $table) {
            $table->bigInteger('id');
            $table->primary('id');
            $table->string('name')->nullable();
            $table->string('sku')->nullable();
            $table->string('status')->nullable();
            $table->text('variations')->nullable();
            $table->decimal('price', 20, 2)->nullable();
            $table->string('currency', 20)->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
