<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sites')
            ->whereNotIn('status', ['wait_assign', 'inprogress', 'finished', 'deadline_passed'])
            ->update(['status' => 'wait_assign']);

        Schema::table('sites', function (Blueprint $table) {
            $table->string('status')->default('wait_assign')->change();
        });
    }

    public function down(): void
    {
        DB::table('sites')
            ->where('status', 'wait_assign')
            ->update(['status' => 'active']);

        Schema::table('sites', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }
};
