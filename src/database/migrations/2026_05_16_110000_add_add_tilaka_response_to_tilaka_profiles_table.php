<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tilaka_profiles', function (Blueprint $table) {
            $table->string('user_identifier')->nullable()->after('email');
            $table->json('verification_result')->nullable()->after('user_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('tilaka_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'user_identifier',
                'verification_result'
            ]);
        });
    }
};