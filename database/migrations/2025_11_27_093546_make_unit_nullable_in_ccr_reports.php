<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->string('unit')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->string('unit')->nullable(false)->change();
        });
    }
};
