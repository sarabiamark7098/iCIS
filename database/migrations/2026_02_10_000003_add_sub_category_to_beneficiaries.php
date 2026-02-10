<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds missing columns to beneficiaries, profiles, and transactions tables
     * that were defined in the original migrations but not present in the actual database.
     */
    public function up(): void
    {
        // Add missing columns to beneficiaries
        Schema::table('beneficiaries', function (Blueprint $table) {
            if (!Schema::hasColumn('beneficiaries', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('civil_status');
            }
            if (!Schema::hasColumn('beneficiaries', 'occupation')) {
                $table->string('occupation')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('beneficiaries', 'category')) {
                $table->string('category')->nullable()->after('occupation');
            }
            if (!Schema::hasColumn('beneficiaries', 'sub_category')) {
                $table->string('sub_category')->nullable()->after('category');
            }
            if (!Schema::hasColumn('beneficiaries', 'region')) {
                $table->string('region')->nullable()->after('sub_category');
            }
            if (!Schema::hasColumn('beneficiaries', 'province')) {
                $table->string('province')->nullable()->after('region');
            }
            if (!Schema::hasColumn('beneficiaries', 'city')) {
                $table->string('city')->nullable()->after('province');
            }
            if (!Schema::hasColumn('beneficiaries', 'barangay')) {
                $table->string('barangay')->nullable()->after('city');
            }
        });

        // Add missing columns to profiles
        Schema::table('profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('profiles', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('civil_status');
            }
            if (!Schema::hasColumn('profiles', 'occupation')) {
                $table->string('occupation')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('profiles', 'category')) {
                $table->string('category')->nullable()->after('occupation');
            }
            if (!Schema::hasColumn('profiles', 'region')) {
                $table->string('region')->nullable()->after('category');
            }
            if (!Schema::hasColumn('profiles', 'province')) {
                $table->string('province')->nullable()->after('region');
            }
            if (!Schema::hasColumn('profiles', 'city')) {
                $table->string('city')->nullable()->after('province');
            }
            if (!Schema::hasColumn('profiles', 'barangay')) {
                $table->string('barangay')->nullable()->after('city');
            }
        });

        // Add missing columns to transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'entered_by')) {
                $table->string('entered_by')->nullable()->after('beneficiary_id');
            }
            if (!Schema::hasColumn('transactions', 'remarks')) {
                $table->string('remarks')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $columns = ['contact_number', 'occupation', 'category', 'sub_category', 'region', 'province', 'city', 'barangay'];
            $toDrop = array_filter($columns, fn($col) => Schema::hasColumn('beneficiaries', $col));
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        Schema::table('profiles', function (Blueprint $table) {
            $columns = ['contact_number', 'occupation', 'category', 'region', 'province', 'city', 'barangay'];
            $toDrop = array_filter($columns, fn($col) => Schema::hasColumn('profiles', $col));
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            $columns = ['entered_by', 'remarks'];
            $toDrop = array_filter($columns, fn($col) => Schema::hasColumn('transactions', $col));
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
