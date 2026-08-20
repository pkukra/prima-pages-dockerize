<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tilaka_profiles', function (Blueprint $table) {
            $table->string('signature_path')
                ->nullable()
                ->after('selfie_path');
        });
    }

    public function down(): void
    {
        Schema::table('tilaka_profiles', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};

