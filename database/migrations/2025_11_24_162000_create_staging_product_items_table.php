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
        Schema::create('staging_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staging_product_id')->nullable()->constrained();
            $table->longText('raw_product');
            $table->string('status')->default('pending'); // pending, processing, done, failed
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staging_product_items');
    }
};
