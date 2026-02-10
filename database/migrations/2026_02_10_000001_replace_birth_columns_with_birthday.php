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
        // Add birthday column to both tables first
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('extension_name');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('extension_name');
        });

        // Migrate existing data: construct date from year/month/day
        DB::table('beneficiaries')
            ->whereNotNull('birth_year')
            ->whereNotNull('birth_month')
            ->whereNotNull('birth_day')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $date = sprintf('%04d-%02d-%02d', $row->birth_year, $row->birth_month, $row->birth_day);
                    DB::table('beneficiaries')->where('id', $row->id)->update(['birthday' => $date]);
                }
            });

        DB::table('profiles')
            ->whereNotNull('birth_year')
            ->whereNotNull('birth_month')
            ->whereNotNull('birth_day')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $date = sprintf('%04d-%02d-%02d', $row->birth_year, $row->birth_month, $row->birth_day);
                    DB::table('profiles')->where('id', $row->id)->update(['birthday' => $date]);
                }
            });

        // Drop the old columns
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['birth_year', 'birth_month', 'birth_day']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['birth_year', 'birth_month', 'birth_day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the old columns
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->integer('birth_year')->nullable()->after('extension_name');
            $table->integer('birth_month')->nullable()->after('birth_year');
            $table->integer('birth_day')->nullable()->after('birth_month');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->integer('birth_year')->nullable()->after('extension_name');
            $table->integer('birth_month')->nullable()->after('birth_year');
            $table->integer('birth_day')->nullable()->after('birth_month');
        });

        // Migrate data back
        DB::table('beneficiaries')
            ->whereNotNull('birthday')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $date = \Carbon\Carbon::parse($row->birthday);
                    DB::table('beneficiaries')->where('id', $row->id)->update([
                        'birth_year' => $date->year,
                        'birth_month' => $date->month,
                        'birth_day' => $date->day,
                    ]);
                }
            });

        DB::table('profiles')
            ->whereNotNull('birthday')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $date = \Carbon\Carbon::parse($row->birthday);
                    DB::table('profiles')->where('id', $row->id)->update([
                        'birth_year' => $date->year,
                        'birth_month' => $date->month,
                        'birth_day' => $date->day,
                    ]);
                }
            });

        // Drop the birthday column
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }
};
