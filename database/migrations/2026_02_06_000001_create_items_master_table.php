<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_master', function (Blueprint $table) {
            $table->id();

            // contoh: seat / engine (biar global)
            $table->string('module', 30)->default('seat');

            $table->unsignedInteger('no')->nullable();
            $table->string('category', 80)->nullable();
            $table->string('pn', 80)->nullable();
            $table->string('item', 255);
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->unsignedBigInteger('sales_price')->nullable();

            $table->timestamps();

            $table->index(['module']);
            $table->index(['module', 'pn']);
            $table->index(['module', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_master');
    }
};
