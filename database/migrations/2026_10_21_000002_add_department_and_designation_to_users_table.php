<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
        });

        // Fill the new profile fields for existing business-head accounts from
        // the signup lead that created each account, where that lead is present.
        if (Schema::hasTable('leads')) {
            DB::table('leads')
                ->where('status', 'approved')
                ->orderBy('id')
                ->get(['email', 'designation', 'department'])
                ->each(function ($lead) {
                    DB::table('users')
                        ->where('email', $lead->email)
                        ->where('user_type', 'business_head')
                        ->update([
                            'designation' => $lead->designation,
                            'department' => $lead->department,
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['designation', 'department']);
        });
    }
};