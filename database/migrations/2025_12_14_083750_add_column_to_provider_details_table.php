<?php

use Illuminate\Support\Facades\Schema;
use App\Enum\ProviderApprovalStatusEnum;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->String("status")->default(ProviderApprovalStatusEnum::REVIEW);
            $table->String("rejection_reason")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->dropColumn(["status" , "rejection_reason"]);
        });
    } 
};
