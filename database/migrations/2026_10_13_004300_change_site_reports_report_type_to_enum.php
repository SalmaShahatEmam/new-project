<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPORT_TYPES = ['labor', 'materials', 'equipment', 'issues'];

    public function up(): void
    {
        Schema::table('site_reports', function (Blueprint $table) {
            $table->enum('report_type', self::REPORT_TYPES)->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_reports', function (Blueprint $table) {
            $table->string('report_type')->change();
        });
    }
};
