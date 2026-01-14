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
        Schema::table('imports', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedInteger('generation_total')->default(0);
            $table->unsignedInteger('generation_done')->default(0);
            $table->string('generation_stage')->nullable(); // generating, duplicates_fix, etc.
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            //
        });
    }
};
