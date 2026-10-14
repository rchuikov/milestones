<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('id');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNull('password')
            ->update([
                'password' => Hash::make(Str::random(64)),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('external_id');
            $table->string('password')->nullable(false)->change();
        });
    }
};
