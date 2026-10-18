<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             AND REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), 'project_resources', 'project_engineer_id']
        );

        foreach ($foreignKeys as $foreignKey) {
            Schema::table('project_resources', function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey->CONSTRAINT_NAME);
            });
        }

        if (Schema::hasColumn('project_resources', 'project_engineer_id')) {
            Schema::table('project_resources', function (Blueprint $table) {
                $table->dropColumn('project_engineer_id');
            });
        }

        if (! Schema::hasColumn('project_resources', 'engineer_id')) {
            Schema::table('project_resources', function (Blueprint $table) {
                $table->foreignId('engineer_id')->constrained('users');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('project_resources', 'engineer_id')) {
            Schema::table('project_resources', function (Blueprint $table) {
                $table->dropConstrainedForeignId('engineer_id');
            });
        }

        if (! Schema::hasColumn('project_resources', 'project_engineer_id')) {
            Schema::table('project_resources', function (Blueprint $table) {
                $table->foreignId('project_engineer_id')->nullable()->constrained('project_engineers')->cascadeOnDelete();
            });
        }
    }
};
