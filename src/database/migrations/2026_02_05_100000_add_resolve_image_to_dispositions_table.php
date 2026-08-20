<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->string('resolved_image_path')->nullable()->after('resolved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropColumn('resolved_image_path');
        });
    }
};
