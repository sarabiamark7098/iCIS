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
        Schema::table('imports', function (Blueprint $table) {
            $table->text('remark')->nullable()->after('imported_rows');
            $table->string('serving_status')->nullable()->after('remark');
            $table->date('payout_schedule_date')->nullable()->after('serving_status');
            $table->timestamp('archived_at')->nullable()->after('payout_schedule_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['remark', 'serving_status', 'payout_schedule_date', 'archived_at']);
        });
    }
};
