<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combined_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('name');
            $table->timestamps();

            $table->index(['site_id', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combined_reports');
    }
};